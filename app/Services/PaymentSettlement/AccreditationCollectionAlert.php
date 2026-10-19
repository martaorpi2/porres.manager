<?php

namespace App\Services\PaymentSettlement;

use App\Models\AccountingEntry;
use App\Models\QuotaAccountingBatch;
use App\Models\QuotaAccountingCollectionDate;
use App\Services\MercadoPagoReleaseSettlement;
use App\Services\QuotaPaymentAccounts;

/**
 * Compara el debe de la cobranza de la fecha de cobro del archivo
 * con el haber de la acreditación que cierra esa fecha.
 * La cobranza incluye las cuotas y las cuotas de plan de pago de ese medio.
 * La fecha de acreditación puede ser otra: no se busca un asiento del mismo día.
 */
final class AccreditationCollectionAlert
{
    /**
     * @param  array<string, mixed>  $definition
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{menu: string, kind: string, collected_on: list<string>, accredited_on: list<string>, debe_cents: int|null, haber_cents: int}>
     */
    public function forRows(array $definition, array $rows): array
    {
        $paymentType = (string) ($definition['payment_type'] ?? '');
        $menu = (string) ($definition['menu'] ?? $paymentType);
        $byDate = [];

        foreach ($rows as $row) {
            if (! in_array((string) ($row['outcome'] ?? ''), ['ready', 'update'], true)) {
                continue;
            }
            $collected = (string) ($row['collected_on'] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $collected) !== 1) {
                continue;
            }
            if (! isset($byDate[$collected])) {
                $byDate[$collected] = ['haber' => 0, 'accredited' => []];
            }
            $byDate[$collected]['haber'] += (int) ($row['gross_cents'] ?? 0);
            $accredited = (string) ($row['date'] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $accredited) === 1) {
                $byDate[$collected]['accredited'][$accredited] = $accredited;
            }
        }

        ksort($byDate);
        $alerts = [];
        foreach ($byDate as $collected => $bucket) {
            $alert = $this->compare(
                $menu,
                [$collected],
                array_values($bucket['accredited']),
                $this->collectionDebitCents($paymentType, $collected),
                (int) $bucket['haber'],
                $paymentType,
            );
            if ($alert !== null) {
                $alerts[] = $alert;
            }
        }

        return $alerts;
    }

    /**
     * Casos que siguen abiertos: falta la cobranza o los importes todavía no coinciden.
     *
     * @return list<array{menu: string, kind: string, collected_on: list<string>, accredited_on: list<string>, debe_cents: int|null, haber_cents: int}>
     */
    public function pending(): array
    {
        $links = QuotaAccountingCollectionDate::query()
            ->whereHas('batch', function ($query) {
                $query->whereIn('kind', [
                    QuotaAccountingBatch::KIND_MP_SETTLEMENT,
                    QuotaAccountingBatch::KIND_NX_SETTLEMENT,
                    QuotaAccountingBatch::KIND_SOL_SETTLEMENT,
                    QuotaAccountingBatch::KIND_QR_SETTLEMENT,
                ])->whereHas('entry', fn ($entry) => $entry->where('status', AccountingEntry::STATUS_POSTED));
            })
            ->with(['batch.entry.lines'])
            ->get()
            ->groupBy('quota_accounting_batch_id');

        $perDate = [];
        $alerts = [];
        foreach ($links as $batchLinks) {
            $batch = $batchLinks->first()->batch;
            $entry = $batch?->entry;
            $definition = $batch === null ? null : PaymentSettlementChannels::forBatchKind((string) $batch->kind);
            if ($batch === null || $entry === null || $definition === null) {
                continue;
            }
            $paymentType = (string) ($batch->payment_type ?: $definition['payment_type']);
            $menu = (string) $definition['menu'];
            $accredited = $entry->date?->toDateString() ?? '';
            $dates = $batchLinks
                ->map(fn (QuotaAccountingCollectionDate $link) => $link->collected_on?->toDateString())
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
            if ($batch->kind === QuotaAccountingBatch::KIND_MP_SETTLEMENT && count($dates) > 1 && $accredited !== '') {
                $alerts[] = $this->notice('mixed', $menu, $dates, [$accredited], [
                    'payment_type' => $paymentType,
                    'collection_entry_ids' => $this->collectionEntryIds($paymentType, $dates),
                    'accreditation_entry_ids' => [(int) $entry->id],
                ]);
            }
            $known = $batchLinks->every(fn (QuotaAccountingCollectionDate $link) => $link->haber_cents !== null);
            if ($known) {
                foreach ($batchLinks as $link) {
                    $date = $link->collected_on?->toDateString();
                    if ($date === null) {
                        continue;
                    }
                    $this->addHaber($perDate, $paymentType, $menu, $date, (int) $link->haber_cents, $accredited, (int) $entry->id);
                }

                continue;
            }

            if ($dates === []) {
                continue;
            }
            if (count($dates) === 1) {
                $this->addHaber($perDate, $paymentType, $menu, $dates[0], $this->creditCents($entry), $accredited, (int) $entry->id);

                continue;
            }
            $alert = $this->compare(
                $menu,
                $dates,
                $accredited === '' ? [] : [$accredited],
                $this->debitOfDates($paymentType, $dates),
                $this->creditCents($entry),
                $paymentType,
                [(int) $entry->id],
            );
            if ($alert !== null) {
                $alerts[] = $alert;
            }
        }

        ksort($perDate);
        foreach ($perDate as $bucket) {
            $alert = $this->compare(
                $bucket['menu'],
                [$bucket['collected']],
                array_values($bucket['accredited']),
                $this->collectionDebitCents($bucket['payment_type'], $bucket['collected']),
                $bucket['haber'],
                $bucket['payment_type'],
                array_values($bucket['entry_ids']),
            );
            if ($alert !== null) {
                $alerts[] = $alert;
            }
        }

        foreach (app(MercadoPagoReleaseSettlement::class)->exclusions() as $row) {
            $accredited = isset($row['release_on']) && is_string($row['release_on']) && $row['release_on'] !== '' ? [$row['release_on']] : [];
            $alerts[] = $this->notice(
                'excluded',
                'Mercado Pago',
                [],
                $accredited,
                $row + [
                    'collection_entry_ids' => [],
                    'accreditation_entry_ids' => $this->settlementEntryIds(QuotaAccountingBatch::KIND_MP_SETTLEMENT, $accredited),
                ],
            );
        }

        return $alerts;
    }

    public function sentence(array $case): string
    {
        if (($case['kind'] ?? '') === 'excluded') {
            return $this->exclusionSentence($case);
        }
        if (($case['kind'] ?? '') === 'mixed') {
            $collected = $case['collected_on'];
            sort($collected);
            $collectedLabel = implode(' y ', array_map(
                fn (string $date) => 'el '.$this->dateLabel($date),
                $collected
            ));

            return 'La acreditación de Mercado Pago del '.$this->datesLabel($case['accredited_on']).' incluye cupones cobrados '.$collectedLabel.'.';
        }

        $menu = (string) $case['menu'];
        $collected = $this->datesLabel($case['collected_on']);
        $accredited = $this->datesLabel($case['accredited_on']);
        $accreditedOf = $accredited !== '' ? ' del '.$accredited : '';
        if ($case['kind'] === 'missing') {
            return 'No se encontró el asiento de cobranza de '.$menu.' del '.$collected.', correspondiente a la acreditación'.$accreditedOf.'. No es posible verificar la coincidencia de los importes.';
        }

        $debe = '$'.$this->money((int) $case['debe_cents']);
        $haber = '$'.$this->money((int) $case['haber_cents']);
        $difference = '$'.$this->money(abs((int) $case['haber_cents'] - (int) $case['debe_cents']));
        if ($case['collected_on'] === $case['accredited_on'] && count($case['collected_on']) === 1) {
            return 'Los importes de cobranza y acreditación de '.$menu.' no coinciden para el '.$collected.'. Cobranza: '.$debe.'. Acreditación: '.$haber.'. Diferencia: '.$difference.'.';
        }

        return 'Diferencia de importes entre la cobranza y la acreditación de '.$menu.'. Cobranza del '.$collected.': '.$debe.'. Acreditación'.$accreditedOf.': '.$haber.'. Diferencia: '.$difference.'.';
    }

    /**
     * @param  list<array{menu: string, kind: string, collected_on: list<string>, accredited_on: list<string>, debe_cents: int|null, haber_cents: int}>  $cases
     * @return list<array{menu: string, count: int, amounts: list<array{when: string, debe: string, haber: string, difference: string}>, missing: list<string>}>
     */
    public function homeGroups(array $cases): array
    {
        $byMenu = [];
        foreach ($cases as $case) {
            $byMenu[(string) $case['menu']][] = $case;
        }

        $order = [];
        foreach (PaymentSettlementChannels::keys() as $channel) {
            $order[] = (string) PaymentSettlementChannels::get($channel)['menu'];
        }
        $menus = array_keys($byMenu);
        usort($menus, function (string $left, string $right) use ($order): int {
            $leftAt = array_search($left, $order, true);
            $rightAt = array_search($right, $order, true);

            return ($leftAt === false ? 99 : $leftAt) <=> ($rightAt === false ? 99 : $rightAt);
        });

        $groups = [];
        foreach ($menus as $menu) {
            $amounts = [];
            $missing = [];
            $excluded = [];
            $mixed = [];
            foreach ($byMenu[$menu] as $case) {
                if ($case['kind'] === 'missing') {
                    $missing[] = $this->homeItem($case);

                    continue;
                }
                if ($case['kind'] === 'excluded') {
                    $excluded[] = $this->homeItem($case);

                    continue;
                }
                if ($case['kind'] === 'mixed') {
                    $mixed[] = $this->homeItem($case);

                    continue;
                }
                $diff = (int) $case['haber_cents'] - (int) $case['debe_cents'];
                $amounts[] = $this->homeItem($case) + [
                    'when' => $this->whenLabel($case['collected_on'], $case['accredited_on']),
                    'debe' => '$'.$this->money((int) $case['debe_cents']),
                    'haber' => '$'.$this->money((int) $case['haber_cents']),
                    'difference' => ($diff > 0 ? '+' : '−').'$'.$this->money(abs($diff)),
                ];
            }
            $groups[] = [
                'menu' => $menu,
                'count' => count($amounts) + count($missing) + count($excluded) + count($mixed),
                'amounts' => $amounts,
                'missing' => $missing,
                'excluded' => $excluded,
                'mixed' => $mixed,
            ];
        }

        return $groups;
    }

    /**
     * @param  list<string>  $collected
     * @param  list<string>  $accredited
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function notice(string $kind, string $menu, array $collected, array $accredited, array $extra = []): array
    {
        return array_merge([
            'menu' => $menu,
            'kind' => $kind,
            'collected_on' => array_values($collected),
            'accredited_on' => array_values($accredited),
            'debe_cents' => null,
            'haber_cents' => 0,
            'payment_id' => '',
            'reasons' => [],
            'gross_cents' => 0,
            'commission_cents' => 0,
            'net_cents' => 0,
            'order_id' => 0,
            'collection_entry_ids' => [],
            'accreditation_entry_ids' => [],
        ], $extra);
    }

    /**
     * @param  array<string, mixed>  $case
     */
    private function exclusionSentence(array $case): string
    {
        $id = trim((string) ($case['payment_id'] ?? ''));
        $who = $id !== '' ? 'El pago '.$id : 'Un pago';
        $accredited = is_array($case['accredited_on'] ?? null) ? $case['accredited_on'] : [];
        $when = isset($accredited[0]) && $accredited[0] !== '' ? ' del '.$this->dateLabel((string) $accredited[0]) : '';
        $reasons = is_array($case['reasons'] ?? null) ? $case['reasons'] : [];
        $bits = [];
        if (in_array('unbalanced', $reasons, true)) {
            $bits[] = 'el neto más la comisión no cierran con el cobro';
        }
        if (in_array('missing_collected', $reasons, true)) {
            $bits[] = 'el cupón no tiene fecha de cobro';
        }
        if (in_array('missing_release', $reasons, true)) {
            $bits[] = 'no tiene fecha de liberación';
        }
        $sentence = $who.' no entra al asiento de acreditación de Mercado Pago'.$when.': '.implode(' y ', $bits).'.';
        if (in_array('unbalanced', $reasons, true)) {
            $sentence .= ' Cobro: $'.$this->money((int) ($case['gross_cents'] ?? 0)).'. Comisión: $'.$this->money((int) ($case['commission_cents'] ?? 0)).'. Neto: $'.$this->money((int) ($case['net_cents'] ?? 0)).'.';
        }

        return $sentence;
    }

    private function collectionDebitCents(string $paymentType, string $date): ?int
    {
        $batches = QuotaAccountingBatch::query()
            ->whereIn('kind', [
                QuotaAccountingBatch::KIND_COLLECTION,
                QuotaAccountingBatch::KIND_PLAN_COLLECTION,
            ])
            ->where('payment_type', $paymentType)
            ->whereDate('entry_date', $date)
            ->whereHas('entry', fn ($query) => $query->where('status', AccountingEntry::STATUS_POSTED))
            ->with('entry.lines.account')
            ->get();
        if ($batches->isEmpty()) {
            return null;
        }

        $bankCode = QuotaPaymentAccounts::bankCode($paymentType);
        $cents = 0;
        foreach ($batches as $batch) {
            foreach ($batch->entry->lines as $line) {
                if ($batch->kind === QuotaAccountingBatch::KIND_PLAN_COLLECTION
                    && ($bankCode === null || (string) $line->account?->code !== $bankCode)) {
                    continue;
                }
                $cents += (int) round(((float) $line->debit) * 100);
            }
        }

        return $cents;
    }

    /**
     * @param  array<string, array{payment_type: string, menu: string, collected: string, haber: int, accredited: array<string, string>, entry_ids: array<int, int>}>  $perDate
     */
    private function addHaber(array &$perDate, string $paymentType, string $menu, string $date, int $haber, string $accredited, int $entryId = 0): void
    {
        $key = $paymentType.'|'.$date;
        if (! isset($perDate[$key])) {
            $perDate[$key] = [
                'payment_type' => $paymentType,
                'menu' => $menu,
                'collected' => $date,
                'haber' => 0,
                'accredited' => [],
                'entry_ids' => [],
            ];
        }
        $perDate[$key]['haber'] += $haber;
        if ($accredited !== '') {
            $perDate[$key]['accredited'][$accredited] = $accredited;
        }
        if ($entryId > 0) {
            $perDate[$key]['entry_ids'][$entryId] = $entryId;
        }
    }

    /**
     * @param  list<string>  $dates
     * @return list<int>
     */
    private function collectionEntryIds(string $paymentType, array $dates): array
    {
        return array_values(array_unique(array_merge(
            $this->entryIdsFor(QuotaAccountingBatch::KIND_COLLECTION, $dates, $paymentType),
            $this->entryIdsFor(QuotaAccountingBatch::KIND_PLAN_COLLECTION, $dates, $paymentType),
        )));
    }

    /**
     * @param  list<string>  $dates
     * @return list<int>
     */
    private function settlementEntryIds(string $kind, array $dates): array
    {
        return $this->entryIdsFor($kind, $dates, null);
    }

    /**
     * @param  list<string>  $dates
     * @return list<int>
     */
    private function entryIdsFor(string $kind, array $dates, ?string $paymentType): array
    {
        $dates = array_values(array_filter(
            $dates,
            fn ($date) => is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1
        ));
        if ($dates === [] || ($paymentType !== null && $paymentType === '')) {
            return [];
        }

        return QuotaAccountingBatch::query()
            ->where('kind', $kind)
            ->when($paymentType !== null, fn ($query) => $query->where('payment_type', $paymentType))
            ->whereIn('entry_date', $dates)
            ->whereHas('entry', fn ($query) => $query->where('status', AccountingEntry::STATUS_POSTED))
            ->orderBy('id')
            ->pluck('accounting_entry_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $case
     * @return array{text: string, entries: list<int>, orders: list<int>, missing_collection: bool}
     */
    private function homeItem(array $case): array
    {
        $entries = [];
        foreach (['collection_entry_ids', 'accreditation_entry_ids'] as $key) {
            foreach (is_array($case[$key] ?? null) ? $case[$key] : [] as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $entries[$id] = $id;
                }
            }
        }
        $orderId = (int) ($case['order_id'] ?? 0);

        return [
            'text' => $this->sentence($case),
            'entries' => array_values($entries),
            'orders' => $orderId > 0 ? [$orderId] : [],
            'missing_collection' => ($case['kind'] ?? '') === 'missing',
            'on' => $this->referenceDate($case),
        ];
    }

    /**
     * La fecha de cobro. Si no hay, la de acreditación.
     *
     * @param  array<string, mixed>  $case
     */
    private function referenceDate(array $case): ?string
    {
        foreach (['collected_on', 'accredited_on'] as $key) {
            $dates = [];
            foreach (is_array($case[$key] ?? null) ? $case[$key] : [] as $date) {
                if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
                    $dates[] = $date;
                }
            }
            if ($dates !== []) {
                rsort($dates);

                return $dates[0];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $dates
     */
    private function debitOfDates(string $paymentType, array $dates): ?int
    {
        $sum = 0;
        foreach ($dates as $date) {
            $debe = $this->collectionDebitCents($paymentType, $date);
            if ($debe === null) {
                return null;
            }
            $sum += $debe;
        }

        return $sum;
    }

    private function creditCents(AccountingEntry $entry): int
    {
        $cents = 0;
        foreach ($entry->lines as $line) {
            $cents += (int) round(((float) $line->credit) * 100);
        }

        return $cents;
    }

    /**
     * @param  list<string>  $collectedDates
     * @param  list<string>  $accreditedDates
     * @param  list<int>  $accreditationEntryIds
     * @return array{menu: string, kind: string, collected_on: list<string>, accredited_on: list<string>, debe_cents: int|null, haber_cents: int}|null
     */
    private function compare(string $menu, array $collectedDates, array $accreditedDates, ?int $debe, int $haber, string $paymentType = '', array $accreditationEntryIds = []): ?array
    {
        $collectedDates = array_values(array_unique($collectedDates));
        $accreditedDates = array_values(array_unique(array_filter($accreditedDates)));
        sort($collectedDates);
        sort($accreditedDates);
        if ($debe !== null && abs($debe - $haber) <= 2) {
            return null;
        }

        return [
            'menu' => $menu,
            'kind' => $debe === null ? 'missing' : 'amount',
            'collected_on' => $collectedDates,
            'accredited_on' => $accreditedDates,
            'debe_cents' => $debe,
            'haber_cents' => $haber,
            'payment_type' => $paymentType,
            'collection_entry_ids' => $debe === null ? [] : $this->collectionEntryIds($paymentType, $collectedDates),
            'accreditation_entry_ids' => array_values(array_unique(array_filter(array_map('intval', $accreditationEntryIds)))),
        ];
    }

    /**
     * @param  list<string>  $collected
     * @param  list<string>  $accredited
     */
    private function whenLabel(array $collected, array $accredited): string
    {
        $collected = array_values(array_unique($collected));
        $accredited = array_values(array_unique(array_filter($accredited)));
        sort($collected);
        sort($accredited);
        if (count($collected) === 1 && $collected === $accredited) {
            return $this->dateLabel($collected[0]);
        }

        $year = count($accredited) === 1 ? substr($accredited[0], 0, 4) : null;
        $left = [];
        foreach ($collected as $date) {
            $left[] = $year !== null && str_starts_with($date, $year.'-')
                ? $this->dayMonth($date)
                : $this->dateLabel($date);
        }

        $right = $this->datesLabel($accredited);

        return $right === '' ? $this->joinLabels($left) : $this->joinLabels($left).' → '.$right;
    }

    private function dayMonth(string $date): string
    {
        $parts = explode('-', $date);

        return count($parts) === 3 ? $parts[2].'/'.$parts[1] : $date;
    }

    /**
     * @param  list<string>  $labels
     */
    private function joinLabels(array $labels): string
    {
        if (count($labels) < 2) {
            return $labels[0] ?? '';
        }
        $last = array_pop($labels);

        return implode(', ', $labels).' y '.$last;
    }

    /**
     * @param  list<string>  $dates
     */
    private function datesLabel(array $dates): string
    {
        $labels = [];
        foreach ($dates as $date) {
            $labels[$date] = $this->dateLabel($date);
        }
        ksort($labels);

        return implode(' y ', $labels);
    }

    private function dateLabel(string $date): string
    {
        $parts = explode('-', $date);

        return count($parts) === 3 ? $parts[2].'/'.$parts[1].'/'.$parts[0] : $date;
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.');
    }
}
