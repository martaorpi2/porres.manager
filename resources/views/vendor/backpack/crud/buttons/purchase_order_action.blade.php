@php
    // Responsable de área: sin acciones de OC. Representante legal: puede ver OC existente, no generar.
    $user = backpack_user();
    $canAccess = !($user && $user->hasRole('role_responsable_area', 'backpack'));
    $representanteLegalNoGeneraOc = $user && $user->hasRole('role_representante_legal', 'backpack');
    
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
        
        // Verificar si es compra directa autorizada
        $isDirectPurchaseAuthorized = $currentEntry->is_direct_purchase 
                                   && $currentEntry->direct_purchase_authorized_by 
                                   && $currentEntry->direct_purchase_supplier_id
                                   && !$currentEntry->direct_purchase_authorization_rejected;
        
        // Verificar que la solicitud esté aprobada
        $isApproved = $currentEntry->status === 'Aprobada';
        
        // Para compras directas autorizadas, puede generar si está aprobada
        // Para compras normales, puede generar si: (monto <= 60000 O tiene 3+ cotizaciones) Y está aprobada Y tiene cotización seleccionada
        // Para monto > 60000 además cada producto debe estar cotizado en al menos 3 cotizaciones distintas
        if ($isDirectPurchaseAuthorized) {
            $canGenerate = $isApproved;
        } else {
            $hasSelectedQuote = $currentEntry->selected_market_rate_id != null;
            $allProductsHaveThreeQuotations = $totalAmount <= $threshold || $currentEntry->getProductsWithFewerThanThreeQuotations()->isEmpty();
            $canGenerate = $isApproved && (($totalAmount <= $threshold && $hasSelectedQuote) || ($quotationsCount >= 3 && $hasSelectedQuote && $allProductsHaveThreeQuotations));
        }
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
    @elseif ($canGenerate && ! $representanteLegalNoGeneraOc && $currentEntry->status != 'Completada')
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

