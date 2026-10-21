@php
    use App\Models\AccountingEntry;
@endphp
@if($missingCollection && $entries->isNotEmpty())
    <p class="mb-3">No hay asiento de cobranza para esta fecha.</p>
@endif
@forelse($entries as $entry)
    @php
        $isCollection = in_array($entry->kind, [
            AccountingEntry::KIND_QUOTA_COLLECTION,
            AccountingEntry::KIND_QUOTA_PLAN_COLLECTION,
        ], true);
    @endphp
    <p class="mb-1"><strong>{{ $isCollection ? 'Cobranza' : 'Acreditación' }}</strong></p>
    @include('admin.accounting.partials.journal_entry', ['entry' => $entry, 'heading' => true])
    <button type="button" class="btn btn-sm mb-3" style="background:#871f1f;color:#fff;" data-coupons-url="{{ backpack_url('accounting-journal/'.$entry->id.'/coupons') }}">Ver cupones</button>
    <div class="reconciliation-coupons mb-3" hidden></div>
@empty
    <p class="mb-0">{{ $missingCollection ? 'No hay asiento de cobranza para esta fecha.' : 'No hay un asiento asociado a esta alerta.' }}</p>
@endforelse
@if($excluded)
    <h6 class="mt-2">Cupón que no entró al asiento</h6>
    @include('admin.accounting.partials.daily_payment_orders', $excluded)
@endif
