<?php

namespace App\Support\Eporres;

use App\Models\Eporres\Order;
use Illuminate\Support\Collection;

final class ReportePagosDiaMedioPagoOrder
{
    /** @var list<string> */
    private const MEDIOS_ORDEN = [
        'QR',
        'BSE',
        'Tarjeta de Débito',
        'Tarjeta Naranja',
        'Tarjeta Naranja Débito',
        'Tarjeta Sol',
        'Mercado Pago',
        'Tarjeta de Crédito',
        'Plan de Pago',
    ];

    private const RANK_OTROS = 1000;

    private const RANK_SIN_ESPECIFICAR = 2000;

    public static function rank(string $paymentType): int
    {
        if ($paymentType === '') {
            return self::RANK_SIN_ESPECIFICAR;
        }

        $idx = array_search($paymentType, self::MEDIOS_ORDEN, true);

        return $idx !== false ? $idx : self::RANK_OTROS;
    }

    public static function sortOrdersForSameDay(Collection $orders): Collection
    {
        return $orders->sort(function (Order $a, Order $b) {
            $ta = (string) ($a->payment_type ?? '');
            $tb = (string) ($b->payment_type ?? '');
            $ra = self::rank($ta);
            $rb = self::rank($tb);
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }
            if ($ta !== $tb) {
                return strcmp($ta, $tb);
            }

            return $b->id <=> $a->id;
        })->values();
    }

    public static function sortLinesForSameDay(Collection $lines): Collection
    {
        return $lines->sort(function (array $a, array $b) {
            $orderA = $a['order'];
            $orderB = $b['order'];
            $paidA = $orderA->paid_at ? strtotime((string) $orderA->paid_at) : 0;
            $paidB = $orderB->paid_at ? strtotime((string) $orderB->paid_at) : 0;
            if ($paidA !== $paidB) {
                return $paidB <=> $paidA;
            }
            if ($orderA->id !== $orderB->id) {
                return $orderB->id <=> $orderA->id;
            }

            $ta = (string) ($a['payment_type'] ?? '');
            $tb = (string) ($b['payment_type'] ?? '');
            $ra = self::rank($ta);
            $rb = self::rank($tb);
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }

            return ($a['sequence'] ?? 0) <=> ($b['sequence'] ?? 0);
        })->values();
    }
}
