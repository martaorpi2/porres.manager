<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountingAccount extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $table = 'accounting_accounts';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function suppliers()
    {
        return $this->hasMany(Supplier::class);
    }

    public function invoices()
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    /**
     * @return array<int, string>
     */
    public static function optionsForSelect(?int $includeId = null): array
    {
        return static::query()
            ->where(function ($query) use ($includeId) {
                $query->where('is_active', true);
                if ($includeId) {
                    $query->orWhere('id', $includeId);
                }
            })
            ->orderBy('code')
            ->get()
            ->mapWithKeys(function (self $account) {
                return [$account->id => $account->identifying_label];
            })
            ->all();
    }

    public function getIdentifyingLabelAttribute(): string
    {
        $code = trim((string) $this->code);
        $name = trim((string) $this->name);

        if ($code === '') {
            return $name;
        }

        if ($name === '') {
            return $code;
        }

        return $code.' - '.$name;
    }
}
