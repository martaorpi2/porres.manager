<?php

namespace App\Services;

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
