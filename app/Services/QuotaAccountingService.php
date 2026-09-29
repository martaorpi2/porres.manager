<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\AccountingEntry;
use App\Models\QuotaAccountingBatch;
use App\Models\QuotaAccountingOrder;
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
     * Devenga las cuotas que vencen en el mes y asienta los cobros con paid_at en ese mes.
     *
     * @return array<string, mixed>
     */
    public function postMonth(Carbon $month, bool $dryRun = false): array
    {
        $month = $month->copy()->startOfMonth();
        $accounts = $this->accountsByCode();

        return [
            'month' => $month->format('Y-m'),
            'dry_run' => $dryRun,
            'accrual' => $this->postAccrual($month, $accounts, $dryRun),
            'collections' => $this->postCollections($month, $accounts, $dryRun),
        ];
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
    private function postCollections(Carbon $month, array $accounts, bool $dryRun): array
    {
        $posted = $this->postedOrderIdSet(QuotaAccountingBatch::KIND_COLLECTION);
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
        ];
        $unpostedCents = 0;
        $groups = [];

        foreach ($this->collectionRows($month) as $row) {
            $orderId = (int) $row->id;
            if (isset($posted[$orderId])) {
                continue;
            }
            if (isset($splitIds[$orderId])) {
                $skipped['split']++;
                continue;
            }
            $paymentType = trim((string) $row->payment_type);
            if ($paymentType === '' ) {
                $skipped['no_payment_type']++;
                continue;
            }
            if ($paymentType === 'Plan de Pago' || isset($settledByPlan[$orderId])) {
                $skipped['plan']++;
                continue;
            }
            $bankCode = QuotaPaymentAccounts::bankCode($paymentType);
            if ($bankCode === null) {
                $skipped['unmapped'][$paymentType] = ($skipped['unmapped'][$paymentType] ?? 0) + 1;
                continue;
            }

            $split = QuotaCollectionSplit::fromRow($row);
            $bankCents = $this->cents($split['bank']);
            if ($bankCents < 1) {
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
                    'debtors' => 0,
                    'interest' => 0,
                    'bank' => 0,
                    'unposted' => 0,
                ];
            }
            $debtors = $this->cents($split['debtors']);
            $interest = $this->cents($split['interest']);
            $groups[$key]['debtors'] += $debtors;
            $groups[$key]['interest'] += $interest;
            $groups[$key]['bank'] += $bankCents;
            $groups[$key]['unposted'] += $this->cents($split['unposted']);
            $groups[$key]['orders'][] = [
                'eporres_order_id' => $orderId,
                'debtors_amount' => $this->money($debtors),
                'interest_amount' => $this->money($interest),
                'bank_amount' => $this->money($bankCents),
            ];
            $unpostedCents += $this->cents($split['unposted']);
        }

        ksort($groups);
        $postedGroups = [];

        foreach ($groups as $group) {
            $description = 'ACREDITACION COBRANZA CUOTAS '.mb_strtoupper($group['payment_type']);
            $item = [
                'date' => $group['date'],
                'payment_type' => $group['payment_type'],
                'bank_code' => $group['bank_code'],
                'orders' => count($group['orders']),
                'debtors' => $this->amount($group['debtors']),
                'interest' => $this->amount($group['interest']),
                'bank' => $this->amount($group['bank']),
                'description' => $description,
                'entry_number' => null,
            ];

            if (! $dryRun) {
                $firstOrder = $group['orders'][0]['eporres_order_id'];
                $lines = [
                    $this->line($accounts[$group['bank_code']], $group['bank'], 0, 'Cobranza'),
                ];
                if ($group['debtors'] > 0) {
                    $lines[] = $this->line($accounts[QuotaPaymentAccounts::DEBTORS], 0, $group['debtors'], 'Cuotas cobradas');
                }
                if ($group['interest'] > 0) {
                    $lines[] = $this->line($accounts[QuotaPaymentAccounts::LATE_INTEREST], 0, $group['interest'], 'Intereses por mora');
                }
                $this->assertBalanced($lines);

                $entry = $this->writeBatch(
                    kind: QuotaAccountingBatch::KIND_COLLECTION,
                    batchKey: 'collection:'.$group['date'].':'.$group['payment_type'].':'.$firstOrder,
                    period: null,
                    entryDate: $group['date'],
                    paymentType: $group['payment_type'],
                    entryKind: AccountingEntry::KIND_QUOTA_COLLECTION,
                    description: $description,
                    lines: $lines,
                    orders: $group['orders'],
                    role: QuotaAccountingBatch::KIND_COLLECTION,
                );
                $item['entry_number'] = $entry->entry_number;
            }

            $postedGroups[] = $item;
        }

        return [
            'groups' => $postedGroups,
            'skipped' => $skipped,
            'unposted_surcharge' => $this->amount($unpostedCents),
        ];
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
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                QuotaAccountingOrder::query()->insert($rows);
            }

            return $entry;
        });
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
            ],
            array_values(QuotaPaymentAccounts::BY_PAYMENT_TYPE),
        )));

        $found = AccountingAccount::query()->whereIn('code', $codes)->get()->keyBy('code');
        $missing = array_values(array_diff($codes, $found->keys()->all()));
        if ($missing !== []) {
            throw new RuntimeException('Faltan cuentas en el plan: '.implode(', ', $missing));
        }

        return $found->all();
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
