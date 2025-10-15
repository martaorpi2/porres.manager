@if ($crud->hasAccess('show', $entry))
    <a href="{{ route('payment-order.pdf', $entry->getKey()) }}" 
       class="btn btn-sm btn-info" 
       data-toggle="tooltip" 
       title="Descargar PDF de Orden de Pago">
        <i class="la la-file-pdf"></i> <span>PDF</span>
    </a>
@endif
