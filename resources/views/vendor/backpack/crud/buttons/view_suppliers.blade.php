@if ($crud->hasAccess('list'))
    <a href="{{ url('admin/supplier?rubro=' . $entry->getKey()) }}" class="btn btn-sm btn-primary" title="Ver proveedores de este rubro">
        <i class="la la-users"></i> Ver Proveedores
    </a>
@endif
