@if (! empty($traceSteps))
<aside class="trace-rail">
    <details open>
        <summary>Trazabilidad</summary>
        <div class="trace-rail-list">
            @foreach ($traceSteps as $step)
                <div class="trace-step depth-{{ (int) ($step['depth'] ?? 0) }} {{ ! empty($step['current']) ? 'is-current' : '' }}">
                    <div class="trace-label">{{ $step['label'] }}</div>
                    <a href="{{ $step['url'] }}">{{ $step['code'] }}</a>
                    @if (! empty($step['badges']))
                        <div class="trace-badges">{{ implode(' · ', $step['badges']) }}</div>
                    @endif
                    @if (! empty($step['meta']))
                        <div class="trace-meta">{{ $step['meta'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    </details>
</aside>
<style>
    .trace-rail {
        flex: 0 0 260px;
        width: 260px;
        max-width: 100%;
        font-size: 12px;
        line-height: 1.35;
        color: #212529;
    }
    .trace-rail details {
        border: 1px solid #d8dee6;
        border-radius: 6px;
        background: #fff;
        position: sticky;
        top: 12px;
    }
    .trace-rail summary {
        cursor: pointer;
        font-weight: 600;
        font-size: 13px;
        padding: 8px 10px;
        list-style: none;
    }
    .trace-rail summary::-webkit-details-marker { display: none; }
    .trace-rail-list { padding: 0 8px 8px; }
    .trace-step {
        padding: 6px 0 6px 8px;
        border-left: 2px solid #c5ced8;
        margin-bottom: 2px;
    }
    .trace-step.depth-1 { margin-left: 8px; }
    .trace-step.depth-2 { margin-left: 16px; }
    .trace-step.is-current {
        border-left-color: #198754;
        background: #f3faf5;
    }
    .trace-label {
        color: #6c757d;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .02em;
    }
    .trace-step a {
        font-weight: 600;
        word-break: break-word;
    }
    .trace-badges, .trace-meta {
        color: #495057;
        word-break: break-word;
    }
    @media (max-width: 991.98px) {
        .trace-rail { flex: 1 1 auto; width: 100%; }
        .trace-rail details { position: static; }
    }
    @media print {
        .trace-rail details { position: static; }
    }
</style>
@endif
