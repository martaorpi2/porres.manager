@extends(backpack_view('blank'))

@php
    use App\Support\Eporres\OrderPaymentLines;
    use App\Support\Eporres\ReportePagosDiaCriteria;
    use App\Support\Eporres\ReportePagosDiaMedioPagoOrder;
    $vista = $vista ?? ReportePagosDiaCriteria::VISTA_COBROS;
    $relatedByPlanId = $relatedByPlanId ?? collect();
@endphp

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
        <h1 class="mb-0" bp-section="page-heading">Reporte de Pagos Registrados por Día</h1>
    </section>
@endsection

@section('content')
<style>
    .btn_bordo { background: #881f1f !important; color: #fff !important; }
    .mp-picker { display: flex; flex-wrap: wrap; gap: 0.45rem; }
    .mp-pill { display: inline-flex; margin: 0; cursor: pointer; }
    .mp-pill-input { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); }
    .mp-pill-body { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 0.65rem; border: 1px solid #ced4da; border-radius: 0.375rem; background: #fff; }
    .mp-pill-check { display: inline-flex; align-items: center; justify-content: center; width: 1.15rem; height: 1.15rem; border-radius: 0.25rem; border: 1px solid #adb5bd; background: #f8f9fa; color: transparent; font-size: 0.7rem; }
    .mp-pill-input:checked + .mp-pill-body { border-color: #198754; background: #e9f7ef; color: #0a3622; }
    .mp-pill-input:checked + .mp-pill-body .mp-pill-check { background: #198754; border-color: #198754; color: #fff; }
    .vista-hint, .nota-plan { font-size: 0.8rem; color: #6c757d; }
    tr.row-imputacion { background: #fff8e6; }
    tr.row-plan-cuota { background: #f0f7ff; }
    tr.row-otro-concepto { background: #f3faf3; }
    .badge-beca { background-color: #6f42c1; color: #fff; }
</style>

<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">Filtros</h5></div>
    <div class="card-body">
        <form method="GET" action="{{ backpack_url('accounting-daily-payments') }}" class="row g-3">
            <div class="col-md-2">
                <label for="fecha_desde" class="form-label">Fecha de pago desde</label>
                <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" value="{{ $fechaDesde }}" required>
            </div>
            <div class="col-md-2">
                <label for="fecha_hasta" class="form-label">Fecha de pago hasta</label>
                <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" value="{{ $fechaHasta }}" required>
            </div>
            <div class="col-md-2">
                <label for="vista" class="form-label">Vista</label>
                <select name="vista" id="vista" class="form-control">
                    <option value="cobros" {{ $vista === 'cobros' ? 'selected' : '' }}>Cobros de caja</option>
                    <option value="imputaciones" {{ $vista === 'imputaciones' ? 'selected' : '' }}>Imputaciones a plan</option>
                </select>
                <div class="vista-hint">
                    @if($vista === 'imputaciones')
                        Cuotas/matrícula canceladas vía plan (no son ingreso de ese medio).
                    @else
                        Plata que entró: matrícula, cuotas, plan y otros. Excluye “Plan de Pago”.
                    @endif
                </div>
            </div>
            <div class="col-md-4">
                <span class="form-label d-block">Medios de pago</span>
                <div class="mp-picker">
                    @foreach($mediosPagoOpciones ?? [] as $mp)
                        <label class="mp-pill">
                            <input type="checkbox" name="medio_pago[]" value="{{ $mp }}" class="mp-pill-input" {{ in_array($mp, $mediosPago ?? [], true) ? 'checked' : '' }}>
                            <span class="mp-pill-body">
                                <span class="mp-pill-check"><i class="la la-check"></i></span>
                                <span>{{ $mp }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <div class="form-text">Si no marca ninguno, se muestran todos.</div>
            </div>
            <div class="col-md-2">
                <label for="tipo_pago" class="form-label">Tipo</label>
                <select name="tipo_pago" id="tipo_pago" class="form-control">
                    <option value="" {{ empty($tipoPago) ? 'selected' : '' }}>Todos</option>
                    <option value="matricula" {{ $tipoPago === 'matricula' ? 'selected' : '' }}>Matrícula</option>
                    <option value="cuota" {{ $tipoPago === 'cuota' ? 'selected' : '' }}>Cuota arancel</option>
                    @if($vista !== 'imputaciones')
                        <option value="plan" {{ $tipoPago === 'plan' ? 'selected' : '' }}>Cuota de plan</option>
                        <option value="otros" {{ $tipoPago === 'otros' ? 'selected' : '' }}>Otros (libreta, etc.)</option>
                    @endif
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end mt-2">
                <button type="submit" class="btn btn-primary me-2">Filtrar</button>
                <a href="{{ backpack_url('accounting-daily-payments') }}" class="btn btn-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h5 class="mb-0">
            {{ $vista === 'imputaciones' ? 'Resumen de imputaciones por medio (informativo)' : 'Resumen por forma/medio de pago (caja)' }}
        </h5>
    </div>
    <div class="card-body">
        @if($vista === 'imputaciones')
            <p class="text-muted small">Estos montos no deben sumarse como cobro de “Plan de Pago” en caja: el ingreso real está en Cobros de caja.</p>
        @endif
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="table-light">
                    <tr>
                        <th>Medio de Pago</th>
                        <th class="text-center">Cantidad de cobros</th>
                        <th class="text-end">Cuota pura ($)</th>
                        <th class="text-end">Int. financiación plan ($)</th>
                        <th class="text-end">Int. mora ($)</th>
                        <th class="text-end">Otros int. ($)</th>
                        <th class="text-end">Total ($)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($resumenPorMedio as $medio => $datos)
                        <tr>
                            <td>{{ $medio ?: 'Sin especificar' }}</td>
                            <td class="text-center">{{ $datos['cantidad'] }}</td>
                            <td class="text-end">${{ number_format($datos['cuota_pura'] ?? 0, 2, ',', '.') }}</td>
                            <td class="text-end">${{ number_format($datos['interes_financiacion_plan'] ?? 0, 2, ',', '.') }}</td>
                            <td class="text-end">${{ number_format($datos['interes_mora'] ?? 0, 2, ',', '.') }}</td>
                            <td class="text-end">${{ number_format($datos['otros_intereses'] ?? 0, 2, ',', '.') }}</td>
                            <td class="text-end">${{ number_format($datos['total'], 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center">No hay pagos en el período seleccionado</td></tr>
                    @endforelse
                    @if($resumenPorMedio->isNotEmpty())
                        <tr class="table-active fw-bold">
                            <td>TOTAL</td>
                            <td class="text-center">{{ $resumenPorMedio->sum('cantidad') }}</td>
                            <td class="text-end">${{ number_format($resumenPorMedio->sum('cuota_pura'), 2, ',', '.') }}</td>
                            <td class="text-end">${{ number_format($resumenPorMedio->sum('interes_financiacion_plan'), 2, ',', '.') }}</td>
                            <td class="text-end">${{ number_format($resumenPorMedio->sum('interes_mora'), 2, ',', '.') }}</td>
                            <td class="text-end">${{ number_format($resumenPorMedio->sum('otros_intereses'), 2, ',', '.') }}</td>
                            <td class="text-end">${{ number_format($resumenPorMedio->sum('total'), 2, ',', '.') }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Detalle por fecha — {{ $vista === 'imputaciones' ? 'imputaciones a plan' : 'cobros de caja' }}</h5>
        @php
            $excelParams = [
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
                'tipo_pago' => $tipoPago ?? '',
                'vista' => $vista,
            ];
            if (!empty($mediosPago)) {
                $excelParams['medio_pago'] = $mediosPago;
            }
        @endphp
        <a href="{{ backpack_url('accounting-daily-payments/excel').'?'.http_build_query($excelParams) }}" class="btn btn-success btn-sm">
            <i class="la la-file-excel"></i> Descargar Excel
        </a>
    </div>
    <div class="card-body">
        @forelse($pagosPorDia->sortKeysDesc() as $fechaPago => $pagos)
            @php
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
        @empty
            <p class="text-muted text-center py-4">No hay pagos en el período seleccionado.</p>
        @endforelse
    </div>
</div>
@endsection
