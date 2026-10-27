@php
    $periods = $openPeriods ?? collect();
    $ranges = $periods
        ->map(fn ($period) => $period->start_date->format('d/m/Y').' al '.$period->end_date->format('d/m/Y'))
        ->all();
    $listed = count($ranges) <= 1
        ? ($ranges[0] ?? '')
        : implode(', ', array_slice($ranges, 0, -1)).' y '.$ranges[array_key_last($ranges)];
@endphp
@if($ranges !== [])
    <div class="form-text">Solo se pueden cargar asientos dentro de {{ count($ranges) === 1 ? 'el período abierto' : 'los períodos abiertos' }}: {{ $listed }}.</div>
@endif
