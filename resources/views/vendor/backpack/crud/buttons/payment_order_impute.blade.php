@php
    $user = backpack_user();
    $canImpute = $user instanceof \App\Models\User
        && $user->canManageSupplierInvoices()
        && $entry->status !== 'Anulada'
        && $entry->purchase_order_id;
@endphp
@if ($canImpute)
    <a href="{{ url($crud->route.'/'.$entry->getKey().'/imputar') }}"
       class="btn btn-sm btn-primary"
       data-toggle="tooltip"
       title="Imputar esta orden de pago a una factura (total o parcial)">
        <i class="la la-link"></i> <span>Imputar a factura</span>
    </a>
@endif
