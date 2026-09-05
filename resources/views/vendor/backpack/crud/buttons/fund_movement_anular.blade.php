@php
    $user = backpack_user();
    $can = $user instanceof \App\Models\User && $user->canManageFundMovements() && $entry->status !== \App\Models\FundMovement::STATUS_ANULADO;
@endphp
@if ($can)
    <a href="{{ url($crud->route.'/'.$entry->getKey().'/anular') }}" class="btn btn-sm btn-warning" title="Anular egreso">
        <i class="la la-ban"></i> <span>Anular</span>
    </a>
@endif
