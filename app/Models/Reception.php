<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reception extends Model
{
    use CrudTrait;
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'receptions';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    // protected $fillable = [];
    // protected $hidden = [];

    protected $casts = [
        'corroborado_por_arca_at' => 'datetime',
        'comprobante_valido_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function purchase_order()
    {
        return $this->belongsTo(\App\Models\PurchaseOrder::class, 'purchase_order_id');
    }
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'area_manager_id');
    }

    public function corroboradoPorArcaBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'corroborado_por_arca_by_id');
    }

    public function comprobanteValidoBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'comprobante_valido_by_id');
    }

    public function devolutions()
    {
        return $this->hasMany(\App\Models\Devolution::class, 'reception_id');
    }

    public function deliveries()
    {
        return $this->hasMany(\App\Models\Delivery::class, 'reception_id');
    }
    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */
    public function getNumberAttribute()
    {
        return 'REC-' . $this->id;
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
