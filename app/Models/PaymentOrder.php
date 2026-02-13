<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentOrder extends Model
{
    use CrudTrait;
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'payment_orders';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    // protected $fillable = [];
    // protected $hidden = [];

    protected $casts = [
        'date' => 'date',
        'payment_date' => 'date',
        'annulled_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * Genera el siguiente número de orden de pago consecutivo para el año actual.
     * Formato: OP-YYYY-NNN (ej. OP-2025-001).
     * Debe llamarse dentro de una transacción para evitar duplicados en concurrencia.
     */
    public static function getNextPaymentNumber(): string
    {
        $year = date('Y');
        $prefix = "OP-{$year}-";

        $last = self::where('payment_number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING_INDEX(payment_number, "-", -1) AS UNSIGNED) DESC')
            ->lockForUpdate()
            ->first();

        $nextSeq = 1;
        if ($last && preg_match('/^OP-\d{4}-(\d+)$/', $last->payment_number, $m)) {
            $nextSeq = (int) $m[1] + 1;
        }

        return $prefix . str_pad((string) $nextSeq, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Obtiene el siguiente número (vista previa) para mostrar en el formulario, sin lock.
     */
    public static function getNextPaymentNumberPreview(): string
    {
        $year = date('Y');
        $prefix = "OP-{$year}-";

        $last = self::where('payment_number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING_INDEX(payment_number, "-", -1) AS UNSIGNED) DESC')
            ->first();

        $nextSeq = 1;
        if ($last && preg_match('/^OP-\d{4}-(\d+)$/', $last->payment_number, $m)) {
            $nextSeq = (int) $m[1] + 1;
        }

        return $prefix . str_pad((string) $nextSeq, 3, '0', STR_PAD_LEFT);
    }

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
        return $this->belongsTo(\App\Models\User::class, 'authorizing_user_id');
    }

    public function annulledBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'annulled_by_id');
    }

    public function isAnnulled(): bool
    {
        return $this->status === 'Anulada';
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

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
