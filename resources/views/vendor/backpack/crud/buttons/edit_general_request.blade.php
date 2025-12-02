@php
    $user = backpack_user();
    $canEdit = false;
    
    // Solo el creador puede editar, solo si el estado es "creada" y no está convertida a compra
    // Excepción: role_responsable_area puede cambiar el estado de solicitudes de su área
    $isOwnRequest = $entry->created_by == $user->id;
    $isResponsableArea = $user && $user->hasRole('role_responsable_area', 'backpack');
    $isPersonal = $user && $user->hasRole('role_personal', 'backpack');
    
    if (!$entry->is_converted) {
        if ($isPersonal) {
            // role_personal solo puede editar sus propias solicitudes con estado "creada"
            if ($isOwnRequest && $entry->status === 'creada') {
                $canEdit = true;
            }
        } elseif ($isResponsableArea) {
            // role_responsable_area puede editar solicitudes de su área o las que creó
            if ($isOwnRequest) {
                // Puede editar las que creó (pero solo si estado es "creada" para edición completa)
                if ($entry->status === 'creada') {
                    $canEdit = true;
                } else {
                    // Puede cambiar el estado aunque no sea "creada"
                    $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('id');
                    if ($entry->area_id && $userAreas->contains($entry->area_id)) {
                        $canEdit = true;
                    }
                }
            } else {
                // Puede cambiar el estado de solicitudes de su área
                $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('id');
                if ($entry->area_id && $userAreas->contains($entry->area_id)) {
                    $canEdit = true;
                }
            }
        } else {
            // Otros roles: solo el creador puede editar y solo si el estado es "creada"
            if ($isOwnRequest && $entry->status === 'creada') {
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

