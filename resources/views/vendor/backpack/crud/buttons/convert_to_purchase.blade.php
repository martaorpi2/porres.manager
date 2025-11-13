@php
    $hasAccess = $crud->hasAccess('show', $entry);
    $notConverted = $entry->status != 'convertida_a_compra';
    // Permitir convertir incluso si no tiene detalles, el usuario puede agregarlos al convertir
    $canConvert = $hasAccess && $notConverted;
@endphp

@if ($canConvert)
    <a href="{{ backpack_url('purchase-request/create?converted_from=' . $entry->getKey()) }}" 
       class="btn btn-sm btn-success" 
       data-toggle="tooltip" 
       title="Convertir a Solicitud de Compra">
        <i class="la la-exchange-alt"></i> <span>Convertir</span>
    </a>
@endif
