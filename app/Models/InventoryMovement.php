<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'location_id',
        'quantity',
        'type',
        'reference',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    /**
     * Get the product that owns the inventory movement.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the location that owns the inventory movement.
     */
    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the user who created the inventory movement.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
