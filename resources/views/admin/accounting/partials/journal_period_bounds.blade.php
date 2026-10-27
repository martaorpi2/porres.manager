@if(($openPeriods ?? collect())->count() === 1) min="{{ $openPeriods->first()->start_date->toDateString() }}" max="{{ $openPeriods->first()->end_date->toDateString() }}" @endif
