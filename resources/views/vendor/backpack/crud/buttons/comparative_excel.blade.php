@if ($crud->hasAccess('show', $entry))
    <a href="{{ route('purchase-request.comparative-excel', $entry->getKey()) }}" 
       class="btn btn-sm btn-success" 
       data-toggle="tooltip" 
       title="Descargar Planilla Comparativa Excel">
        <i class="la la-file-excel"></i> <span>Planilla Excel</span>
    </a>
@endif
