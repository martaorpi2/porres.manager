@once
<style>
    tr.row-imputacion { background: #fff8e6; }
    tr.row-plan-cuota { background: #f0f7ff; }
    tr.row-otro-concepto { background: #f3faf3; }
    .badge-beca { background-color: #6f42c1; color: #fff; }
    .badge-beca-perdida { background-color: #9a3412; color: #fff; }
    .nota-beca-perdida { display: block; font-size: 0.75rem; color: #9a3412; margin-top: 0.15rem; max-width: 18rem; }
    .nota-plan { font-size: 0.8rem; color: #6c757d; }
</style>
@endonce
@php
    use App\Support\Eporres\OrderPaymentLines;
    use App\Support\Eporres\ReportePagosDiaCriteria;
    use App\Support\Eporres\ReportePagosDiaMedioPagoOrder;
    $lineasDia = ReportePagosDiaMedioPagoOrder::sortLinesForSameDay(OrderPaymentLines::expandOrdersForReport($pagos));
    $planCuotas = $planCuotas ?? [];
    $money = fn ($n) => '$'.number_format((float) $n, 2, ',', '.');
@endphp
<div class="mb-4">
    <h6 class="text-primary border-bottom pb-2">
        Fecha de pago: {{ $fechaPago != 'sin_fecha' ? \Carbon\Carbon::parse($fechaPago)->format('d/m/Y') : 'Sin fecha' }}
        <span class="badge bg-secondary">{{ $pagos->count() }} cupón(es)</span>
        @if($lineasDia->count() > $pagos->count())
            <span class="badge bg-info">{{ $lineasDia->count() }} cobro(s)</span>
        @endif
    </h6>
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead class="table-light">
                <tr>
                    <th>Comprobante</th>
                    <th>Nro de transacción/cupón</th>
                    <th>Estudiante</th>
                    <th>Carrera</th>
                    <th>Tipo</th>
                    <th class="text-center">Nº cuota</th>
                    <th>Medio de Pago</th>
                    <th class="text-end">Importe pagado del plan</th>
                    <th class="text-end">Cuota pura del plan</th>
                    <th class="text-end">Interés por mora del plan</th>
                    <th class="text-end">Interés de financiación</th>
                    <th class="text-end">Interés del medio de pago</th>
                    <th class="text-end">Imputación cuota pura</th>
                    <th class="text-end">Imputación interés capitalizado</th>
                    <th class="text-end">Otros intereses</th>
                    <th class="text-end">Total línea</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $diaOtros = 0.0; $diaPagado = 0.0;
                    $diaPlanPaid = 0.0; $diaPlanPure = 0.0; $diaPlanLate = 0.0; $diaPlanFinancing = 0.0;
                    $diaPlanMethod = 0.0; $diaImputePure = 0.0; $diaImputeCapitalized = 0.0;
                    $conceptoAsignado = [];
                @endphp
                @foreach($pagos as $pago)
                    @php
                        $lines = OrderPaymentLines::forOrder($pago, true);
                        $rowspan = count($lines);
                        $esImputacion = ReportePagosDiaCriteria::isImputacionPlan($pago);
                        $esPlan = ReportePagosDiaCriteria::isCuotaPlanPago($pago);
                        $esOtro = ReportePagosDiaCriteria::isOtroConcepto($pago);
                        $labelBeca = ReportePagosDiaCriteria::labelBeca($pago);
                        $rowClass = $esImputacion ? 'row-imputacion' : ($esPlan ? 'row-plan-cuota' : ($esOtro ? 'row-otro-concepto' : ''));
                        $nota = ReportePagosDiaCriteria::notaVinculo($pago, $relatedByPlanId);
                        $cuotaPlan = ($esPlan && isset($planCuotas[(int) $pago->id])) ? $planCuotas[(int) $pago->id] : null;
                        if (!isset($conceptoAsignado[$pago->id])) {
                            $conceptoAsignado[$pago->id] = true;
                            if ($cuotaPlan !== null) {
                                $diaPlanPaid += (float) $cuotaPlan['paid'];
                                $diaPlanPure += (float) $cuotaPlan['pure'];
                                $diaPlanLate += (float) $cuotaPlan['late'];
                                $diaPlanFinancing += (float) $cuotaPlan['financing'];
                                $diaPlanMethod += (float) $cuotaPlan['method'];
                                $diaImputePure += (float) $cuotaPlan['impute_pure'];
                                $diaImputeCapitalized += (float) $cuotaPlan['impute_capitalized'];
                            }
                        }
                        $diaPagado += (float) $pago->amount_paid;
                    @endphp
                    @foreach($lines as $line)
                        @php
                            $diaOtros += (float) $line['interest_other'];
                            $totalLinea = OrderPaymentLines::totalLinea($line);
                        @endphp
                        <tr class="{{ $rowClass }}">
                            @if($line['is_first_line'])
                                <td rowspan="{{ $rowspan }}">
                                    {{ $pago->id }}
                                    @if($esPlan)<span class="badge bg-primary">Plan</span>@endif
                                    @if($esOtro)<span class="badge bg-success">Otro</span>@endif
                                    @if($esImputacion)<span class="badge bg-warning text-dark">Imputación</span>@endif
                                    @if(!empty($line['beca_perdida']))
                                        <span class="badge badge-beca-perdida">{{ ReportePagosDiaCriteria::etiquetaBecaPerdida($pago) }}</span>
                                        <span class="nota-beca-perdida">{{ ReportePagosDiaCriteria::notaCuotaOriginalPorBecaPerdida((float) ($line['order_pure'] ?? 0)) }}</span>
                                    @elseif($labelBeca !== '')
                                        <span class="badge badge-beca">{{ $labelBeca }}</span>
                                    @endif
                                    @if($nota !== '')<span class="nota-plan d-block">{{ $nota }}</span>@endif
                                </td>
                                <td rowspan="{{ $rowspan }}">{{ trim((string) ($pago->payment_id ?? '')) !== '' ? $pago->payment_id : '—' }}</td>
                                <td rowspan="{{ $rowspan }}">{{ $pago->student ? $pago->student->last_name.', '.$pago->student->first_name.' - '.($pago->student->dni ?: 's/d') : '-' }}</td>
                                <td rowspan="{{ $rowspan }}">{{ $pago->student && $pago->student->career ? $pago->student->career->short_name : '-' }}</td>
                                <td rowspan="{{ $rowspan }}">{{ ReportePagosDiaCriteria::labelTipo($pago) }}</td>
                                <td rowspan="{{ $rowspan }}" class="text-center">{{ ReportePagosDiaCriteria::labelNumeroCuota($pago, $pago->student_plan) }}</td>
                            @endif
                            <td>{{ $line['payment_type'] ?: 'Sin especificar' }}</td>
                            @if($line['is_first_line'])
                                <td rowspan="{{ $rowspan }}" class="text-end">{{ $cuotaPlan ? $money($cuotaPlan['paid']) : '—' }}</td>
                                <td rowspan="{{ $rowspan }}" class="text-end">{{ $cuotaPlan ? $money($cuotaPlan['pure']) : '—' }}</td>
                                <td rowspan="{{ $rowspan }}" class="text-end">{{ $cuotaPlan ? $money($cuotaPlan['late']) : '—' }}</td>
                                <td rowspan="{{ $rowspan }}" class="text-end">{{ $cuotaPlan ? $money($cuotaPlan['financing']) : '—' }}</td>
                                <td rowspan="{{ $rowspan }}" class="text-end">{{ $cuotaPlan ? $money($cuotaPlan['method']) : '—' }}</td>
                                <td rowspan="{{ $rowspan }}" class="text-end">{{ $cuotaPlan ? $money($cuotaPlan['impute_pure']) : '—' }}</td>
                                <td rowspan="{{ $rowspan }}" class="text-end">{{ $cuotaPlan ? $money($cuotaPlan['impute_capitalized']) : '—' }}</td>
                            @endif
                            <td class="text-end">${{ number_format($line['interest_other'], 2, ',', '.') }}</td>
                            <td class="text-end">${{ number_format($totalLinea, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="7" class="text-end">{{ $vista === 'imputaciones' ? 'Subtotal día (imputado, no caja)' : 'Subtotal día (caja)' }}</td>
                    <td class="text-end">{{ $money($diaPlanPaid) }}</td>
                    <td class="text-end">{{ $money($diaPlanPure) }}</td>
                    <td class="text-end">{{ $money($diaPlanLate) }}</td>
                    <td class="text-end">{{ $money($diaPlanFinancing) }}</td>
                    <td class="text-end">{{ $money($diaPlanMethod) }}</td>
                    <td class="text-end">{{ $money($diaImputePure) }}</td>
                    <td class="text-end">{{ $money($diaImputeCapitalized) }}</td>
                    <td class="text-end">${{ number_format($diaOtros, 2, ',', '.') }}</td>
                    <td class="text-end">${{ number_format($diaPagado, 2, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
