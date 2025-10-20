<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockLevel extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $fillable = [
        'product_id',
        'location_id',
        'quantity',
        'last_updated_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * Get the product that owns the stock level.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the location that owns the stock level.
     */
    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the user who last updated the stock level.
     */
    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }
}
