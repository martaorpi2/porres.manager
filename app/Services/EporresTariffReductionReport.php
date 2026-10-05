<?php

namespace App\Services;

use App\Models\Eporres\EconomicReduction;
use App\Models\Eporres\Order;
use App\Support\Eporres\ReportePagosDiaCriteria;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EporresTariffReductionReport
{
    public const DISCOUNT_REASON = 'Reducción arancelaria por razones económicas';

    public const DISCOUNT_REASON_ART14 = 'Reducción arancelaria excepcional (art. 14)';

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(int $year, bool $soloCero, bool $soloNoAplicada): array
    {
        return $this->numerar($this->filter($this->buildRows($year), $soloCero, $soloNoAplicada));
    }

    /**
     * @return list<int>
     */
    public function years(): array
    {
        $desde = max(2026, (int) date('Y'));

        return range($desde, $desde - 4);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildRows(int $year): array
    {
        $reductions = EconomicReduction::query()
            ->with(['batch.instrumento'])
            ->where('year', $year)
            ->where('status', 'Aprobado')
            ->whereNotNull('percentage')
            ->get();

        $studentIds = $reductions->pluck('student_id');
        $conDescuento = Order::query()
            ->whereBetween('quota_number', [1, 10])
            ->whereYear('expirated_at', $year)
            ->where(function ($amount) {
                $amount->where('discount_amount', '>', 0)
                    ->orWhere('percentage_discount', '>', 0);
            })
            ->where(function ($reason) {
                $reason->where('discount_reason', 'like', 'Reducción arancelaria%')
                    ->orWhere('discount_reason', 'like', 'Reduccion arancelaria%');
            })
            ->pluck('student_id');

        $ids = $studentIds->merge($conDescuento)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $orders = Order::query()
            ->with(['student.career'])
            ->whereIn('student_id', $ids)
            ->whereBetween('quota_number', [1, 10])
            ->whereYear('expirated_at', $year)
            ->get();

        return $this->build($reductions, $orders);
    }

    private function build(Collection $reductions, Collection $orders): array
    {
        $reductionsByStudent = $reductions->groupBy('student_id');
        $rows = [];

        foreach ($orders->groupBy('student_id') as $studentId => $studentOrders) {
            $studentReductions = $reductionsByStudent->get($studentId, collect());
            $sibling = $this->siblingBenefit($studentOrders);

            foreach ($studentOrders as $order) {
                $row = $this->rowFor($order, $studentReductions, $sibling);
                if ($row !== null) {
                    $rows[] = $row;
                }
            }
        }

        usort($rows, function (array $a, array $b) {
            $career = strcasecmp((string) $a['carrera'], (string) $b['carrera']);
            if ($career !== 0) {
                return $career;
            }
            $name = strcasecmp((string) $a['alumno'], (string) $b['alumno']);
            if ($name !== 0) {
                return $name;
            }

            return $a['cuota_n'] <=> $b['cuota_n'];
        });

        return $rows;
    }

    private function rowFor(Order $order, Collection $reductions, ?array $sibling): ?array
    {
        $quota = round((float) $order->quota_amount, 2);
        if ($quota <= 0) {
            return null;
        }

        $reduction = $this->coveringReduction($reductions, $order);
        $storedPct = $this->percentageFromStored(
            (float) ($order->percentage_discount ?? 0),
            (float) ($order->discount_amount ?? 0),
            $quota
        );

        if ($reduction) {
            $percentage = $this->percentageForOrder($reduction);
            $reason = $reduction->isArt14() ? self::DISCOUNT_REASON_ART14 : self::DISCOUNT_REASON;
        } elseif ($storedPct > 0 && $this->isReductionReason((string) ($order->discount_reason ?? ''))) {
            $percentage = $storedPct;
            $reason = trim((string) $order->discount_reason);
        } elseif ($sibling && $reductions->isEmpty()) {
            $percentage = $sibling['percentage'];
            $reason = $sibling['reason'];
        } else {
            return null;
        }

        if ($percentage <= 0) {
            return null;
        }

        $beca = round($quota * ($percentage / 100), 2);
        $saldo = round((float) ($order->positive_balance ?? 0), 2);
        $aPagar = round($quota - $beca - $saldo, 2);
        $student = $order->student;
        $expira = $order->expirated_at ? Carbon::parse($order->expirated_at) : null;

        return [
            'alumno' => $student
                ? trim($student->last_name.', '.$student->first_name).($student->dni ? ' - '.$student->dni : '')
                : 'Alumno '.$order->student_id,
            'carrera' => $student?->career?->short_name ?? '',
            'cuota_n' => (int) $order->quota_number,
            'cuota' => $this->cuotaLabel((int) $order->quota_number, $expira),
            'cuota_original' => $quota,
            'porcentaje' => $percentage,
            'porcentaje_label' => ReportePagosDiaCriteria::formatBecaPercentage($percentage),
            'monto_beca' => $beca,
            'saldo_a_favor' => $saldo,
            'pago' => round((float) ($order->amount_paid ?? 0), 2),
            'saldo_cero' => abs($aPagar) < 0.05,
            'aplicada' => $storedPct > 0 && abs($storedPct - $percentage) < 0.05,
            'motivo' => $reason,
        ];
    }

    private function coveringReduction(Collection $reductions, Order $order): ?EconomicReduction
    {
        $match = null;
        foreach ($reductions as $reduction) {
            if ($reduction->percentage === null) {
                continue;
            }
            [$min, $max] = $reduction->isArt14() ? [2, 10] : [1, 10];
            $quotaNumber = (int) $order->quota_number;
            if ($quotaNumber < $min || $quotaNumber > $max) {
                continue;
            }
            if (! $this->withinSignatureMonth($reduction, $order)) {
                continue;
            }
            $match = $reduction;
        }

        return $match;
    }

    private function withinSignatureMonth(EconomicReduction $reduction, Order $order): bool
    {
        $signedAt = $reduction->batch?->signed_at
            ?? $reduction->batch?->instrumento?->fecha_emision
            ?? null;
        if ($signedAt === null || ! $order->expirated_at) {
            return true;
        }

        $desde = Carbon::parse($signedAt)->startOfMonth()->startOfDay();

        return Carbon::parse($order->expirated_at)->gte($desde);
    }

    private function siblingBenefit(Collection $orders): ?array
    {
        foreach ($orders as $order) {
            $quota = (float) $order->quota_amount;
            $pct = $this->percentageFromStored(
                (float) ($order->percentage_discount ?? 0),
                (float) ($order->discount_amount ?? 0),
                $quota
            );
            $reason = trim((string) ($order->discount_reason ?? ''));
            if ($pct > 0 && $this->isReductionReason($reason)) {
                return ['percentage' => $pct, 'reason' => $reason];
            }
        }

        return null;
    }

    private function percentageForOrder(EconomicReduction $reduction): float
    {
        $raw = (float) $reduction->percentage;

        return $raw > 1 ? round($raw, 2) : round($raw * 100, 2);
    }

    private function percentageFromStored(float $percentageDiscount, float $discountAmount, float $quotaAmount): float
    {
        if ($percentageDiscount > 1) {
            return round($percentageDiscount, 2);
        }
        if ($percentageDiscount > 0) {
            return round($percentageDiscount * 100, 2);
        }
        if ($quotaAmount > 0 && $discountAmount > 0) {
            return round(($discountAmount / $quotaAmount) * 100, 2);
        }

        return 0.0;
    }

    private function isReductionReason(string $reason): bool
    {
        $reason = trim($reason);
        if ($reason === '') {
            return false;
        }

        return strncasecmp($reason, 'Reducción arancelaria', strlen('Reducción arancelaria')) === 0
            || strncasecmp($reason, 'Reduccion arancelaria', strlen('Reduccion arancelaria')) === 0;
    }

    private function cuotaLabel(int $number, ?Carbon $expira): string
    {
        $label = 'Cuota '.$number;
        if (! $expira) {
            return $label;
        }

        $meses = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ];

        return $label.' · '.($meses[(int) $expira->month] ?? '').' '.$expira->year;
    }

    private function filter(array $rows, bool $soloCero, bool $soloNoAplicada): array
    {
        return array_values(array_filter($rows, function (array $row) use ($soloCero, $soloNoAplicada) {
            if ($soloCero && ! $row['saldo_cero']) {
                return false;
            }
            if ($soloNoAplicada && $row['aplicada']) {
                return false;
            }

            return true;
        }));
    }

    private function numerar(array $rows): array
    {
        $nro = 0;
        $anterior = null;
        foreach ($rows as $i => $row) {
            $clave = (string) $row['alumno'];
            if ($clave !== $anterior) {
                $nro++;
                $anterior = $clave;
                $rows[$i]['nro'] = $nro;
            } else {
                $rows[$i]['nro'] = '';
                $rows[$i]['alumno'] = '';
                $rows[$i]['carrera'] = '';
            }
        }

        return $rows;
    }
}
