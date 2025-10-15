@if ($crud->hasAccess('show', $entry))
    <a href="{{ route('purchase-order.pdf', $entry->getKey()) }}" 
       class="btn btn-sm btn-info" 
       data-toggle="tooltip" 
       title="Descargar PDF de Orden de Compra">
        <i class="la la-file-pdf"></i> <span>PDF</span>
    </a>
@endif
