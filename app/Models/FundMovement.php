<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class FundMovement extends Model
{
    use CrudTrait;

    public const TYPE_EGRESO = 'egreso';

    public const TYPE_INGRESO = 'ingreso';

    public const TYPE_TRANSFERENCIA = 'transferencia';

    public const STATUS_PENDIENTE = 'Pendiente';

    public const STATUS_CONFIRMADO = 'Confirmado';

    public const STATUS_ANULADO = 'Anulado';

    protected $table = 'fund_movements';

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'annulled_at' => 'datetime',
    ];

    protected $attributes = [
        'type' => self::TYPE_EGRESO,
        'status' => self::STATUS_PENDIENTE,
        'currency_code' => 'ARS',
    ];

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_EGRESO => 'Egreso',
            self::TYPE_INGRESO => 'Ingreso',
            self::TYPE_TRANSFERENCIA => 'Transferencia',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDIENTE => 'Pendiente',
            self::STATUS_CONFIRMADO => 'Confirmado',
        ];
    }

    public static function getNextNumber(): string
    {
        $year = date('Y');
        $prefix = "EG-{$year}-";
        $last = self::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByRaw('CAST(SUBSTRING_INDEX(number, "-", -1) AS UNSIGNED) DESC')
            ->lockForUpdate()
            ->first();
        $nextSeq = 1;
        if ($last && preg_match('/^EG-\d{4}-(\d+)$/', $last->number, $m)) {
            $nextSeq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $nextSeq, 3, '0', STR_PAD_LEFT);
    }

    public static function getNextNumberPreview(): string
    {
        $year = date('Y');
        $prefix = "EG-{$year}-";
        $last = self::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByRaw('CAST(SUBSTRING_INDEX(number, "-", -1) AS UNSIGNED) DESC')
            ->first();
        $nextSeq = 1;
        if ($last && preg_match('/^EG-\d{4}-(\d+)$/', $last->number, $m)) {
            $nextSeq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $nextSeq, 3, '0', STR_PAD_LEFT);
    }

    public function isAnnulled(): bool
    {
        return $this->status === self::STATUS_ANULADO;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMADO;
    }

    public function getTypeLabelAttribute(): string
    {
        return self::typeLabels()[$this->type] ?? (string) $this->type;
    }

    public function getOriginLabelAttribute(): string
    {
        if ($this->paymentOrder) {
            return 'OP '.$this->paymentOrder->payment_number;
        }
        if ($this->internalVoucher) {
            return 'CI '.$this->internalVoucher->number;
        }
        if ($this->supplierInvoice) {
            return 'Factura '.$this->supplierInvoice->invoice_number;
        }

        return 'Otro';
    }

    public function fundsAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'funds_account_id');
    }

    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class);
    }

    public function internalVoucher(): BelongsTo
    {
        return $this->belongsTo(InternalVoucher::class);
    }

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function annulledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annulled_by_id');
    }

    public function imputations(): HasMany
    {
        return $this->hasMany(FundMovementImputation::class)->orderBy('id');
    }

    public function accountingEntries(): MorphMany
    {
        return $this->morphMany(AccountingEntry::class, 'source')->orderByDesc('id');
    }
}
