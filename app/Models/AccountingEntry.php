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

    public const STATUS_POSTED = 'posted';

    public const STATUS_REVERSED = 'reversed';

    protected $table = 'accounting_entries';

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
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
