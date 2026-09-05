@php
    $inbox = $workInbox ?? null;
    $sectionId = $workInboxSectionId ?? 'work-inbox-section';
    $headerTitle = $workInboxHeaderTitle ?? 'Su bandeja de trabajo';
    $headerSubtitle = $workInboxHeaderSubtitle ?? '';
@endphp
@if ($inbox && count($inbox['items'] ?? []) > 0)
<div class="admin-inbox mb-4" id="{{ $sectionId }}">
    <div class="admin-inbox__header card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3">
                <div>
                    <h2 class="h5 mb-1 text-dark">
                        <i class="la la-tasks text-primary"></i> {{ $headerTitle }}
                    </h2>
                    @if ($headerSubtitle !== '')
                    <p class="text-muted small mb-0">{!! $headerSubtitle !!}</p>
                    @endif
                </div>
                <span class="badge bg-danger fs-6 px-3 py-2">
                    {{ $inbox['total_actionable'] }} ítem(es) pendiente(s)
                </span>
            </div>
        </div>
    </div>

    <div class="row g-3">
        @foreach ($inbox['items'] ?? [] as $item)
            @php
                $count = (int) ($item['count'] ?? 0);
                $variant = $item['variant'] ?? 'primary';
                $purchaseRequests = $item['purchase_requests'] ?? null;
                $supplierInvoices = $item['supplier_invoices'] ?? null;
                $purchaseOrders = $item['purchase_orders'] ?? null;
                $hasRequestList = $purchaseRequests instanceof \Illuminate\Support\Collection
                    ? $purchaseRequests->isNotEmpty()
                    : (is_countable($purchaseRequests) && count($purchaseRequests) > 0);
                $hasInvoiceList = $supplierInvoices instanceof \Illuminate\Support\Collection
                    ? $supplierInvoices->isNotEmpty()
                    : (is_countable($supplierInvoices) && count($supplierInvoices) > 0);
                $hasPurchaseOrderList = $purchaseOrders instanceof \Illuminate\Support\Collection
                    ? $purchaseOrders->isNotEmpty()
                    : (is_countable($purchaseOrders) && count($purchaseOrders) > 0);
                $hasList = $hasRequestList || $hasInvoiceList || $hasPurchaseOrderList;
            @endphp
            <div class="col-md-6 col-xl-4">
                @if ($hasList)
                <div class="admin-inbox__card admin-inbox__card--active admin-inbox__card--with-list card h-100 border shadow-sm text-dark border-{{ $variant }}">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <span class="admin-inbox__icon rounded-circle d-inline-flex align-items-center justify-content-center bg-{{ $variant }} text-white">
                                <i class="{{ $item['icon'] ?? 'la la-arrow-right' }} la-lg"></i>
                            </span>
                            <span class="badge rounded-pill fs-6 bg-{{ $variant }} text-white">
                                {{ $count }}
                            </span>
                        </div>
                        <h3 class="h6 mb-1 text-dark">{{ $item['title'] ?? '' }}</h3>
                        <p class="small mb-0 text-dark">{{ $item['description'] ?? '' }}</p>
                        @if ($hasRequestList)
                            @include('admin.dashboard.inc.admin_inbox_purchase_request_entries', [
                                'purchaseRequests' => $purchaseRequests,
                                'variant' => $variant,
                                'listUrl' => $item['url'] ?? null,
                            ])
                        @endif
                        @if ($hasInvoiceList)
                            @include('admin.dashboard.inc.admin_inbox_supplier_invoice_entries', [
                                'supplierInvoices' => $supplierInvoices,
                                'variant' => $variant,
                                'listUrl' => $item['url'] ?? null,
                            ])
                        @endif
                        @if ($hasPurchaseOrderList)
                            @include('admin.dashboard.inc.admin_inbox_purchase_order_entries', [
                                'purchaseOrders' => $purchaseOrders,
                                'variant' => $variant,
                                'listUrl' => $item['url'] ?? null,
                            ])
                        @endif
                    </div>
                </div>
                @else
                <a href="{{ $item['url'] ?? '#' }}" class="admin-inbox__card admin-inbox__card--active card h-100 text-decoration-none border shadow-sm text-dark border-{{ $variant }}">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <span class="admin-inbox__icon rounded-circle d-inline-flex align-items-center justify-content-center bg-{{ $variant }} text-white">
                                <i class="{{ $item['icon'] ?? 'la la-arrow-right' }} la-lg"></i>
                            </span>
                            <span class="badge rounded-pill fs-6 bg-{{ $variant }} text-white">
                                {{ $count }}
                            </span>
                        </div>
                        <h3 class="h6 mb-1 text-dark">{{ $item['title'] ?? '' }}</h3>
                        <p class="small mb-0 flex-grow-1 text-dark">{{ $item['description'] ?? '' }}</p>
                        <span class="small fw-semibold text-{{ $variant }} mt-2">
                            Ir ahora <i class="la la-angle-right"></i>
                        </span>
                    </div>
                </a>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endif
