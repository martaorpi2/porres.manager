@if ($crud->hasAccess('show', $entry) && $entry->status != 'convertida_a_compra')
    <a href="{{ backpack_url('purchase-request/create?converted_from=' . $entry->getKey()) }}" 
       class="btn btn-sm btn-success" 
       data-toggle="tooltip" 
       title="Convertir a Solicitud de Compra">
        <i class="la la-exchange-alt"></i> <span>Convertir</span>
    </a>
@endif
