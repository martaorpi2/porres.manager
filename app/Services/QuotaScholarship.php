<?php

namespace App\Services;

/**
 * Importe de beca de una cuota.
 * La beca histórica está en grant_amount. La reducción arancelaria vigente
 * está en discount_amount, con un motivo que empieza por «Reducción arancelaria».
 */
final class QuotaScholarship
{
    /**
     * @param  object{quota_amount?: mixed, grant_amount?: mixed, discount_amount?: mixed, discount_reason?: mixed}  $row
     */
    public static function amount(object $row): float
    {
        $quota = round(max(0, (float) ($row->quota_amount ?? 0)), 2);
        $grant = round(max(0, (float) ($row->grant_amount ?? 0)), 2);
        $discount = self::isScholarshipReason((string) ($row->discount_reason ?? ''))
            ? round(max(0, (float) ($row->discount_amount ?? 0)), 2)
            : 0.0;
        $amount = round($grant + $discount, 2);
        if ($quota > 0) {
            $amount = min($amount, $quota);
        }

        return round(max(0, $amount), 2);
    }

    public static function isScholarshipReason(string $reason): bool
    {
        $reason = trim($reason);
        if ($reason === '') {
            return false;
        }

        return strncasecmp($reason, 'Reducción arancelaria', strlen('Reducción arancelaria')) === 0
            || strncasecmp($reason, 'Reduccion arancelaria', strlen('Reduccion arancelaria')) === 0;
    }
}
