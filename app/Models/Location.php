<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Get the stock levels for the location.
     */
    public function stockLevels()
    {
        return $this->hasMany(StockLevel::class);
    }

    /**
     * Get the inventory movements for the location.
     */
    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
