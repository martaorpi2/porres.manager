@php
    // Obtener el ID de la entrada actual de múltiples formas
    $entryId = null;
    $currentEntry = null;
    
    // Método 1: Variable $entry (para list)
    if (isset($entry) && $entry && method_exists($entry, 'getKey')) {
        $currentEntry = $entry;
        $entryId = $entry->getKey();
    }
    
    // Método 2: CRUD getCurrentEntry (para show)
    if (!$currentEntry && isset($crud) && method_exists($crud, 'getCurrentEntry')) {
        $currentEntry = $crud->getCurrentEntry();
        if ($currentEntry && method_exists($currentEntry, 'getKey')) {
            $entryId = $currentEntry->getKey();
        }
    }
    
    // Método 3: Desde la URL (fallback)
    if (!$entryId) {
        $entryId = request()->route('id') ?? request()->route('purchase_request');
        if ($entryId) {
            $currentEntry = \App\Models\PurchaseRequest::with('marketRates')->find($entryId);
        }
    }
    
    // Verificar si hay más de una cotización
    $hasMultipleQuotations = false;
    if ($currentEntry) {
        $currentEntry->load('marketRates');
        $quotationsCount = $currentEntry->marketRates->count();
        $hasMultipleQuotations = $quotationsCount > 1;
    }
@endphp

@if ($entryId && $hasMultipleQuotations)
    <a href="{{ route('purchase-request.comparative-excel', $entryId) }}" 
       class="btn btn-sm btn-success" 
       data-toggle="tooltip" 
       title="Descargar Planilla Comparativa Excel">
        <i class="la la-file-excel"></i> <span>Planilla Comparativa</span>
    </a>
@endif
