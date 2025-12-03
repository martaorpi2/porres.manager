@php
    $user = backpack_user();
    $canEdit = false;
    
    // role_admin_institucion solo puede editar sus propias calificaciones
    if ($user && $user->hasRole('role_admin_institucion', 'backpack')) {
        $canEdit = $entry->rated_by == $user->id;
    } else {
        // Otros roles pueden editar si tienen acceso
        $canEdit = $crud->hasAccess('update', $entry);
    }
@endphp

@if ($canEdit)
    <a href="{{ url($crud->route.'/'.$entry->getKey().'/edit') }}" bp-button="update" class="btn btn-sm btn-link">
        <i class="la la-edit"></i> <span>{{ trans('backpack::crud.edit') }}</span>
    </a>
@endif

