@once
<style>
    .journal-entry-links {
        margin-top: 0.35rem;
        font-weight: 400;
        font-size: 0.92rem;
    }
    .journal-entry-links p {
        margin-bottom: 0.15rem;
    }
</style>
@endonce
@if($entry->relatedEntries->isNotEmpty() || $entry->citingEntries->isNotEmpty())
    <div class="journal-entry-links mb-3">
        @if($entry->relatedEntries->isNotEmpty())
            <p>Relacionado con
                @foreach($entry->relatedEntries as $related)
                    <a href="{{ backpack_url('accounting-journal/'.$related->id) }}" class="journal-account" title="{{ $related->relationLabel() }}">{{ $related->entry_number }} ({{ $related->date?->format('d/m/Y') }})</a>@if(! $loop->last), @endif
                @endforeach
            </p>
        @endif
        @if($entry->citingEntries->isNotEmpty())
            <p>Citado por
                @foreach($entry->citingEntries as $citing)
                    <a href="{{ backpack_url('accounting-journal/'.$citing->id) }}" class="journal-account" title="{{ $citing->relationLabel() }}">{{ $citing->entry_number }} ({{ $citing->date?->format('d/m/Y') }})</a>@if(! $loop->last), @endif
                @endforeach
            </p>
        @endif
    </div>
@endif
