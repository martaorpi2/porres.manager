@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
        <h1 class="mb-0" bp-section="page-heading">Pago / Morosidad</h1>
    </section>
@endsection

@section('content')
@php
    $mesNro = ((int) $anio < (int) date('Y')) ? 12 : (int) date('n');
    $meses = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
@endphp
<style>
    .btn_bordo { background: #881f1f !important; color: #fff !important; }
    .metric-card { color: #fff; }
    .metric-card h3 { color: #fff; margin-bottom: 0; }
</style>

<div class="row mb-3">
    <div class="col-md-4">
        <label for="anio" class="form-label">Año</label>
        <select class="form-control" id="anio" onchange="window.location = '{{ backpack_url('accounting-delinquency') }}/' + this.value;">
            @foreach($years as $year)
                <option value="{{ $year }}" {{ (int) $anio === (int) $year ? 'selected' : '' }}>{{ $year }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4 d-flex align-items-end">
        <a href="{{ backpack_url('accounting-delinquency/'.$anio.'/pdf') }}" target="_blank" class="btn btn_bordo">Ver PDF</a>
    </div>
</div>

<h4 class="text-uppercase"><b>Métricas de Pago - Año {{ $anio }}</b></h4>
<div class="row">
    @for ($i = 3; $i <= $mesNro; $i++)
        @php
            $percentage = $count_quota[$i] != 0 ? ($count_quota_paid[$i] * 100) / $count_quota[$i] : 0;
        @endphp
        <div class="col-xl-4 col-sm-6 col-12">
            <div class="card bg-secondary metric-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3>${{ number_format((float) $total_paid[$i]['paid'], 2) }}</h3>
                            <span>{{ $meses[$i] }}</span>
                        </div>
                        <i class="la la-wallet la-2x"></i>
                    </div>
                    <div class="progress mt-2" style="height: 7px;">
                        <div class="progress-bar bg-success" style="width: {{ number_format($percentage, 2) }}%"></div>
                    </div>
                    {{ number_format($percentage, 2) }}%
                    @if(($total_interest_paid[$i] ?? 0) > 0)
                        <div class="mt-1"><strong>Intereses pagados: ${{ $money($total_interest_paid[$i]) }}</strong></div>
                    @endif
                </div>
            </div>
        </div>
    @endfor
</div>

<h4 class="text-uppercase mt-3"><b>Métricas de Morosidad - Año {{ $anio }}</b></h4>
<div class="row">
    @for ($i = 3; $i <= $mesNro; $i++)
        @php
            $countPending = $count_quota[$i] - $count_quota_paid[$i];
            $percentage = $count_quota[$i] != 0 ? ($countPending * 100) / $count_quota[$i] : 0;
        @endphp
        <div class="col-xl-4 col-sm-6 col-12">
            <div class="card bg-danger metric-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3>${{ number_format((float) $total_pending[$i]['pending'], 2) }}</h3>
                            <span>{{ $meses[$i] }}</span>
                        </div>
                        <i class="la la-exclamation-triangle la-2x"></i>
                    </div>
                    <div class="progress mt-2" style="height: 7px;">
                        <div class="progress-bar bg-warning" style="width: {{ number_format($percentage, 2) }}%"></div>
                    </div>
                    {{ number_format($percentage, 2) }}%
                </div>
            </div>
        </div>
    @endfor
</div>

<h4 class="text-uppercase mt-3"><b>Resumen por cuota - Año {{ $anio }}</b></h4>
<div class="table-responsive">
    <table class="table table-bordered table-sm mb-0">
        <thead class="table-light">
            <tr>
                <th>CUOTA</th>
                <th>MES</th>
                <th class="text-end">TOTAL GENERADAS</th>
                <th class="text-end">TOTAL PAGADAS</th>
                <th class="text-end">MONTO CUOTA PURA (GENERADO)</th>
                <th class="text-end">MONTO PAGADO (CUOTA PURA)</th>
                <th class="text-end">MONTO PAGADO REAL</th>
                <th class="text-end">DESCUENTOS</th>
                <th class="text-end">INTERES MORA</th>
                <th class="text-end">INTERES MERCADO PAGO</th>
                <th class="text-end">OTROS INTERESES</th>
                <th class="text-end">MONTO ADEUDADO</th>
            </tr>
        </thead>
        <tbody>
            @foreach($resumenPorCuota as $row)
                <tr>
                    <td>{{ $row['cuota'] }}</td>
                    <td>{{ $row['mes'] }}</td>
                    <td class="text-end">{{ number_format($row['total_generadas'], 0, ',', '.') }}</td>
                    <td class="text-end">{{ number_format($row['total_pagadas'], 0, ',', '.') }}</td>
                    <td class="text-end">${{ $money($row['monto_generado']) }}</td>
                    <td class="text-end">${{ $money($row['monto_pagado_cuota']) }}</td>
                    <td class="text-end">${{ $money($row['monto_pagado_real']) }}</td>
                    <td class="text-end">${{ $money($row['monto_descuentos']) }}</td>
                    <td class="text-end">${{ $money($row['monto_pagado_interes_mora']) }}</td>
                    <td class="text-end">${{ $money($row['monto_pagado_interes_mp']) }}</td>
                    <td class="text-end">${{ $money($row['monto_diferencia_conciliacion']) }}</td>
                    <td class="text-end">${{ $money($row['monto_adeudado']) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="table-light">
            <tr>
                <th colspan="2" class="text-end">TOTAL</th>
                <th class="text-end">{{ number_format(array_sum(array_column($resumenPorCuota, 'total_generadas')), 0, ',', '.') }}</th>
                <th class="text-end">{{ number_format(array_sum(array_column($resumenPorCuota, 'total_pagadas')), 0, ',', '.') }}</th>
                <th class="text-end">${{ $money(array_sum(array_column($resumenPorCuota, 'monto_generado'))) }}</th>
                <th class="text-end">${{ $money(array_sum(array_column($resumenPorCuota, 'monto_pagado_cuota'))) }}</th>
                <th class="text-end">${{ $money(array_sum(array_column($resumenPorCuota, 'monto_pagado_real'))) }}</th>
                <th class="text-end">${{ $money(array_sum(array_column($resumenPorCuota, 'monto_descuentos'))) }}</th>
                <th class="text-end">${{ $money(array_sum(array_column($resumenPorCuota, 'monto_pagado_interes_mora'))) }}</th>
                <th class="text-end">${{ $money(array_sum(array_column($resumenPorCuota, 'monto_pagado_interes_mp'))) }}</th>
                <th class="text-end">${{ $money(array_sum(array_column($resumenPorCuota, 'monto_diferencia_conciliacion'))) }}</th>
                <th class="text-end">${{ $money(array_sum(array_column($resumenPorCuota, 'monto_adeudado'))) }}</th>
            </tr>
        </tfoot>
    </table>
</div>
<div class="mt-2 mb-4">
    <a href="{{ backpack_url('accounting-delinquency/'.$anio.'/resumen.xlsx') }}" class="btn btn_bordo me-2">Descargar solo cuadro (Excel)</a>
    <a href="{{ backpack_url('accounting-delinquency/'.$anio.'/deudores.xlsx') }}" class="btn btn_bordo">Descargar listado de deudores (Excel)</a>
</div>

<h4 class="text-uppercase"><b>Matrícula - pagos por mes - año {{ $anio }}</b></h4>
<div class="table-responsive">
    <table class="table table-bordered table-sm mb-0">
        <thead class="table-light">
            <tr>
                <th>Nº</th>
                <th>MES</th>
                <th>CONCEPTO</th>
                <th class="text-end">TOTAL PAGADAS</th>
                <th class="text-end">MONTO PAGADO (CUOTA PURA)</th>
                <th class="text-end">MONTO PAGADO REAL</th>
                <th class="text-end">INTERES MERCADO PAGO</th>
                <th class="text-end">OTROS INTERESES</th>
            </tr>
        </thead>
        <tbody>
            @foreach($resumenMatriculaPagos as $row)
                <tr>
                    <td>{{ $row['nro_mes'] }}</td>
                    <td>{{ $row['mes'] }}</td>
                    <td>{{ $row['concepto'] }}</td>
                    <td class="text-end">{{ number_format($row['total_pagadas'], 0, ',', '.') }}</td>
                    <td class="text-end">${{ $money($row['monto_pagado_cuota']) }}</td>
                    <td class="text-end">${{ $money($row['monto_pagado_real']) }}</td>
                    <td class="text-end">${{ $money($row['monto_pagado_interes_mp']) }}</td>
                    <td class="text-end">${{ $money($row['monto_diferencia_conciliacion']) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="table-light">
            <tr>
                <th colspan="3" class="text-end">TOTAL</th>
                <th class="text-end">{{ number_format(array_sum(array_column($resumenMatriculaPagos, 'total_pagadas')), 0, ',', '.') }}</th>
                <th class="text-end">${{ $money(array_sum(array_column($resumenMatriculaPagos, 'monto_pagado_cuota'))) }}</th>
                <th class="text-end">${{ $money(array_sum(array_column($resumenMatriculaPagos, 'monto_pagado_real'))) }}</th>
                <th class="text-end">${{ $money(array_sum(array_column($resumenMatriculaPagos, 'monto_pagado_interes_mp'))) }}</th>
                <th class="text-end">${{ $money(array_sum(array_column($resumenMatriculaPagos, 'monto_diferencia_conciliacion'))) }}</th>
            </tr>
        </tfoot>
    </table>
</div>
<div class="mt-2 mb-4">
    <a href="{{ backpack_url('accounting-delinquency/'.$anio.'/matricula.xlsx') }}" class="btn btn_bordo">Descargar matrícula por mes (Excel)</a>
</div>

<div class="row">
    <div class="col-md-6">
        <h5>Por cantidad de cuotas adeudadas</h5>
        <table class="table table-bordered table-sm">
            <thead class="table-light">
                <tr>
                    <th>Cantidad de cuotas adeudadas</th>
                    <th class="text-end">Cantidad de alumnos</th>
                    <th class="text-center">Listado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($deudoresPorTramoResumen as $fila)
                    <tr>
                        <td>{{ $fila['etiqueta'] }}</td>
                        <td class="text-end">{{ number_format($fila['cantidad'], 0, ',', '.') }}</td>
                        <td class="text-center">
                            @if($fila['cantidad'] > 0)
                                <a href="{{ backpack_url('accounting-delinquency/'.$anio.'/deudores/'.$fila['export_key'].'.xlsx') }}" class="btn btn-sm btn_bordo">Obtener listado en Excel</a>
                            @else
                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Obtener listado en Excel</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="col-md-6">
        <h5>Egresados del año con cuota pendiente</h5>
        <table class="table table-bordered table-sm">
            <thead class="table-light">
                <tr>
                    <th>Concepto</th>
                    <th class="text-end">Cantidad</th>
                    <th class="text-center">Listado</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Estado Egresado, egreso en {{ $anio }} (último examen aprobado) y adeudan cuota</td>
                    <td class="text-end">{{ number_format($egresadosDeudoresCuotasCount, 0, ',', '.') }}</td>
                    <td class="text-center">
                        @if($egresadosDeudoresCuotasCount > 0)
                            <a href="{{ backpack_url('accounting-delinquency/'.$anio.'/egresados.xlsx') }}" class="btn btn-sm btn_bordo">Obtener listado en Excel</a>
                        @else
                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Obtener listado en Excel</button>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
