<?php

namespace App\Support\Eporres;

use App\Models\Eporres\Order;

/**
 * Desglose de importes de un cupón de ePorres.
 *
 * En el reporte de pagos por día, si la reducción arancelaria se perdió porque
 * el pago es posterior al vencimiento, ese descuento no se resta: el importe
 * cobrado vuelve a la cuota pura y no se informa como otros intereses.
 */
final class OrderPaymentBreakdown
{
    /**
     * @return array{
     *   pure: float,
     *   net_pure: float,
     *   interest: float,
     *   interest_late_fee: float,
     *   interest_other: float,
     *   interest_plan_financing: float,
     *   beca_perdida: bool
     * }
     */
    public static function amounts(Order $order): array
    {
        return self::compute($order, false);
    }

    /**
     * Desglose del reporte de pagos por día.
     *
     * @return array{
     *   pure: float,
     *   net_pure: float,
     *   interest: float,
     *   interest_late_fee: float,
     *   interest_other: float,
     *   interest_plan_financing: float,
     *   beca_perdida: bool
     * }
     */
    public static function amountsForReporte(Order $order): array
    {
        return self::compute($order, true);
    }

    /**
     * @return array{
     *   pure: float,
     *   net_pure: float,
     *   interest: float,
     *   interest_late_fee: float,
     *   interest_other: float,
     *   interest_plan_financing: float,
     *   beca_perdida: bool
     * }
     */
    private static function compute(Order $order, bool $reportePagosDia): array
    {
        $quota = (float) $order->quota_amount;
        $planFinancing = self::planFinancingAmount($order);
        $pure = round(max(0, $quota - $planFinancing), 2);
        $lateFee = (float) $order->surcharge_amount;
        $splitCard = $order->relationLoaded('splitPayment')
            ? (float) ($order->splitPayment?->surcharge_amount_card ?? 0)
            : (float) ($order->splitPayment()->value('surcharge_amount_card') ?? 0);
        $otherExplicit = (float) $order->surcharge_amountMP
            + (float) $order->surcharge_amountCard
            + $splitCard;
        $explicit = $lateFee + $otherExplicit;
        $discount = (float) $order->discount_amount;
        $lostBeca = $reportePagosDia ? self::lostTariffDiscount($order) : 0.0;
        if ($lostBeca > 0.005) {
            $discount = round(max(0, $discount - $lostBeca), 2);
        }
        $grant = (float) $order->grant_amount;
        $paid = round((float) $order->amount_paid, 2);

        if ($explicit > 0.005) {
            $interestLateFee = $lateFee;
            $interestOther = $otherExplicit;
        } else {
            $interestLateFee = 0.0;
            $interestOther = max(0, $paid - $quota);
        }

        $accounted = round($pure - $discount - $grant + $interestLateFee + $interestOther + $planFinancing, 2);

        if ($paid > $accounted + 0.02) {
            $interestOther = round($interestOther + ($paid - $accounted), 2);
        }

        $interest = round($interestLateFee + $interestOther + $planFinancing, 2);
        $netPure = round(max(0, $paid - $interestLateFee - $interestOther - $planFinancing), 2);
        $capitalEsperado = round(max(0, $pure - $grant), 2);

        return [
            'pure' => $pure,
            'net_pure' => $netPure,
            'interest' => $interest,
            'interest_late_fee' => round($interestLateFee, 2),
            'interest_other' => round($interestOther, 2),
            'interest_plan_financing' => $planFinancing,
            'beca_perdida' => $lostBeca > 0.005 && $netPure + 0.05 >= $capitalEsperado,
        ];
    }

    /**
     * Importe de reducción arancelaria que no corresponde porque el pago
     * es posterior al vencimiento. Cero si la beca sigue vigente.
     */
    private static function lostTariffDiscount(Order $order): float
    {
        $paidYmd = self::paymentDateYmd($order->paid_at ?? null);
        if ($paidYmd === null || ! self::becaLostForLatePayment($order, $paidYmd)) {
            return 0.0;
        }

        return round(max(0, (float) $order->discount_amount), 2);
    }

    private static function becaLostForLatePayment(Order $order, string $paymentDateYmd): bool
    {
        if (! self::isTariffReductionReason((string) ($order->discount_reason ?? ''))) {
            return false;
        }

        $hasDiscount = (float) ($order->discount_amount ?? 0) > 0.005
            || (float) ($order->percentage_discount ?? 0) > 0;
        if (! $hasDiscount) {
            return false;
        }

        $due = self::paymentDateYmd($order->expirated_at ?? null);

        return $due !== null && $paymentDateYmd > $due;
    }

    private static function isTariffReductionReason(string $reason): bool
    {
        $reason = trim($reason);
        if ($reason === '') {
            return false;
        }

        return strncasecmp($reason, 'Reducción arancelaria', strlen('Reducción arancelaria')) === 0
            || strncasecmp($reason, 'Reduccion arancelaria', strlen('Reduccion arancelaria')) === 0;
    }

    private static function paymentDateYmd(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $raw = trim((string) $value);
        if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
            return null;
        }

        if (preg_match('/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})/', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        return null;
    }

    private static function planFinancingAmount(Order $order): float
    {
        if ((int) $order->quota_number < ReportePagosDiaCriteria::PLAN_QUOTA_MIN) {
            return 0.0;
        }

        $amortization = null;
        if ($order->relationLoaded('amortization')) {
            $amortization = $order->getRelation('amortization');
        } elseif ($order->exists) {
            $amortization = $order->amortization()->first();
        }

        if ($amortization === null) {
            return 0.0;
        }

        return round(max(0, (float) $amortization->interest_amount), 2);
    }
}
