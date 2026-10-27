<?php

namespace App\Services;

use App\Models\AccountingEntry;
use App\Models\QuotaAccountingBatch;
use App\Support\Eporres\ReportePagosDiaCriteria;
use Illuminate\Support\Facades\DB;

/**
 * Une los comprobantes de ePorres que salieron en el mismo cupón del POS.
 * El número queda en payment_id. Cuotas originales y cuotas de plan no se mezclan.
 */
final class PosCouponOrders
{
    /**
     * @param  list<int>  $orderIds
     * @return list<int>
     */
    public static function expand(array $orderIds, bool $planInstallments): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds), fn (int $id) => $id > 0)));
        if ($orderIds === []) {
            return [];
        }

        $seeds = DB::connection('eporres')->table('orders')
            ->whereIn('id', $orderIds)
            ->get(['id', 'payment_id', 'payment_type', 'quota_number']);

        $wanted = [];
        foreach ($seeds as $seed) {
            if (! self::isPos((string) $seed->payment_type)) {
                continue;
            }
            if (self::isPlanInstallment((int) $seed->quota_number) !== $planInstallments) {
                continue;
            }
            $coupon = self::normalize((string) $seed->payment_id);
            if ($coupon === '') {
                continue;
            }
            $wanted[$coupon] = $coupon;
        }
        if ($wanted === []) {
            return $orderIds;
        }

        $found = DB::connection('eporres')->table('orders')
            ->whereIn('payment_type', QuotaPaymentAccounts::POS_PAYMENT_TYPES)
            ->whereNotNull('payment_id')
            ->where('payment_id', '!=', '')
            ->where(function ($query) use ($wanted) {
                foreach ($wanted as $coupon) {
                    $query->orWhereRaw("CASE WHEN TRIM(LEADING '0' FROM TRIM(payment_id)) = '' THEN '0' ELSE TRIM(LEADING '0' FROM TRIM(payment_id)) END = ?", [$coupon]);
                }
            })
            ->get(['id', 'quota_number']);

        $ids = $orderIds;
        foreach ($found as $order) {
            if (self::isPlanInstallment((int) $order->quota_number) !== $planInstallments) {
                continue;
            }
            $ids[] = (int) $order->id;
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    /**
     * @param  list<string>  $coupons
     * @param  list<string>  $paymentTypes
     * @return array<string, list<int>>
     */
    public static function matchingIds(array $coupons, array $paymentTypes): array
    {
        $wanted = [];
        foreach ($coupons as $coupon) {
            $normalized = self::normalize((string) $coupon);
            if ($normalized !== '') {
                $wanted[$normalized] = $normalized;
            }
        }
        $found = [];
        foreach ($wanted as $coupon) {
            $found[$coupon] = [];
        }
        if ($wanted === [] || $paymentTypes === []) {
            return $found;
        }

        $orders = DB::connection('eporres')->table('orders')
            ->whereIn('payment_type', $paymentTypes)
            ->whereNotNull('payment_id')
            ->where('payment_id', '!=', '')
            ->where(function ($query) use ($wanted) {
                foreach ($wanted as $coupon) {
                    $query->orWhereRaw("CASE WHEN TRIM(LEADING '0' FROM TRIM(payment_id)) = '' THEN '0' ELSE TRIM(LEADING '0' FROM TRIM(payment_id)) END = ?", [$coupon]);
                }
            })
            ->get(['id', 'payment_id']);

        foreach ($orders as $order) {
            $coupon = self::normalize((string) $order->payment_id);
            if (! isset($found[$coupon])) {
                continue;
            }
            $found[$coupon][] = (int) $order->id;
        }
        foreach ($found as $coupon => $ids) {
            $found[$coupon] = array_values(array_unique($ids));
        }

        return $found;
    }

    /**
     * @param  list<int>  $orderIds
     * @return list<int>
     */
    public static function collectionEntryIds(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds), fn (int $id) => $id > 0)));
        if ($orderIds === []) {
            return [];
        }

        return QuotaAccountingBatch::query()
            ->whereIn('kind', [QuotaAccountingBatch::KIND_COLLECTION, QuotaAccountingBatch::KIND_PLAN_COLLECTION])
            ->whereNotNull('accounting_entry_id')
            ->whereHas('orders', fn ($query) => $query->whereIn('eporres_order_id', $orderIds))
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

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $paymentTypes
     * @return list<array<string, mixed>>
     */
    public static function annotate(array $rows, array $paymentTypes): array
    {
        if ($paymentTypes === []) {
            return $rows;
        }

        $matches = self::matchingIds(array_map(
            fn (array $row) => (string) ($row['coupon'] ?? ''),
            $rows,
        ), $paymentTypes);
        $orderIds = [];
        foreach ($matches as $ids) {
            foreach ($ids as $id) {
                $orderIds[] = $id;
            }
        }
        $numbersByOrder = self::entryNumbersByOrder($orderIds);

        foreach ($rows as $index => $row) {
            if (! in_array($row['outcome'] ?? '', ['ready', 'update', 'already_posted'], true)) {
                continue;
            }
            $coupon = trim((string) ($row['coupon'] ?? ''));
            $normalized = self::normalize($coupon);
            if ($normalized === '') {
                continue;
            }
            $ids = $matches[$normalized] ?? [];
            $numbers = [];
            foreach ($ids as $id) {
                foreach ($numbersByOrder[$id] ?? [] as $number) {
                    $numbers[$number] = $number;
                }
            }
            $numbers = array_values($numbers);
            if ($ids === []) {
                $note = ' El cupón '.$coupon.' no está en las cobranzas.';
            } elseif ($numbers === []) {
                $note = ' El cupón '.$coupon.' está cargado y no tiene asiento de cobranza.';
            } elseif (count($numbers) === 1) {
                $note = ' El cupón '.$coupon.' corresponde al asiento '.$numbers[0].'.';
            } else {
                $note = ' El cupón '.$coupon.' corresponde a los asientos '.implode(' y ', $numbers).'.';
            }
            $rows[$index]['detail'] = trim((string) ($row['detail'] ?? '').$note);
        }

        return $rows;
    }

    /**
     * @param  list<int>  $orderIds
     * @return array<int, list<string>>
     */
    private static function entryNumbersByOrder(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        $rows = DB::table('quota_accounting_orders')
            ->join('quota_accounting_batches', 'quota_accounting_batches.id', '=', 'quota_accounting_orders.quota_accounting_batch_id')
            ->join('accounting_entries', 'accounting_entries.id', '=', 'quota_accounting_batches.accounting_entry_id')
            ->whereIn('quota_accounting_orders.eporres_order_id', $orderIds)
            ->whereIn('quota_accounting_batches.kind', [
                QuotaAccountingBatch::KIND_COLLECTION,
                QuotaAccountingBatch::KIND_PLAN_COLLECTION,
            ])
            ->where('accounting_entries.status', AccountingEntry::STATUS_POSTED)
            ->get([
                'quota_accounting_orders.eporres_order_id',
                'accounting_entries.entry_number',
            ]);

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->eporres_order_id][] = (string) $row->entry_number;
        }

        return $map;
    }

    public static function normalize(string $paymentId): string
    {
        $paymentId = trim($paymentId);
        if ($paymentId === '') {
            return '';
        }
        $stripped = ltrim($paymentId, '0');

        return $stripped === '' ? '0' : $stripped;
    }

    public static function isPos(string $paymentType): bool
    {
        return in_array(trim($paymentType), QuotaPaymentAccounts::POS_PAYMENT_TYPES, true);
    }

    public static function isPlanInstallment(int $quotaNumber): bool
    {
        return $quotaNumber >= ReportePagosDiaCriteria::PLAN_QUOTA_MIN;
    }
}
