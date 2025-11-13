<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralRequestDetail extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $fillable = [
        'general_request_id',
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
     * Get the general request that owns this detail.
     */
    public function generalRequest()
    {
        return $this->belongsTo(GeneralRequest::class);
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
     * Get the total delivered quantity for this detail.
     */
    public function getDeliveredQuantityAttribute()
    {
        if (!$this->general_request_id || !$this->product_id) {
            return 0;
        }

        // Obtener todas las entregas relacionadas con esta solicitud general
        $deliveries = \App\Models\Delivery::where('general_request_id', $this->general_request_id)
            ->with('details')
            ->get();

        $totalDelivered = 0;
        foreach ($deliveries as $delivery) {
            // Sumar las cantidades entregadas de este producto en esta entrega
            $deliveryDetail = $delivery->details->where('product_id', $this->product_id)->first();
            if ($deliveryDetail) {
                $totalDelivered += $deliveryDetail->delivered_quantity ?? 0;
            }
        }

        return $totalDelivered;
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
