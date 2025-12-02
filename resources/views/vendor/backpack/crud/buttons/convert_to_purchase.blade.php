@php
    $hasAccess = $crud->hasAccess('show', $entry);
    $notConverted = !$entry->is_converted;
    
    // Verificar si hay productos sin suficiente stock
    $hasInsufficientStock = false;
    if ($entry->details && $entry->details->count() > 0) {
        // Cargar los detalles con los productos si no están cargados
        $entry->load('details.product');
        
        foreach ($entry->details as $detail) {
            if ($detail->product_id) {
                // Calcular stock total disponible para este producto (sumando todas las ubicaciones)
                $stockAvailable = (int) \App\Models\StockLevel::where('product_id', $detail->product_id)->sum('quantity');
                $requestedQuantity = $detail->requested_quantity ?? 0;
                
                // Si al menos un producto no tiene suficiente stock, mostrar el botón
                if ($stockAvailable < $requestedQuantity) {
                    $hasInsufficientStock = true;
                    break;
                }
            }
        }
    } else {
        // Si no tiene detalles, permitir convertir (el usuario puede agregarlos al convertir)
        $hasInsufficientStock = true;
    }
    
    // Solo mostrar el botón si no está convertida, tiene acceso y hay productos sin suficiente stock
    $canConvert = $hasAccess && $notConverted && $hasInsufficientStock;
@endphp

@if ($canConvert)
    <a href="{{ backpack_url('purchase-request/create?converted_from=' . $entry->getKey()) }}" 
       class="btn btn-sm btn-success" 
       data-toggle="tooltip" 
       title="Convertir a Solicitud de Compra">
        <i class="la la-exchange-alt"></i> <span>Convertir</span>
    </a>
@endif
