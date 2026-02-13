<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de Entrega - {{ $delivery->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 18px; margin: 0 0 10px 0; color: #17a2b8; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .muted { color: #666; }
        .box { border: 1px solid #ccc; padding: 10px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { padding: 6px 8px; border: 1px solid #ddd; text-align: left; }
        th { background: #f3f3f3; }
        .right { text-align: right; }
        .center { text-align: center; }
        .mb-4 { margin-bottom: 16px; }
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
        function money_format_local($value) { return '$ ' . number_format((float)$value, 2, ',', '.'); }
        function fmt_date($d) { return $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : ''; }
    @endphp
</head>
<body>
    <div class="watermark">porresManager - ISMP</div>
    
    <div class="header">
        <h1>COMPROBANTE DE ENTREGA</h1>
        <div class="muted">N.º {{ $delivery->number }}</div>
    </div>

    <div class="box">
        <div><strong>Fecha de Entrega:</strong> {{ fmt_date($delivery->delivery_date) }}</div>
        <div><strong>Estado:</strong> {{ $delivery->status ?? 'Completada' }}</div>
        <div><strong>Entregado por:</strong> {{ $delivery->deliveredBy ? $delivery->deliveredBy->name : 'N/A' }}</div>
        <div><strong>Recibido por:</strong> {{ $delivery->receivedBy ? $delivery->receivedBy->name : 'N/A' }}</div>
    </div>

    @if($delivery->generalRequest)
    <div class="box">
        <div><strong>Solicitud General:</strong> {{ $delivery->generalRequest->number }}</div>
        <div><strong>Título:</strong> {{ $delivery->generalRequest->title }}</div>
        @if($delivery->generalRequest->area)
        <div><strong>Área:</strong> {{ $delivery->generalRequest->area->name }}</div>
        @endif
        @if($delivery->generalRequest->createdBy)
        <div><strong>Solicitante:</strong> {{ $delivery->generalRequest->createdBy->name }}</div>
        @endif
    </div>
    @endif

    @if($delivery->purchaseRequest)
    <div class="box">
        <div><strong>Solicitud de Compra:</strong> {{ $delivery->purchaseRequest->request_number }}</div>
        <div><strong>Fecha de Solicitud:</strong> {{ fmt_date($delivery->purchaseRequest->request_date) }}</div>
        @if($delivery->purchaseRequest->responsibilityArea)
        <div><strong>Área:</strong> {{ $delivery->purchaseRequest->responsibilityArea->name }}</div>
        @endif
        @if($delivery->purchaseRequest->requestingUser)
        <div><strong>Solicitante:</strong> {{ $delivery->purchaseRequest->requestingUser->name }}</div>
        @endif
    </div>
    @endif

    @if($delivery->reception)
    <div class="box">
        <div><strong>Recepción relacionada:</strong> {{ $delivery->reception->number }}</div>
        <div><strong>Fecha de Recepción:</strong> {{ fmt_date($delivery->reception->date) }}</div>
        @if($delivery->reception->purchase_order)
        <div><strong>Proveedor(es):</strong> {{ $delivery->reception->purchase_order->supplier_display_name }}</div>
        @endif
    </div>
    @endif

    <div class="mb-4"><strong>Detalle de productos entregados</strong></div>
    <table>
        <thead>
            <tr>
                <th class="center">Ítem</th>
                <th>Producto</th>
                <th class="center">Cantidad Entregada</th>
                <th>Observaciones</th>
            </tr>
        </thead>
        <tbody>
            @if($delivery->details && $delivery->details->count() > 0)
                @foreach($delivery->details as $index => $detail)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $detail->product ? $detail->product->name : 'Producto no encontrado' }}</td>
                    <td class="center">{{ $detail->delivered_quantity ?? 0 }}</td>
                    <td>{{ $detail->observations ?? 'Sin observaciones' }}</td>
                </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="4" class="center">No hay productos registrados en esta entrega</td>
                </tr>
            @endif
        </tbody>
    </table>

    @if($delivery->observations)
    <div class="box">
        <div><strong>Observaciones Generales:</strong></div>
        <div style="margin-top: 8px;">{{ $delivery->observations }}</div>
    </div>
    @endif

    <div class="box">
        <div class="muted" style="margin-top: 8px; font-size: 10px;">
            Documento generado el {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html>

