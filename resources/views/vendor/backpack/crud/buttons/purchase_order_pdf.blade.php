@php
    // Obtener el ID de la entrada actual de múltiples formas
    $entryId = null;
    
    // Método 1: Variable $entry (para list)
    if (isset($entry) && $entry && method_exists($entry, 'getKey')) {
        $entryId = $entry->getKey();
    }
    
    // Método 2: CRUD getCurrentEntry (para show)
    if (!$entryId && isset($crud) && method_exists($crud, 'getCurrentEntry')) {
        $currentEntry = $crud->getCurrentEntry();
        if ($currentEntry && method_exists($currentEntry, 'getKey')) {
            $entryId = $currentEntry->getKey();
        }
    }
    
    // Método 3: Desde la URL (fallback)
    if (!$entryId) {
        $entryId = request()->route('id') ?? request()->route('purchase_order');
    }
@endphp

@if ($entryId)
    <a href="{{ route('purchase-order.pdf', $entryId) }}" 
       class="btn btn-sm btn-info" 
       data-toggle="tooltip" 
       title="Descargar PDF de Orden de Compra">
        <i class="la la-file-pdf"></i> <span>PDF</span>
    </a>
@endif
