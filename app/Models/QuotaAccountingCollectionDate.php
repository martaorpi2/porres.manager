<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotaAccountingCollectionDate extends Model
{
    protected $table = 'quota_accounting_collection_dates';

    protected $guarded = ['id'];

    protected $casts = [
        'collected_on' => 'date',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(QuotaAccountingBatch::class, 'quota_accounting_batch_id');
    }
}
