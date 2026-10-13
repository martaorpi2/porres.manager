<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotaAccountingBatch extends Model
{
    public const KIND_ACCRUAL = 'accrual';

    public const KIND_GRANT = 'grant';

    public const KIND_COLLECTION = 'collection';

    public const KIND_MP_SETTLEMENT = 'mp_settlement';

    public const KIND_NX_SETTLEMENT = 'nx_settlement';

    public const KIND_SOL_SETTLEMENT = 'sol_settlement';

    public const KIND_QR_SETTLEMENT = 'qr_settlement';

    protected $table = 'quota_accounting_batches';

    protected $guarded = ['id'];

    protected $casts = [
        'entry_date' => 'date',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(AccountingEntry::class, 'accounting_entry_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(QuotaAccountingOrder::class);
    }

    public function collectionDates(): HasMany
    {
        return $this->hasMany(QuotaAccountingCollectionDate::class);
    }
}
