@php
    $user = backpack_user();
    $canAnular = $user && $user->hasRole('role_admin_institucion', 'backpack') && $entry->status !== 'Anulada';
@endphp
@if ($canAnular)
    <a href="{{ url($crud->route.'/'.$entry->getKey().'/anular') }}"
       class="btn btn-sm btn-warning"
       data-toggle="tooltip"
       title="Anular orden de pago (solo administradora)">
        <i class="la la-ban"></i> <span>Anular</span>
    </a>
@endif
