@once
<style>
    tr.row-imputacion { background: #fff8e6; }
    tr.row-plan-cuota { background: #f0f7ff; }
    tr.row-otro-concepto { background: #f3faf3; }
    .badge-beca { background-color: #6f42c1; color: #fff; }
    .nota-plan { font-size: 0.8rem; color: #6c757d; }
</style>
@endonce
@php
    use App\Support\Eporres\OrderPaymentLines;
    use App\Support\Eporres\ReportePagosDiaCriteria;
    use App\Support\Eporres\ReportePagosDiaMedioPagoOrder;
    $lineasDia = ReportePagosDiaMedioPagoOrder::sortLinesForSameDay(OrderPaymentLines::expandOrdersForReport($pagos));
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
                    <th>Estudiante</th>
                    <th>Carrera</th>
                    <th>Tipo</th>
                    <th class="text-center">Nº cuota</th>
                    <th>Medio de Pago</th>
                    <th class="text-end">Monto del medio</th>
                    <th class="text-end">Importe cuota pura</th>
                    <th class="text-end">Int. financiación plan</th>
                    <th class="text-end">Interés por mora</th>
                    <th class="text-end">Otros intereses</th>
                    <th class="text-end">Total línea</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $diaPure = 0.0; $diaPlanFin = 0.0; $diaMora = 0.0; $diaOtros = 0.0; $diaPagado = 0.0;
                    $conceptoAsignado = [];
                @endphp
                @foreach($pagos as $pago)
                    @php
                        $lines = OrderPaymentLines::forOrder($pago);
                        $rowspan = count($lines);
                        $esImputacion = ReportePagosDiaCriteria::isImputacionPlan($pago);
                        $esPlan = ReportePagosDiaCriteria::isCuotaPlanPago($pago);
                        $esOtro = ReportePagosDiaCriteria::isOtroConcepto($pago);
                        $labelBeca = ReportePagosDiaCriteria::labelBeca($pago);
                        $rowClass = $esImputacion ? 'row-imputacion' : ($esPlan ? 'row-plan-cuota' : ($esOtro ? 'row-otro-concepto' : ''));
                        $nota = ReportePagosDiaCriteria::notaVinculo($pago, $relatedByPlanId);
                        if (!isset($conceptoAsignado[$pago->id])) {
                            $conceptoAsignado[$pago->id] = true;
                            $diaPure += (float) ($lines[0]['order_pure'] ?? 0);
                            $diaPlanFin += (float) ($lines[0]['order_interest_plan'] ?? 0);
                            $diaMora += (float) ($lines[0]['order_interest_late'] ?? 0);
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
                                    @if($labelBeca !== '')<span class="badge badge-beca">{{ $labelBeca }}</span>@endif
                                    @if($nota !== '')<span class="nota-plan d-block">{{ $nota }}</span>@endif
                                </td>
                                <td rowspan="{{ $rowspan }}">{{ $pago->student ? $pago->student->last_name.', '.$pago->student->first_name.' - '.($pago->student->dni ?: 's/d') : '-' }}</td>
                                <td rowspan="{{ $rowspan }}">{{ $pago->student && $pago->student->career ? $pago->student->career->short_name : '-' }}</td>
                                <td rowspan="{{ $rowspan }}">{{ ReportePagosDiaCriteria::labelTipo($pago) }}</td>
                                <td rowspan="{{ $rowspan }}" class="text-center">{{ ReportePagosDiaCriteria::labelNumeroCuota($pago, $pago->student_plan) }}</td>
                            @endif
                            <td>{{ $line['payment_type'] ?: 'Sin especificar' }}</td>
                            <td class="text-end">${{ number_format($totalLinea, 2, ',', '.') }}</td>
                            @if($line['is_first_line'])
                                <td rowspan="{{ $rowspan }}" class="text-end">${{ number_format((float) ($line['order_pure'] ?? 0), 2, ',', '.') }}</td>
                                <td rowspan="{{ $rowspan }}" class="text-end">${{ number_format((float) ($line['order_interest_plan'] ?? 0), 2, ',', '.') }}</td>
                                <td rowspan="{{ $rowspan }}" class="text-end">${{ number_format((float) ($line['order_interest_late'] ?? 0), 2, ',', '.') }}</td>
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
                    <td class="text-end">${{ number_format($diaPure, 2, ',', '.') }}</td>
                    <td class="text-end">${{ number_format($diaPlanFin, 2, ',', '.') }}</td>
                    <td class="text-end">${{ number_format($diaMora, 2, ',', '.') }}</td>
                    <td class="text-end">${{ number_format($diaOtros, 2, ',', '.') }}</td>
                    <td class="text-end">${{ number_format($diaPagado, 2, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
