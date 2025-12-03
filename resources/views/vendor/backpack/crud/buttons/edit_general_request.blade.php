@php
    $user = backpack_user();
    $canEdit = false;
    
    // Verificar si el usuario es administrador (puede editar cualquier solicitud)
    $adminRoles = ['role_admin_sistema', 'role_admin_institucion', 'role_responsable_compras'];
    $isAdmin = false;
    foreach ($adminRoles as $role) {
        if ($user && $user->hasRole($role, 'backpack')) {
            $isAdmin = true;
            break;
        }
    }
    
    // Solo el creador puede editar, solo si el estado es "creada" y no está convertida a compra
    // Excepción: role_responsable_area puede cambiar el estado de solicitudes de su área
    // Excepción: administradores pueden editar cualquier solicitud
    $isOwnRequest = $entry->created_by == $user->id;
    $isResponsableArea = $user && $user->hasRole('role_responsable_area', 'backpack');
    
    if (!$entry->is_converted) {
        if ($isAdmin) {
            // Los administradores pueden editar cualquier solicitud
            $canEdit = true;
        } elseif ($isOwnRequest) {
            // Todos los usuarios (incluyendo responsables de área) solo pueden editar sus propias solicitudes
            // y solo si el estado es "creada"
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

