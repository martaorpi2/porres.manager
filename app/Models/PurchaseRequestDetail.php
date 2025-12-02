<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequestDetail extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $fillable = [
        'purchase_request_id',
        'product_id',
        'requested_quantity',
        'specifications',
        'justification',
        'estimated_unit_price',
        'estimated_total',
        'status',
    ];

    protected $casts = [
        'estimated_unit_price' => 'decimal:2',
        'estimated_total' => 'decimal:2',
    ];

    /**
     * Get the purchase request that owns this detail.
     */
    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    /**
     * Get the product for this detail.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calculate the estimated total based on quantity and unit price.
     */
    public function calculateEstimatedTotal()
    {
        if ($this->estimated_unit_price && $this->requested_quantity) {
            $this->estimated_total = $this->estimated_unit_price * $this->requested_quantity;
        }
    }

    /**
     * Get the total received quantity from receptions for this detail.
     * Para solicitudes de compra, la cantidad recibida viene de las recepciones de las órdenes de compra.
     */
    public function getDeliveredQuantityAttribute()
    {
        if (!$this->purchase_request_id || !$this->product_id) {
            return 0;
        }

        // Obtener todas las órdenes de compra relacionadas con esta solicitud de compra
        $purchaseOrders = \App\Models\PurchaseOrder::where('purchase_request_id', $this->purchase_request_id)
            ->with(['receptions', 'details.input'])
            ->get();

        $totalReceived = 0;
        
        foreach ($purchaseOrders as $purchaseOrder) {
            // Solo contar recepciones que estén conforme
            $receptions = $purchaseOrder->receptions->where('according', 'Si');
            
            if ($receptions->isEmpty()) {
                continue;
            }
            
            // Para cada recepción conforme, obtener la cantidad del producto desde los detalles de la orden
            foreach ($purchaseOrder->details as $orderDetail) {
                if (!$orderDetail->input) {
                    continue;
                }
                
                // Buscar el producto correspondiente al input
                $product = \App\Models\Product::where('name', $orderDetail->input->name)->first();
                
                if ($product && $product->id == $this->product_id) {
                    // Si hay al menos una recepción conforme, sumar la cantidad de la orden
                    $totalReceived += $orderDetail->quantity ?? 0;
                    break; // Solo contar una vez por orden
                }
            }
        }

        return $totalReceived;
    }

    /**
     * Check if this detail is fully delivered.
     */
    public function getIsFullyDeliveredAttribute()
    {
        return $this->delivered_quantity >= $this->requested_quantity;
    }

    /**
     * Get delivery status text.
     */
    public function getDeliveryStatusAttribute()
    {
        $delivered = $this->delivered_quantity;
        $requested = $this->requested_quantity;

        if ($delivered == 0) {
            return 'Pendiente';
        } elseif ($delivered >= $requested) {
            return 'Completo';
        } else {
            return 'Parcial';
        }
    }
}
