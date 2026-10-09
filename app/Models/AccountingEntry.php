<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AccountingEntry extends Model
{
    use CrudTrait;

    public const KIND_OUTFLOW = 'outflow';

    public const KIND_REVERSAL = 'reversal';

    public const KIND_QUOTA_ACCRUAL = 'quota_accrual';

    public const KIND_QUOTA_GRANT = 'quota_grant';

    public const KIND_QUOTA_COLLECTION = 'quota_collection';

    public const KIND_QUOTA_MP_SETTLEMENT = 'quota_mp_settlement';

    public const KIND_QUOTA_NX_SETTLEMENT = 'quota_nx_settlement';

    public const KIND_QUOTA_SOL_SETTLEMENT = 'quota_sol_settlement';

    public const KIND_QUOTA_QR_SETTLEMENT = 'quota_qr_settlement';

    public const STATUS_POSTED = 'posted';

    public const STATUS_REVERSED = 'reversed';

    /**
     * Asientos que arma el libro a partir de ePorres.
     * Egresos de compras y órdenes de pago quedan fuera hasta que se incorporen.
     *
     * @return list<string>
     */
    public static function quotaKinds(): array
    {
        return [
            self::KIND_QUOTA_ACCRUAL,
            self::KIND_QUOTA_GRANT,
            self::KIND_QUOTA_COLLECTION,
            self::KIND_QUOTA_MP_SETTLEMENT,
            self::KIND_QUOTA_NX_SETTLEMENT,
            self::KIND_QUOTA_SOL_SETTLEMENT,
            self::KIND_QUOTA_QR_SETTLEMENT,
        ];
    }

    /**
     * Contrapartida de la cobranza, tomada de la fecha de acreditación del archivo.
     *
     * @return list<string>
     */
    public static function accreditationKinds(): array
    {
        return [
            self::KIND_QUOTA_MP_SETTLEMENT,
            self::KIND_QUOTA_NX_SETTLEMENT,
            self::KIND_QUOTA_SOL_SETTLEMENT,
            self::KIND_QUOTA_QR_SETTLEMENT,
        ];
    }

    protected $table = 'accounting_entries';

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
        'manually_adjusted' => 'boolean',
    ];

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingEntryLine::class)->orderBy('id');
    }

    public function reversedEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_entry_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function getKindLabelAttribute(): string
    {
        return match ($this->kind) {
            self::KIND_QUOTA_ACCRUAL => 'Devengamiento de cuotas',
            self::KIND_QUOTA_GRANT => 'Becas otorgadas',
            self::KIND_QUOTA_COLLECTION => 'Cobranza de cuotas',
            self::KIND_QUOTA_MP_SETTLEMENT => 'Acreditación Mercado Pago',
            self::KIND_QUOTA_NX_SETTLEMENT => 'Acreditación Naranja X',
            self::KIND_QUOTA_SOL_SETTLEMENT => 'Acreditación Sol Pago',
            self::KIND_QUOTA_QR_SETTLEMENT => 'Acreditación QR',
            self::KIND_OUTFLOW => 'Egreso',
            self::KIND_REVERSAL => 'Reverso',
            default => (string) $this->kind,
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_POSTED => 'Registrado',
            self::STATUS_REVERSED => 'Revertido',
            default => (string) $this->status,
        };
    }

    public static function nextEntryNumber(): string
    {
        $year = date('Y');
        $prefix = "AS-{$year}-";

        $last = self::query()
            ->where('entry_number', 'like', $prefix.'%')
            ->orderByRaw('CAST(SUBSTRING_INDEX(entry_number, "-", -1) AS UNSIGNED) DESC')
            ->lockForUpdate()
            ->first();

        $nextSeq = 1;
        if ($last && preg_match('/^AS-\d{4}-(\d+)$/', $last->entry_number, $m)) {
            $nextSeq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $nextSeq, 3, '0', STR_PAD_LEFT);
    }
}
