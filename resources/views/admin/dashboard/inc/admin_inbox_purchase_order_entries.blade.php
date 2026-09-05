@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\PurchaseOrder> $purchaseOrders */
    $purchaseOrders = $purchaseOrders ?? collect();
    $variant = $variant ?? 'warning';
    $listUrl = $listUrl ?? null;
@endphp
@if ($purchaseOrders->isNotEmpty())
<ul class="admin-inbox__entries list-group list-group-flush border-top mt-2 mb-0">
    @foreach ($purchaseOrders->take(8) as $purchaseOrder)
        @php
            $aging = $purchaseOrder->paymentAgingDate();
        @endphp
        <li class="list-group-item px-0 py-2 border-0 border-bottom">
            <a href="{{ backpack_url('purchase-order/'.$purchaseOrder->id.'/show') }}"
               class="admin-inbox__entry-link d-block text-decoration-none text-dark rounded px-2 py-1">
                <span class="fw-semibold d-block">{{ $purchaseOrder->number ?? ('OC #'.$purchaseOrder->id) }}</span>
                <span class="small text-muted d-flex flex-wrap gap-2 mt-1">
                    <span><i class="la la-truck"></i> {{ $purchaseOrder->supplier->company_name ?? '—' }}</span>
                    @if ($aging)
                    <span><i class="la la-calendar"></i> {{ $aging->format('d/m/Y') }}</span>
                    @endif
                    <span><i class="la la-clock"></i> {{ $purchaseOrder->daysSinceIssue() }} día(s)</span>
                </span>
            </a>
        </li>
    @endforeach
</ul>
@if ($listUrl)
<p class="small mb-0 mt-2">
    <a href="{{ $listUrl }}" class="fw-semibold text-{{ $variant }} text-decoration-none">
        Ver listado completo <i class="la la-angle-right"></i>
    </a>
</p>
@endif
@endif
