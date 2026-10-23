<?php

namespace App\Services;

/**
 * Cobranza de una cuota de plan de pago.
 * En término: el medio a cobrar contra el plan.
 * Fuera de término: el mismo asiento, más el interés por mora en el haber.
 * El interés de financiación se reconoce en la acreditación.
 * El recargo del medio de pago no entra en este asiento.
 */
final class PaymentPlanCollectionEntry
{
    /**
     * @return list<array{code: string, debit: int, credit: int, memo: string}>
     */
    public static function lines(string $paymentType, int $planCents, int $lateCents): array
    {
        $bank = QuotaPaymentAccounts::bankCode($paymentType);
        $planCents = max(0, $planCents);
        $lateCents = max(0, $lateCents);
        $receivable = $planCents + $lateCents;
        if ($bank === null || $receivable < 1) {
            return [];
        }

        $lines = [[
            'code' => $bank,
            'debit' => $receivable,
            'credit' => 0,
            'memo' => QuotaPaymentAccounts::receivableMemo($paymentType),
        ]];
        if ($planCents > 0) {
            $lines[] = [
                'code' => QuotaPaymentAccounts::PLAN,
                'debit' => 0,
                'credit' => $planCents,
                'memo' => 'Plan de pago deudores por cuota',
            ];
        }
        if ($lateCents > 0) {
            $lines[] = [
                'code' => QuotaPaymentAccounts::LATE_INTEREST,
                'debit' => 0,
                'credit' => $lateCents,
                'memo' => 'Intereses por mora',
            ];
        }

        return $lines;
    }
}
