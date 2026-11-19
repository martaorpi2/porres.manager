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
    .plan-note {
        background: #cfe2ff;
        color: #000;
        border: 1px solid #9ec5fe;
    }
</style>

<div class="row mb-3">
    <div class="col-sm-6 col-xl-3">
        <div class="card plan-metric">
            <div class="card-body">
                <div class="text-muted">Planes activos</div>
                <h3>{{ $active }}</h3>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card plan-metric">
            <div class="card-body">
                <div class="text-muted">Planes caídos</div>
                <h3>{{ $fallen }}</h3>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card plan-metric">
            <div class="card-body">
                <div class="text-muted">Cuotas pagadas</div>
                <h3>{{ $paid_installments }} <span class="fs-6 text-muted">de {{ $installments }}</span></h3>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card plan-metric">
            <div class="card-body">
                <div class="text-muted">Saldo por pagar</div>
                <h3>${{ $money($pending) }}</h3>
            </div>
        </div>
    </div>
</div>

<div class="alert plan-note" role="alert">
    El detalle lista los planes vigentes que todavía tienen alguna cuota sin pagar. La tarjeta de caídos cuenta los que ePorres marcó como caídos por mora.
    Período, estado, cuotas y monto son los del listado de planes de ePorres. En cada plan se abren las cuotas originales y las cuotas del plan.
    Cuota pura, mora y financiación son los importes con los que se abrió el plan.
    El saldo es la suma de las cuotas del plan que todavía no se cobraron.
</div>

<div class="table-responsive">
    <table class="table table-sm table-bordered bg-white">
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
                <th class="text-end">Mora</th>
                <th class="text-end">Financiación</th>
                <th class="text-end">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse($plans as $plan)
                <tr>
                    <td>{{ $plan['student'] }}</td>
                    <td>{{ $plan['period'] }}</td>
                    <td>{{ $plan['status'] }}</td>
                    <td class="text-center">{{ $plan['agreed_dues'] }}</td>
                    <td class="text-end">${{ $money($plan['amount_owed']) }}</td>
                    <td>{{ $plan['opened_on'] }}</td>
                    <td class="text-center">{{ $plan['paid'] }} / {{ $plan['dues'] }}</td>
                    <td class="text-end">${{ $money($plan['debt']) }}</td>
                    <td class="text-end">${{ $money($plan['late']) }}</td>
                    <td class="text-end">${{ $money($plan['financing']) }}</td>
                    <td class="text-end">${{ $money($plan['pending']) }}</td>
                </tr>
                <tr>
                    <td colspan="11" class="bg-light">
                        <details>
                            <summary>Cuotas originales ({{ count($plan['originals']) }}) y cuotas del plan ({{ count($plan['schedule']) }})</summary>
                            <div class="row mt-2">
                                <div class="col-lg-6">
                                    <p class="mb-1"><strong>Cuotas originales</strong></p>
                                    @include('admin.accounting.partials.payment_plan_orders', ['orders' => $plan['originals']])
                                </div>
                                <div class="col-lg-6">
                                    <p class="mb-1"><strong>Cuotas del plan</strong></p>
                                    @include('admin.accounting.partials.payment_plan_orders', ['orders' => $plan['schedule']])
                                </div>
                            </div>
                        </details>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11">No hay planes de pago con cuotas pendientes.</td>
                </tr>
            @endforelse
        </tbody>
        @if($plans !== [])
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="3">Total</td>
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
        @endif
    </table>
</div>
@endsection
