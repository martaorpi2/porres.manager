<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AccountingPeriod extends Model
{
    use CrudTrait;

    protected $table = 'accounting_periods';

    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function getLabelAttribute(): string
    {
        $range = $this->start_date?->format('d/m/Y').' al '.$this->end_date?->format('d/m/Y');
        $name = trim((string) $this->name);

        return $name !== '' ? $name.' ('.$range.')' : $range;
    }

    public function scopeOverlapping(Builder $query, string $startDate, string $endDate, ?int $ignoreId = null): Builder
    {
        return $query
            ->when($ignoreId, fn (Builder $inner) => $inner->where('id', '!=', $ignoreId))
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate);
    }
}
