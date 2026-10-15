@forelse($pagosPorDia->sortKeysDesc() as $fechaPago => $pagos)
    @include('admin.accounting.partials.daily_payment_day', [
        'fechaPago' => $fechaPago,
        'pagos' => $pagos,
        'relatedByPlanId' => $relatedByPlanId,
        'vista' => $vista,
    ])
@empty
    <p class="mb-0">No se encontraron esos cupones en el reporte de pagos por día.</p>
@endforelse
