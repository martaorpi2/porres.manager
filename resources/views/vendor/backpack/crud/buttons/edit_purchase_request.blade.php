@php
    $user = backpack_user();
    $canEdit = false;

    // role_admin_sistema y role_responsable_compras pueden editar cualquier solicitud
    $isAdminSistema = $user && $user->hasRole('role_admin_sistema', 'backpack');
    $isResponsableCompras = $user && $user->hasRole('role_responsable_compras', 'backpack');
    
    // role_admin_institucion solo puede editar sus propias solicitudes
    $isAdminInstitucion = $user && $user->hasRole('role_admin_institucion', 'backpack');

    $isOwnRequest = $entry->requesting_user_id == $user->id;

    if ($isAdminSistema || $isResponsableCompras) {
        // Administradores del sistema y responsables de compras pueden editar cualquier solicitud
        $canEdit = true;
    } elseif ($isAdminInstitucion) {
        // El administrador del instituto solo puede editar sus propias solicitudes
        if ($isOwnRequest) {
            $canEdit = true;
        }
    } elseif ($isOwnRequest && $entry->status === 'Pendiente') {
        // Otros usuarios solo pueden editar sus propias solicitudes si están pendientes
        $canEdit = true;
    }
@endphp

@if ($canEdit && $crud->hasAccess('update', $entry))
    <a href="{{ url($crud->route.'/'.$entry->getKey().'/edit') }}" bp-button="update" class="btn btn-sm btn-link">
        <i class="la la-edit"></i> <span>{{ trans('backpack::crud.edit') }}</span>
    </a>
@endif

