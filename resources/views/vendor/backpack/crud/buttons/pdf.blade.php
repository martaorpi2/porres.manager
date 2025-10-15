@if ($crud->hasAccess('show', $entry))
    <a href="{{ url('admin/market-rate/' . $entry->getKey() . '/pdf') }}" 
       target="_blank" 
       class="btn btn-sm btn-link" 
       title="Generar PDF">
        <i class="la la-file-pdf-o"></i> <span>PDF</span>
    </a>
@endif