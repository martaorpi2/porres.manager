<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden de Compra - {{ $purchaseOrder->number }}</title>
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
        .center { text-align: center; }
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
        function money_format_local($value) { return '$ ' . number_format((float)$value, 2, ',', '.'); }
        function fmt_date($d) { return $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : ''; }
        $paymentConditionsDisplay = $purchaseOrder->paymentConditionsForDocument();
    @endphp
</head>
<body>
    <div class="watermark">porresManager - ISMP</div>
    <div class="header">
        <h1>ORDEN DE COMPRA</h1>
        <div class="muted">N.º {{ $purchaseOrder->number }}</div>
    </div>

    <div class="box">
        <div><strong>Fecha:</strong> {{ fmt_date($purchaseOrder->date) }}</div>
    </div>

    @php
        $suppliers = $purchaseOrder->suppliers;
        $singleSupplier = $suppliers->count() === 1 ? $suppliers->first() : null;
    @endphp
    @if($singleSupplier)
    <div class="box">
        <div><strong>Proveedor:</strong> {{ $singleSupplier->company_name }}</div>
        <div><strong>CUIT:</strong> {{ $singleSupplier->cuit }}</div>
        @if($singleSupplier->address)
        <div><strong>Dirección:</strong> {{ $singleSupplier->address }}</div>
        @endif
    </div>
    @elseif($suppliers->isNotEmpty())
    <div class="box">
        <div><strong>Proveedores:</strong> Esta orden incluye ítems de {{ $suppliers->count() }} proveedor(es). Ver detalle por línea abajo.</div>
    </div>
    @endif

    <div class="box">
        <div><strong>Condiciones de pago:</strong> {{ $paymentConditionsDisplay ?? '—' }}</div>
        <div><strong>Entrega estimada:</strong> {{ $purchaseOrder->estimated_delivery_date ? fmt_date($purchaseOrder->estimated_delivery_date) : ($purchaseOrder->date ? $purchaseOrder->date->copy()->addDays(8)->format('d/m/Y') : 'N/A') }}</div>
    </div>

    <div class="mb-4"><strong>Detalle de productos/servicios</strong></div>
    <table>
        <thead>
            <tr>
                <th class="center">Ítem</th>
                <th>Descripción</th>
                @if($suppliers->count() > 1)
                <th>Proveedor</th>
                @endif
                <th class="center">Cantidad</th>
            </tr>
        </thead>
        <tbody>
            @foreach($purchaseOrder->details as $index => $detail)
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td>{{ $detail->input->name ?? 'Producto' }}</td>
                @if($suppliers->count() > 1)
                <td>{{ $detail->supplier->company_name ?? '—' }}</td>
                @endif
                <td class="center">{{ $detail->quantity }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-box">
        Total de la Orden de Compra: {{ money_format_local($purchaseOrder->total) }}
    </div>

    <div class="box">
        <div><strong>Observaciones:</strong> {{ $purchaseOrder->observations ?? 'Sin observaciones' }}</div>
        <div class="muted" style="margin-top: 8px; font-size: 10px;">
            Documento generado el {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html>
