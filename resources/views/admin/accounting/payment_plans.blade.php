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
</style>

<div class="row mb-3">
    <div class="col-md-4">
        <div class="card plan-metric">
            <div class="card-body">
                <div class="text-muted">Planes activos</div>
                <h3>{{ $active }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card plan-metric">
            <div class="card-body">
                <div class="text-muted">Cuotas pagadas</div>
                <h3>{{ $paid_installments }} <span class="fs-6 text-muted">de {{ $installments }}</span></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card plan-metric">
            <div class="card-body">
                <div class="text-muted">Saldo por pagar</div>
                <h3>${{ $money($pending) }}</h3>
            </div>
        </div>
    </div>
</div>

<p class="text-muted">
    Un plan figura mientras tenga alguna cuota del plan sin pagar.
    Cuota pura, mora y financiación son los importes con los que se abrió el plan.
    El saldo es la suma de las cuotas del plan que todavía no se cobraron.
</p>

<div class="table-responsive">
    <table class="table table-sm table-bordered bg-white">
        <thead class="table-light">
            <tr>
                <th>Plan</th>
                <th>Alumno</th>
                <th>Alta</th>
                <th class="text-center">Cuotas</th>
                <th class="text-end">Cuota pura</th>
                <th class="text-end">Mora</th>
                <th class="text-end">Financiación</th>
                <th class="text-end">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse($plans as $plan)
                <tr>
                    <td>{{ $plan['id'] }}</td>
                    <td>{{ $plan['student'] }}</td>
                    <td>{{ $plan['opened_on'] }}</td>
                    <td class="text-center">{{ $plan['paid'] }} / {{ $plan['dues'] }}</td>
                    <td class="text-end">${{ $money($plan['debt']) }}</td>
                    <td class="text-end">${{ $money($plan['late']) }}</td>
                    <td class="text-end">${{ $money($plan['financing']) }}</td>
                    <td class="text-end">${{ $money($plan['pending']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">No hay planes de pago con cuotas pendientes.</td>
                </tr>
            @endforelse
        </tbody>
        @if($plans !== [])
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="3">Total</td>
                    <td class="text-center">{{ $paid_installments }} / {{ $installments }}</td>
                    <td class="text-end">${{ $money($debt) }}</td>
                    <td class="text-end">${{ $money($late) }}</td>
                    <td class="text-end">${{ $money($financing) }}</td>
                    <td class="text-end">${{ $money($pending) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
@endsection
