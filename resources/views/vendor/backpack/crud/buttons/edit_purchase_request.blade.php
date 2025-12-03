@php
    $user = backpack_user();
    $canEdit = false;

    $adminRoles = ['role_admin_sistema', 'role_admin_institucion', 'role_responsable_compras'];
    $isAdmin = false;
    foreach ($adminRoles as $role) {
        if ($user && $user->hasRole($role, 'backpack')) {
            $isAdmin = true;
            break;
        }
    }

    $isOwnRequest = $entry->requesting_user_id == $user->id;

    if ($isAdmin) {
        $canEdit = true;
    } elseif ($isOwnRequest && $entry->status === 'Pendiente') {
        $canEdit = true;
    }
@endphp

@if ($canEdit && $crud->hasAccess('update', $entry))
    <a href="{{ url($crud->route.'/'.$entry->getKey().'/edit') }}" bp-button="update" class="btn btn-sm btn-link">
        <i class="la la-edit"></i> <span>{{ trans('backpack::crud.edit') }}</span>
    </a>
@endif

