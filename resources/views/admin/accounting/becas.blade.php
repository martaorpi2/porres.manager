@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
        <h1 class="mb-0" bp-section="page-heading">Informe de becas</h1>
    </section>
@endsection

@section('content')
@php
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
    $clase = function (array $row): string {
        if (! empty($row['perdio_la_beca'])) {
            return 'fila-perdio';
        }
        if (in_array($row['situacion'], ['fuera_de_termino', 'vencida_sin_pagar'], true)) {
            return 'fila-caida';
        }

        return '';
    };
@endphp
<style>
    .btn_bordo { background: #881f1f !important; color: #fff !important; }
    tr.fila-caida { background: #fff8e6; }
    tr.fila-perdio { background: #fde8e4; }
</style>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ backpack_url('accounting-becas') }}" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label for="year">Año</label>
                <select name="year" id="year" class="form-control">
                    @foreach($years as $opcion)
                        <option value="{{ $opcion }}" {{ (int) $year === (int) $opcion ? 'selected' : '' }}>{{ $opcion }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn_bordo">Ver</button>
                <a href="{{ backpack_url('accounting-becas/excel').'?'.http_build_query(request()->query()) }}" class="btn btn-secondary">Excel</a>
            </div>
        </form>
        <p class="text-muted mb-0 mt-3">
            Económica y art. 14, desde la cuota de octubre. Una cuota pagada después del vencimiento,
            o vencida y sin pagar, es una caída de ese mes. El día del vencimiento todavía no cae.
            Tres caídas seguidas dan de baja la beca completa: el alumno sale de activos y cada mes
            de caída queda abajo, con lo abonado. Calculado al {{ $hoy }}.
            La fila <span style="background:#fff8e6;border:1px solid #e6d59a;padding:0 0.35rem;">en amarillo</span>
            es una caída que todavía no tira la beca.
            La fila <span style="background:#fde8e4;border:1px solid #f0b8b0;padding:0 0.35rem;">en rojo</span>
            es el mes en que la perdió por completo.
        </p>
    </div>
</div>

<p>
    <strong>{{ $alumnosActivos }}</strong> alumnos con beca activa
    · <strong>{{ count($activas) }}</strong> cupones
    · <strong>{{ $alumnosPerdidos }}</strong> perdieron la beca
</p>

<h2 class="h5">Becas activas</h2>
<div class="table-responsive mb-4">
    <table class="table table-sm table-bordered bg-white">
        <thead>
            <tr>
                <th>Nº</th>
                <th>Alumno</th>
                <th>Carrera</th>
                <th>Cuota</th>
                <th class="text-end">Cuota original</th>
                <th class="text-end">% beca</th>
                <th class="text-end">Monto beca</th>
                <th class="text-end">Saldo a favor</th>
                <th class="text-end">Pagó</th>
                <th>Situación</th>
                <th>Motivo</th>
            </tr>
        </thead>
        <tbody>
            @forelse($activas as $row)
                <tr class="{{ $clase($row) }}">
                    <td>{{ $row['nro'] }}</td>
                    <td>{{ $row['alumno'] }}</td>
                    <td>{{ $row['carrera'] }}</td>
                    <td>{{ $row['cuota'] }}</td>
                    <td class="text-end">{{ $money($row['cuota_original']) }}</td>
                    <td class="text-end">{{ $row['porcentaje_label'] }}%</td>
                    <td class="text-end">{{ $money($row['monto_beca']) }}</td>
                    <td class="text-end">{{ $money($row['saldo_a_favor']) }}</td>
                    <td class="text-end">{{ $money($row['pago']) }}</td>
                    <td>{{ $row['situacion_label'] }}</td>
                    <td>{{ $row['motivo'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11">No hay becas activas desde octubre para ese año.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<h2 class="h5">Perdieron la beca</h2>
<p class="text-muted">Cada fila es un mes de caída. En el mes marcado, la beca cayó por completo. Pagó es lo abonado en ese cupón.</p>
<div class="table-responsive">
    <table class="table table-sm table-bordered bg-white">
        <thead>
            <tr>
                <th>Nº</th>
                <th>Alumno</th>
                <th>Carrera</th>
                <th>Cuota</th>
                <th class="text-end">Cuota original</th>
                <th class="text-end">% beca</th>
                <th class="text-end">Monto beca</th>
                <th class="text-end">Pagó</th>
                <th>Situación</th>
                <th>Perdió la beca</th>
                <th>Motivo</th>
            </tr>
        </thead>
        <tbody>
            @forelse($perdidas as $row)
                <tr class="{{ $clase($row) }}">
                    <td>{{ $row['nro'] }}</td>
                    <td>{{ $row['alumno'] }}</td>
                    <td>{{ $row['carrera'] }}</td>
                    <td>{{ $row['cuota'] }}</td>
                    <td class="text-end">{{ $money($row['cuota_original']) }}</td>
                    <td class="text-end">{{ $row['porcentaje_label'] }}%</td>
                    <td class="text-end">{{ $money($row['monto_beca']) }}</td>
                    <td class="text-end">{{ $money($row['pago']) }}</td>
                    <td>{{ $row['situacion_label'] }}</td>
                    <td>{{ ! empty($row['perdio_la_beca']) ? 'Sí' : '' }}</td>
                    <td>{{ $row['motivo'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11">Nadie perdió la beca. Hacen falta tres caídas seguidas desde octubre.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
