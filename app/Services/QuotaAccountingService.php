<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\AccountingEntry;
use App\Models\QuotaAccountingBatch;
use App\Models\QuotaAccountingCollectionDate;
use App\Models\QuotaAccountingOrder;
use App\Services\PaymentSettlement\PaymentSettlementChannels;
use App\Support\Eporres\ReportePagosDiaCriteria;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class QuotaAccountingService
{
    private const MONTHLY_TYPES = [
        'App\\Models\\MonthlyOrder',
        'App\\Models\\Order',
    ];

    private const PAID_STATE = 'App\\States\\Order\\Paid';

    private const CANCELLED_STATE = 'App\\States\\Order\\Cancelled';

    /**
     * Devenga las cuotas que vencen en el mes, asienta los cobros y los planes de pago.
     *
     * @return array<string, mixed>
     */
    public function postMonth(Carbon $month, bool $dryRun = false): array
    {
        $month = $month->copy()->startOfMonth();
        $accounts = $this->accountsByCode();

        $accrual = $this->postAccrual($month, $accounts, $dryRun);
        $grants = $this->postGrants($month, $accounts, $dryRun);
        $collections = $this->postCollections($month, $accounts, $dryRun);

        return [
            'month' => $month->format('Y-m'),
            'dry_run' => $dryRun,
            'accrual' => $accrual,
            'grants' => $grants,
            'collections' => $collections,
            'plans' => $this->postPaymentPlans($month, $accounts, $dryRun),
            'plan_collections' => $this->postPlanCollections($month, $accounts, $dryRun),
            'settlements' => $this->postMercadoPagoSettlements($month, $dryRun),
        ];
    }

    /**
     * Asienta una liquidación de Mercado Pago, Naranja X, Sol Pago o QR.
     * El asiento es uno por fecha, con los totales del archivo. No cruza cupones con ePorres.
     * El archivo acredita el neto en el banco, la comisión al gasto y cancela la cuenta a cobrar.
     * La cobranza de ese medio ya quedó registrada el día del cobro.
     *
     * @param  list<array{key: string, date: string, gross_cents: int, commission_cents: int, interest_cents: int, net_cents: int, document: string}>  $groups
     * @return list<AccountingEntry>
     */
    public function postImportedPaymentSettlements(string $channel, array $groups): array
    {
        if ($groups === []) {
            return [];
        }

        $definition = PaymentSettlementChannels::get($channel);
        $codes = array_values(array_filter([
            $definition['bank'],
            $definition['receivable'],
            $definition['commission'],
            $definition['interest'],
        ]));
        $accounts = $this->accountsForCodes($codes);

        return DB::transaction(function () use ($groups, $definition, $accounts, $channel) {
            $this->deleteNonFileAccreditations((string) $definition['batch_kind'], array_column($groups, 'date'));

            $entries = [];
            foreach ($groups as $group) {
                $batch = QuotaAccountingBatch::query()
                    ->where('batch_key', $group['key'])
                    ->lockForUpdate()
                    ->first();
                if ($batch !== null) {
                    $updated = $this->replaceImportedSettlement($batch, $definition, $group, $accounts);
                    if ($updated !== null) {
                        $entries[] = $updated;
                    }

                    continue;
                }
                $entries[] = $this->writeBatch(
                    kind: $definition['batch_kind'],
                    batchKey: $group['key'],
                    period: null,
                    entryDate: $group['date'],
                    paymentType: $definition['payment_type'],
                    entryKind: $definition['entry_kind'],
                    description: $this->importedSettlementDescription($definition, $group),
                    lines: $this->channelSettlementLines($definition, $group, $accounts),
                    orders: [],
                    role: $definition['batch_kind'],
                );
            }

            $this->linkImportedCollectionDates($groups);

            return $entries;
        });
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $group
     * @param  array<string, AccountingAccount>  $accounts
     */
    private function replaceImportedSettlement(QuotaAccountingBatch $batch, array $definition, array $group, array $accounts): ?AccountingEntry
    {
        $entry = $batch->entry;
        if ($entry !== null && $entry->manually_adjusted) {
            return null;
        }

        if ($entry === null || $entry->status !== AccountingEntry::STATUS_POSTED) {
            $batch->orders()->delete();
            $batch->collectionDates()->delete();
            if ($entry !== null) {
                $entry->lines()->delete();
                $entry->delete();
            }
            $batch->delete();

            return $this->writeBatch(
                kind: $definition['batch_kind'],
                batchKey: (string) $group['key'],
                period: null,
                entryDate: (string) $group['date'],
                paymentType: $definition['payment_type'],
                entryKind: $definition['entry_kind'],
                description: $this->importedSettlementDescription($definition, $group),
                lines: $this->channelSettlementLines($definition, $group, $accounts),
                orders: [],
                role: $definition['batch_kind'],
            );
        }

        $entry->lines()->delete();
        foreach ($this->channelSettlementLines($definition, $group, $accounts) as $line) {
            $entry->lines()->create($line);
        }
        $entry->update([
            'date' => $group['date'],
            'description' => $this->importedSettlementDescription($definition, $group),
            'status' => AccountingEntry::STATUS_POSTED,
        ]);

        return $entry->fresh();
    }

    /**
     * Guarda qué fechas de cobro cierra cada acreditación, para verlas juntas en el libro.
     *
     * @param  list<array{key?: string, collected_on?: mixed}>  $groups
     */
    public function linkImportedCollectionDates(array $groups): void
    {
        foreach ($groups as $group) {
            $key = (string) ($group['key'] ?? '');
            $dates = $group['collected_on'] ?? [];
            if ($key === '' || ! is_array($dates) || $dates === []) {
                continue;
            }
            $batch = QuotaAccountingBatch::query()->where('batch_key', $key)->first();
            if ($batch === null) {
                continue;
            }
            $valid = [];
            foreach ($dates as $date) {
                $date = (string) $date;
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
                    $valid[$date] = $date;
                }
            }
            if ($valid === []) {
                continue;
            }
            $amounts = is_array($group['collected_amounts'] ?? null) ? $group['collected_amounts'] : null;
            $batch->collectionDates()->whereNotIn('collected_on', array_values($valid))->delete();
            foreach ($valid as $date) {
                $link = QuotaAccountingCollectionDate::query()->firstOrCreate([
                    'quota_accounting_batch_id' => $batch->id,
                    'collected_on' => $date,
                ]);
                if ($amounts !== null && array_key_exists($date, $amounts)) {
                    $link->haber_cents = (int) $amounts[$date];
                    $link->save();
                }
            }
            $batch->unsetRelation('collectionDates');
            $this->syncAccreditationDescription($batch);
            app(AccountingEntryLinks::class)->syncFromCollectionDates($batch);
        }
    }

    /**
     * La fecha del asiento es la acreditación. La descripción lleva la fecha de cobro.
     */
    public function syncAccreditationDescription(QuotaAccountingBatch $batch): void
    {
        $batch->loadMissing(['entry', 'collectionDates']);
        $entry = $batch->entry;
        if ($entry === null || $entry->manually_adjusted) {
            return;
        }

        $definition = PaymentSettlementChannels::forBatchKind((string) $batch->kind);
        if ($definition === null) {
            return;
        }

        $dates = $batch->collectionDates
            ->map(fn (QuotaAccountingCollectionDate $row) => $row->collected_on?->toDateString())
            ->filter()
            ->all();
        $label = $this->collectionDatesLabel($dates);
        if ($label === '') {
            return;
        }

        $description = mb_substr(trim($definition['description'].' cobro '.$label), 0, 255);
        if ($description === $entry->description) {
            return;
        }

        $entry->description = $description;
        $entry->save();
    }

    public function syncAccreditationDescriptions(): void
    {
        QuotaAccountingBatch::query()
            ->whereIn('kind', [
                QuotaAccountingBatch::KIND_MP_SETTLEMENT,
                QuotaAccountingBatch::KIND_NX_SETTLEMENT,
                QuotaAccountingBatch::KIND_SOL_SETTLEMENT,
                QuotaAccountingBatch::KIND_QR_SETTLEMENT,
            ])
            ->whereHas('collectionDates')
            ->with(['entry', 'collectionDates'])
            ->orderBy('id')
            ->each(function (QuotaAccountingBatch $batch) {
                $this->syncAccreditationDescription($batch);
            });
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $group
     */
    private function importedSettlementDescription(array $definition, array $group): string
    {
        $label = $this->collectionDatesLabel($group['collected_on'] ?? null);
        if ($label !== '') {
            return mb_substr(trim($definition['description'].' cobro '.$label), 0, 255);
        }

        return mb_substr(trim($definition['description'].' '.trim((string) ($group['document'] ?? ''))), 0, 255);
    }

    private function collectionDatesLabel(mixed $dates): string
    {
        if (! is_array($dates)) {
            return '';
        }

        $labels = [];
        foreach ($dates as $date) {
            $date = (string) $date;
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $match) !== 1) {
                continue;
            }
            $labels[$date] = $match[3].'/'.$match[2].'/'.$match[1];
        }
        ksort($labels);

        return implode(', ', $labels);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array{gross_cents: int, commission_cents: int, interest_cents: int, net_cents: int}  $group
     * @param  array<string, AccountingAccount>  $accounts
     * @return list<array{accounting_account_id: int, debit: string, credit: string, memo: string}>
     */
    private function channelSettlementLines(array $definition, array $group, array $accounts): array
    {
        if ($definition['mode'] === 'fee') {
            $fee = (int) $group['commission_cents'];
            if ($fee < 1) {
                throw new RuntimeException('La liquidación no tiene comisión para asentar.');
            }
            $lines = [
                $this->line($accounts[$definition['commission']], $fee, 0, (string) $definition['commission_memo']),
                $this->line($accounts[$definition['bank']], 0, $fee, (string) $definition['bank_memo']),
            ];
            $this->assertBalanced($lines);

            return $lines;
        }

        $lines = [
            $this->line($accounts[$definition['bank']], (int) $group['net_cents'], 0, (string) $definition['bank_memo']),
        ];
        if ((int) $group['commission_cents'] > 0) {
            $lines[] = $this->line($accounts[$definition['commission']], (int) $group['commission_cents'], 0, (string) $definition['commission_memo']);
        }
        if ((int) $group['interest_cents'] > 0 && $definition['interest'] !== null) {
            $lines[] = $this->line($accounts[$definition['interest']], (int) $group['interest_cents'], 0, (string) $definition['interest_memo']);
        }
        $lines[] = $this->line($accounts[$definition['receivable']], 0, (int) $group['gross_cents'], (string) $definition['receivable_memo']);
        $this->assertBalanced($lines);

        return $lines;
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, AccountingAccount>
     */
    private function accountsForCodes(array $codes): array
    {
        $found = AccountingAccount::query()->whereIn('code', $codes)->get()->keyBy('code');
        $missing = array_values(array_diff($codes, $found->keys()->all()));
        if ($missing !== []) {
            throw new RuntimeException('Faltan cuentas en el plan: '.implode(', ', $missing));
        }
        $this->rejectGroupingAccounts($found->all());

        return $found->all();
    }

    /**
     * @param  array<string, AccountingAccount>  $accounts
     */
    private function rejectGroupingAccounts(array $accounts): void
    {
        $message = AccountingAccount::groupingUsedMessage(array_map(
            fn (AccountingAccount $account) => (int) $account->id,
            $accounts
        ));
        if ($message !== null) {
            throw new RuntimeException($message);
        }
    }

    /**
     * @param  array<string, AccountingAccount>  $accounts
     * @return array<string, mixed>
     */
    private function postAccrual(Carbon $month, array $accounts, bool $dryRun): array
    {
        $period = $month->format('Y-m');
        $already = QuotaAccountingBatch::query()
            ->where('kind', QuotaAccountingBatch::KIND_ACCRUAL)
            ->where('period', $period)
            ->exists();

        if ($already) {
            return [
                'status' => 'already_posted',
                'orders' => 0,
                'amount' => 0.0,
            ];
        }

        $posted = $this->postedOrderIdSet(QuotaAccountingBatch::KIND_ACCRUAL);
        $orders = [];
        $totalCents = 0;

        foreach ($this->accrualRows($month) as $row) {
            if (isset($posted[(int) $row->id])) {
                continue;
            }
            $amount = QuotaCollectionSplit::accrualAmount($row);
            if ($amount < 0.01) {
                continue;
            }
            $cents = $this->cents($amount);
            $totalCents += $cents;
            $orders[] = [
                'eporres_order_id' => (int) $row->id,
                'debtors_amount' => $this->money($cents),
                'interest_amount' => $this->money(0),
                'bank_amount' => $this->money(0),
            ];
        }

        $result = [
            'status' => $orders === [] ? 'empty' : ($dryRun ? 'preview' : 'posted'),
            'orders' => count($orders),
            'amount' => $this->amount($totalCents),
            'entry_number' => null,
            'description' => $this->accrualDescription($month),
        ];

        if ($dryRun || $orders === []) {
            return $result;
        }

        $entry = $this->writeBatch(
            kind: QuotaAccountingBatch::KIND_ACCRUAL,
            batchKey: 'accrual:'.$period,
            period: $period,
            entryDate: $month->toDateString(),
            paymentType: null,
            entryKind: AccountingEntry::KIND_QUOTA_ACCRUAL,
            description: $result['description'],
            lines: [
                $this->line($accounts[QuotaPaymentAccounts::DEBTORS], $totalCents, 0, 'Cuotas a cobrar'),
                $this->line($accounts[QuotaPaymentAccounts::QUOTAS_INCOME], 0, $totalCents, 'Cuotas'),
            ],
            orders: $orders,
            role: QuotaAccountingBatch::KIND_ACCRUAL,
        );

        $result['status'] = 'posted';
        $result['entry_number'] = $entry->entry_number;

        return $result;
    }

    /**
     * @param  array<string, AccountingAccount>  $accounts
     * @return array<string, mixed>
     */
    private function postGrants(Carbon $month, array $accounts, bool $dryRun): array
    {
        $period = $month->format('Y-m');
        $orders = [];
        $totalCents = 0;

        foreach ($this->grantRows($month) as $row) {
            $amount = QuotaScholarship::amount($row);
            if ($amount < 0.01) {
                continue;
            }
            $cents = $this->cents($amount);
            $totalCents += $cents;
            $orders[] = [
                'eporres_order_id' => (int) $row->id,
                'debtors_amount' => $this->money($cents),
                'interest_amount' => $this->money(0),
                'bank_amount' => $this->money(0),
            ];
        }

        $description = 'BECAS OTORGADAS MES '.$month->format('m/y');
        $batch = QuotaAccountingBatch::query()
            ->where('kind', QuotaAccountingBatch::KIND_GRANT)
            ->where('period', $period)
            ->with(['orders', 'entry'])
            ->first();

        $result = [
            'status' => $orders === [] ? 'empty' : ($dryRun ? 'preview' : 'posted'),
            'orders' => count($orders),
            'amount' => $this->amount($totalCents),
            'entry_number' => $batch?->entry?->entry_number,
            'description' => $description,
        ];

        if ($batch?->entry?->manually_adjusted) {
            $result['status'] = 'adjusted';

            return $result;
        }

        $stored = [];
        foreach ($batch?->orders ?? [] as $order) {
            $stored[(int) $order->eporres_order_id] = $this->cents($order->debtors_amount);
        }
        $desired = [];
        foreach ($orders as $order) {
            $desired[(int) $order['eporres_order_id']] = $this->cents($order['debtors_amount']);
        }
        ksort($stored);
        ksort($desired);
        if ($batch !== null && $stored === $desired && $batch->entry?->status === AccountingEntry::STATUS_POSTED) {
            $result['status'] = 'already_posted';

            return $result;
        }

        if ($dryRun) {
            $result['status'] = $batch === null ? ($orders === [] ? 'empty' : 'preview') : 'updated';

            return $result;
        }

        if ($orders === []) {
            if ($batch !== null) {
                $this->clearEntry($batch->entry);
                $batch->orders()->delete();
            }
            $result['status'] = 'empty';

            return $result;
        }

        if ($batch === null) {
            $entry = $this->writeBatch(
                kind: QuotaAccountingBatch::KIND_GRANT,
                batchKey: 'grant:'.$period,
                period: $period,
                entryDate: $month->toDateString(),
                paymentType: null,
                entryKind: AccountingEntry::KIND_QUOTA_GRANT,
                description: $description,
                lines: $this->grantLines($totalCents, $accounts),
                orders: $orders,
                role: QuotaAccountingBatch::KIND_GRANT,
            );
            $result['status'] = 'posted';
            $result['entry_number'] = $entry->entry_number;

            return $result;
        }

        $entry = DB::transaction(function () use ($batch, $orders, $totalCents, $accounts, $description, $month) {
            $batch->orders()->delete();
            $now = now();
            foreach (array_chunk($orders, 500) as $chunk) {
                $rows = [];
                foreach ($chunk as $order) {
                    $rows[] = [
                        'quota_accounting_batch_id' => $batch->id,
                        'role' => QuotaAccountingBatch::KIND_GRANT,
                        'eporres_order_id' => $order['eporres_order_id'],
                        'debtors_amount' => $order['debtors_amount'],
                        'interest_amount' => $order['interest_amount'],
                        'bank_amount' => $order['bank_amount'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                QuotaAccountingOrder::query()->insert($rows);
            }

            $entry = $batch->entry;
            if ($entry === null) {
                $entry = AccountingEntry::query()->create([
                    'entry_number' => AccountingEntry::nextEntryNumber(),
                    'date' => $month->toDateString(),
                    'kind' => AccountingEntry::KIND_QUOTA_GRANT,
                    'status' => AccountingEntry::STATUS_POSTED,
                    'source_type' => $batch->getMorphClass(),
                    'source_id' => $batch->id,
                    'description' => $description,
                    'created_by_id' => null,
                ]);
                $batch->update(['accounting_entry_id' => $entry->id]);
            }

            $entry->lines()->delete();
            foreach ($this->grantLines($totalCents, $accounts) as $line) {
                $entry->lines()->create($line);
            }
            $entry->update([
                'status' => AccountingEntry::STATUS_POSTED,
                'description' => $description,
                'date' => $month->toDateString(),
            ]);

            return $entry->fresh();
        });

        $result['status'] = 'updated';
        $result['entry_number'] = $entry?->entry_number;

        return $result;
    }

    /**
     * @param  array<string, AccountingAccount>  $accounts
     * @return list<array{accounting_account_id: int, debit: string, credit: string, memo: string}>
     */
    private function grantLines(int $totalCents, array $accounts): array
    {
        $lines = [
            $this->line($accounts[QuotaPaymentAccounts::SCHOLARSHIP], $totalCents, 0, 'Descuentos por beca'),
            $this->line($accounts[QuotaPaymentAccounts::DEBTORS], 0, $totalCents, 'Becas otorgadas'),
        ];
        $this->assertBalanced($lines);

        return $lines;
    }

    /**
     * @return list<object>
     */
    private function grantRows(Carbon $month): array
    {
        return DB::connection('eporres')->table('orders')
            ->whereBetween('quota_number', [1, 10])
            ->whereIn('type', self::MONTHLY_TYPES)
            ->where('state', '!=', self::CANCELLED_STATE)
            ->where(function ($query) {
                $query->whereNull('estado')->orWhere('estado', 1);
            })
            ->whereBetween('expirated_at', [
                $month->copy()->startOfMonth()->toDateTimeString(),
                $month->copy()->endOfMonth()->toDateTimeString(),
            ])
            ->where(function ($query) {
                $query->where('grant_amount', '>', 0)
                    ->orWhere('discount_amount', '>', 0);
            })
            ->orderBy('id')
            ->get(['id', 'quota_amount', 'grant_amount', 'discount_amount', 'discount_reason'])
            ->all();
    }

    /**
     * @param  array<string, AccountingAccount>  $accounts
     * @return array<string, mixed>
     */
    private function postCollections(Carbon $month, array $accounts, bool $dryRun): array
    {
        $splitIds = $this->idSet(
            DB::connection('eporres')->table('order_split_payments')->pluck('order_id')
        );
        $settledByPlan = $this->settledByPlanIds();

        $skipped = [
            'split' => 0,
            'plan' => 0,
            'no_payment_type' => 0,
            'unmapped' => [],
            'zero' => 0,
            'adjusted' => 0,
        ];
        $unpostedCents = 0;
        $groups = [];

        foreach ($this->collectionRows($month) as $row) {
            $classified = $this->classifyCollectionRow($row, $splitIds, $settledByPlan);
            if ($classified === null) {
                $reason = $this->collectionSkipReason($row, $splitIds, $settledByPlan);
                if ($reason === 'unmapped') {
                    $type = trim((string) $row->payment_type);
                    $skipped['unmapped'][$type] = ($skipped['unmapped'][$type] ?? 0) + 1;
                } elseif ($reason !== null) {
                    $skipped[$reason]++;
                }
                continue;
            }

            $key = $classified['date'].'|'.$classified['payment_type'];
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'date' => $classified['date'],
                    'payment_type' => $classified['payment_type'],
                    'bank_code' => $classified['bank_code'],
                    'orders' => [],
                    'debtors' => 0,
                    'interest' => 0,
                    'bank' => 0,
                    'unposted' => 0,
                ];
            }
            $groups[$key]['debtors'] += $classified['debtors'];
            $groups[$key]['interest'] += $classified['interest'];
            $groups[$key]['bank'] += $classified['bank'];
            $groups[$key]['unposted'] += $classified['unposted'];
            $groups[$key]['orders'][] = [
                'eporres_order_id' => $classified['order_id'],
                'debtors_amount' => $this->money($classified['debtors']),
                'interest_amount' => $this->money($classified['interest']),
                'bank_amount' => $this->money($classified['bank']),
            ];
            $unpostedCents += $classified['unposted'];
        }

        ksort($groups);

        $batchesByGroup = [];
        $batches = QuotaAccountingBatch::query()
            ->where('kind', QuotaAccountingBatch::KIND_COLLECTION)
            ->whereDate('entry_date', '>=', $month->copy()->startOfMonth()->toDateString())
            ->whereDate('entry_date', '<=', $month->copy()->endOfMonth()->toDateString())
            ->whereHas('entry', fn ($query) => $query->where('status', AccountingEntry::STATUS_POSTED))
            ->with(['orders', 'entry'])
            ->orderBy('id')
            ->get();

        foreach ($batches as $batch) {
            $key = $batch->entry_date->toDateString().'|'.$batch->payment_type;
            $batchesByGroup[$key][] = $batch;
        }

        $postedGroups = [];
        $updatedGroups = [];
        $keys = array_unique(array_merge(array_keys($groups), array_keys($batchesByGroup)));
        sort($keys);

        foreach ($keys as $key) {
            $group = $groups[$key] ?? null;
            $groupBatches = $batchesByGroup[$key] ?? [];
            if ($group === null) {
                $sample = $groupBatches[0];
                $group = [
                    'date' => $sample->entry_date->toDateString(),
                    'payment_type' => (string) $sample->payment_type,
                    'bank_code' => QuotaPaymentAccounts::bankCode($sample->payment_type) ?? '',
                    'orders' => [],
                    'debtors' => 0,
                    'interest' => 0,
                    'bank' => 0,
                    'unposted' => 0,
                ];
            }

            $description = $group['payment_type'] === 'Mercado Pago'
                ? 'COBRANZA CUOTAS MERCADO PAGO A COBRAR'
                : 'COBRANZA CUOTAS '.mb_strtoupper($group['payment_type']);
            $stored = $this->storedCollectionCents($groupBatches);
            $desired = $this->desiredCollectionCents($group['orders']);

            if ($groupBatches !== [] && $this->groupIsAdjusted($groupBatches)) {
                if (QuotaCollectionReconcile::differs($stored, $desired)) {
                    $skipped['adjusted']++;
                }
                continue;
            }

            if ($groupBatches !== [] && ! QuotaCollectionReconcile::differs($stored, $desired)) {
                continue;
            }

            $item = [
                'date' => $group['date'],
                'payment_type' => $group['payment_type'],
                'bank_code' => $group['bank_code'],
                'orders' => count($group['orders']),
                'debtors' => $this->amount($group['debtors']),
                'interest' => $this->amount($group['interest']),
                'bank' => $this->amount($group['bank']),
                'previous_bank' => $this->amount($this->sumCents($stored, 2)),
                'description' => $description,
                'entry_number' => isset($groupBatches[0]) ? $groupBatches[0]->entry?->entry_number : null,
            ];

            if (! $dryRun) {
                if ($groupBatches === []) {
                    $entry = DB::transaction(function () use ($group, $accounts, $description) {
                        $this->releaseCollectionOrders(
                            array_column($group['orders'], 'eporres_order_id'),
                            null,
                            $accounts,
                        );

                        return $this->writeBatch(
                            kind: QuotaAccountingBatch::KIND_COLLECTION,
                            batchKey: 'collection:'.$group['date'].':'.$group['payment_type'].':'.$group['orders'][0]['eporres_order_id'].':'.now()->format('YmdHis'),
                            period: null,
                            entryDate: $group['date'],
                            paymentType: $group['payment_type'],
                            entryKind: AccountingEntry::KIND_QUOTA_COLLECTION,
                            description: $description,
                            lines: $this->collectionLines($group, $accounts),
                            orders: $group['orders'],
                            role: QuotaAccountingBatch::KIND_COLLECTION,
                        );
                    });
                    $item['entry_number'] = $entry->entry_number;
                } else {
                    $entry = $this->rewriteCollectionGroup($group, $groupBatches, $accounts, $description);
                    $item['entry_number'] = $entry?->entry_number;
                }
            }

            if ($groupBatches === []) {
                $postedGroups[] = $item;
            } else {
                $updatedGroups[] = $item;
            }
        }

        $collectedOrderIds = [];
        foreach ($groups as $group) {
            foreach ($group['orders'] as $order) {
                $collectedOrderIds[(int) $order['eporres_order_id']] = true;
            }
        }

        return [
            'groups' => $postedGroups,
            'updated' => $updatedGroups,
            'skipped' => $skipped,
            'unposted_surcharge' => $this->amount($unpostedCents),
            'collected_order_ids' => $collectedOrderIds,
        ];
    }

    /**
     * Un asiento por fecha de liberación. Suma los cupones de ese día.
     * El que no cierra, o no tiene fecha de cobro, queda afuera y se ve en el inicio.
     *
     * @return array{groups: list<array<string, mixed>>, updated: list<array<string, mixed>>, skipped: array<string, int>, removed: int, replaced: int}
     */
    private function postMercadoPagoSettlements(Carbon $month, bool $dryRun): array
    {
        $built = app(MercadoPagoReleaseSettlement::class)->forMonth($month);
        $groups = $built['groups'];
        $skipped = $built['skipped'];
        $dates = [];
        $cursor = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        while ($cursor->lte($end)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        $fromFile = $this->excelMercadoPagoDates(array_column($groups, 'date'));
        $adjustedDates = $this->manuallyAdjustedMercadoPagoDates(array_column($groups, 'date'));
        $blocked = array_values(array_unique(array_merge($fromFile, $adjustedDates)));
        if ($blocked !== []) {
            $groups = array_values(array_filter(
                $groups,
                fn (array $group) => ! in_array($group['date'], $blocked, true)
            ));
            $skipped['adjusted'] += count($adjustedDates);
        }

        $keys = array_column($groups, 'key');
        $existing = $keys === []
            ? []
            : QuotaAccountingBatch::query()->whereIn('batch_key', $keys)->pluck('batch_key')->all();

        if ($dryRun) {
            return $this->mercadoPagoSettlementReport($groups, $existing, [], $skipped, 0, 0);
        }

        $removed = $this->deleteNonFileAccreditations(null, $dates);
        $entries = $this->postImportedPaymentSettlements(PaymentSettlementChannels::MERCADOPAGO, $groups);
        $numbers = [];
        foreach ($entries as $entry) {
            $batchKey = QuotaAccountingBatch::query()->where('accounting_entry_id', $entry->id)->value('batch_key');
            if (is_string($batchKey) && $batchKey !== '') {
                $numbers[$batchKey] = $entry->entry_number;
            }
        }

        return $this->mercadoPagoSettlementReport($groups, $existing, $numbers, $skipped, $removed, 0);
    }

    /**
     * Fechas que ya tienen el asiento cargado con el Excel de Mercado Pago.
     * Actualizar no las vuelve a escribir.
     *
     * @param  list<string>  $dates
     * @return list<string>
     */
    private function excelMercadoPagoDates(array $dates): array
    {
        $dates = array_values(array_filter($dates, fn ($date) => is_string($date) && $date !== ''));
        if ($dates === []) {
            return [];
        }

        return QuotaAccountingBatch::query()
            ->where('kind', QuotaAccountingBatch::KIND_MP_SETTLEMENT)
            ->whereIn('entry_date', $dates)
            ->where(function ($query) {
                $query->where('batch_key', 'like', 'mercadopago:%')
                    ->orWhere('batch_key', 'like', 'mp-settlement:excel:%');
            })
            ->whereHas('entry', fn ($query) => $query->where('status', AccountingEntry::STATUS_POSTED))
            ->pluck('entry_date')
            ->map(fn ($date) => $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) $date)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $dates
     * @return list<string>
     */
    private function manuallyAdjustedMercadoPagoDates(array $dates): array
    {
        $dates = array_values(array_filter($dates, fn ($date) => is_string($date) && $date !== ''));
        if ($dates === []) {
            return [];
        }

        return QuotaAccountingBatch::query()
            ->where('kind', QuotaAccountingBatch::KIND_MP_SETTLEMENT)
            ->whereIn('entry_date', $dates)
            ->whereHas('entry', fn ($query) => $query->where('manually_adjusted', true))
            ->pluck('entry_date')
            ->map(fn ($date) => $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) $date)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @param  list<string>  $existing
     * @param  array<string, string|null>  $numbers
     * @param  array<string, int>  $skipped
     * @return array{groups: list<array<string, mixed>>, updated: list<array<string, mixed>>, skipped: array<string, int>, removed: int, replaced: int}
     */
    private function mercadoPagoSettlementReport(array $groups, array $existing, array $numbers, array $skipped, int $removed, int $replaced): array
    {
        $created = [];
        $updated = [];
        foreach ($groups as $group) {
            $row = [
                'date' => $group['date'],
                'orders' => (int) ($group['orders'] ?? 0),
                'net' => ((int) $group['net_cents']) / 100,
                'commission' => ((int) $group['commission_cents']) / 100,
                'gross' => ((int) $group['gross_cents']) / 100,
                'entry_number' => $numbers[$group['key']] ?? null,
            ];
            if (in_array($group['key'], $existing, true)) {
                $updated[] = $row;
            } else {
                $created[] = $row;
            }
        }

        return [
            'groups' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'removed' => $removed,
            'replaced' => $replaced,
        ];
    }

    /**
     * @param  list<mixed>  $dates
     */
    private function deleteNonFileAccreditations(?string $kind, array $dates): int
    {
        $dates = array_values(array_unique(array_filter(
            $dates,
            fn ($date) => is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1
        )));
        if ($dates === []) {
            return 0;
        }

        return DB::transaction(function () use ($kind, $dates) {
            $batches = QuotaAccountingBatch::query()
                ->whereIn('kind', $kind === null ? $this->accreditationKinds() : [$kind])
                ->whereIn('entry_date', $dates)
                ->whereHas('entry', function ($query) {
                    $query->where('status', AccountingEntry::STATUS_POSTED)
                        ->where('manually_adjusted', false);
                })
                ->with('entry')
                ->lockForUpdate()
                ->get()
                ->filter(fn (QuotaAccountingBatch $batch) => ! $this->isUploadedAccreditation((string) $batch->batch_key))
                ->values();

            $count = 0;
            foreach ($batches as $batch) {
                $entry = $batch->entry;
                $batch->orders()->delete();
                $batch->delete();
                if ($entry !== null) {
                    $entry->lines()->delete();
                    $entry->delete();
                }
                $count++;
            }

            return $count;
        });
    }

    /**
     * @return list<string>
     */
    private function accreditationKinds(): array
    {
        return [
            QuotaAccountingBatch::KIND_MP_SETTLEMENT,
            QuotaAccountingBatch::KIND_NX_SETTLEMENT,
            QuotaAccountingBatch::KIND_SOL_SETTLEMENT,
            QuotaAccountingBatch::KIND_QR_SETTLEMENT,
        ];
    }

    private function isUploadedAccreditation(string $batchKey): bool
    {
        foreach (['mercadopago:', 'naranja:', 'sol:', 'qr:', 'mp-settlement:excel:', 'mp-release:'] as $prefix) {
            if (str_starts_with($batchKey, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, true>  $splitIds
     * @param  array<int, true>  $settledByPlan
     * @return array{order_id: int, date: string, payment_type: string, bank_code: string, debtors: int, interest: int, bank: int, unposted: int}|null
     */
    private function classifyCollectionRow(object $row, array $splitIds, array $settledByPlan): ?array
    {
        if ($this->collectionSkipReason($row, $splitIds, $settledByPlan) !== null) {
            return null;
        }

        $paymentType = trim((string) $row->payment_type);
        $split = QuotaCollectionSplit::fromRow($row);
        $bank = $this->cents($split['bank']);
        $unposted = $this->cents($split['unposted']);
        if ($paymentType === 'Mercado Pago') {
            $bank += $unposted;
            $unposted = 0;
        }

        return [
            'order_id' => (int) $row->id,
            'date' => Carbon::parse($row->paid_at)->toDateString(),
            'payment_type' => $paymentType,
            'bank_code' => (string) QuotaPaymentAccounts::bankCode($paymentType),
            'debtors' => $this->cents($split['debtors']),
            'interest' => $this->cents($split['interest']),
            'bank' => $bank,
            'unposted' => $unposted,
        ];
    }

    /**
     * @param  array<int, true>  $splitIds
     * @param  array<int, true>  $settledByPlan
     */
    private function collectionSkipReason(object $row, array $splitIds, array $settledByPlan): ?string
    {
        $orderId = (int) $row->id;
        if (isset($splitIds[$orderId])) {
            return 'split';
        }
        $paymentType = trim((string) $row->payment_type);
        if ($paymentType === '') {
            return 'no_payment_type';
        }
        if ($paymentType === 'Plan de Pago' || isset($settledByPlan[$orderId])) {
            return 'plan';
        }
        if (QuotaPaymentAccounts::bankCode($paymentType) === null) {
            return 'unmapped';
        }
        if ($this->cents(QuotaCollectionSplit::fromRow($row)['bank']) < 1) {
            return 'zero';
        }

        return null;
    }

    /**
     * @param  list<QuotaAccountingBatch>  $batches
     * @return array<int, array{0: int, 1: int, 2: int}>
     */
    private function storedCollectionCents(array $batches): array
    {
        $stored = [];
        foreach ($batches as $batch) {
            foreach ($batch->orders as $order) {
                $stored[(int) $order->eporres_order_id] = [
                    $this->cents($order->debtors_amount),
                    $this->cents($order->interest_amount),
                    $this->cents($order->bank_amount),
                ];
            }
        }

        return $stored;
    }

    /**
     * @param  list<array{eporres_order_id: int, debtors_amount: string, interest_amount: string, bank_amount: string}>  $orders
     * @return array<int, array{0: int, 1: int, 2: int}>
     */
    private function desiredCollectionCents(array $orders): array
    {
        $desired = [];
        foreach ($orders as $order) {
            $desired[(int) $order['eporres_order_id']] = [
                $this->cents($order['debtors_amount']),
                $this->cents($order['interest_amount']),
                $this->cents($order['bank_amount']),
            ];
        }

        return $desired;
    }

    /**
     * @param  array<int, array{0: int, 1: int, 2: int}>  $amounts
     */
    private function sumCents(array $amounts, int $index): int
    {
        $total = 0;
        foreach ($amounts as $row) {
            $total += $row[$index];
        }

        return $total;
    }

    /**
     * @param  array{date: string, payment_type: string, bank_code: string, orders: list<array{eporres_order_id: int, debtors_amount: string, interest_amount: string, bank_amount: string}>, debtors: int, interest: int, bank: int}  $group
     * @param  list<QuotaAccountingBatch>  $batches
     * @param  array<string, AccountingAccount>  $accounts
     */
    private function rewriteCollectionGroup(array $group, array $batches, array $accounts, string $description): ?AccountingEntry
    {
        return DB::transaction(function () use ($group, $batches, $accounts, $description) {
            $primary = $batches[0];
            $orderIds = array_column($group['orders'], 'eporres_order_id');
            $this->releaseCollectionOrders($orderIds, $primary->id, $accounts);

            foreach (array_slice($batches, 1) as $extra) {
                $extra->orders()->delete();
                $this->clearEntry($extra->entry);
            }

            $primary->orders()->delete();
            if ($group['orders'] === []) {
                $this->clearEntry($primary->entry);
                app(AccountingEntryLinks::class)->attachCollection($primary);

                return $primary->entry;
            }

            $now = now();
            foreach (array_chunk($group['orders'], 500) as $chunk) {
                $rows = [];
                foreach ($chunk as $order) {
                    $rows[] = [
                        'quota_accounting_batch_id' => $primary->id,
                        'role' => QuotaAccountingBatch::KIND_COLLECTION,
                        'eporres_order_id' => $order['eporres_order_id'],
                        'debtors_amount' => $order['debtors_amount'],
                        'interest_amount' => $order['interest_amount'],
                        'bank_amount' => $order['bank_amount'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                QuotaAccountingOrder::query()->insert($rows);
            }

            $entry = $primary->entry;
            $entry->lines()->delete();
            foreach ($this->collectionLines($group, $accounts) as $line) {
                $entry->lines()->create($line);
            }
            $entry->update([
                'status' => AccountingEntry::STATUS_POSTED,
                'description' => $description,
            ]);
            app(AccountingEntryLinks::class)->attachCollection($primary);

            return $entry->fresh();
        });
    }

    /**
     * Saca estas cuotas de otros asientos vigentes para poder ubicarlas en el grupo correcto.
     *
     * @param  list<int>  $orderIds
     * @param  array<string, AccountingAccount>  $accounts
     */
    private function releaseCollectionOrders(array $orderIds, ?int $keepBatchId, array $accounts, string $role = QuotaAccountingBatch::KIND_COLLECTION): void
    {
        if ($orderIds === []) {
            return;
        }

        $misplaced = QuotaAccountingOrder::query()
            ->where('role', $role)
            ->whereIn('eporres_order_id', $orderIds)
            ->when($keepBatchId, fn ($query) => $query->where('quota_accounting_batch_id', '!=', $keepBatchId))
            ->with('batch.entry')
            ->get();

        if ($misplaced->isEmpty()) {
            return;
        }

        $batchIds = $misplaced->pluck('quota_accounting_batch_id')->unique()->all();
        QuotaAccountingOrder::query()->whereIn('id', $misplaced->pluck('id'))->delete();

        $batches = QuotaAccountingBatch::query()->with(['orders', 'entry'])->whereIn('id', $batchIds)->get();
        foreach ($batches as $batch) {
            if ($batch->entry === null || $batch->entry->status !== AccountingEntry::STATUS_POSTED) {
                continue;
            }
            $this->rewriteBatchFromRemainingOrders($batch, $accounts);
        }
    }

    /**
     * @param  array<string, AccountingAccount>  $accounts
     */
    private function rewriteBatchFromRemainingOrders(QuotaAccountingBatch $batch, array $accounts): void
    {
        $batch->load('orders', 'entry');
        if (in_array($batch->kind, $this->accreditationKinds(), true)) {
            if (! $this->isUploadedAccreditation((string) $batch->batch_key)) {
                $this->deleteNonFileAccreditations((string) $batch->kind, [$batch->entry_date->toDateString()]);
            }

            return;
        }
        if ($batch->orders->isEmpty()) {
            $this->clearEntry($batch->entry);

            return;
        }

        $debtors = 0;
        $interest = 0;
        $bank = 0;
        foreach ($batch->orders as $order) {
            $debtors += $this->cents($order->debtors_amount);
            $interest += $this->cents($order->interest_amount);
            $bank += $this->cents($order->bank_amount);
        }

        $bankCode = QuotaPaymentAccounts::bankCode($batch->payment_type);
        if ($bankCode === null || $batch->entry === null) {
            return;
        }

        $group = [
            'bank_code' => $bankCode,
            'debtors' => $debtors,
            'interest' => $interest,
            'bank' => $bank,
        ];
        $batch->entry->lines()->delete();
        foreach ($this->collectionLines($group, $accounts) as $line) {
            $batch->entry->lines()->create($line);
        }
    }

    /**
     * @param  list<QuotaAccountingBatch>  $batches
     */
    private function groupIsAdjusted(array $batches): bool
    {
        foreach ($batches as $batch) {
            if ($batch->entry?->manually_adjusted) {
                return true;
            }
        }

        return false;
    }

    private function clearEntry(?AccountingEntry $entry): void
    {
        if ($entry === null || $entry->status !== AccountingEntry::STATUS_POSTED) {
            return;
        }

        $entry->lines()->delete();
        $entry->update(['status' => AccountingEntry::STATUS_REVERSED]);
    }

    /**
     * @param  array{bank_code: string, debtors: int, interest: int, bank: int}  $group
     * @param  array<string, AccountingAccount>  $accounts
     * @return list<array{accounting_account_id: int, debit: string, credit: string, memo: string}>
     */
    private function collectionLines(array $group, array $accounts): array
    {
        $memo = $group['bank_code'] === QuotaPaymentAccounts::MP_RECEIVABLE
            ? 'Mercado Pago a cobrar'
            : 'Cobranza';
        $lines = [
            $this->line($accounts[$group['bank_code']], $group['bank'], 0, $memo),
        ];
        if ($group['debtors'] > 0) {
            $lines[] = $this->line($accounts[QuotaPaymentAccounts::DEBTORS], 0, $group['debtors'], 'Cuotas cobradas');
        }
        if ($group['interest'] > 0) {
            $lines[] = $this->line($accounts[QuotaPaymentAccounts::LATE_INTEREST], 0, $group['interest'], 'Intereses por mora');
        }
        $surcharge = $group['bank'] - $group['debtors'] - $group['interest'];
        if ($surcharge > 0 && $group['bank_code'] === QuotaPaymentAccounts::MP_RECEIVABLE) {
            $lines[] = $this->line($accounts[QuotaPaymentAccounts::MP_SURCHARGE_INCOME], 0, $surcharge, 'Recargo Mercado Pago');
        }
        $this->assertBalanced($lines);

        return $lines;
    }

    /**
     * @param  list<array{accounting_account_id: int, debit: string, credit: string, memo: string}>  $lines
     */
    private function assertBalanced(array $lines): void
    {
        $debit = 0;
        $credit = 0;
        foreach ($lines as $line) {
            $debit += $this->cents($line['debit']);
            $credit += $this->cents($line['credit']);
        }
        if ($debit !== $credit) {
            throw new RuntimeException('El asiento de cobranza no balancea.');
        }
    }

    /**
     * @param  list<array{accounting_account_id: int, debit: string, credit: string, memo: string}>  $lines
     * @param  list<array{eporres_order_id: int, debtors_amount: string, interest_amount: string, bank_amount: string}>  $orders
     */
    private function writeBatch(
        string $kind,
        string $batchKey,
        ?string $period,
        string $entryDate,
        ?string $paymentType,
        string $entryKind,
        string $description,
        array $lines,
        array $orders,
        string $role,
    ): AccountingEntry {
        return DB::transaction(function () use ($kind, $batchKey, $period, $entryDate, $paymentType, $entryKind, $description, $lines, $orders, $role) {
            $batch = QuotaAccountingBatch::query()->create([
                'kind' => $kind,
                'batch_key' => $batchKey,
                'period' => $period,
                'entry_date' => $entryDate,
                'payment_type' => $paymentType,
            ]);

            $entry = AccountingEntry::query()->create([
                'entry_number' => AccountingEntry::nextEntryNumber(),
                'date' => $entryDate,
                'kind' => $entryKind,
                'status' => AccountingEntry::STATUS_POSTED,
                'source_type' => $batch->getMorphClass(),
                'source_id' => $batch->id,
                'description' => $description,
                'created_by_id' => null,
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create($line);
            }

            $batch->update(['accounting_entry_id' => $entry->id]);

            $now = now();
            foreach (array_chunk($orders, 500) as $chunk) {
                $rows = [];
                foreach ($chunk as $order) {
                    $rows[] = [
                        'quota_accounting_batch_id' => $batch->id,
                        'role' => $role,
                        'eporres_order_id' => $order['eporres_order_id'],
                        'debtors_amount' => $order['debtors_amount'],
                        'interest_amount' => $order['interest_amount'],
                        'bank_amount' => $order['bank_amount'],
                        'financing_amount' => $order['financing_amount'] ?? $this->money(0),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                QuotaAccountingOrder::query()->insert($rows);
            }

            if ($kind === QuotaAccountingBatch::KIND_COLLECTION) {
                app(AccountingEntryLinks::class)->attachCollection($batch);
            }

            return $entry;
        });
    }

    /**
     * Un asiento por plan generado en el mes: el plan reemplaza a deudores por cuotas,
     * reconoce la mora y deja el interés de financiación a devengar.
     *
     * @param  array<string, AccountingAccount>  $accounts
     * @return array{groups: list<array<string, mixed>>, updated: list<array<string, mixed>>, skipped: array{adjusted: int}}
     */
    private function postPaymentPlans(Carbon $month, array $accounts, bool $dryRun): array
    {
        $plans = DB::connection('eporres')->table('students_plans')
            ->whereBetween('created_at', [
                $month->copy()->startOfMonth()->toDateTimeString(),
                $month->copy()->endOfMonth()->toDateTimeString(),
            ])
            ->orderBy('id')
            ->get(['id', 'created_at']);

        $built = [];
        if ($plans->isNotEmpty()) {
            $ids = $plans->pluck('id')->map(fn ($id) => (int) $id)->all();
            $pendByPlan = DB::connection('eporres')->table('orders_pend_plan')
                ->whereIn('student_plan_id', $ids)
                ->get(['student_plan_id', 'order_id', 'type', 'amount'])
                ->groupBy(fn ($row) => (int) $row->student_plan_id);
            $amortByPlan = DB::connection('eporres')->table('amortizations')
                ->whereIn('student_plan_id', $ids)
                ->get(['student_plan_id', 'order_id', 'interest_amount'])
                ->groupBy(fn ($row) => (int) $row->student_plan_id);

            foreach ($plans as $plan) {
                $planId = (int) $plan->id;
                $orders = $this->paymentPlanOpeningOrders(
                    $pendByPlan->get($planId, collect()),
                    $amortByPlan->get($planId, collect()),
                );
                if ($orders === []) {
                    continue;
                }
                $built[$planId] = [
                    'date' => Carbon::parse($plan->created_at)->toDateString(),
                    'orders' => $orders,
                ];
            }
        }

        $existing = QuotaAccountingBatch::query()
            ->where('kind', QuotaAccountingBatch::KIND_PLAN)
            ->whereDate('entry_date', '>=', $month->copy()->startOfMonth()->toDateString())
            ->whereDate('entry_date', '<=', $month->copy()->endOfMonth()->toDateString())
            ->with(['orders', 'entry'])
            ->get()
            ->keyBy(fn (QuotaAccountingBatch $batch) => $this->planIdFromBatchKey((string) $batch->batch_key));

        $posted = [];
        $updated = [];
        $adjusted = 0;
        $planIds = array_values(array_unique(array_merge(array_keys($built), $existing->keys()->map(fn ($id) => (int) $id)->all())));
        sort($planIds);

        foreach ($planIds as $planId) {
            if ($planId < 1) {
                continue;
            }
            $desired = $built[$planId] ?? null;
            $batch = $existing->get($planId);
            if ($batch === null && $desired !== null) {
                $batch = QuotaAccountingBatch::query()
                    ->where('batch_key', 'plan:'.$planId)
                    ->with(['orders', 'entry'])
                    ->first();
            }
            $orders = $desired['orders'] ?? [];
            $date = $desired['date'] ?? $batch?->entry_date?->toDateString();
            if ($date === null) {
                continue;
            }
            if ($batch !== null && $batch->entry?->manually_adjusted && $this->planOrdersDiffer($batch->orders, $orders)) {
                $adjusted++;

                continue;
            }
            if ($batch !== null && ! $this->planOrdersDiffer($batch->orders, $orders) && $batch->entry?->status === AccountingEntry::STATUS_POSTED) {
                continue;
            }
            if ($orders === [] && ($batch === null || $batch->entry?->status !== AccountingEntry::STATUS_POSTED)) {
                continue;
            }

            $totals = $this->planOpeningTotals($orders);
            $description = 'ALTA PLAN DE PAGO '.$planId;
            $item = [
                'date' => $date,
                'plan_id' => $planId,
                'plan' => $this->amount($totals['plan']),
                'debtors' => $this->amount($totals['debtors']),
                'late' => $this->amount($totals['late']),
                'financing' => $this->amount($totals['financing']),
                'description' => $description,
                'entry_number' => $batch?->entry?->entry_number,
            ];

            if (! $dryRun) {
                $entry = $this->savePlanBatch(
                    batch: $batch,
                    kind: QuotaAccountingBatch::KIND_PLAN,
                    batchKey: 'plan:'.$planId,
                    entryDate: $date,
                    paymentType: null,
                    entryKind: AccountingEntry::KIND_QUOTA_PLAN,
                    description: $description,
                    lines: $this->planOpeningLines($totals['debtors'], $totals['late'], $totals['financing'], $accounts),
                    orders: $orders,
                    role: QuotaAccountingBatch::KIND_PLAN,
                    accounts: $accounts,
                );
                $item['entry_number'] = $entry?->entry_number;
            }

            if ($batch === null || $batch->entry?->status !== AccountingEntry::STATUS_POSTED) {
                $posted[] = $item;
            } else {
                $updated[] = $item;
            }
        }

        return [
            'groups' => $posted,
            'updated' => $updated,
            'skipped' => ['adjusted' => $adjusted],
        ];
    }

    /**
     * Cobro de una cuota del plan (cuota 100 en adelante).
     * El medio recibe lo pagado. El plan baja por la cuota. La mora y el recargo del medio son ingreso.
     * El interés de financiación pasa de «a devengar» a intereses ganados.
     *
     * @param  array<string, AccountingAccount>  $accounts
     * @return array{groups: list<array<string, mixed>>, updated: list<array<string, mixed>>, skipped: array{split: int, unmapped: array<string, int>, zero: int, adjusted: int}}
     */
    private function postPlanCollections(Carbon $month, array $accounts, bool $dryRun): array
    {
        $splitIds = $this->idSet(
            DB::connection('eporres')->table('order_split_payments')->pluck('order_id')
        );
        $skipped = [
            'split' => 0,
            'unmapped' => [],
            'zero' => 0,
            'adjusted' => 0,
        ];
        $groups = [];

        foreach ($this->planCollectionRows($month) as $row) {
            $orderId = (int) $row->id;
            if (isset($splitIds[$orderId])) {
                $skipped['split']++;

                continue;
            }
            $paymentType = trim((string) $row->payment_type);
            $bankCode = QuotaPaymentAccounts::bankCode($paymentType);
            if ($paymentType === '' || $bankCode === null) {
                $label = $paymentType === '' ? '(sin medio)' : $paymentType;
                $skipped['unmapped'][$label] = ($skipped['unmapped'][$label] ?? 0) + 1;

                continue;
            }
            $split = PaymentPlanSplit::collection(
                (float) $row->amount_paid,
                (float) $row->quota_amount,
                (float) $row->surcharge_amount,
                (float) $row->interest_amount,
            );
            if ($this->cents($split['paid']) < 1) {
                $skipped['zero']++;

                continue;
            }

            $date = Carbon::parse($row->paid_at)->toDateString();
            $key = $date.'|'.$paymentType;
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'date' => $date,
                    'payment_type' => $paymentType,
                    'bank_code' => $bankCode,
                    'orders' => [],
                    'paid' => 0,
                    'plan' => 0,
                    'late' => 0,
                    'financing' => 0,
                    'surcharge' => 0,
                ];
            }
            $paid = $this->cents($split['paid']);
            $plan = $this->cents($split['plan']);
            $late = $this->cents($split['late']);
            $financing = $this->cents($split['financing']);
            $surcharge = $this->cents($split['surcharge']);
            $groups[$key]['paid'] += $paid;
            $groups[$key]['plan'] += $plan;
            $groups[$key]['late'] += $late;
            $groups[$key]['financing'] += $financing;
            $groups[$key]['surcharge'] += $surcharge;
            $groups[$key]['orders'][] = [
                'eporres_order_id' => $orderId,
                'debtors_amount' => $this->money($plan),
                'interest_amount' => $this->money($late),
                'bank_amount' => $this->money($paid),
                'financing_amount' => $this->money($financing),
            ];
        }

        ksort($groups);

        $batchesByGroup = [];
        $batches = QuotaAccountingBatch::query()
            ->where('kind', QuotaAccountingBatch::KIND_PLAN_COLLECTION)
            ->whereDate('entry_date', '>=', $month->copy()->startOfMonth()->toDateString())
            ->whereDate('entry_date', '<=', $month->copy()->endOfMonth()->toDateString())
            ->with(['orders', 'entry'])
            ->orderBy('id')
            ->get();
        foreach ($batches as $batch) {
            $batchesByGroup[$batch->entry_date->toDateString().'|'.$batch->payment_type][] = $batch;
        }

        $posted = [];
        $updated = [];
        $keys = array_values(array_unique(array_merge(array_keys($groups), array_keys($batchesByGroup))));
        sort($keys);

        foreach ($keys as $key) {
            $group = $groups[$key] ?? null;
            $groupBatches = $batchesByGroup[$key] ?? [];
            if ($group === null) {
                $sample = $groupBatches[0];
                $bankCode = QuotaPaymentAccounts::bankCode($sample->payment_type) ?? '';
                if ($bankCode === '') {
                    continue;
                }
                $group = [
                    'date' => $sample->entry_date->toDateString(),
                    'payment_type' => (string) $sample->payment_type,
                    'bank_code' => $bankCode,
                    'orders' => [],
                    'paid' => 0,
                    'plan' => 0,
                    'late' => 0,
                    'financing' => 0,
                    'surcharge' => 0,
                ];
            }

            $storedOrders = [];
            foreach ($groupBatches as $batch) {
                foreach ($batch->orders as $order) {
                    $storedOrders[] = $order;
                }
            }
            if ($this->groupIsAdjusted($groupBatches) && $this->planOrdersDiffer($storedOrders, $group['orders'])) {
                $skipped['adjusted']++;

                continue;
            }
            if ($groupBatches !== [] && ! $this->planOrdersDiffer($storedOrders, $group['orders'])) {
                continue;
            }
            if ($group['orders'] === [] && $groupBatches === []) {
                continue;
            }

            $description = 'COBRANZA PLAN DE PAGO '.mb_strtoupper($group['payment_type']);
            $primary = $groupBatches[0] ?? null;
            $item = [
                'date' => $group['date'],
                'payment_type' => $group['payment_type'],
                'orders' => count($group['orders']),
                'paid' => $this->amount($group['paid']),
                'plan' => $this->amount($group['plan']),
                'late' => $this->amount($group['late']),
                'financing' => $this->amount($group['financing']),
                'surcharge' => $this->amount($group['surcharge']),
                'entry_number' => $primary?->entry?->entry_number,
            ];

            if (! $dryRun) {
                $entry = $this->savePlanBatch(
                    batch: $primary,
                    kind: QuotaAccountingBatch::KIND_PLAN_COLLECTION,
                    batchKey: 'plan-collection:'.$group['date'].':'.$group['payment_type'],
                    entryDate: $group['date'],
                    paymentType: $group['payment_type'],
                    entryKind: AccountingEntry::KIND_QUOTA_PLAN_COLLECTION,
                    description: $description,
                    lines: $this->planCollectionLines($group, $accounts),
                    orders: $group['orders'],
                    role: QuotaAccountingBatch::KIND_PLAN_COLLECTION,
                    accounts: $accounts,
                    extraBatches: array_slice($groupBatches, 1),
                );
                $item['entry_number'] = $entry?->entry_number;
            }

            if ($primary === null || $primary->entry?->status !== AccountingEntry::STATUS_POSTED) {
                $posted[] = $item;
            } else {
                $updated[] = $item;
            }
        }

        return [
            'groups' => $posted,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  iterable<int, object>  $pend
     * @param  iterable<int, object>  $amortizations
     * @return list<array{eporres_order_id: int, debtors_amount: string, interest_amount: string, bank_amount: string, financing_amount: string}>
     */
    private function paymentPlanOpeningOrders(iterable $pend, iterable $amortizations): array
    {
        $byOrder = [];
        foreach ($pend as $row) {
            $orderId = (int) $row->order_id;
            if ($orderId < 1) {
                continue;
            }
            $byOrder[$orderId] ??= ['debtors' => 0.0, 'late' => 0.0, 'financing' => 0.0];
            if (strcasecmp((string) $row->type, 'Interes') === 0) {
                $byOrder[$orderId]['late'] += (float) $row->amount;
            } else {
                $byOrder[$orderId]['debtors'] += (float) $row->amount;
            }
        }
        foreach ($amortizations as $row) {
            $orderId = (int) $row->order_id;
            if ($orderId < 1) {
                continue;
            }
            $byOrder[$orderId] ??= ['debtors' => 0.0, 'late' => 0.0, 'financing' => 0.0];
            $byOrder[$orderId]['financing'] += (float) $row->interest_amount;
        }

        ksort($byOrder);
        $orders = [];
        foreach ($byOrder as $orderId => $parts) {
            $debtors = $this->cents($parts['debtors']);
            $late = $this->cents($parts['late']);
            $financing = $this->cents($parts['financing']);
            if ($debtors === 0 && $late === 0 && $financing === 0) {
                continue;
            }
            $orders[] = [
                'eporres_order_id' => $orderId,
                'debtors_amount' => $this->money($debtors),
                'interest_amount' => $this->money($late),
                'bank_amount' => $this->money(0),
                'financing_amount' => $this->money($financing),
            ];
        }

        return $orders;
    }

    /**
     * @param  list<array{debtors_amount: string, interest_amount: string, financing_amount?: string}>  $orders
     * @return array{debtors: int, late: int, financing: int, plan: int}
     */
    private function planOpeningTotals(array $orders): array
    {
        $debtors = 0;
        $late = 0;
        $financing = 0;
        foreach ($orders as $order) {
            $debtors += $this->cents($order['debtors_amount']);
            $late += $this->cents($order['interest_amount']);
            $financing += $this->cents($order['financing_amount'] ?? 0);
        }

        return [
            'debtors' => $debtors,
            'late' => $late,
            'financing' => $financing,
            'plan' => $debtors + $late + $financing,
        ];
    }

    /**
     * @param  iterable<int, mixed>  $stored
     * @param  list<array<string, mixed>>  $desired
     */
    private function planOrdersDiffer(iterable $stored, array $desired): bool
    {
        return $this->planOrderMap($stored) !== $this->planOrderMap($desired);
    }

    /**
     * @param  iterable<int, mixed>  $orders
     * @return array<int, array{0: int, 1: int, 2: int, 3: int}>
     */
    private function planOrderMap(iterable $orders): array
    {
        $map = [];
        foreach ($orders as $order) {
            $isArray = is_array($order);
            $id = (int) ($isArray ? $order['eporres_order_id'] : $order->eporres_order_id);
            $map[$id] = [
                $this->cents($isArray ? $order['debtors_amount'] : $order->debtors_amount),
                $this->cents($isArray ? $order['interest_amount'] : $order->interest_amount),
                $this->cents($isArray ? $order['bank_amount'] : $order->bank_amount),
                $this->cents($isArray ? ($order['financing_amount'] ?? 0) : ($order->financing_amount ?? 0)),
            ];
        }
        ksort($map);

        return $map;
    }

    private function planIdFromBatchKey(string $batchKey): int
    {
        if (preg_match('/^plan:(\d+)$/', $batchKey, $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * @return list<object>
     */
    private function planCollectionRows(Carbon $month): array
    {
        return DB::connection('eporres')->table('orders as o')
            ->leftJoin('amortizations as a', 'a.order_id', '=', 'o.id')
            ->where('o.quota_number', '>=', ReportePagosDiaCriteria::PLAN_QUOTA_MIN)
            ->where('o.state', self::PAID_STATE)
            ->where(function ($query) {
                $query->whereNull('o.estado')->orWhere('o.estado', 1);
            })
            ->whereBetween('o.paid_at', [
                $month->copy()->startOfMonth()->toDateTimeString(),
                $month->copy()->endOfMonth()->toDateTimeString(),
            ])
            ->groupBy('o.id')
            ->orderBy('o.id')
            ->get([
                'o.id',
                DB::raw('MAX(o.quota_amount) as quota_amount'),
                DB::raw('MAX(o.amount_paid) as amount_paid'),
                DB::raw('MAX(o.surcharge_amount) as surcharge_amount'),
                DB::raw('MAX(o.payment_type) as payment_type'),
                DB::raw('MAX(o.paid_at) as paid_at'),
                DB::raw('SUM(a.interest_amount) as interest_amount'),
            ])
            ->all();
    }

    /**
     * @param  array<string, AccountingAccount>  $accounts
     * @return list<array{accounting_account_id: int, debit: string, credit: string, memo: string}>
     */
    private function planOpeningLines(int $debtors, int $late, int $financing, array $accounts): array
    {
        $plan = $debtors + $late + $financing;
        if ($plan < 1) {
            return [];
        }

        $lines = [
            $this->line($accounts[QuotaPaymentAccounts::PLAN], $plan, 0, 'Plan de pago'),
        ];
        if ($debtors > 0) {
            $lines[] = $this->line($accounts[QuotaPaymentAccounts::DEBTORS], 0, $debtors, 'Cuotas incluidas en el plan');
        }
        if ($late > 0) {
            $lines[] = $this->line($accounts[QuotaPaymentAccounts::LATE_INTEREST], 0, $late, 'Intereses por mora');
        }
        if ($financing > 0) {
            $lines[] = $this->line($accounts[QuotaPaymentAccounts::PLAN_FINANCING_DEFERRED], 0, $financing, 'Interés financiación a devengar');
        }
        $this->assertBalanced($lines);

        return $lines;
    }

    /**
     * @param  array{payment_type: string, bank_code: string, paid: int, plan: int, late: int, financing: int, surcharge: int}  $group
     * @param  array<string, AccountingAccount>  $accounts
     * @return list<array{accounting_account_id: int, debit: string, credit: string, memo: string}>
     */
    private function planCollectionLines(array $group, array $accounts): array
    {
        if ($group['paid'] < 1) {
            return [];
        }

        $paymentType = $group['payment_type'];
        $surchargeMemo = match (true) {
            $paymentType === 'Mercado Pago' => 'Recargo Mercado Pago',
            str_starts_with($paymentType, 'Tarjeta') => 'Int. por cobro tarjeta',
            default => 'Otros recargos del medio',
        };
        $lines = [
            $this->line($accounts[$group['bank_code']], $group['paid'], 0, 'Cobranza plan de pago'),
        ];
        if ($group['financing'] > 0) {
            $lines[] = $this->line($accounts[QuotaPaymentAccounts::PLAN_FINANCING_DEFERRED], $group['financing'], 0, 'Interés financiación a devengar');
        }
        if ($group['plan'] > 0) {
            $lines[] = $this->line($accounts[QuotaPaymentAccounts::PLAN], 0, $group['plan'], 'Plan de pago');
        }
        if ($group['financing'] > 0) {
            $lines[] = $this->line($accounts[QuotaPaymentAccounts::PLAN_FINANCING_INCOME], 0, $group['financing'], 'Intereses ganados por financiación');
        }
        if ($group['late'] > 0) {
            $lines[] = $this->line($accounts[QuotaPaymentAccounts::LATE_INTEREST], 0, $group['late'], 'Intereses por mora');
        }
        if ($group['surcharge'] > 0) {
            $lines[] = $this->line(
                $accounts[QuotaPaymentAccounts::planSurchargeCode($paymentType)],
                0,
                $group['surcharge'],
                $surchargeMemo,
            );
        }
        $this->assertBalanced($lines);

        return $lines;
    }

    /**
     * @param  list<QuotaAccountingBatch>  $extraBatches
     * @param  list<array{accounting_account_id: int, debit: string, credit: string, memo: string}>  $lines
     * @param  list<array{eporres_order_id: int, debtors_amount: string, interest_amount: string, bank_amount: string, financing_amount: string}>  $orders
     * @param  array<string, AccountingAccount>  $accounts
     */
    private function savePlanBatch(
        ?QuotaAccountingBatch $batch,
        string $kind,
        string $batchKey,
        string $entryDate,
        ?string $paymentType,
        string $entryKind,
        string $description,
        array $lines,
        array $orders,
        string $role,
        array $accounts,
        array $extraBatches = [],
    ): ?AccountingEntry {
        return DB::transaction(function () use ($batch, $kind, $batchKey, $entryDate, $paymentType, $entryKind, $description, $lines, $orders, $role, $accounts, $extraBatches) {
            foreach ($extraBatches as $extra) {
                $extra->orders()->delete();
                $this->clearEntry($extra->entry);
                $extra->delete();
            }

            if ($batch !== null && ($batch->entry === null || $batch->entry->status !== AccountingEntry::STATUS_POSTED)) {
                $batch->orders()->delete();
                if ($batch->entry !== null) {
                    $batch->entry->lines()->delete();
                    $batch->entry->delete();
                }
                $batch->delete();
                $batch = null;
            }

            $this->releasePlanRoleOrders(array_column($orders, 'eporres_order_id'), $batch?->id, $role, $accounts);

            if ($lines === []) {
                $orders = [];
            }

            if ($batch === null) {
                if ($orders === []) {
                    return null;
                }

                return $this->writeBatch(
                    kind: $kind,
                    batchKey: $batchKey,
                    period: null,
                    entryDate: $entryDate,
                    paymentType: $paymentType,
                    entryKind: $entryKind,
                    description: $description,
                    lines: $lines,
                    orders: $orders,
                    role: $role,
                );
            }

            $batch->orders()->delete();
            if ($orders === []) {
                $this->clearEntry($batch->entry);

                return $batch->entry;
            }

            $now = now();
            foreach (array_chunk($orders, 500) as $chunk) {
                $rows = [];
                foreach ($chunk as $order) {
                    $rows[] = [
                        'quota_accounting_batch_id' => $batch->id,
                        'role' => $role,
                        'eporres_order_id' => $order['eporres_order_id'],
                        'debtors_amount' => $order['debtors_amount'],
                        'interest_amount' => $order['interest_amount'],
                        'bank_amount' => $order['bank_amount'],
                        'financing_amount' => $order['financing_amount'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                QuotaAccountingOrder::query()->insert($rows);
            }

            $entry = $batch->entry;
            $entry->lines()->delete();
            foreach ($lines as $line) {
                $entry->lines()->create($line);
            }
            $entry->update([
                'date' => $entryDate,
                'status' => AccountingEntry::STATUS_POSTED,
                'description' => $description,
            ]);
            $batch->update([
                'entry_date' => $entryDate,
                'payment_type' => $paymentType,
            ]);

            return $entry->fresh();
        });
    }

    /**
     * @param  list<int>  $orderIds
     * @param  array<string, AccountingAccount>  $accounts
     */
    private function releasePlanRoleOrders(array $orderIds, ?int $keepBatchId, string $role, array $accounts): void
    {
        if ($orderIds === []) {
            return;
        }

        $misplaced = QuotaAccountingOrder::query()
            ->where('role', $role)
            ->whereIn('eporres_order_id', $orderIds)
            ->when($keepBatchId, fn ($query) => $query->where('quota_accounting_batch_id', '!=', $keepBatchId))
            ->get();
        if ($misplaced->isEmpty()) {
            return;
        }

        $batchIds = $misplaced->pluck('quota_accounting_batch_id')->unique()->all();
        QuotaAccountingOrder::query()->whereIn('id', $misplaced->pluck('id'))->delete();

        $batches = QuotaAccountingBatch::query()->with(['orders', 'entry'])->whereIn('id', $batchIds)->get();
        foreach ($batches as $batch) {
            $this->rewritePlanBatchFromOrders($batch, $accounts);
        }
    }

    /**
     * @param  array<string, AccountingAccount>  $accounts
     */
    private function rewritePlanBatchFromOrders(QuotaAccountingBatch $batch, array $accounts): void
    {
        $batch->load('orders', 'entry');
        if ($batch->entry === null || $batch->entry->status !== AccountingEntry::STATUS_POSTED) {
            return;
        }
        if ($batch->orders->isEmpty()) {
            $this->clearEntry($batch->entry);

            return;
        }

        $debtors = 0;
        $late = 0;
        $paid = 0;
        $financing = 0;
        foreach ($batch->orders as $order) {
            $debtors += $this->cents($order->debtors_amount);
            $late += $this->cents($order->interest_amount);
            $paid += $this->cents($order->bank_amount);
            $financing += $this->cents($order->financing_amount);
        }

        if ($batch->kind === QuotaAccountingBatch::KIND_PLAN) {
            $lines = $this->planOpeningLines($debtors, $late, $financing, $accounts);
        } else {
            $lines = $this->planCollectionLines([
                'payment_type' => (string) $batch->payment_type,
                'bank_code' => QuotaPaymentAccounts::bankCode($batch->payment_type) ?? '',
                'paid' => $paid,
                'plan' => $debtors,
                'late' => $late,
                'financing' => $financing,
                'surcharge' => $paid - $debtors - $late,
            ], $accounts);
        }

        if ($lines === []) {
            $this->clearEntry($batch->entry);

            return;
        }

        $batch->entry->lines()->delete();
        foreach ($lines as $line) {
            $batch->entry->lines()->create($line);
        }
    }

    /**
     * @return array<string, AccountingAccount>
     */
    private function accountsByCode(): array
    {
        $codes = array_values(array_unique(array_merge(
            [
                QuotaPaymentAccounts::DEBTORS,
                QuotaPaymentAccounts::QUOTAS_INCOME,
                QuotaPaymentAccounts::LATE_INTEREST,
                QuotaPaymentAccounts::SCHOLARSHIP,
                QuotaPaymentAccounts::MP_AVAILABLE,
                QuotaPaymentAccounts::MP_COMMISSION,
                QuotaPaymentAccounts::MP_SURCHARGE_INCOME,
                QuotaPaymentAccounts::PLAN,
                QuotaPaymentAccounts::PLAN_FINANCING_DEFERRED,
                QuotaPaymentAccounts::PLAN_FINANCING_INCOME,
                QuotaPaymentAccounts::CARD_INTEREST,
                QuotaPaymentAccounts::OTHER_FINANCIAL_INCOME,
            ],
            array_values(QuotaPaymentAccounts::BY_PAYMENT_TYPE),
        )));

        $found = AccountingAccount::query()->whereIn('code', $codes)->get()->keyBy('code');
        foreach ($this->accountsNamedAs(array_values(array_diff($codes, $found->keys()->all()))) as $code => $account) {
            $found->put($code, $account);
        }
        $missing = array_values(array_diff($codes, $found->keys()->all()));
        if ($missing !== []) {
            $labels = array_map(function (string $code) {
                $name = QuotaPaymentAccounts::NAMES[$code] ?? null;

                return $name === null ? $code : $code.' ('.$name.')';
            }, $missing);
            throw new RuntimeException('Faltan cuentas en el plan: '.implode(', ', $labels));
        }
        $this->rejectGroupingAccounts($found->all());

        return $found->all();
    }

    /**
     * Cuentas del plan que están cargadas con otro código, pero con el nombre oficial.
     *
     * @param  list<string>  $codes
     * @return array<string, AccountingAccount>
     */
    private function accountsNamedAs(array $codes): array
    {
        $wanted = [];
        foreach ($codes as $code) {
            $name = QuotaPaymentAccounts::NAMES[$code] ?? null;
            if ($name !== null) {
                $wanted[QuotaPaymentAccounts::normalizeName($name)] = $code;
            }
        }
        if ($wanted === []) {
            return [];
        }

        $matched = [];
        $grouping = [];
        foreach (AccountingAccount::query()->orderBy('code')->get() as $account) {
            $code = $wanted[QuotaPaymentAccounts::normalizeName((string) $account->name)] ?? null;
            if ($code === null || isset($matched[$code])) {
                continue;
            }
            if ($account->is_grouping) {
                $grouping[$code] ??= $account;

                continue;
            }
            $matched[$code] = $account;
        }
        foreach ($grouping as $code => $account) {
            $matched[$code] ??= $account;
        }

        return $matched;
    }

    /**
     * @return list<object>
     */
    private function accrualRows(Carbon $month): array
    {
        return DB::connection('eporres')->table('orders')
            ->whereBetween('quota_number', [1, 10])
            ->whereIn('type', self::MONTHLY_TYPES)
            ->where('state', '!=', self::CANCELLED_STATE)
            ->where(function ($query) {
                $query->whereNull('estado')->orWhere('estado', 1);
            })
            ->whereBetween('expirated_at', [
                $month->copy()->startOfMonth()->toDateTimeString(),
                $month->copy()->endOfMonth()->toDateTimeString(),
            ])
            ->orderBy('id')
            ->get(['id', 'quota_amount'])
            ->all();
    }

    /**
     * @return list<object>
     */
    private function collectionRows(Carbon $month): array
    {
        return DB::connection('eporres')->table('orders')
            ->whereBetween('quota_number', [1, 10])
            ->whereIn('type', self::MONTHLY_TYPES)
            ->where('state', self::PAID_STATE)
            ->where(function ($query) {
                $query->whereNull('estado')->orWhere('estado', 1);
            })
            ->whereBetween('paid_at', [
                $month->copy()->startOfMonth()->toDateTimeString(),
                $month->copy()->endOfMonth()->toDateTimeString(),
            ])
            ->orderBy('id')
            ->get([
                'id',
                'quota_amount',
                'amount_paid',
                'surcharge_amount',
                'surcharge_amountMP',
                'surcharge_amountCard',
                'payment_type',
                'paid_at',
            ])
            ->all();
    }

    /**
     * @return array<int, true>
     */
    private function settledByPlanIds(): array
    {
        $ids = DB::connection('eporres')->table('orders_pend_plan')
            ->select('order_id')
            ->groupBy('order_id')
            ->havingRaw('SUM(CASE WHEN state <> ? THEN 1 ELSE 0 END) = 0', ['Pagado'])
            ->pluck('order_id');

        return $this->idSet($ids);
    }

    /**
     * @return array<int, true>
     */
    private function postedOrderIdSet(string $role): array
    {
        return $this->idSet(
            QuotaAccountingOrder::query()->where('role', $role)->pluck('eporres_order_id')
        );
    }

    /**
     * @param  iterable<int, mixed>  $ids
     * @return array<int, true>
     */
    private function idSet(iterable $ids): array
    {
        $set = [];
        foreach ($ids as $id) {
            $set[(int) $id] = true;
        }

        return $set;
    }

    /**
     * @return array{accounting_account_id: int, debit: string, credit: string, memo: string}
     */
    private function line(AccountingAccount $account, int $debitCents, int $creditCents, string $memo): array
    {
        return [
            'accounting_account_id' => $account->id,
            'debit' => $this->money($debitCents),
            'credit' => $this->money($creditCents),
            'memo' => $memo,
        ];
    }

    private function accrualDescription(Carbon $month): string
    {
        return 'DEVENGAMIENTOS CUOTAS A COBRAR MES '.$month->format('m/y');
    }

    private function cents(float|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function amount(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
