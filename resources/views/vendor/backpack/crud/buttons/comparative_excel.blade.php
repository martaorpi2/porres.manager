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
        $entryId = request()->route('id') ?? request()->route('purchase_request');
    }
@endphp

@if ($entryId)
    <a href="{{ route('purchase-request.comparative-excel', $entryId) }}" 
       class="btn btn-sm btn-success" 
       data-toggle="tooltip" 
       title="Descargar Planilla Comparativa Excel">
        <i class="la la-file-excel"></i> <span>Planilla Excel</span>
    </a>
@else
    <!-- Debug: Mostrar información si no se encuentra el ID -->
    <div class="alert alert-warning">
        <small>Debug: No se pudo obtener el ID de la entrada</small>
    </div>
@endif
