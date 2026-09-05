@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\SupplierInvoice> $supplierInvoices */
    $supplierInvoices = $supplierInvoices ?? collect();
    $variant = $variant ?? 'danger';
    $listUrl = $listUrl ?? null;
@endphp
@if ($supplierInvoices->isNotEmpty())
<ul class="admin-inbox__entries list-group list-group-flush border-top mt-2 mb-0">
    @foreach ($supplierInvoices->take(8) as $invoice)
        <li class="list-group-item px-0 py-2 border-0 border-bottom">
            <a href="{{ backpack_url('supplier-invoice/'.$invoice->id.'/show') }}"
               class="admin-inbox__entry-link d-block text-decoration-none text-dark rounded px-2 py-1">
                <span class="fw-semibold d-block">{{ $invoice->invoice_number }}</span>
                <span class="small text-muted d-flex flex-wrap gap-2 mt-1">
                    <span><i class="la la-truck"></i> {{ $invoice->supplier->company_name ?? '—' }}</span>
                    @if ($invoice->invoice_date)
                    <span><i class="la la-calendar"></i> {{ $invoice->invoice_date->format('d/m/Y') }}</span>
                    @endif
                    <span><i class="la la-clock"></i> {{ $invoice->daysSinceInvoice() }} día(s)</span>
                    <span><i class="la la-money-bill"></i> ${{ number_format($invoice->openBalance(), 2, ',', '.') }}</span>
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
