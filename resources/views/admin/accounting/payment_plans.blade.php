@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
        <h1 class="mb-0" bp-section="page-heading">Planes de pago</h1>
        <a href="{{ backpack_url('accounting-payment-plans/excel') }}" class="btn btn-secondary ms-3">Excel</a>
    </section>
@endsection

@section('content')
@php
    $money = fn ($n) => number_format((float) $n, 2, ',', '.');
@endphp
<style>
    .plan-metric h3 { margin-bottom: 0; }
    .plan-metric .text-muted { font-size: 0.85rem; }
    #payment-plans-table_wrapper .dataTables_filter,
    #payment-plans-table_wrapper .dataTables_length,
    #payment-plans-table_wrapper .dataTables_info,
    #payment-plans-table_wrapper .dataTables_paginate { color: #1e2a4a; }
</style>

<div class="row mb-3">
    <div class="col-6 col-xl-2">
        <div class="card plan-metric">
            <div class="card-body">
                <div class="text-muted">Planes activos</div>
                <h3>{{ $active }}</h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="card plan-metric">
            <div class="card-body">
                <div class="text-muted">Planes caídos</div>
                <h3>{{ $fallen }}</h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="card plan-metric">
            <div class="card-body">
                <div class="text-muted">Planes completados</div>
                <h3>{{ $completed }}</h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="card plan-metric">
            <div class="card-body">
                <div class="text-muted">Planes reemplazados</div>
                <h3>{{ $replaced }}</h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="card plan-metric">
            <div class="card-body">
                <div class="text-muted">Cuotas pagadas</div>
                <h3>{{ $paid_installments }} <span class="fs-6 text-muted">de {{ $installments }}</span></h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="card plan-metric">
            <div class="card-body">
                <div class="text-muted">Saldo por pagar</div>
                <h3>${{ $money($pending) }}</h3>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info text-black" role="alert">
    El detalle lista los planes vigentes que todavía tienen alguna cuota sin pagar. Las tarjetas cuentan los planes caídos por mora, los completados y los reemplazados.
    Período, estado, cuotas y monto son los del listado de planes de ePorres.
    Cuota pura, interés por mora e interés de financiación muestran solo lo que todavía se debe.
    El saldo es la suma de las cuotas del plan que todavía no se cobraron.
</div>

@if($plans === [])
    <p class="mb-0">No hay planes de pago con cuotas pendientes.</p>
@else
<div class="table-responsive">
    <table id="payment-plans-table" class="table table-sm table-bordered bg-white w-100">
        <thead class="table-light">
            <tr>
                <th>Alumno</th>
                <th>Período</th>
                <th>Estado</th>
                <th class="text-center">Cuotas</th>
                <th class="text-end">Monto</th>
                <th>Alta</th>
                <th class="text-center">Pagadas</th>
                <th class="text-end">Cuota pura</th>
                <th class="text-end">Interés por mora</th>
                <th class="text-end">Interés financiación</th>
                <th class="text-end">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @foreach($plans as $plan)
                @php
                    $openedOrder = $plan['opened_on'];
                    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $plan['opened_on'], $openedMatch)) {
                        $openedOrder = $openedMatch[3].$openedMatch[2].$openedMatch[1];
                    }
                @endphp
                <tr>
                    <td data-order="{{ $plan['student'] }}">{{ $plan['student'] }}</td>
                    <td>{{ $plan['period'] }}</td>
                    <td>{{ $plan['status'] }}</td>
                    <td class="text-center" data-order="{{ $plan['agreed_dues'] }}">{{ $plan['agreed_dues'] }}</td>
                    <td class="text-end" data-order="{{ $plan['amount_owed'] }}">${{ $money($plan['amount_owed']) }}</td>
                    <td data-order="{{ $openedOrder }}">{{ $plan['opened_on'] }}</td>
                    <td class="text-center" data-order="{{ $plan['paid'] }}">{{ $plan['paid'] }} / {{ $plan['dues'] }}</td>
                    <td class="text-end" data-order="{{ $plan['debt'] }}">${{ $money($plan['debt']) }}</td>
                    <td class="text-end" data-order="{{ $plan['late'] }}">${{ $money($plan['late']) }}</td>
                    <td class="text-end" data-order="{{ $plan['financing'] }}">${{ $money($plan['financing']) }}</td>
                    <td class="text-end" data-order="{{ $plan['pending'] }}">${{ $money($plan['pending']) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="table-light fw-bold">
            <tr>
                <td>Total</td>
                <td></td>
                <td></td>
                <td class="text-center">{{ $agreed_dues }}</td>
                <td class="text-end">${{ $money($amount_owed) }}</td>
                <td></td>
                <td class="text-center">{{ $paid_installments }} / {{ $installments }}</td>
                <td class="text-end">${{ $money($debt) }}</td>
                <td class="text-end">${{ $money($late) }}</td>
                <td class="text-end">${{ $money($financing) }}</td>
                <td class="text-end">${{ $money($pending) }}</td>
            </tr>
        </tfoot>
    </table>
</div>
@endif
@endsection

@section('after_styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css">
@endsection

@section('after_scripts')
<script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js"></script>
<script>
    jQuery(function () {
        var table = document.getElementById('payment-plans-table');
        if (!table || !jQuery.fn.DataTable) {
            return;
        }
        jQuery(table).DataTable({
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Todos']],
            order: [[0, 'asc']],
            scrollX: true,
            language: {
                decimal: ',',
                thousands: '.',
                emptyTable: 'No hay planes de pago con cuotas pendientes.',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ planes',
                infoEmpty: 'No hay planes para mostrar',
                infoFiltered: '(filtrados de _MAX_)',
                lengthMenu: 'Mostrar _MENU_',
                search: 'Buscar:',
                zeroRecords: 'No se encontraron planes',
                paginate: {
                    first: 'Primero',
                    last: 'Último',
                    next: 'Siguiente',
                    previous: 'Anterior'
                }
            }
        });
    });
</script>
@endsection
