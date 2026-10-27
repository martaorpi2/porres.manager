<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Carbon\Carbon;
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

    public static function entryDateError(string $date): ?string
    {
        $periods = static::query()->orderBy('start_date')->orderBy('id')->get();
        if ($periods->isEmpty()) {
            return null;
        }

        $day = Carbon::parse($date)->toDateString();
        $open = $periods->contains(function (self $period) use ($day) {
            return $day >= $period->start_date->toDateString() && $day <= $period->end_date->toDateString();
        });
        if ($open) {
            return null;
        }

        $ranges = $periods
            ->map(fn (self $period) => $period->start_date->format('d/m/Y').' al '.$period->end_date->format('d/m/Y'))
            ->all();
        $listed = count($ranges) === 1
            ? $ranges[0]
            : implode(', ', array_slice($ranges, 0, -1)).' y '.$ranges[array_key_last($ranges)];

        return 'La fecha está fuera '.
            (count($ranges) === 1 ? 'del período abierto' : 'de los períodos abiertos').
            ' ('.$listed.').';
    }

    public function scopeOverlapping(Builder $query, string $startDate, string $endDate, ?int $ignoreId = null): Builder
    {
        return $query
            ->when($ignoreId, fn (Builder $inner) => $inner->where('id', '!=', $ignoreId))
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate);
    }
}
