<?php

namespace App\Services;

/**
 * Importes del alta de un plan de pago y del cobro de una cuota del plan.
 * Los montos entran en pesos y salen redondeados a centavos.
 */
final class PaymentPlanSplit
{
    /**
     * @return array{plan: float, debtors: float, late: float, financing: float}
     */
    public static function opening(float $debtors, float $late, float $financing): array
    {
        $debtors = round(max(0, $debtors), 2);
        $late = round(max(0, $late), 2);
        $financing = round(max(0, $financing), 2);

        return [
            'plan' => round($debtors + $late + $financing, 2),
            'debtors' => $debtors,
            'late' => $late,
            'financing' => $financing,
        ];
    }

    /**
     * La cuota del plan baja por lo cobrado, sin pasar del valor de la cuota.
     * La mora y el recargo del medio son lo que se pagó de más.
     * El interés de financiación se reconoce entero cuando entra algo a cuenta del plan.
     *
     * @return array{paid: float, plan: float, late: float, financing: float, surcharge: float}
     */
    public static function collection(float $paid, float $quota, float $late, float $financing): array
    {
        $paid = round(max(0, $paid), 2);
        $quota = round(max(0, $quota), 2);
        $late = round(min(max(0, $late), $paid), 2);
        $financing = round(max(0, $financing), 2);
        $plan = round(min($quota, max(0, $paid - $late)), 2);
        $surcharge = round(max(0, $paid - $plan - $late), 2);
        if ($plan < 0.005) {
            $financing = 0.0;
        }

        return [
            'paid' => $paid,
            'plan' => $plan,
            'late' => $late,
            'financing' => $financing,
            'surcharge' => $surcharge,
        ];
    }
}
