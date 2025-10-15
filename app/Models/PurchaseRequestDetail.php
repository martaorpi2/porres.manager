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
}
