<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalVoucher extends Model
{
    use CrudTrait;

    public const TYPE_EGRESO = 'egreso';

    public const TYPE_INGRESO = 'ingreso';

    public const TYPE_TRANSFERENCIA = 'transferencia';

    public const STATUS_PENDIENTE = 'Pendiente';

    public const STATUS_EMITIDO = 'Emitido';

    public const STATUS_ANULADO = 'Anulado';

    protected $table = 'internal_vouchers';

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'annulled_at' => 'datetime',
    ];

    protected $attributes = [
        'currency_code' => 'ARS',
        'status' => self::STATUS_EMITIDO,
        'type' => self::TYPE_EGRESO,
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
    public static function documentTitles(): array
    {
        return [
            self::TYPE_EGRESO => 'Comprobante Interno de Egreso',
            self::TYPE_INGRESO => 'Comprobante Interno de Ingreso',
            self::TYPE_TRANSFERENCIA => 'Comprobante Interno de Transferencia',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function motiveLabels(): array
    {
        return [
            'retiro_banco_caja' => 'Retiro de banco a caja',
            'anticipo_persona' => 'Anticipo a una persona',
            'reintegro_gastos' => 'Reintegro de gastos',
            'entrega_fondos' => 'Entrega de fondos para una actividad',
            'devolucion' => 'Devolución de dinero',
            'transferencia_cuentas' => 'Transferencia entre cuentas',
            'caja_chica' => 'Caja chica',
            'pago_interno' => 'Pago interno',
            'ajuste_contable' => 'Ajuste contable',
            'otro' => 'Otro',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDIENTE => 'Pendiente',
            self::STATUS_EMITIDO => 'Emitido',
        ];
    }

    public static function getNextNumber(): string
    {
        $year = date('Y');
        $prefix = "CI-{$year}-";

        $last = self::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByRaw('CAST(SUBSTRING_INDEX(number, "-", -1) AS UNSIGNED) DESC')
            ->lockForUpdate()
            ->first();

        $nextSeq = 1;
        if ($last && preg_match('/^CI-\d{4}-(\d+)$/', $last->number, $m)) {
            $nextSeq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $nextSeq, 3, '0', STR_PAD_LEFT);
    }

    public static function getNextNumberPreview(): string
    {
        $year = date('Y');
        $prefix = "CI-{$year}-";

        $last = self::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByRaw('CAST(SUBSTRING_INDEX(number, "-", -1) AS UNSIGNED) DESC')
            ->first();

        $nextSeq = 1;
        if ($last && preg_match('/^CI-\d{4}-(\d+)$/', $last->number, $m)) {
            $nextSeq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $nextSeq, 3, '0', STR_PAD_LEFT);
    }

    public function isAnnulled(): bool
    {
        return $this->status === self::STATUS_ANULADO;
    }

    public function getTypeLabelAttribute(): string
    {
        return self::typeLabels()[$this->type] ?? (string) $this->type;
    }

    public function getDocumentTitleAttribute(): string
    {
        return self::documentTitles()[$this->type] ?? 'Comprobante Interno';
    }

    public function getMotiveLabelAttribute(): string
    {
        return self::motiveLabels()[$this->motive] ?? (string) $this->motive;
    }

    public function accountingAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class);
    }

    public function authorizingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorizing_user_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class, 'payment_order_id');
    }

    public function fundMovements()
    {
        return $this->hasMany(FundMovement::class)->orderByDesc('id');
    }

    public function annulledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annulled_by_id');
    }
}
