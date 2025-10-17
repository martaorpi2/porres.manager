@if ($crud->hasAccess('show'))
    <a href="{{ url($crud->route.'/'.$entry->getKey().'/assign') }}" class="btn btn-sm btn-primary">
        <i class="la la-gift"></i> Asignar Productos
    </a>
@endif
