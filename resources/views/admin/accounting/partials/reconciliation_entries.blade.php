@php
    use App\Models\AccountingEntry;
@endphp
@if($missingCollection && $entries->isNotEmpty())
    <p class="mb-3">No hay asiento de cobranza para esta fecha.</p>
@endif
@forelse($entries as $entry)
    @php
        $coupons = (int) ($orderCounts[$entry->id] ?? 0);
        $isCollection = $entry->kind === AccountingEntry::KIND_QUOTA_COLLECTION;
    @endphp
    <p class="mb-1"><strong>{{ $isCollection ? 'Cobranza' : 'Acreditación' }}</strong></p>
    @include('admin.accounting.partials.journal_entry', ['entry' => $entry, 'heading' => true])
    @if($coupons > 0)
        <button type="button" class="btn btn-sm mb-3" style="background:#871f1f;color:#fff;" data-coupons-url="{{ backpack_url('accounting-journal/'.$entry->id.'/coupons') }}">Ver cupones</button>
        <div class="reconciliation-coupons mb-3" hidden></div>
    @elseif($isCollection)
        <p class="mb-3">Este asiento de cobranza no tiene cupones asociados.</p>
    @elseif(!$missingCollection)
        <p class="mb-3">Este asiento no guarda cupón por cupón. El detalle de los cupones cobrados está en el asiento de cobranza.</p>
    @endif
@empty
    <p class="mb-0">{{ $missingCollection ? 'No hay asiento de cobranza para esta fecha.' : 'No hay un asiento asociado a esta alerta.' }}</p>
@endforelse
@if($excluded)
    <h6 class="mt-2">Cupón que no entró al asiento</h6>
    @include('admin.accounting.partials.daily_payment_orders', $excluded)
@endif
