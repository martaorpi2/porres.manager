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

    /**
     * Siguiente correlativo para OC-{área}-{año}-CORRELATIVO-{proveedor} (por letra de área y año civil).
     */
    public static function nextCorrelativeForAreaAndYear(string $areaLetter, int $fullYear): int
    {
        $yy = sprintf('%02d', $fullYear % 100);
        $prefix = 'OC-' . $areaLetter . '-' . $yy . '-';

        $max = 0;
        foreach (static::query()->where('number', 'like', $prefix . '%')->pluck('number') as $num) {
            if (preg_match('/^OC-[A-Z]-\d{2}-(\d+)-\d+$/', $num, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max + 1;
    }

    public static function formatPurchaseOrderNumber(string $areaLetter, int $fullYear, int $correlative, int $supplierPart): string
    {
        $yy = sprintf('%02d', $fullYear % 100);

        return sprintf('OC-%s-%s-%d-%d', $areaLetter, $yy, $correlative, $supplierPart);
    }

    /**
     * Asigna correlativo nuevo y arma el número (un solo proveedor / una sola OC).
     */
    public static function allocateNextFormattedNumber(?ResponsibilityArea $area, int $supplierIndex = 1): string
    {
        $letter = $area ? $area->purchaseOrderLetter() : 'X';
        $year = (int) now()->year;
        $corr = self::nextCorrelativeForAreaAndYear($letter, $year);

        return self::formatPurchaseOrderNumber($letter, $year, $corr, $supplierIndex);
    }
    /**
     * Marca la OC como Recibida cuando ya hay recepción conforme (regla completa en recepciones.according).
     * El dashboard y los contadores usan la columna status; sin esto quedaría en Pendiente aunque exista OP.
     */
    public function markAsRecibidaIfHasConformeReception(): void
    {
        if ($this->receptions()->where('according', 'Si')->doesntExist()) {
            return;
        }
        if ($this->getAttribute('status') !== 'Recibida') {
            $this->update(['status' => 'Recibida']);
        }
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
    /**
     * Total de la OC: suma de líneas (cantidad × precio unitario guardado). Si todas las líneas están en 0,
     * se muestra el monto total de la solicitud de compra asociada (sin repartir en ítems).
     */
    public function getTotalAttribute()
    {
        $this->loadMissing('details');
        $fromLines = (float) $this->details->sum(function (PurchaseOrderDetail $d) {
            return (float) $d->quantity * (float) ($d->getAttributes()['unit_price'] ?? 0);
        });
        if ($fromLines > 0) {
            return $fromLines;
        }
        if ($this->purchase_request_id) {
            $this->loadMissing('purchaseRequest');
            if ($this->purchaseRequest) {
                return $this->fallbackTotalWhenLineAmountsAreZero();
            }
        }

        return 0.0;
    }

    /**
     * Cuando no hay importes en líneas: una sola OC usa el total de la solicitud; si hay varias OC (varios proveedores),
     * se intenta el total de la cotización de ese proveedor para no repetir el monto global en cada orden.
     */
    protected function fallbackTotalWhenLineAmountsAreZero(): float
    {
        $pr = $this->purchaseRequest;
        if (!$pr) {
            return 0.0;
        }
        $orderCount = static::query()->where('purchase_request_id', $pr->id)->count();
        if ($orderCount <= 1) {
            return (float) ($pr->total_amount ?? 0);
        }
        if ($this->supplier_id) {
            $mr = MarketRate::query()
                ->where('purchase_request_id', $pr->id)
                ->where('supplier_id', $this->supplier_id)
                ->first();
            if ($mr) {
                $fromMr = (float) ($mr->total_amount ?? 0);
                if ($fromMr > 0) {
                    return $fromMr;
                }
                $mr->loadMissing('quoteDetails');
                $sumLines = (float) $mr->quoteDetails->sum(function ($qd) {
                    return (float) ($qd->quantity ?? 0) * (float) ($qd->unit_price ?? 0);
                });
                if ($sumLines > 0) {
                    return $sumLines;
                }
            }
        }

        return 0.0;
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
