<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Neto, comisión y fecha de liberación que trae el pago de Mercado Pago.
 * Si el aviso sigue en pendiente pero la fecha de liberación ya pasó, el neto
 * del pago alcanza: Mercado Pago informa esos importes desde la aprobación.
 */
final class MercadoPagoRelease
{
    /**
     * @param  array<string, mixed>  $payment
     * @return array{gross: float, net: float, commission: float, release_date: string}|null
     */
    public static function fromPayment(array $payment, ?string $asOf = null): ?array
    {
        $gross = round((float) ($payment['transaction_amount'] ?? 0), 2);
        $net = round((float) ($payment['transaction_details']['net_received_amount'] ?? 0), 2);
        $commission = self::collectorFee($payment);

        if ($gross < 0.01 || $net < 0.01 || $commission < 0) {
            return null;
        }
        if (abs(($net + $commission) - $gross) > 0.02) {
            return null;
        }

        $releaseDate = self::releaseDate($payment['money_release_date'] ?? null);
        if ($releaseDate === null) {
            return null;
        }

        $released = ($payment['money_release_status'] ?? '') === 'released';
        $due = is_string($asOf) && $asOf !== '' && $releaseDate <= $asOf;
        if (! $released && ! $due) {
            return null;
        }

        return [
            'gross' => $gross,
            'net' => $net,
            'commission' => $commission,
            'release_date' => $releaseDate,
        ];
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private static function collectorFee(array $payment): float
    {
        $fee = 0.0;
        $charges = $payment['charges_details'] ?? [];
        if (! is_array($charges)) {
            return 0.0;
        }

        foreach ($charges as $charge) {
            if (! is_array($charge) || ($charge['name'] ?? '') !== 'mercadopago_fee') {
                continue;
            }
            $from = $charge['accounts']['from'] ?? '';
            if ($from !== 'collector') {
                continue;
            }
            $fee += (float) ($charge['amounts']['original'] ?? 0);
        }

        return round($fee, 2);
    }

    private static function releaseDate(mixed $raw): ?string
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable($raw);
        } catch (\Exception) {
            return null;
        }

        return $date
            ->setTimezone(new DateTimeZone('America/Argentina/Buenos_Aires'))
            ->format('Y-m-d');
    }
}
