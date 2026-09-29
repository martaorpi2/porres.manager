<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotaAccountingOrder extends Model
{
    protected $table = 'quota_accounting_orders';

    protected $guarded = ['id'];

    protected $casts = [
        'debtors_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'bank_amount' => 'decimal:2',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(QuotaAccountingBatch::class, 'quota_accounting_batch_id');
    }
}
