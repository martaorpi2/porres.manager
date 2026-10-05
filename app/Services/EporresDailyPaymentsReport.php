<?php

namespace App\Services;

use App\Models\Eporres\Order;
use App\Support\Eporres\OrderPaymentLines;
use App\Support\Eporres\ReportePagosDiaCriteria;
use App\Support\Eporres\ReportePagosDiaMedioPagoOrder;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EporresDailyPaymentsReport
{
    /** @return array<string, string> */
    public static function paymentTypeOptions(): array
    {
        return [
            'BSE' => 'BSE',
            'Mercado Pago' => 'Mercado Pago',
            'QR' => 'QR',
            'Tarjeta de Crédito' => 'Tarjeta de Crédito',
            'Tarjeta Naranja' => 'Tarjeta Naranja',
            'Tarjeta Naranja Débito' => 'Tarjeta Naranja Débito',
            'Tarjeta Sol' => 'Tarjeta Sol',
            'Tarjeta de Débito' => 'Tarjeta de Débito',
        ];
    }

    /**
     * @param  list<string>  $mediosPago
     * @return array{
     *   pagosPorDia: Collection,
     *   resumenPorMedio: Collection,
     *   mediosPagoOpciones: Collection,
     *   mediosPago: list<string>,
     *   tipoPago: ?string,
     *   vista: string,
     *   relatedByPlanId: Collection,
     *   lineas: Collection
     * }
     */
    public function build(string $fechaDesde, string $fechaHasta, array $mediosPago, ?string $tipoPago, string $vista): array
    {
        $vista = ReportePagosDiaCriteria::normalizeVista($vista);
        $tipoPago = in_array($tipoPago, ['matricula', 'cuota', 'plan', 'otros'], true) ? $tipoPago : null;

        $desdeDb = Order::query()
            ->whereNotNull('payment_type')
            ->where('payment_type', '!=', '')
            ->distinct()
            ->pluck('payment_type');
        $mediosPagoOpciones = collect(array_keys(self::paymentTypeOptions()))
            ->merge($desdeDb)
            ->unique()
            ->sort()
            ->values();

        if ($vista === ReportePagosDiaCriteria::VISTA_COBROS) {
            $mediosPagoOpciones = $mediosPagoOpciones
                ->reject(fn ($m) => $m === ReportePagosDiaCriteria::PAYMENT_TYPE_PLAN)
                ->values();
            $mediosPago = array_values(array_filter(
                $mediosPago,
                fn ($m) => $m !== ReportePagosDiaCriteria::PAYMENT_TYPE_PLAN
            ));
        }

        $query = ReportePagosDiaCriteria::baseQuery($fechaDesde, $fechaHasta)
            ->with(['student.career', 'tariff_category', 'splitPayment', 'student_plan', 'pend_plans', 'amortization'])
            ->orderBy('paid_at', 'desc')
            ->orderBy('id', 'desc');

        ReportePagosDiaCriteria::applyVista($query, $vista);
        ReportePagosDiaCriteria::applyTipoPago($query, $tipoPago, $vista);
        $this->applyMedioPago($query, $mediosPago);

        $pagos = $query->get();
        $relatedByPlanId = $vista === ReportePagosDiaCriteria::VISTA_IMPUTACIONES
            ? ReportePagosDiaCriteria::planInstallmentsByPlanId($pagos)
            : ReportePagosDiaCriteria::coveredArancelByPlanId($pagos);

        $pagosPorDia = $pagos->groupBy(function ($order) {
            return $order->paid_at ? Carbon::parse($order->paid_at)->format('Y-m-d') : 'sin_fecha';
        })->map(function ($items) {
            return ReportePagosDiaMedioPagoOrder::sortOrdersForSameDay($items);
        });

        $lineas = ReportePagosDiaMedioPagoOrder::sortLinesForSameDay(
            OrderPaymentLines::expandOrdersForReport($pagos)
        );

        return [
            'pagosPorDia' => $pagosPorDia,
            'resumenPorMedio' => OrderPaymentLines::resumenPorMedio($pagos),
            'mediosPagoOpciones' => $mediosPagoOpciones,
            'mediosPago' => $mediosPago,
            'tipoPago' => $tipoPago,
            'vista' => $vista,
            'relatedByPlanId' => $relatedByPlanId,
            'lineas' => $lineas,
        ];
    }

    /**
     * @param  list<string>  $mediosPago
     * @return list<list<int|float|string>>
     */
    public function excelRows(Collection $lineas, Collection $relatedByPlanId): array
    {
        $conceptoAsignado = [];
        $rows = [];

        foreach ($lineas as $line) {
            $pago = $line['order'];
            $estudiante = $pago->student
                ? $pago->student->last_name.', '.$pago->student->first_name.' - '.($pago->student->dni ?: 's/d')
                : '-';
            $carrera = $pago->student && $pago->student->career
                ? $pago->student->career->short_name
                : '-';
            $nota = $line['is_first_line']
                ? ReportePagosDiaCriteria::notaReporte($pago, $relatedByPlanId)
                : '';
            $pure = '';
            $planFin = '';
            $mora = '';
            if ($line['is_first_line'] && ! isset($conceptoAsignado[$pago->id])) {
                $conceptoAsignado[$pago->id] = true;
                $pure = (float) ($line['order_pure'] ?? 0);
                $planFin = (float) ($line['order_interest_plan'] ?? 0);
                $mora = (float) ($line['order_interest_late'] ?? 0);
            }

            $rows[] = [
                $line['is_split'] && ! $line['is_first_line'] ? '↳ '.$pago->id : $pago->id,
                $estudiante,
                $carrera,
                ReportePagosDiaCriteria::labelTipo($pago),
                ReportePagosDiaCriteria::labelNumeroCuota($pago, $pago->student_plan),
                $pago->paid_at ? Carbon::parse($pago->paid_at)->format('d/m/Y') : '',
                OrderPaymentLines::etiquetaMedio($line['payment_type']),
                OrderPaymentLines::montoDelMedio($line),
                $pure,
                $planFin,
                $mora,
                (float) $line['interest_other'],
                OrderPaymentLines::totalLinea($line),
                $nota,
            ];
        }

        return $rows;
    }

    private function applyMedioPago($query, array $mediosPago): void
    {
        if ($mediosPago === []) {
            return;
        }

        $query->where(function ($q) use ($mediosPago) {
            $q->whereIn('payment_type', $mediosPago)
                ->orWhereHas('splitPayment', function ($sq) use ($mediosPago) {
                    $sq->whereIn('payment_type', $mediosPago);
                });
        });
    }
}
