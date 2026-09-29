<?php

namespace App\Services;

/**
 * Reparte un cobro de cuota sin comisión del medio de pago.
 * El banco es la cuota cobrada más la mora. El recargo de Mercado Pago o tarjeta
 * queda fuera del asiento hasta definir esas cuentas.
 */
final class QuotaCollectionSplit
{
    /**
     * @param  object{quota_amount: mixed, amount_paid: mixed, surcharge_amount?: mixed, surcharge_amountMP?: mixed, surcharge_amountCard?: mixed}  $row
     * @return array{debtors: float, interest: float, bank: float, unposted: float}
     */
    public static function fromRow(object $row, float $splitCardSurcharge = 0.0): array
    {
        $quota = round((float) $row->quota_amount, 2);
        $paid = round(max(0, (float) $row->amount_paid), 2);
        $late = round(max(0, (float) ($row->surcharge_amount ?? 0)), 2);
        $otherExplicit = round(
            max(0, (float) ($row->surcharge_amountMP ?? 0))
            + max(0, (float) ($row->surcharge_amountCard ?? 0))
            + max(0, $splitCardSurcharge),
            2
        );

        if (($late + $otherExplicit) > 0.005) {
            $interestLate = $late;
            $interestOther = $otherExplicit;
        } else {
            $interestLate = 0.0;
            $interestOther = round(max(0, $paid - $quota), 2);
        }

        $capital = round(max(0, $paid - $interestLate - $interestOther), 2);
        $mora = round(min($interestLate, $paid), 2);
        $capital = round(min($capital, max(0, $paid - $mora)), 2);
        $bank = round($capital + $mora, 2);

        return [
            'debtors' => $capital,
            'interest' => $mora,
            'bank' => $bank,
            'unposted' => round(max(0, $paid - $bank), 2),
        ];
    }

    public static function accrualAmount(object $row): float
    {
        return round(max(0, (float) $row->quota_amount), 2);
    }
}
