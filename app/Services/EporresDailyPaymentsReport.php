<?php

namespace App\Services;

use App\Models\Eporres\Order;
use App\Support\Eporres\OrderPaymentBreakdown;
use App\Support\Eporres\OrderPaymentLines;
use App\Support\Eporres\ReportePagosDiaCriteria;
use App\Support\Eporres\ReportePagosDiaMedioPagoOrder;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
     *   lineas: Collection,
     *   planCuotas: array<int, array{paid: float, pure: float, late: float, financing: float, method: float, impute_pure: float, impute_capitalized: float}>
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
            'planCuotas' => $this->planCuotas($pagos),
        ];
    }

    /**
     * Mismo detalle que el reporte de cobros de caja, limitado a estos cupones.
     *
     * @param  list<int>  $orderIds
     * @return array{pagosPorDia: Collection, relatedByPlanId: Collection, vista: string, planCuotas: array<int, array<string, float>>}
     */
    public function forOrderIds(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        $pagos = $orderIds === []
            ? collect()
            : Order::query()
                ->whereIn('id', $orderIds)
                ->with(['student.career', 'tariff_category', 'splitPayment', 'student_plan', 'pend_plans', 'amortization'])
                ->orderBy('paid_at', 'desc')
                ->orderBy('id', 'desc')
                ->get();

        $pagosPorDia = $pagos->groupBy(function ($order) {
            return $order->paid_at ? Carbon::parse($order->paid_at)->format('Y-m-d') : 'sin_fecha';
        })->map(function ($items) {
            return ReportePagosDiaMedioPagoOrder::sortOrdersForSameDay($items);
        });

        return [
            'pagosPorDia' => $pagosPorDia,
            'relatedByPlanId' => ReportePagosDiaCriteria::coveredArancelByPlanId($pagos),
            'vista' => ReportePagosDiaCriteria::VISTA_COBROS,
            'planCuotas' => $this->planCuotas($pagos),
        ];
    }

    /**
     * @param  list<string>  $mediosPago
     * @return list<list<int|float|string>>
     */
    public function excelRows(Collection $lineas, Collection $relatedByPlanId, array $planCuotas = []): array
    {
        $conceptoAsignado = [];
        $rows = [];
        $lineasPorOrden = [];
        foreach ($lineas as $line) {
            $lineasPorOrden[(int) $line['order']->id][] = $line;
        }

        foreach ($lineas as $line) {
            $pago = $line['order'];
            $estudiante = $pago->student
                ? $pago->student->last_name.', '.$pago->student->first_name.' - '.($pago->student->dni ?: 's/d')
                : '-';
            $carrera = $pago->student && $pago->student->career
                ? $pago->student->career->short_name
                : '-';
            $nota = $line['is_first_line']
                ? ReportePagosDiaCriteria::notaReporte(
                    $pago,
                    $relatedByPlanId,
                    (bool) ($line['beca_perdida'] ?? false),
                    (float) ($line['order_pure'] ?? 0)
                )
                : '';
            $planCells = ['', '', '', '', '', '', ''];
            if ($line['is_first_line'] && ! isset($conceptoAsignado[$pago->id])) {
                $conceptoAsignado[$pago->id] = true;
                $cuotaPlan = $planCuotas[(int) $pago->id] ?? null;
                if ($cuotaPlan !== null && ReportePagosDiaCriteria::isCuotaPlanPago($pago)) {
                    $planCells = [
                        (float) $cuotaPlan['paid'],
                        (float) $cuotaPlan['pure'],
                        (float) $cuotaPlan['late'],
                        (float) $cuotaPlan['financing'],
                        (float) $cuotaPlan['method'],
                        (float) $cuotaPlan['impute_pure'],
                        (float) $cuotaPlan['impute_capitalized'],
                    ];
                } else {
                    $comun = OrderPaymentLines::desgloseCuotaComun($pago, $lineasPorOrden[(int) $pago->id] ?? [$line]);
                    $planCells = [
                        $comun['paid'],
                        $comun['pure'],
                        $comun['late'],
                        $comun['financing'],
                        $comun['method'],
                        '',
                        '',
                    ];
                }
            }

            $rows[] = [
                $line['is_split'] && ! $line['is_first_line'] ? '↳ '.$pago->id : $pago->id,
                $line['is_first_line'] ? trim((string) ($pago->payment_id ?? '')) : '',
                $estudiante,
                $carrera,
                ReportePagosDiaCriteria::labelTipo($pago),
                ReportePagosDiaCriteria::labelNumeroCuota($pago, $pago->student_plan),
                $pago->paid_at ? Carbon::parse($pago->paid_at)->format('d/m/Y') : '',
                OrderPaymentLines::etiquetaMedio($line['payment_type']),
                ...$planCells,
                (float) $line['interest_other'],
                OrderPaymentLines::totalLinea($line),
                $nota,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{paid: float, pure: float, late: float, financing: float, method: float, impute_pure: float, impute_capitalized: float}>
     */
    private function planCuotas(Collection $orders): array
    {
        $planOrders = [];
        foreach ($orders as $order) {
            if (! $order instanceof Order || ! ReportePagosDiaCriteria::isCuotaPlanPago($order)) {
                continue;
            }
            $planId = (int) $order->student_plan_id;
            if ($planId < 1) {
                continue;
            }
            $planOrders[$planId][] = $order;
        }
        if ($planOrders === []) {
            return [];
        }

        $planIds = array_keys($planOrders);
        $pend = DB::connection('eporres')->table('orders_pend_plan')
            ->whereIn('student_plan_id', $planIds)
            ->get(['student_plan_id', 'order_id', 'type', 'amount']);
        $amorts = DB::connection('eporres')->table('amortizations')
            ->whereIn('student_plan_id', $planIds)
            ->orderBy('quota_number')
            ->orderBy('id')
            ->get(['student_plan_id', 'order_id', 'quota_number', 'quota_amount', 'interest_amount', 'capital']);

        $pendByPlan = [];
        foreach ($pend as $row) {
            $pendByPlan[(int) $row->student_plan_id][] = $row;
        }
        $amortByPlan = [];
        foreach ($amorts as $row) {
            $amortByPlan[(int) $row->student_plan_id][] = $row;
        }

        $out = [];
        foreach ($planOrders as $planId => $list) {
            $installments = [];
            foreach ($amortByPlan[$planId] ?? [] as $row) {
                $orderId = (int) $row->order_id;
                if ($orderId < 1) {
                    continue;
                }
                $interest = round(max(0, (float) $row->interest_amount), 2);
                $capital = $row->capital !== null
                    ? round(max(0, (float) $row->capital), 2)
                    : round(max(0, (float) $row->quota_amount - $interest), 2);
                if (! isset($installments[$orderId])) {
                    $installments[$orderId] = [
                        'order_id' => $orderId,
                        'quota_number' => (int) $row->quota_number,
                        'capital' => 0.0,
                        'financing' => 0.0,
                    ];
                }
                $installments[$orderId]['capital'] = round($installments[$orderId]['capital'] + $capital, 2);
                $installments[$orderId]['financing'] = round($installments[$orderId]['financing'] + $interest, 2);
                $installments[$orderId]['quota_number'] = min($installments[$orderId]['quota_number'], (int) $row->quota_number);
            }
            foreach ($list as $order) {
                $orderId = (int) $order->id;
                if (isset($installments[$orderId])) {
                    continue;
                }
                $breakdown = OrderPaymentBreakdown::amountsForReporte($order);
                $installments[$orderId] = [
                    'order_id' => $orderId,
                    'quota_number' => (int) $order->quota_number,
                    'capital' => round(max(0, (float) $order->quota_amount - $breakdown['interest_plan_financing']), 2),
                    'financing' => round((float) $breakdown['interest_plan_financing'], 2),
                ];
            }

            $opening = PaymentPlanCuotaSplit::opening($pendByPlan[$planId] ?? []);
            $shares = PaymentPlanCuotaSplit::impute(array_values($installments), $opening['debt'], $opening['late']);
            foreach ($list as $order) {
                $orderId = (int) $order->id;
                $row = $installments[$orderId];
                $breakdown = OrderPaymentBreakdown::amountsForReporte($order);
                $share = $shares[$orderId] ?? ['pure' => $row['capital'], 'capitalized' => 0.0];
                $out[$orderId] = [
                    'paid' => round((float) $order->amount_paid, 2),
                    'pure' => $row['capital'],
                    'late' => round((float) $breakdown['interest_late_fee'], 2),
                    'financing' => $row['financing'],
                    'method' => round((float) $breakdown['interest_other'], 2),
                    'impute_pure' => $share['pure'],
                    'impute_capitalized' => $share['capitalized'],
                ];
            }
        }

        return $out;
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
