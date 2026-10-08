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
        'is_grouping' => 'boolean',
    ];

    public function suppliers()
    {
        return $this->hasMany(Supplier::class);
    }

    public function invoices()
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function internalVouchers()
    {
        return $this->hasMany(InternalVoucher::class);
    }

    public function imputationPaymentOrders()
    {
        return $this->hasMany(PaymentOrder::class, 'imputation_account_id');
    }

    public function fundsPaymentOrders()
    {
        return $this->hasMany(PaymentOrder::class, 'funds_account_id');
    }

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            'activo' => 'Activo (caja, banco, bienes de uso)',
            'pasivo' => 'Pasivo',
            'patrimonio' => 'Patrimonio neto',
            'ingreso' => 'Ingreso',
            'gasto' => 'Gasto',
            'egreso' => 'Egreso',
        ];
    }

    public static function defaultBalanceNature(?string $accountType): ?string
    {
        return match ($accountType) {
            'activo', 'egreso' => 'deudor',
            'pasivo', 'patrimonio', 'ingreso', 'gasto' => 'acreedor',
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function balanceNatureByType(): array
    {
        $map = [];
        foreach (array_keys(self::typeOptions()) as $type) {
            $nature = self::defaultBalanceNature($type);
            if ($nature !== null) {
                $map[$type] = $nature;
            }
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    public static function balanceNatureOptions(): array
    {
        return [
            'deudor' => 'Deudor',
            'acreedor' => 'Acreedor',
        ];
    }

    public function getBalanceNatureLabelAttribute(): string
    {
        $options = self::balanceNatureOptions();

        return $options[$this->balance_nature] ?? '';
    }

    public function getTypeLabelAttribute(): string
    {
        $types = self::typeOptions();

        return $types[$this->account_type] ?? ($this->account_type ?: '—');
    }

    public static function chartIsLoaded(): bool
    {
        return static::query()->where('is_active', true)->where('is_grouping', false)->exists();
    }

    /**
     * @param  list<int>  $ids
     */
    public static function groupingUsedMessage(array $ids): ?string
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return null;
        }

        $labels = static::query()
            ->whereIn('id', $ids)
            ->where('is_grouping', true)
            ->orderBy('code')
            ->get()
            ->map(fn (self $account) => $account->identifying_label)
            ->all();

        if ($labels === []) {
            return null;
        }

        $subject = count($labels) === 1
            ? $labels[0].' es un rubro o subrubro'
            : implode(', ', $labels).' son rubros o subrubros';

        return $subject.'. El asiento solo puede usar cuentas.';
    }

    /**
     * @return array<int, string>
     */
    public static function optionsForSelect(?int $includeId = null): array
    {
        return static::query()
            ->where(function ($query) use ($includeId) {
                $query->where('is_active', true)->where('is_grouping', false);
                if ($includeId) {
                    $query->orWhere(function ($inner) use ($includeId) {
                        $inner->whereKey($includeId)->where('is_grouping', false);
                    });
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
