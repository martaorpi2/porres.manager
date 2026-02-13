<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderDetail extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $table = 'oc_details';
    protected $guarded = ['id'];

    protected $fillable = [
        'purchase_order_id',
        'supplier_id',
        'input_id',
        'quantity',
        'unit_price',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function supplier()
    {
        return $this->belongsTo(\App\Models\Supplier::class, 'supplier_id');
    }

    public function input()
    {
        return $this->belongsTo(\App\Models\Input::class, 'input_id');
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */
    public function getSubtotalAttribute()
    {
        return $this->quantity * $this->unit_price;
    }
}
