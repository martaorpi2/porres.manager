@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\PurchaseRequest> $purchaseRequests */
    $purchaseRequests = $purchaseRequests ?? collect();
    $variant = $variant ?? 'primary';
    $listUrl = $listUrl ?? null;
@endphp
@if ($purchaseRequests->isNotEmpty())
<ul class="admin-inbox__entries list-group list-group-flush border-top mt-2 mb-0">
    @foreach ($purchaseRequests as $purchaseRequest)
        <li class="list-group-item px-0 py-2 border-0 border-bottom">
            <a href="{{ backpack_url('purchase-request/'.$purchaseRequest->id.'/show') }}"
               class="admin-inbox__entry-link d-block text-decoration-none text-dark rounded px-2 py-1">
                <span class="fw-semibold d-block">{{ $purchaseRequest->request_number }}</span>
                <span class="small text-muted d-flex flex-wrap gap-2 mt-1">
                    <span><i class="la la-user"></i> {{ $purchaseRequest->requestingUser->name ?? 'N/A' }}</span>
                    <span><i class="la la-building"></i> {{ $purchaseRequest->responsibilityArea->name ?? 'N/A' }}</span>
                    @if ($purchaseRequest->request_date)
                    <span><i class="la la-calendar"></i> {{ $purchaseRequest->request_date->format('d/m/Y') }}</span>
                    @endif
                    <span><i class="la la-box"></i> {{ $purchaseRequest->details_count ?? $purchaseRequest->details->count() }} producto(s)</span>
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
