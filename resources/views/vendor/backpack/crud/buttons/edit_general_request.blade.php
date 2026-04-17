@php
    $user = backpack_user();
    $canEdit = false;
    
    // Verificar si el usuario es administrador del sistema (puede editar cualquier solicitud)
    $isAdmin = $user && $user->hasRole('role_admin_sistema', 'backpack');
    
    // role_admin_institucion solo puede editar sus propias solicitudes
    $isAdminInstitucion = $user && $user->hasRole('role_admin_institucion', 'backpack');
    
    // Solo el creador puede editar, solo si el estado es "creada" y no está convertida a compra
    // Excepción: role_responsable_area puede cambiar el estado de solicitudes de su área
    // Excepción: administradores pueden editar cualquier solicitud
    // Excepción: role_responsable_compras solo puede editar sus propias solicitudes
    $isOwnRequest = $entry->created_by == $user->id;
    $isResponsableArea = $user && $user->hasRole('role_responsable_area', 'backpack');
    $isResponsableCompras = $user && $user->hasRole('role_responsable_compras', 'backpack');
    
    if (!$entry->is_converted) {
        if ($isAdmin) {
            // Los administradores del sistema pueden editar cualquier solicitud
            $canEdit = true;
        } elseif ($isAdminInstitucion) {
            // El administrador del instituto solo puede editar sus propias solicitudes
            if ($isOwnRequest && $entry->status === 'creada') {
                $canEdit = true;
            }
        } elseif ($isResponsableCompras) {
            // El usuario de compras solo puede editar sus propias solicitudes
            if ($isOwnRequest && $entry->status === 'creada') {
                $canEdit = true;
            }
        } elseif ($entry->isCreatedByOrNominatedRequester($user->id)) {
            // Creador o solicitante nominado (p. ej. compras registró a nombre del usuario)
            if ($entry->status === 'creada') {
                $canEdit = true;
            }
        }
    }
@endphp

@if ($canEdit && $crud->hasAccess('update', $entry))
    <a href="{{ url($crud->route.'/'.$entry->getKey().'/edit') }}" bp-button="update" class="btn btn-sm btn-link">
        <i class="la la-edit"></i> <span>{{ trans('backpack::crud.edit') }}</span>
    </a>
@endif

