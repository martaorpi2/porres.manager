@if ($crud->hasAccess('list'))
    <a href="{{ url($crud->route.'/'.$entry->getKey().'/pdf') }}" class="btn btn-sm btn-link">
        <i class="la la-file-pdf"></i> PDF
    </a>
@endif
