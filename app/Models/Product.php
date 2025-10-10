<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'unit_measurement',
        'minimum_stock',
        'expiration_date',
        'location',
        'utilization_percentage',
        'category_id',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'minimum_stock' => 'integer',
    ];

    /**
     * Get the category that owns the product.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the stock levels for the product.
     */
    public function stockLevels()
    {
        return $this->hasMany(StockLevel::class);
    }

    /**
     * Get the inventory movements for the product.
     */
    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
