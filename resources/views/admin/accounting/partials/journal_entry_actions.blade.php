@once
<style>
    .entry-detail-panel {
        margin-top: 0.75rem;
        padding: 0.75rem 0.9rem 0.9rem;
        background: #fff;
        border: 1px solid #e7c4c4;
        border-left: 4px solid #871f1f;
        border-radius: 4px;
    }
    .entry-detail-caption {
        margin-bottom: 0.65rem;
        color: #871f1f;
        font-weight: 700;
    }
    .entry-detail-body {
        display: block;
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
    }
    .entry-detail-body .table-responsive {
        overflow-x: visible;
    }
    .entry-detail-body table {
        width: max-content;
        min-width: 100%;
        margin-bottom: 0;
        table-layout: auto;
    }
    .entry-detail-body th,
    .entry-detail-body td {
        white-space: nowrap;
        width: auto;
        max-width: none;
    }
</style>
@endonce
@if($entry->showsCollectionCoupons() || $entry->showsPaymentPlans())
    <div class="journal-entry-actions">
        <div class="mb-1">
            @if($entry->showsCollectionCoupons())
                <button type="button" class="btn btn-sm mb-2 me-2 mr-2" style="background:#871f1f;color:#fff;" data-coupons-url="{{ backpack_url('accounting-journal/'.$entry->id.'/coupons') }}" data-detail-target="entry-detail-{{ $entry->id }}-coupons" data-closed-label="Ver cupones" data-open-label="Ocultar cupones">Ver cupones</button>
            @endif
            @if($entry->showsPaymentPlans())
                <button type="button" class="btn btn-sm mb-2" style="background:#1e2a4a;color:#fff;" data-coupons-url="{{ backpack_url('accounting-journal/'.$entry->id.'/plans') }}" data-detail-target="entry-detail-{{ $entry->id }}-plans" data-closed-label="Ver planes" data-open-label="Ocultar planes">Ver planes</button>
            @endif
        </div>
        @if($entry->showsCollectionCoupons())
            <div id="entry-detail-{{ $entry->id }}-coupons" class="entry-detail-panel" hidden>
                <p class="entry-detail-caption">Cupones del asiento {{ $entry->entry_number }}</p>
                <div class="entry-detail-body"></div>
            </div>
        @endif
        @if($entry->showsPaymentPlans())
            <div id="entry-detail-{{ $entry->id }}-plans" class="entry-detail-panel" hidden>
                <p class="entry-detail-caption">Planes del asiento {{ $entry->entry_number }}</p>
                <div class="entry-detail-body"></div>
            </div>
        @endif
    </div>
@endif
