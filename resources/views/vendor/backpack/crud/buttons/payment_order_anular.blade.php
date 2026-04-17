@php
    $user = backpack_user();
    $canAnular = $user instanceof \App\Models\User && $user->hasAdministradoraInstitucionRole() && $entry->status !== 'Anulada';
@endphp
@if ($canAnular)
    <a href="{{ url($crud->route.'/'.$entry->getKey().'/anular') }}"
       class="btn btn-sm btn-warning"
       data-toggle="tooltip"
       title="Anular orden de pago (solo administradora)">
        <i class="la la-ban"></i> <span>Anular</span>
    </a>
@endif
