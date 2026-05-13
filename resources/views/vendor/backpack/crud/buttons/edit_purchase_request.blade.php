@php
    $user = backpack_user();
    $canEdit = false;

    // role_admin_sistema y sector de compras (o administradora si no hay rol compras) pueden editar cualquier solicitud
    $isAdminSistema = $user && $user->hasRole('role_admin_sistema', 'backpack');
    $isResponsableComprasEffective = $user && $user->effectivelyHasResponsableComprasRole();
    
    // role_admin_institucion solo puede editar sus propias solicitudes
    $isAdminInstitucion = $user && $user->hasRole('role_admin_institucion', 'backpack');

    $isActingCreator = $user && $entry->isActingAsCreatingUser((int) $user->id);

    if ($isAdminSistema || $isResponsableComprasEffective) {
        // Administradores del sistema y sector de compras (o administradora centralizando compras) pueden editar cualquier solicitud
        $canEdit = true;
    } elseif ($isAdminInstitucion) {
        // El administrador del instituto solo puede editar solicitudes que él registró
        if ($isActingCreator) {
            $canEdit = true;
        }
    } elseif ($isActingCreator && $entry->status === 'Pendiente') {
        // Otros usuarios: solicitudes que registraron en el sistema, mientras estén pendientes
        $canEdit = true;
    }
@endphp

@if ($canEdit && $crud->hasAccess('update', $entry))
    <a href="{{ url($crud->route.'/'.$entry->getKey().'/edit') }}" bp-button="update" class="btn btn-sm btn-link">
        <i class="la la-edit"></i> <span>{{ trans('backpack::crud.edit') }}</span>
    </a>
@endif

