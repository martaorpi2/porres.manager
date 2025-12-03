@php
    // Verificar que el usuario no sea role_responsable_area
    $user = backpack_user();
    $canAccess = !($user && $user->hasRole('role_responsable_area', 'backpack'));
    
    // Obtener la entrada actual (funciona tanto en list como en show)
    $currentEntry = null;
    $entryId = null;
    
    // Método 1: Variable $entry (para list)
    if (isset($entry) && $entry) {
        $currentEntry = $entry;
        $entryId = $entry->getKey() ?? null;
    }
    
    // Método 2: CRUD getCurrentEntry (para show)
    if (!$currentEntry && isset($crud) && method_exists($crud, 'getCurrentEntry')) {
        $currentEntry = $crud->getCurrentEntry();
        if ($currentEntry) {
            $entryId = $currentEntry->getKey();
        }
    }
    
    // Si no hay entrada, no mostrar botón
    if (!$currentEntry) {
        $canAccess = false;
    } else {
        // Verificar si existe una orden de compra generada
        $purchaseOrder = $currentEntry->purchaseOrders->first();
        
        // Calcular condiciones para mostrar botón de generar
        $totalAmount = $currentEntry->total_amount ?? 0;
        $threshold = 60000;
        $currentEntry->load('marketRates');
        $quotationsCount = $currentEntry->marketRates->count();
        
        // Puede generar si: monto <= 60000 O tiene 3+ cotizaciones
        $canGenerate = ($totalAmount <= $threshold) || ($quotationsCount >= 3);
    }
@endphp

@if ($canAccess && $currentEntry)
    @if ($purchaseOrder)
        {{-- Si ya existe una orden de compra, mostrar botón para verla --}}
        <a href="{{ backpack_url('purchase-order/' . $purchaseOrder->id . '/show') }}" 
           class="btn btn-sm btn-info" 
           data-toggle="tooltip" 
           title="Ver Orden de Compra Generada">
            <i class="la la-eye"></i> <span>Ver OC</span>
        </a>
    @elseif ($canGenerate && $currentEntry->status != 'Completada')
        {{-- Si puede generar y no está completada, mostrar botón para generar --}}
        @if ($entryId)
            <form method="POST" action="{{ route('purchase-request.generate-purchase-order', $entryId) }}" style="display: inline;">
                @csrf
                <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('¿Está seguro de generar la orden de compra?')" data-toggle="tooltip" title="Generar Orden de Compra">
                    <i class="la la-shopping-cart"></i> <span>Generar OC</span>
                </button>
            </form>
        @endif
    @endif
@endif

