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
    .vista-ayuda, .nota-plan { font-size: 0.8rem; color: #6c757d; }
    tr.row-imputacion { background: #fff8e6; }
    tr.row-plan-cuota { background: #f0f7ff; }
    tr.row-otro-concepto { background: #f3faf3; }
    .badge-beca { background-color: #6f42c1; color: #fff; }
    .badge-beca-perdida { background-color: #9a3412; color: #fff; }
    .nota-beca-perdida { display: block; font-size: 0.75rem; color: #9a3412; margin-top: 0.15rem; max-width: 18rem; }
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
            <div class="col-md-3">
                <label for="vista" class="form-label">Qué pagos ver</label>
                <select name="vista" id="vista" class="form-control">
                    <option value="cobros" {{ $vista === 'cobros' ? 'selected' : '' }}>Dinero que ingresó</option>
                    <option value="imputaciones" {{ $vista === 'imputaciones' ? 'selected' : '' }}>Cuotas que cubrió un plan</option>
                </select>
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
                    <option value="plan" class="tipo-solo-cobros" {{ $tipoPago === 'plan' ? 'selected' : '' }} @if($vista === 'imputaciones') hidden disabled @endif>Cuota de plan</option>
                    <option value="otros" class="tipo-solo-cobros" {{ $tipoPago === 'otros' ? 'selected' : '' }} @if($vista === 'imputaciones') hidden disabled @endif>Otros (libreta, etc.)</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end mt-2">
                <button type="submit" class="btn btn-primary me-2">Filtrar</button>
                <a href="{{ backpack_url('accounting-daily-payments') }}" class="btn btn-secondary">Limpiar</a>
            </div>
            <div class="col-12">
                <p class="vista-ayuda mb-0" data-vista="cobros" @if($vista !== 'cobros') hidden @endif>
                    Plata que realmente entró: matrícula, cuotas, cuotas del plan de pago y otros (libreta, título, etc.). No incluye cuotas que solo figuran pagas porque las cubrió un plan.
                </p>
                <p class="vista-ayuda mb-0" data-vista="imputaciones" @if($vista !== 'imputaciones') hidden @endif>
                    Cuotas y matrículas que figuran pagas porque las cubrió un plan. No es plata que entró: ese cobro está en “Dinero que ingresó”, en las cuotas del plan.
                </p>
            </div>
        </form>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h5 class="mb-0">
            {{ $vista === 'imputaciones' ? 'Resumen de cuotas cubiertas por un plan (no es ingreso)' : 'Resumen del dinero que ingresó, por medio de pago' }}
        </h5>
    </div>
    <div class="card-body">
        @if($vista === 'imputaciones')
            <p class="text-muted small">Estos importes no son plata que entró. El ingreso está en “Dinero que ingresó”, en las cuotas del plan.</p>
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
        <h5 class="mb-0">Detalle por fecha — {{ $vista === 'imputaciones' ? 'cuotas que cubrió un plan' : 'dinero que ingresó' }}</h5>
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
        <p class="text-muted small">Cada cobro muestra lo pagado, la cuota pura, la mora, la financiación y el interés del medio, con el mismo criterio del resumen. En las cuotas de plan, la imputación indica cuánto de ese capital cubre la cuota original y cuánto el interés capitalizado. En el resto, esas dos columnas quedan vacías.</p>
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
<script>
    document.getElementById('vista').addEventListener('change', function () {
        var value = this.value;
        document.querySelectorAll('.vista-ayuda').forEach(function (el) {
            el.hidden = el.getAttribute('data-vista') !== value;
        });
        document.querySelectorAll('.tipo-solo-cobros').forEach(function (opt) {
            var visible = value === 'cobros';
            opt.hidden = !visible;
            opt.disabled = !visible;
        });
        var tipo = document.getElementById('tipo_pago');
        if (value !== 'cobros' && (tipo.value === 'plan' || tipo.value === 'otros')) {
            tipo.value = '';
        }
    });
</script>
@endsection
