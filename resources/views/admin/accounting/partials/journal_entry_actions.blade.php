@if($entry->showsCollectionCoupons() || $entry->showsPaymentPlans())
    <div class="journal-entry-actions mb-2">
        @if($entry->showsCollectionCoupons())
            <button type="button" class="btn btn-sm mb-2 me-2 mr-2" style="background:#871f1f;color:#fff;" data-coupons-url="{{ backpack_url('accounting-journal/'.$entry->id.'/coupons') }}" data-closed-label="Ver cupones" data-open-label="Ocultar cupones">Ver cupones</button>
            <div class="entry-detail-panel mb-3" hidden></div>
        @endif
        @if($entry->showsPaymentPlans())
            <button type="button" class="btn btn-sm mb-2" style="background:#1e2a4a;color:#fff;" data-coupons-url="{{ backpack_url('accounting-journal/'.$entry->id.'/plans') }}" data-closed-label="Ver planes" data-open-label="Ocultar planes">Ver planes</button>
            <div class="entry-detail-panel mb-3" hidden></div>
        @endif
    </div>
@endif
