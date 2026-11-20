<?php

namespace App\Services;

/**
 * Reparte el capital de cada cuota del plan entre la cuota original
 * y el interés capitalizado del alta. El ajuste de centavos queda en la última cuota.
 */
final class PaymentPlanCuotaSplit
{
    /**
     * @param  iterable<int, object>  $pend
     * @return array{debt: float, late: float}
     */
    public static function opening(iterable $pend): array
    {
        $debt = 0.0;
        $late = 0.0;
        foreach ($pend as $row) {
            if ((int) ($row->order_id ?? 0) < 1) {
                continue;
            }
            if (strcasecmp((string) ($row->type ?? ''), 'Interes') === 0) {
                $late += (float) ($row->amount ?? 0);
            } else {
                $debt += (float) ($row->amount ?? 0);
            }
        }

        return [
            'debt' => round($debt, 2),
            'late' => round($late, 2),
        ];
    }

    /**
     * @param  list<array{order_id: int, quota_number?: int, capital: float}>  $installments
     * @return array<int, array{pure: float, capitalized: float}>
     */
    public static function impute(array $installments, float $debt, float $late): array
    {
        $rows = [];
        foreach ($installments as $row) {
            $rows[] = [
                'order_id' => (int) $row['order_id'],
                'quota_number' => (int) ($row['quota_number'] ?? 0),
                'capital' => round(max(0, (float) $row['capital']), 2),
            ];
        }
        usort($rows, function (array $a, array $b): int {
            return [$a['quota_number'], $a['order_id']] <=> [$b['quota_number'], $b['order_id']];
        });

        $debt = round(max(0, $debt), 2);
        $late = round(max(0, $late), 2);
        $base = round($debt + $late, 2);
        $total = round(array_sum(array_column($rows, 'capital')), 2);
        $target = 0.0;
        if ($rows !== [] && $base > 0 && $total > 0) {
            $target = abs($total - $base) < 0.05
                ? min($debt, $total)
                : round($total * ($debt / $base), 2);
            $target = min($total, max(0, $target));
        }

        $out = [];
        $assigned = 0.0;
        $last = count($rows) - 1;
        foreach ($rows as $i => $row) {
            if ($base <= 0 || $total <= 0) {
                $pure = $row['capital'];
            } elseif ($i === $last) {
                $pure = round($target - $assigned, 2);
                $pure = min($row['capital'], max(0, $pure));
            } else {
                $pure = round($row['capital'] * ($debt / $base), 2);
                $pure = min($row['capital'], max(0, $pure));
                $room = round($target - $assigned, 2);
                if ($pure > $room) {
                    $pure = max(0, $room);
                }
                $assigned = round($assigned + $pure, 2);
            }
            $out[$row['order_id']] = [
                'pure' => $pure,
                'capitalized' => round($row['capital'] - $pure, 2),
            ];
        }

        return $out;
    }
}
