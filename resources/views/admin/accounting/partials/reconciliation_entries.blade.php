@php
    use App\Models\AccountingEntry;
@endphp
@if($missingCollection && $entries->isNotEmpty())
    <p class="mb-3">No hay asiento de cobranza para esta fecha.</p>
@endif
@forelse($entries as $entry)
    @php
        $label = match ($entry->kind) {
            AccountingEntry::KIND_QUOTA_PLAN_COLLECTION => 'Cobranza de plan',
            AccountingEntry::KIND_QUOTA_COLLECTION => 'Cobranza',
            default => 'Acreditación',
        };
    @endphp
    <p class="mb-1"><strong>{{ $label }}</strong></p>
    @include('admin.accounting.partials.journal_entry', ['entry' => $entry, 'heading' => true])
@empty
    <p class="mb-0">{{ $missingCollection ? 'No hay asiento de cobranza para esta fecha.' : 'No hay un asiento asociado a esta alerta.' }}</p>
@endforelse
@if($excluded)
    <h6 class="mt-2">Cupón que no entró al asiento</h6>
    @include('admin.accounting.partials.daily_payment_orders', $excluded)
@endif
