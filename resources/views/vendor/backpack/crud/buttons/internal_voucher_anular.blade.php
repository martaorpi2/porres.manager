@php
    $user = backpack_user();
    $canAnular = $user instanceof \App\Models\User
        && $user->canManageInternalVouchers()
        && $entry->status !== \App\Models\InternalVoucher::STATUS_ANULADO;
@endphp
@if ($canAnular)
    <a href="{{ url($crud->route.'/'.$entry->getKey().'/anular') }}"
       class="btn btn-sm btn-warning"
       data-toggle="tooltip"
       title="Anular comprobante interno">
        <i class="la la-ban"></i> <span>Anular</span>
    </a>
@endif
