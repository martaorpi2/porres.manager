<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden de Pago {{ $paymentOrder->payment_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 18px; margin: 0 0 10px 0; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .muted { color: #666; }
        .box { border: 1px solid #ccc; padding: 10px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { padding: 6px 8px; border: 1px solid #ddd; text-align: left; }
        th { background: #f3f3f3; }
        .right { text-align: right; }
        .mb-4 { margin-bottom: 16px; }
        .total-box { 
            border: 2px solid #333; 
            padding: 10px; 
            text-align: right; 
            font-weight: bold; 
            font-size: 14px;
            margin: 12px 0;
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 60px;
            color: rgba(0, 0, 0, 0.08);
            font-weight: bold;
            z-index: -1;
            white-space: nowrap;
            pointer-events: none;
        }
    </style>
    @php
        $po = $paymentOrder->purchase_order; // related purchase order
        $suppliers = $po ? $po->suppliers : collect();
        $singleSupplier = $suppliers->count() === 1 ? $suppliers->first() : null;
        function money_format_local($value) { return '$ ' . number_format((float)$value, 2, ',', '.'); }
        function fmt_date($d) { return $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : ''; }
    @endphp
</head>
<body>
    <div class="watermark">porresManager - ISMP</div>
    @if(($paymentOrder->status ?? '') === 'Anulada')
    <div class="watermark" style="color: rgba(200, 0, 0, 0.25); font-size: 72px;">ANULADA</div>
    @endif
    <div class="header">
        <h1>ORDEN DE PAGO</h1>
        <div class="muted">N.º {{ $paymentOrder->payment_number }}</div>
    </div>

    <div class="box">
        <strong>Fecha:</strong> {{ fmt_date($paymentOrder->date) }}
    </div>

    <div class="box">
        <div><strong>Proveedor(es):</strong> {{ $po ? $po->supplier_display_name : 'N/A' }}</div>
        @if($singleSupplier)
        <div><strong>CUIT:</strong> {{ $singleSupplier->cuit }}</div>
        @if($singleSupplier->address ?? null)
        <div><strong>Dirección:</strong> {{ $singleSupplier->address }}</div>
        @endif
        @if(!empty($singleSupplier->cvu) || !empty($singleSupplier->alias))
        <div><strong>CVU/Alias:</strong> {{ implode(' — ', array_filter([$singleSupplier->cvu ?? null, $singleSupplier->alias ?? null])) }}</div>
        @endif
        @elseif($suppliers->isNotEmpty())
        @foreach($suppliers as $sup)
        @if(!empty($sup->cvu) || !empty($sup->alias))
        <div><strong>CVU/Alias {{ $sup->company_name }}:</strong> {{ implode(' — ', array_filter([$sup->cvu ?? null, $sup->alias ?? null])) }}</div>
        @endif
        @endforeach
        @endif
    </div>

    <div class="box">
        <div><strong>Forma de pago:</strong> {{ $paymentOrder->payment_method ?? '—' }}</div>
        <div><strong>Banco:</strong> {{ $paymentOrder->bank ?? '—' }}</div>
        <div><strong>Fecha de pago:</strong> {{ $paymentOrder->payment_date ? fmt_date($paymentOrder->payment_date) : '—' }}</div>
    </div>

    <div class="mb-4"><strong>Aplicación a Órdenes de Compra</strong></div>
    <table>
        <thead>
            <tr>
                <th>OC N.º</th>
                <th>Fecha OC</th>
                <th class="right">Monto OC</th>
                <th class="right">Monto Pagado</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $po?->number }}</td>
                <td>{{ fmt_date($po?->date) }}</td>
                <td class="right">{{ money_format_local($po?->total ?? 0) }}</td>
                <td class="right">{{ money_format_local($paymentOrder->total_amount) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="total-box">
        Total Orden de Pago: {{ money_format_local($paymentOrder->total_amount) }}
    </div>

    @if($paymentOrder->opDetails && $paymentOrder->opDetails->isNotEmpty())
    <div class="mb-4"><strong>Detalle de pagos (cuotas / parcialidades)</strong></div>
    <table>
        <thead>
            <tr>
                <th>Concepto</th>
                <th class="right">Monto</th>
                <th>Forma de pago</th>
                <th>Vencimiento</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($paymentOrder->opDetails as $d)
            <tr>
                <td>{{ \App\Models\OpDetail::conceptLabel($d->concept) }}</td>
                <td class="right">{{ money_format_local($d->amount) }}</td>
                <td>{{ $d->method_payment }}</td>
                <td>{{ $d->expiration_date ? fmt_date($d->expiration_date) : '—' }}</td>
                <td>{{ $d->actual_payment_date ? 'Pagado ' . fmt_date($d->actual_payment_date) : 'Pendiente' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="mb-4"><strong>Imputaciones a facturas de proveedor</strong></div>
    @php
        $invForPdf = $paymentOrder->relationLoaded('supplierInvoices') ? $paymentOrder->supplierInvoices : collect();
    @endphp
    @if($invForPdf->isEmpty())
        <p class="muted">Sin imputaciones registradas desde esta orden de pago.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Factura</th>
                    <th>Fecha fact.</th>
                    <th class="right">Monto imputado</th>
                    <th>Fecha imputación</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invForPdf as $invRow)
                    <tr>
                        <td>{{ $invRow->invoice_number }}</td>
                        <td>{{ fmt_date($invRow->invoice_date) }}</td>
                        <td class="right">{{ money_format_local($invRow->pivot->amount_applied ?? 0) }}</td>
                        <td>{{ $invRow->pivot->imputed_at ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if(($paymentOrder->billing_kind ?? '') === 'anticipo')
            @php
                $imPdf = (float) $invForPdf->sum(fn ($i) => (float) ($i->pivot->amount_applied ?? 0));
                $availPdf = max(0, (float) $paymentOrder->total_amount - $imPdf);
            @endphp
            <p><strong>Anticipo disponible:</strong> {{ money_format_local($availPdf) }}</p>
        @endif
    @endif

    <div class="box">
        <div><strong>Estado:</strong> {{ $paymentOrder->status }}</div>
        @if(($paymentOrder->status ?? '') === 'Anulada' && !empty($paymentOrder->annulment_reason))
        <div class="mb-4"><strong>Motivo de anulación:</strong> {{ $paymentOrder->annulment_reason }}</div>
        <div><strong>Anulada el:</strong> {{ $paymentOrder->annulled_at ? \Carbon\Carbon::parse($paymentOrder->annulled_at)->format('d/m/Y H:i') : '—' }}</div>
        @endif
        <div><strong>Observaciones:</strong> {{ $paymentOrder->observations ?? '' }}</div>
    </div>
</body>
</html>


