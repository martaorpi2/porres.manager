<?php

namespace App\Support\Eporres;

use App\Models\Eporres\Order;
use Illuminate\Support\Collection;

/**
 * Líneas de cobro por cupón (pago único o pago combinado).
 */
final class OrderPaymentLines
{
    /** @var list<string> */
    public const CARD_PAYMENT_TYPES = [
        'Tarjeta de Crédito',
        'Tarjeta Naranja',
        'Tarjeta Sol',
    ];

    public static function forOrder(Order $order): array
    {
        $order->loadMissing('splitPayment');
        $breakdown = OrderPaymentBreakdown::amounts($order);
        $split = $order->splitPayment;

        if ($split === null) {
            $cash = round((float) $order->amount_paid, 2);
            $conceptAmount = round(max(0, $cash - $breakdown['interest_other']), 2);

            return [[
                'order' => $order,
                'sequence' => 1,
                'payment_type' => (string) ($order->payment_type ?? ''),
                'amount' => $conceptAmount,
                'interest_other' => $breakdown['interest_other'],
                'order_pure' => $breakdown['net_pure'],
                'order_interest_late' => $breakdown['interest_late_fee'],
                'order_interest_plan' => $breakdown['interest_plan_financing'],
                'is_split' => false,
                'is_first_line' => true,
            ]];
        }

        $type1 = (string) ($order->payment_type ?? '');
        $type2 = (string) $split->payment_type;
        $otherInterest = self::allocateOtherInterest($order, $type1, $type2, $breakdown['interest_other']);
        $amount1 = self::tramoPureAmount($order, 1, $type1);
        $amount2 = self::tramoPureAmount($order, 2, $type2);

        return [
            [
                'order' => $order,
                'sequence' => 1,
                'payment_type' => $type1,
                'amount' => $amount1,
                'interest_other' => $otherInterest['tramo1'],
                'order_pure' => $breakdown['net_pure'],
                'order_interest_late' => $breakdown['interest_late_fee'],
                'order_interest_plan' => $breakdown['interest_plan_financing'],
                'is_split' => true,
                'is_first_line' => true,
            ],
            [
                'order' => $order,
                'sequence' => 2,
                'payment_type' => $type2,
                'amount' => $amount2,
                'interest_other' => $otherInterest['tramo2'],
                'order_pure' => null,
                'order_interest_late' => null,
                'order_interest_plan' => null,
                'is_split' => true,
                'is_first_line' => false,
            ],
        ];
    }

    private static function tramoGrossAmount(Order $order, int $tramo): float
    {
        $split = $order->splitPayment;
        if ($split === null) {
            return round((float) $order->amount_paid, 2);
        }

        $total = round((float) $order->amount_paid, 2);
        $gross2 = round((float) $split->amount, 2);

        return $tramo === 2 ? $gross2 : round($total - $gross2, 2);
    }

    private static function tramoCardInterest(Order $order, int $tramo, string $paymentType): float
    {
        if (! in_array($paymentType, self::CARD_PAYMENT_TYPES, true)) {
            return 0.0;
        }

        if ($tramo === 1) {
            return round((float) $order->surcharge_amountCard, 2);
        }

        return round((float) ($order->splitPayment?->surcharge_amount_card ?? 0), 2);
    }

    private static function tramoPureAmount(Order $order, int $tramo, string $paymentType): float
    {
        $gross = self::tramoGrossAmount($order, $tramo);
        $cardInterest = self::tramoCardInterest($order, $tramo, $paymentType);

        if ($cardInterest > 0.005) {
            return round(max(0, $gross - $cardInterest), 2);
        }

        return $gross;
    }

    private static function allocateOtherInterest(Order $order, string $type1, string $type2, float $totalOther): array
    {
        if ($totalOther <= 0.005) {
            return ['tramo1' => 0.0, 'tramo2' => 0.0];
        }

        $order->loadMissing('splitPayment');
        $card1 = in_array($type1, self::CARD_PAYMENT_TYPES, true);
        $card2 = in_array($type2, self::CARD_PAYMENT_TYPES, true);
        $surcharge1 = $card1 ? (float) $order->surcharge_amountCard : 0.0;
        $surcharge2 = $card2 ? (float) ($order->splitPayment?->surcharge_amount_card ?? 0) : 0.0;
        $assigned = $surcharge1 + $surcharge2;

        if ($assigned > 0.005) {
            $remainder = max(0, round($totalOther - $assigned, 2));
            if ($remainder <= 0.005) {
                return ['tramo1' => round($surcharge1, 2), 'tramo2' => round($surcharge2, 2)];
            }

            return [
                'tramo1' => round($surcharge1 + ($card1 ? $remainder : 0), 2),
                'tramo2' => round($surcharge2 + ($card2 && ! $card1 ? $remainder : 0), 2),
            ];
        }

        $mpSurcharge = (float) $order->surcharge_amountMP;
        if ($mpSurcharge > 0.005) {
            if ($type2 === 'Mercado Pago') {
                return ['tramo1' => 0.0, 'tramo2' => round($mpSurcharge, 2)];
            }
            if ($type1 === 'Mercado Pago') {
                return ['tramo1' => round($mpSurcharge, 2), 'tramo2' => 0.0];
            }
        }

        return ['tramo1' => round($totalOther, 2), 'tramo2' => 0.0];
    }

    public static function expandOrdersForReport(Collection $orders): Collection
    {
        $lines = collect();
        foreach ($orders as $order) {
            foreach (self::forOrder($order) as $line) {
                $lines->push($line);
            }
        }

        return $lines;
    }

    public static function resumenPorMedio(Collection $orders): Collection
    {
        $resumen = [];
        $moraAssigned = [];
        $planFinAssigned = [];

        foreach ($orders as $order) {
            $lines = self::forOrder($order);
            $breakdown = OrderPaymentBreakdown::amounts($order);

            foreach ($lines as $line) {
                $medio = self::etiquetaMedio($line['payment_type']);
                if (! isset($resumen[$medio])) {
                    $resumen[$medio] = [
                        'cantidad' => 0,
                        'total' => 0.0,
                        'cuota_pura' => 0.0,
                        'interes_mora' => 0.0,
                        'interes_financiacion_plan' => 0.0,
                        'otros_intereses' => 0.0,
                    ];
                }
                $resumen[$medio]['cantidad']++;
                $resumen[$medio]['total'] += self::totalLinea($line);
                $resumen[$medio]['otros_intereses'] += $line['interest_other'];
            }

            foreach (self::allocatePurePorMedio($lines, $breakdown) as $medioPure => $pureShare) {
                if (isset($resumen[$medioPure])) {
                    $resumen[$medioPure]['cuota_pura'] += $pureShare;
                }
            }

            $firstMedio = self::etiquetaMedio($lines[0]['payment_type'] ?? '');
            if (! isset($moraAssigned[$order->id]) && $breakdown['interest_late_fee'] > 0.005) {
                $moraAssigned[$order->id] = true;
                if (isset($resumen[$firstMedio])) {
                    $resumen[$firstMedio]['interes_mora'] += $breakdown['interest_late_fee'];
                }
            }
            if (! isset($planFinAssigned[$order->id]) && $breakdown['interest_plan_financing'] > 0.005) {
                $planFinAssigned[$order->id] = true;
                if (isset($resumen[$firstMedio])) {
                    $resumen[$firstMedio]['interes_financiacion_plan'] += $breakdown['interest_plan_financing'];
                }
            }
        }

        return collect($resumen)->sortBy(function ($datos, $medioKey) {
            $clave = $medioKey === 'Sin especificar' ? '' : (string) $medioKey;

            return sprintf('%010d-%s', ReportePagosDiaMedioPagoOrder::rank($clave), $clave);
        });
    }

    public static function etiquetaMedio(?string $paymentType): string
    {
        $t = trim((string) $paymentType);

        return $t !== '' ? $t : 'Sin especificar';
    }

    public static function totalLinea(array $line): float
    {
        return round((float) $line['amount'] + (float) $line['interest_other'], 2);
    }

    public static function montoDelMedio(array $line): float
    {
        return self::totalLinea($line);
    }

    private static function allocatePurePorMedio(array $lines, array $breakdown): array
    {
        $pure = (float) ($breakdown['net_pure'] ?? $breakdown['pure']);
        if ($pure <= 0.005) {
            return [];
        }

        if (count($lines) === 1) {
            $medio = self::etiquetaMedio($lines[0]['payment_type']);

            return [$medio => round($pure, 2)];
        }

        $weights = [];
        $medios = [];
        foreach ($lines as $i => $line) {
            $w = (float) $line['amount'];
            if ($i === 0) {
                $deduct = 0.0;
                if ($breakdown['interest_late_fee'] > 0.005) {
                    $deduct += (float) $breakdown['interest_late_fee'];
                }
                if (($breakdown['interest_plan_financing'] ?? 0) > 0.005) {
                    $deduct += (float) $breakdown['interest_plan_financing'];
                }
                if ($deduct > 0.005) {
                    $w = max(0, round($w - $deduct, 2));
                }
            }
            $weights[] = $w;
            $medios[] = self::etiquetaMedio($line['payment_type']);
        }

        $sumW = array_sum($weights);
        if ($sumW <= 0.005) {
            return [$medios[0] => round($pure, 2)];
        }

        $out = [];
        $assigned = 0.0;
        $last = count($lines) - 1;
        foreach ($lines as $i => $line) {
            if ($i === $last) {
                $share = round($pure - $assigned, 2);
            } else {
                $share = round($pure * ($weights[$i] / $sumW), 2);
                $assigned += $share;
            }
            $m = $medios[$i];
            $out[$m] = ($out[$m] ?? 0.0) + $share;
        }

        return $out;
    }
}
