<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use CrudTrait;
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'purchase_orders';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    // protected $fillable = [];
    // protected $hidden = [];

    protected $casts = [
        'date' => 'date',
        'issue_date' => 'date',
        'estimated_delivery_date' => 'date',
    ];
    
    protected $fillable = [
        'number',
        'date',
        'issue_date',
        'estimated_delivery_date',
        'payment_conditions',
        'status',
        'supplier_id',
        'authorizing_user_id',
        'purchase_request_id',
        'observations',
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
    public function supplier()
    {
        return $this->belongsTo(\App\Models\Supplier::class, 'supplier_id');
    }
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'authorizing_user_id');
    }

    public function details()
    {
        return $this->hasMany(\App\Models\PurchaseOrderDetail::class, 'purchase_order_id');
    }

    public function receptions()
    {
        return $this->hasMany(\App\Models\Reception::class, 'purchase_order_id');
    }

    public function paymentOrders()
    {
        return $this->hasMany(\App\Models\PaymentOrder::class, 'purchase_order_id');
    }

    public function purchaseRequest()
    {
        return $this->belongsTo(\App\Models\PurchaseRequest::class, 'purchase_request_id');
    }
    /*
    |--------------------------------------------------------------------------
    | MODEL EVENTS
    |--------------------------------------------------------------------------
    */
    /*protected static function booted(): void
    {
        static::creating(function (PurchaseOrder $purchaseOrder): void {
            // Auto-generate sequential number if not provided
            if (empty($purchaseOrder->number)) {
                $purchaseOrder->number = self::generateNextNumber();
            }
        });
    }

    public static function generateNextNumber(): string
    {
        $year = now()->year;
        $prefix = 'OC-' . $year . '-';

        // Find the current max sequence for this year based on the number suffix
        $last = static::query()
            ->where('number', 'like', $prefix . '%')
            ->orderByDesc('number')
            ->value('number');

        $nextSequence = 1;
        if ($last) {
            $parts = explode('-', $last);
            $suffix = end($parts);
            $seq = (int) ltrim($suffix, '0');
            $nextSequence = $seq + 1;
        }

        return $prefix . str_pad((string) $nextSequence, 3, '0', STR_PAD_LEFT);
    }*/
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
    public function getTotalAttribute()
    {
        return $this->details->sum('subtotal');
    }

    /**
     * Proveedores únicos de la orden (desde los detalles). Compatible con órdenes de un solo o varios proveedores.
     */
    public function getSuppliersAttribute()
    {
        $details = $this->relationLoaded('details') ? $this->details : $this->details()->with('supplier')->get();
        $suppliers = $details->pluck('supplier')->filter()->unique('id')->values();
        if ($suppliers->isEmpty() && $this->supplier_id && $this->relationLoaded('supplier')) {
            $suppliers = collect([$this->supplier]);
        } elseif ($suppliers->isEmpty() && $this->supplier_id) {
            $suppliers = collect([\App\Models\Supplier::find($this->supplier_id)])->filter();
        }
        return $suppliers;
    }

    /**
     * Nombre(s) de proveedor para mostrar en listados (un nombre, o "Varios (N)").
     */
    public function getSupplierDisplayNameAttribute()
    {
        $suppliers = $this->suppliers;
        if ($suppliers->isEmpty()) {
            return 'Sin proveedor';
        }
        if ($suppliers->count() === 1) {
            return $suppliers->first()->company_name ?? 'Sin nombre';
        }
        return 'Varios (' . $suppliers->count() . ' proveedores)';
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
