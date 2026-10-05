<?php

namespace App\Support\Eporres;

use App\Models\Eporres\Order;

/**
 * Desglose de importes de un cupón de ePorres, igual que el reporte de pagos por día.
 */
final class OrderPaymentBreakdown
{
    public static function amounts(Order $order): array
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

        return [
            'pure' => $pure,
            'net_pure' => $netPure,
            'interest' => $interest,
            'interest_late_fee' => round($interestLateFee, 2),
            'interest_other' => round($interestOther, 2),
            'interest_plan_financing' => $planFinancing,
        ];
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
