@extends(backpack_view('blank'))

@php
    use App\Support\Eporres\ReportePagosDiaCriteria;
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
            @include('admin.accounting.partials.daily_payment_day', [
                'fechaPago' => $fechaPago,
                'pagos' => $pagos,
                'relatedByPlanId' => $relatedByPlanId,
                'vista' => $vista,
            ])
        @empty
            <p class="text-muted text-center py-4">No hay pagos en el período seleccionado.</p>
        @endforelse
    </div>
</div>
@endsection
