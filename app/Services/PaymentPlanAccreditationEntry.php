<?php

namespace App\Services;

/**
 * El interés de financiación se reconoce en la cobranza.
 * La acreditación no vuelve a tocarlo.
 */
final class PaymentPlanAccreditationEntry
{
    /**
     * @return list<array{code: string, debit: int, credit: int, memo: string}>
     */
    public static function financingLines(int $financingCents): array
    {
        return [];
    }
}
