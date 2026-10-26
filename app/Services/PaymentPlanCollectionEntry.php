<?php

namespace App\Services;

/**
 * Cobranza de una cuota de plan de pago.
 * Mercado Pago y QR: el medio a cobrar junta la cuota, la financiación y la comisión.
 * En término va contra el plan. Fuera de término se agrega la mora en el haber.
 * Los demás medios reconocen la financiación y la comisión en sus cuentas.
 */
final class PaymentPlanCollectionEntry
{
    /**
     * @return list<array{code: string, debit: int, credit: int, memo: string}>
     */
    public static function lines(string $paymentType, int $planCents, int $lateCents, int $financingCents = 0, int $surchargeCents = 0): array
    {
        $bank = QuotaPaymentAccounts::bankCode($paymentType);
        $planCents = max(0, $planCents);
        $lateCents = max(0, $lateCents);
        $financingCents = max(0, min($financingCents, $planCents));
        $surchargeCents = max(0, $surchargeCents);
        if ($bank === null) {
            return [];
        }
        if (self::foldsIntoThePlan($paymentType)) {
            return self::foldedLines($bank, $paymentType, $planCents, $lateCents, $surchargeCents);
        }

        $receivable = $planCents + $lateCents + $surchargeCents;
        if ($receivable < 1) {
            return [];
        }

        $lines = [[
            'code' => $bank,
            'debit' => $receivable,
            'credit' => 0,
            'memo' => QuotaPaymentAccounts::receivableMemo($paymentType),
        ]];
        if ($financingCents > 0) {
            $lines[] = [
                'code' => QuotaPaymentAccounts::PLAN_FINANCING_DEFERRED,
                'debit' => $financingCents,
                'credit' => 0,
                'memo' => 'Interés financiación a devengar',
            ];
        }
        if ($planCents > 0) {
            $lines[] = [
                'code' => QuotaPaymentAccounts::PLAN,
                'debit' => 0,
                'credit' => $planCents,
                'memo' => 'Plan de pago deudores por cuota',
            ];
        }
        if ($financingCents > 0) {
            $lines[] = [
                'code' => QuotaPaymentAccounts::PLAN_FINANCING_INCOME,
                'debit' => 0,
                'credit' => $financingCents,
                'memo' => 'Intereses ganados por financiación',
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
        if ($surchargeCents > 0) {
            $lines[] = [
                'code' => QuotaPaymentAccounts::planSurchargeCode($paymentType),
                'debit' => 0,
                'credit' => $surchargeCents,
                'memo' => self::surchargeMemo($paymentType),
            ];
        }

        return $lines;
    }

    /**
     * @return list<array{code: string, debit: int, credit: int, memo: string}>
     */
    private static function foldedLines(string $bank, string $paymentType, int $planCents, int $lateCents, int $surchargeCents): array
    {
        $planCredit = $planCents + $surchargeCents;
        $receivable = $planCredit + $lateCents;
        if ($receivable < 1) {
            return [];
        }

        $lines = [[
            'code' => $bank,
            'debit' => $receivable,
            'credit' => 0,
            'memo' => QuotaPaymentAccounts::receivableMemo($paymentType),
        ]];
        if ($planCredit > 0) {
            $lines[] = [
                'code' => QuotaPaymentAccounts::PLAN,
                'debit' => 0,
                'credit' => $planCredit,
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

    private static function foldsIntoThePlan(string $paymentType): bool
    {
        return in_array(trim($paymentType), ['Mercado Pago', 'QR'], true);
    }

    private static function surchargeMemo(string $paymentType): string
    {
        $type = trim($paymentType);
        if ($type === 'Mercado Pago') {
            return 'Recargo Mercado Pago';
        }
        if (str_starts_with($type, 'Tarjeta')) {
            return 'Interés por cobro con tarjeta';
        }

        return 'Otros ingresos financieros';
    }
}
