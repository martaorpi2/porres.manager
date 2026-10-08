<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class AccountingEntryLine extends Model
{
    protected $table = 'accounting_entry_lines';

    protected $guarded = ['id'];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $line): void {
            $entry = AccountingEntry::query()->find($line->accounting_entry_id);
            if ($entry && $entry->kind === AccountingEntry::KIND_REVERSAL) {
                return;
            }

            $message = AccountingAccount::groupingUsedMessage([(int) $line->accounting_account_id]);
            if ($message !== null) {
                throw new RuntimeException($message);
            }
        });
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(AccountingEntry::class, 'accounting_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'accounting_account_id');
    }
}
