<?php

namespace App\Services;

/**
 * Compara las cuotas ya asentadas con las que hoy corresponden según ePorres.
 * Los importes van en centavos: [deudores, mora, banco].
 */
final class QuotaCollectionReconcile
{
    /**
     * @param  array<int, array{0: int, 1: int, 2: int}>  $stored
     * @param  array<int, array{0: int, 1: int, 2: int}>  $desired
     */
    public static function differs(array $stored, array $desired): bool
    {
        if (count($stored) !== count($desired)) {
            return true;
        }

        foreach ($desired as $orderId => $amounts) {
            if (! isset($stored[$orderId]) || $stored[$orderId] !== $amounts) {
                return true;
            }
        }

        return false;
    }
}
