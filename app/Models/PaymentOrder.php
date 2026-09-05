<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'total_amount' => 'decimal:2',
    ];

    protected $attributes = [
        'billing_kind' => 'normal',
    ];

    protected static function booted(): void
    {
        static::created(function (self $paymentOrder): void {
            if (!$paymentOrder->purchase_order_id) {
                return;
            }
            $purchaseOrder = PurchaseOrder::query()->find($paymentOrder->purchase_order_id);
            if ($purchaseOrder && $purchaseOrder->status === 'Pendiente') {
                $purchaseOrder->update(['status' => 'Aprobada']);
            }
        });
    }

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

    /**
     * Líneas de pago (cuotas / parcialidades) en tabla op_details.
     */
    public function opDetails()
    {
        return $this->hasMany(OpDetail::class, 'payment_order_id')->orderBy('id');
    }

    public function supplierInvoices(): BelongsToMany
    {
        return $this->belongsToMany(SupplierInvoice::class, 'payment_order_invoice', 'payment_order_id', 'supplier_invoice_id')
            ->withPivot(['amount_applied', 'imputed_at'])
            ->withTimestamps();
    }

    public function internalVouchers()
    {
        return $this->hasMany(InternalVoucher::class, 'payment_order_id')->orderByDesc('id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function fundMovements()
    {
        return $this->hasMany(FundMovement::class)->orderByDesc('id');
    }

    /**
     * Proveedor de la OP: el indicado en la cabecera o, si no, el de la orden de compra.
     */
    public function resolvedSupplierId(): ?int
    {
        if ($this->supplier_id) {
            return (int) $this->supplier_id;
        }
        $this->loadMissing('purchase_order');
        if ($this->purchase_order?->supplier_id) {
            return (int) $this->purchase_order->supplier_id;
        }

        return null;
    }

    public function resolvedSupplierName(): string
    {
        $this->loadMissing(['supplier', 'purchase_order.supplier']);
        if ($this->supplier) {
            return (string) $this->supplier->company_name;
        }

        return (string) ($this->purchase_order?->supplier_display_name ?? '—');
    }

    public function imputationAccount()
    {
        return $this->belongsTo(AccountingAccount::class, 'imputation_account_id');
    }

    public function fundsAccount()
    {
        return $this->belongsTo(AccountingAccount::class, 'funds_account_id');
    }

    public function accountingEntries()
    {
        return $this->morphMany(AccountingEntry::class, 'source')->orderByDesc('id');
    }

    public function isAnticipo(): bool
    {
        return $this->billing_kind === 'anticipo';
    }

    public function isAnnulled(): bool
    {
        return $this->status === 'Anulada';
    }

    /**
     * Pago efectivamente realizado (para resumen en dashboard).
     * No cuenta como pagada si solo está aprobada o la fecha de pago es futura.
     */
    public function isDashboardPaymentCompleted(): bool
    {
        if ($this->status === 'Anulada' || $this->status !== 'Ejecutada') {
            return false;
        }
        if (!$this->payment_date) {
            return true;
        }
        $paymentDay = $this->payment_date instanceof CarbonInterface
            ? $this->payment_date->copy()->startOfDay()
            : \Carbon\Carbon::parse($this->payment_date)->startOfDay();

        return !$paymentDay->isAfter(now()->startOfDay());
    }

    /**
     * Etiqueta simplificada para el dashboard (Pendiente / Completada / Anulada).
     */
    public function getDashboardPaymentStatusLabelAttribute(): string
    {
        if ($this->status === 'Anulada') {
            return 'Anulada';
        }
        if ($this->isDashboardPaymentCompleted()) {
            return 'Completada';
        }

        return 'Pendiente';
    }

    /**
     * Sufijo de clase CSS (.status-*) alineado con dashboard.blade.php.
     */
    public function getDashboardPaymentStatusCssSuffixAttribute(): string
    {
        return match ($this->dashboard_payment_status_label) {
            'Completada' => 'completada',
            'Anulada' => 'anulada',
            default => 'pendiente',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * Órdenes de pago que aún no figuran como pagadas en el dashboard
     * (incluye Aprobada y Ejecutada con fecha de pago futura).
     */
    public function scopeDashboardPendingPayment($query)
    {
        return $query->where('status', '!=', 'Anulada')
            ->where(function ($q) {
                $q->where('status', '!=', 'Ejecutada')
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'Ejecutada')
                            ->whereNotNull('payment_date')
                            ->whereDate('payment_date', '>', now());
                    });
            });
    }

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

    public function setPaymentDateAttribute($value): void
    {
        $this->attributes['payment_date'] = ($value === '' || $value === null) ? null : $value;
    }
}
