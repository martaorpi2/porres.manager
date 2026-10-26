<?php

namespace App\Services;

/**
 * En la acreditación de una cuota de plan se reconoce el interés de financiación
 * que había quedado a devengar en el alta.
 */
final class PaymentPlanAccreditationEntry
{
    /**
     * @return list<array{code: string, debit: int, credit: int, memo: string}>
     */
    public static function financingLines(int $financingCents): array
    {
        if ($financingCents < 1) {
            return [];
        }

        return [
            [
                'code' => QuotaPaymentAccounts::PLAN_FINANCING_DEFERRED,
                'debit' => $financingCents,
                'credit' => 0,
                'memo' => 'Interés financiación a devengar',
            ],
            [
                'code' => QuotaPaymentAccounts::PLAN_FINANCING_INCOME,
                'debit' => 0,
                'credit' => $financingCents,
                'memo' => 'Intereses ganados por financiación',
            ],
        ];
    }
}
