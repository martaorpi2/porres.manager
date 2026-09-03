@if (isset($pendingApprovalRequests))
@php
    $isLegalPendingHighlight = isset($isRepresentanteLegal) && $isRepresentanteLegal && $pendingApprovalRequests->count() > 0;
    $approvalBorderColor = $isLegalPendingHighlight ? '#dc3545' : '#ffc107';
    $approvalHeaderBg = $isLegalPendingHighlight ? '#f8d7da' : '#fff3cd';
    $approvalTextColor = $isLegalPendingHighlight ? '#842029' : '#856404';
    $approvalCounterBg = $isLegalPendingHighlight ? '#dc3545' : '#ffc107';
    $approvalCounterText = $isLegalPendingHighlight ? '#fff' : '#856404';
@endphp
<div id="pending-approval-section" class="process-step {{ $isLegalPendingHighlight ? 'pending-approval-highlight' : '' }} mb-4" style="border-left: 4px solid {{ $approvalBorderColor }};">
    <div class="process-step-header" style="background-color: {{ $approvalHeaderBg }};">
        <div class="process-step-title">
            <i class="la la-exclamation-triangle process-step-icon" style="color: {{ $approvalTextColor }};"></i>
            <span style="color: {{ $approvalTextColor }}; font-weight: bold;">Solicitudes pendientes de aprobación</span>
        </div>
        <span class="process-step-count" style="background-color: {{ $approvalCounterBg }}; color: {{ $approvalCounterText }};">{{ $pendingApprovalRequests->count() }}</span>
    </div>
    <div class="process-step-content">
        @forelse($pendingApprovalRequests as $purchaseRequest)
            <div class="process-item-card" onclick="window.location='{{ backpack_url('purchase-request/' . $purchaseRequest->id . '/show') }}'" style="border-left: 3px solid {{ $approvalBorderColor }}; cursor: pointer;">
                <div class="process-item-title">
                    {{ $purchaseRequest->request_number }}
                    @if($purchaseRequest->is_direct_purchase)
                        <span class="badge bg-info text-white" style="margin-left: 10px;">Compra directa</span>
                    @else
                        <span class="badge {{ $isLegalPendingHighlight ? 'bg-danger text-white' : 'bg-warning text-dark' }}" style="margin-left: 10px;">Requiere aprobación</span>
                    @endif
                </div>
                <div class="process-item-meta">
                    <span><i class="la la-user"></i> {{ $purchaseRequest->requestingUser?->name ?? 'N/A' }}</span>
                    <span><i class="la la-building"></i> {{ $purchaseRequest->responsibilityArea?->name ?? 'N/A' }}</span>
                </div>
                <div class="process-item-meta">
                    <span><i class="la la-calendar"></i> {{ optional($purchaseRequest->request_date)->format('d/m/Y') ?? 'N/A' }}</span>
                    <span><i class="la la-dollar-sign"></i> ${{ number_format($purchaseRequest->total_amount, 2) }}</span>
                    @php
                        $ageDays = (int) floor($purchaseRequest->age_in_days);
                        $badgeColor = $purchaseRequest->age_badge_color;
                    @endphp
                    <span class="badge bg-{{ $badgeColor }}" style="margin-left: 5px;" title="Antigüedad: {{ $purchaseRequest->age }}">
                        <i class="la la-hourglass-half"></i> {{ $ageDays }} día(s)
                    </span>
                </div>
                <div class="process-item-meta">
                    <span><i class="la la-box"></i> {{ $purchaseRequest->details->count() }} productos</span>
                    @if($purchaseRequest->is_direct_purchase && $purchaseRequest->directPurchaseSupplier)
                        <span><i class="la la-truck"></i> {{ $purchaseRequest->directPurchaseSupplier->company_name }}</span>
                    @endif
                    <span class="process-item-status status-{{ strtolower(str_replace(' ', '-', $purchaseRequest->status)) }}">{{ $purchaseRequest->status }}</span>
                </div>
            </div>
        @empty
            <div class="text-muted">No hay solicitudes pendientes de aprobación</div>
        @endforelse
    </div>
</div>
@endif
