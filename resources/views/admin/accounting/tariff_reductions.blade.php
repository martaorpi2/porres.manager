@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
        <h1 class="mb-0" bp-section="page-heading">Reducciones arancelarias</h1>
    </section>
@endsection

@section('content')
@php
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
@endphp
<style>
    .btn_bordo { background: #881f1f !important; color: #fff !important; }
    tr.fila-cero { background: #e9f7ef; }
    tr.fila-no-aplicada { background: #fff8e6; }
</style>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ backpack_url('accounting-tariff-reductions') }}" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label for="year">Año</label>
                <select name="year" id="year" class="form-control">
                    @foreach($years as $opcion)
                        <option value="{{ $opcion }}" {{ (int) $year === (int) $opcion ? 'selected' : '' }}>{{ $opcion }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="solo_cero" id="solo_cero" value="1" {{ $soloCero ? 'checked' : '' }}>
                    <label class="form-check-label" for="solo_cero">Solo quedaron en $0</label>
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="solo_no_aplicada" id="solo_no_aplicada" value="1" {{ $soloNoAplicada ? 'checked' : '' }}>
                    <label class="form-check-label" for="solo_no_aplicada">Solo beca no cargada en el cupón</label>
                </div>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn_bordo">Ver</button>
                <a href="{{ backpack_url('accounting-tariff-reductions/excel').'?'.http_build_query(request()->query()) }}" class="btn btn-secondary">Excel</a>
            </div>
        </form>
        <p class="text-muted mb-0 mt-3">
            Si un cupón no tiene la beca cargada, por ejemplo septiembre, igual se muestra y la fila queda
            <span style="background:#fff8e6;border:1px solid #e6d59a;padding:0 0.35rem;">en amarillo</span>.
            Queda en $0, con la fila
            <span style="background:#e9f7ef;border:1px solid #b7e0c2;padding:0 0.35rem;">en verde</span>,
            cuando la cuota original menos la beca menos el saldo a favor da cero, o cuando la beca es del 100%.
            La columna Pagó muestra lo que el alumno pagó en cada cuota.
        </p>
    </div>
</div>

<p>
    <strong>{{ $totalCupones }}</strong> cupones
    · <strong>{{ $totalCero }}</strong> en $0
    · <strong>{{ $totalNoAplicada }}</strong> sin la beca cargada en el cupón
</p>

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
                <th class="text-end">Saldo a favor</th>
                <th class="text-end">Pagó</th>
                <th>Motivo</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr class="{{ $row['saldo_cero'] ? 'fila-cero' : ($row['aplicada'] ? '' : 'fila-no-aplicada') }}">
                    <td>{{ $row['nro'] }}</td>
                    <td>{{ $row['alumno'] }}</td>
                    <td>{{ $row['carrera'] }}</td>
                    <td>{{ $row['cuota'] }}</td>
                    <td class="text-end">{{ $money($row['cuota_original']) }}</td>
                    <td class="text-end">{{ $row['porcentaje_label'] }}%</td>
                    <td class="text-end">{{ $money($row['monto_beca']) }}</td>
                    <td class="text-end">{{ $money($row['saldo_a_favor']) }}</td>
                    <td class="text-end">{{ $money($row['pago']) }}</td>
                    <td>{{ $row['motivo'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">No hay reducciones para ese año.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
