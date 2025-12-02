@php
    // Verificar que el usuario no sea role_responsable_area
    $user = backpack_user();
    $canAccess = !($user && $user->hasRole('role_responsable_area', 'backpack'));
    
    // Obtener el ID de la entrada
    $entryId = $entry->getKey() ?? null;
    
    // Verificar si existe una orden de compra generada
    $purchaseOrder = $entry->purchaseOrders->first();
    
    // Calcular condiciones para mostrar botón de generar
    $totalAmount = $entry->total_amount ?? 0;
    $threshold = 60000;
    $entry->load('marketRates');
    $quotationsCount = $entry->marketRates->count();
    
    // Puede generar si: monto <= 60000 O tiene 3+ cotizaciones
    $canGenerate = ($totalAmount <= $threshold) || ($quotationsCount >= 3);
@endphp

@if ($canAccess)
    @if ($purchaseOrder)
        {{-- Si ya existe una orden de compra, mostrar botón para verla --}}
        <a href="{{ backpack_url('purchase-order/' . $purchaseOrder->id . '/show') }}" 
           class="btn btn-sm btn-info" 
           data-toggle="tooltip" 
           title="Ver Orden de Compra Generada">
            <i class="la la-eye"></i> <span>Ver OC</span>
        </a>
    @elseif ($canGenerate && $entry->status != 'Completada')
        {{-- Si puede generar y no está completada, mostrar botón para generar --}}
        <a href="{{ backpack_url('purchase-request/' . $entryId . '/show') }}" 
           class="btn btn-sm btn-primary" 
           data-toggle="tooltip" 
           title="Generar Orden de Compra">
            <i class="la la-shopping-cart"></i> <span>Generar OC</span>
        </a>
    @endif
@endif

