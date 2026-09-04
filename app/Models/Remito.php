<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Remito extends Model
{
    use CrudTrait;

    protected $table = 'remitos';

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function getIdentifyingLabelAttribute(): string
    {
        $supplier = $this->supplier?->company_name ?? 'Proveedor';
        $date = $this->date?->format('d/m/Y');

        return $supplier.' — '.$this->number.($date ? ' ('.$date.')' : '');
    }

    /**
     * @return array<int, string>
     */
    public static function optionsForSelect(): array
    {
        return static::query()
            ->with('supplier')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(function (self $remito) {
                return [$remito->id => $remito->identifying_label];
            })
            ->all();
    }
}
