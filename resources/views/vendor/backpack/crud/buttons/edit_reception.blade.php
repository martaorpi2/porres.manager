@php
    $user = backpack_user();
    $canEdit = false;

    if ($entry->isAccordingComplete()) {
        $canEdit = false;
    } elseif ($user && $user->hasRole('role_responsable_compras', 'backpack')) {
        // Si es role_responsable_compras, solo puede editar si creó la recepción
        $canEdit = ($entry->area_manager_id == $user->id);
    } else {
        // Otros roles pueden editar normalmente
        $canEdit = $crud->hasAccess('update', $entry);
    }
@endphp

@if ($canEdit)
    <a href="{{ url($crud->route.'/'.$entry->getKey().'/edit') }}" bp-button="update" class="btn btn-sm btn-link">
        <i class="la la-edit"></i> <span>{{ trans('backpack::crud.edit') }}</span>
    </a>
@endif

