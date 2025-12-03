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
        $entryId = request()->route('id') ?? request()->route('reception');
    }
@endphp

@if ($entryId)
    <a href="{{ route('reception.pdf', $entryId) }}" 
       class="btn btn-sm" 
       data-toggle="tooltip" 
       title="Descargar Comprobante de Recepción"
       target="_blank"
       style="background-color: #800020; border-color: #800020; color: white !important;">
        <i class="la la-file-pdf" style="color: white !important;"></i> <span style="color: white !important;">PDF</span>
    </a>
@endif

