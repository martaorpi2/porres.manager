<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryDetail extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $fillable = [
        'delivery_id',
        'product_id',
        'delivered_quantity',
        'observations',
    ];

    /**
     * Get the delivery that owns this detail.
     */
    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    /**
     * Get the product for this detail.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
