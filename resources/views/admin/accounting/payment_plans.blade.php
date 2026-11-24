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
    <div class="col-6 col-xl">
        <div class="card plan-metric h-100">
            <div class="card-body">
                <div class="text-muted">Planes activos</div>
                <h3>{{ $active }}</h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="card plan-metric h-100">
            <div class="card-body">
                <div class="text-muted">Planes caídos</div>
                <h3>{{ $fallen }}</h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="card plan-metric h-100">
            <div class="card-body">
                <div class="text-muted">Planes completados</div>
                <h3>{{ $completed }}</h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="card plan-metric h-100">
            <div class="card-body">
                <div class="text-muted">Planes reemplazados</div>
                <h3>{{ $replaced }}</h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="card plan-metric h-100">
            <div class="card-body">
                <div class="text-muted">Cuotas pagadas</div>
                <h3>{{ $paid_installments }} <span class="fs-6 text-muted">de {{ $installments }}</span></h3>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-6 col-xl">
        <div class="card plan-metric h-100">
            <div class="card-body">
                <div class="text-muted">Monto total</div>
                <h3>${{ $money($amount_owed) }}</h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="card plan-metric h-100">
            <div class="card-body">
                <div class="text-muted">Cuota pura a pagar</div>
                <h3>${{ $money($debt) }}</h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="card plan-metric h-100">
            <div class="card-body">
                <div class="text-muted">Interés por mora a pagar</div>
                <h3>${{ $money($late) }}</h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="card plan-metric h-100">
            <div class="card-body">
                <div class="text-muted">Interés financiación a pagar</div>
                <h3>${{ $money($financing) }}</h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="card plan-metric h-100">
            <div class="card-body">
                <div class="text-muted">Saldo</div>
                <h3>${{ $money($pending) }}</h3>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info text-black" role="alert">
    El detalle es un renglón por plan vigente que todavía tiene alguna cuota sin pagar.
    Los importes suman solo las cuotas ya pagadas. La cuota pura es el capital de esas cuotas. La imputación reparte ese capital entre la cuota original y el interés capitalizado del alta.
    La cuota pendiente no entra en estos importes. Los indicadores de arriba siguen mostrando solo lo que falta cobrar.
</div>

<h2 class="h4 mb-2">Detalle</h2>

@if($plans === [])
    <p class="mb-0">No hay planes de pago con cuotas pendientes.</p>
@else
<div class="table-responsive">
    <table id="payment-plans-table" class="table table-sm table-bordered bg-white w-100">
        <thead class="table-light">
            <tr>
                <th>Alumno</th>
                <th class="text-center">Cuotas</th>
                <th>Alta</th>
                <th class="text-end">Importe pagado del plan</th>
                <th class="text-end">Cuota pura del plan</th>
                <th class="text-end">Interés por mora del plan</th>
                <th class="text-end">Interés de financiación</th>
                <th class="text-end">Interés del medio de pago</th>
                <th class="text-end">Imputación cuota pura</th>
                <th class="text-end">Imputación interés capitalizado</th>
            </tr>
        </thead>
        <tbody>
            @php
                $sumPaid = 0.0; $sumPure = 0.0; $sumLate = 0.0; $sumFinancing = 0.0;
                $sumMethod = 0.0; $sumImputePure = 0.0; $sumImputeCapitalized = 0.0;
            @endphp
            @foreach($plans as $plan)
                @php
                    $openedOrder = $plan['opened_on'];
                    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $plan['opened_on'], $openedMatch)) {
                        $openedOrder = $openedMatch[3].$openedMatch[2].$openedMatch[1];
                    }
                    $sumPaid += (float) $plan['paid_plan'];
                    $sumPure += (float) $plan['pure_plan'];
                    $sumLate += (float) $plan['late_plan'];
                    $sumFinancing += (float) $plan['financing_plan'];
                    $sumMethod += (float) $plan['method_plan'];
                    $sumImputePure += (float) $plan['impute_pure'];
                    $sumImputeCapitalized += (float) $plan['impute_capitalized'];
                @endphp
                <tr>
                    <td data-order="{{ $plan['student'] }}">{{ $plan['student'] }}</td>
                    <td class="text-center" data-order="{{ $plan['paid'] }}">{{ $plan['paid'] }} / {{ $plan['dues'] }}</td>
                    <td data-order="{{ $openedOrder }}">{{ $plan['opened_on'] }}</td>
                    <td class="text-end" data-order="{{ $plan['paid_plan'] }}">${{ $money($plan['paid_plan']) }}</td>
                    <td class="text-end" data-order="{{ $plan['pure_plan'] }}">${{ $money($plan['pure_plan']) }}</td>
                    <td class="text-end" data-order="{{ $plan['late_plan'] }}">${{ $money($plan['late_plan']) }}</td>
                    <td class="text-end" data-order="{{ $plan['financing_plan'] }}">${{ $money($plan['financing_plan']) }}</td>
                    <td class="text-end" data-order="{{ $plan['method_plan'] }}">${{ $money($plan['method_plan']) }}</td>
                    <td class="text-end" data-order="{{ $plan['impute_pure'] }}">${{ $money($plan['impute_pure']) }}</td>
                    <td class="text-end" data-order="{{ $plan['impute_capitalized'] }}">${{ $money($plan['impute_capitalized']) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="table-light fw-bold">
            <tr>
                <td colspan="3">Total</td>
                <td class="text-end">${{ $money($sumPaid) }}</td>
                <td class="text-end">${{ $money($sumPure) }}</td>
                <td class="text-end">${{ $money($sumLate) }}</td>
                <td class="text-end">${{ $money($sumFinancing) }}</td>
                <td class="text-end">${{ $money($sumMethod) }}</td>
                <td class="text-end">${{ $money($sumImputePure) }}</td>
                <td class="text-end">${{ $money($sumImputeCapitalized) }}</td>
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
