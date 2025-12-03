@php
    // Verificar que el usuario no sea role_responsable_area
    $user = backpack_user();
    $canAccess = !($user && $user->hasRole('role_responsable_area', 'backpack'));
    
    // Verificar si existe una orden de compra generada
    $purchaseOrder = $entry->purchaseOrders->first();
@endphp

@if ($canAccess && $purchaseOrder)
    {{-- Solo mostrar botón para ver OC si existe --}}
    <a href="{{ backpack_url('purchase-order/' . $purchaseOrder->id . '/show') }}" 
       class="btn btn-sm btn-info" 
       data-toggle="tooltip" 
       title="Ver Orden de Compra Generada">
        <i class="la la-eye"></i> <span>Ver OC</span>
    </a>
@endif

