<?php

namespace App\Services;

use App\Models\AccountingEntry;
use App\Models\QuotaAccountingBatch;
use App\Services\PaymentSettlement\PaymentSettlementChannels;
use Illuminate\Support\Collection;

class AccountingEntryLinks
{
    /**
     * Arma los vínculos de una acreditación con las cobranzas de las fechas que cierra.
     * Un asiento modificado a mano conserva los vínculos que le cargaron.
     */
    public function syncFromCollectionDates(QuotaAccountingBatch $batch, bool $includeAdjusted = false): void
    {
        $batch->loadMissing(['entry', 'collectionDates']);
        $entry = $batch->entry;
        if ($entry === null || $entry->status !== AccountingEntry::STATUS_POSTED) {
            return;
        }
        if (! $includeAdjusted && $entry->manually_adjusted) {
            return;
        }

        $definition = PaymentSettlementChannels::forBatchKind((string) $batch->kind);
        if ($definition === null) {
            return;
        }

        $dates = $batch->collectionDates
            ->map(fn ($row) => $row->collected_on?->toDateString())
            ->filter()
            ->unique()
            ->values()
            ->all();

        $ids = $dates === []
            ? []
            : $this->collectionEntryIds((string) $definition['payment_type'], $dates, $entry->id);

        $entry->relatedEntries()->sync($ids);
    }

    /**
     * Cuando aparece la cobranza, la engancha a las acreditaciones que ya citan esa fecha.
     */
    public function attachCollection(QuotaAccountingBatch $collection): void
    {
        if (! in_array($collection->kind, [QuotaAccountingBatch::KIND_COLLECTION, QuotaAccountingBatch::KIND_PLAN_COLLECTION], true)
            || $collection->payment_type === null
            || $collection->payment_type === '') {
            return;
        }

        $date = $collection->entry_date?->toDateString();
        if ($date === null) {
            return;
        }

        $batches = QuotaAccountingBatch::query()
            ->whereIn('kind', [
                QuotaAccountingBatch::KIND_MP_SETTLEMENT,
                QuotaAccountingBatch::KIND_NX_SETTLEMENT,
                QuotaAccountingBatch::KIND_SOL_SETTLEMENT,
                QuotaAccountingBatch::KIND_QR_SETTLEMENT,
            ])
            ->where('payment_type', $collection->payment_type)
            ->whereHas('collectionDates', fn ($query) => $query->whereDate('collected_on', $date))
            ->whereNotNull('accounting_entry_id')
            ->with(['entry', 'collectionDates'])
            ->get();

        foreach ($batches as $batch) {
            $this->syncFromCollectionDates($batch);
        }
    }

    public function backfill(): void
    {
        QuotaAccountingBatch::query()
            ->whereIn('kind', [
                QuotaAccountingBatch::KIND_MP_SETTLEMENT,
                QuotaAccountingBatch::KIND_NX_SETTLEMENT,
                QuotaAccountingBatch::KIND_SOL_SETTLEMENT,
                QuotaAccountingBatch::KIND_QR_SETTLEMENT,
            ])
            ->whereHas('collectionDates')
            ->whereHas('entry', function ($query) {
                $query->where('status', AccountingEntry::STATUS_POSTED)
                    ->whereDoesntHave('relatedEntries');
            })
            ->with(['entry', 'collectionDates'])
            ->orderBy('id')
            ->chunkById(100, function ($batches) {
                foreach ($batches as $batch) {
                    $this->syncFromCollectionDates($batch, true);
                }
            });
    }

    /**
     * @param  list<mixed>  $raw
     */
    public static function validateIds(array $raw, ?int $selfId): ?string
    {
        $ids = [];
        foreach ($raw as $id) {
            if (is_array($id) || filter_var($id, FILTER_VALIDATE_INT) === false || (int) $id < 1) {
                return 'Elegí asientos registrados del libro.';
            }
            $ids[] = (int) $id;
        }
        $ids = array_values(array_unique($ids));
        if ($selfId !== null && in_array($selfId, $ids, true)) {
            return 'Un asiento no puede relacionarse consigo mismo.';
        }
        if ($ids === []) {
            return null;
        }

        $count = AccountingEntry::query()
            ->whereIn('id', $ids)
            ->where('status', AccountingEntry::STATUS_POSTED)
            ->whereIn('kind', AccountingEntry::bookKinds())
            ->count();
        if ($count !== count($ids)) {
            return 'Elegí asientos registrados del libro.';
        }

        return null;
    }

    /**
     * @param  list<mixed>  $raw
     */
    public static function replace(AccountingEntry $entry, array $raw): void
    {
        $ids = [];
        foreach ($raw as $id) {
            $ids[] = (int) $id;
        }
        $entry->relatedEntries()->sync(array_values(array_unique($ids)));
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, AccountingEntry>
     */
    public static function findForForm(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return AccountingEntry::query()
            ->whereIn('id', $ids)
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, AccountingEntry>
     */
    public static function search(string $term, ?int $excludeId, ?string $paymentType): Collection
    {
        $term = trim($term);
        $paymentType = self::knownPaymentType($paymentType);
        if (mb_strlen($term) < 2 && $paymentType === null) {
            return collect();
        }

        $query = AccountingEntry::query()
            ->where('status', AccountingEntry::STATUS_POSTED)
            ->whereIn('kind', AccountingEntry::bookKinds())
            ->when($excludeId, fn ($entries) => $entries->where('id', '!=', $excludeId));

        if (mb_strlen($term) >= 2) {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function ($entries) use ($like) {
                $entries->where('entry_number', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereRaw("DATE_FORMAT(`date`, '%d/%m/%Y') like ?", [$like]);
            });
        } else {
            $query->where('kind', AccountingEntry::KIND_QUOTA_COLLECTION)
                ->whereHas('batch', function ($batch) use ($paymentType) {
                    $batch->where('kind', QuotaAccountingBatch::KIND_COLLECTION)
                        ->where('payment_type', $paymentType);
                });
        }

        if ($paymentType !== null) {
            $query->orderByRaw(
                'case when kind = ? and exists (
                    select 1 from quota_accounting_batches
                    where quota_accounting_batches.accounting_entry_id = accounting_entries.id
                      and quota_accounting_batches.kind = ?
                      and quota_accounting_batches.payment_type = ?
                ) then 0 else 1 end',
                [
                    AccountingEntry::KIND_QUOTA_COLLECTION,
                    QuotaAccountingBatch::KIND_COLLECTION,
                    $paymentType,
                ]
            );
        }

        return $query
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(15)
            ->get();
    }

    /**
     * @param  list<string>  $dates
     * @return list<int>
     */
    private function collectionEntryIds(string $paymentType, array $dates, int $exceptId): array
    {
        return QuotaAccountingBatch::query()
            ->whereIn('kind', [
                QuotaAccountingBatch::KIND_COLLECTION,
                QuotaAccountingBatch::KIND_PLAN_COLLECTION,
            ])
            ->where('payment_type', $paymentType)
            ->whereIn('entry_date', $dates)
            ->whereNotNull('accounting_entry_id')
            ->where('accounting_entry_id', '!=', $exceptId)
            ->whereHas('entry', function ($query) {
                $query->where('status', AccountingEntry::STATUS_POSTED)
                    ->whereIn('kind', [
                        AccountingEntry::KIND_QUOTA_COLLECTION,
                        AccountingEntry::KIND_QUOTA_PLAN_COLLECTION,
                    ]);
            })
            ->pluck('accounting_entry_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private static function knownPaymentType(?string $paymentType): ?string
    {
        if ($paymentType === null || $paymentType === '') {
            return null;
        }

        foreach (PaymentSettlementChannels::keys() as $channel) {
            if (PaymentSettlementChannels::get($channel)['payment_type'] === $paymentType) {
                return $paymentType;
            }
        }

        return null;
    }
}
