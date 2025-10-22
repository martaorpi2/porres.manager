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
    </style>
    @php
        function money_format_local($value) { return '$ ' . number_format((float)$value, 2, ',', '.'); }
        function fmt_date($d) { return $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : ''; }
    @endphp
</head>
<body>
    <div class="header">
        <h1>ORDEN DE COMPRA</h1>
        <div class="muted">N.º {{ $purchaseOrder->number }}</div>
    </div>

    <div class="box">
        <div><strong>Fecha:</strong> {{ fmt_date($purchaseOrder->date) }}</div>
        <div><strong>Estado:</strong> {{ $purchaseOrder->status }}</div>
    </div>

    <div class="box">
        <div><strong>Proveedor:</strong> {{ $purchaseOrder->supplier->company_name }}</div>
        <div><strong>CUIT:</strong> {{ $purchaseOrder->supplier->cuit }}</div>
        @if($purchaseOrder->supplier->address)
        <div><strong>Dirección:</strong> {{ $purchaseOrder->supplier->address }}</div>
        @endif
    </div>

    <div class="box">
        <div><strong>Condiciones de pago:</strong> 30 días fecha factura</div>
        <div><strong>Entrega estimada:</strong> {{ $purchaseOrder->date->copy()->addDays(8)->format('d/m/Y') }}</div>
    </div>

    <div class="mb-4"><strong>Detalle de productos/servicios</strong></div>
    <table>
        <thead>
            <tr>
                <th class="center">Ítem</th>
                <th>Descripción</th>
                <th class="center">Cantidad</th>
                <th class="right">Precio Unitario</th>
                <th class="right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($purchaseOrder->details as $index => $detail)
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td>{{ $detail->input->name ?? 'Producto' }}</td>
                <td class="center">{{ $detail->quantity }}</td>
                <td class="right">{{ money_format_local($detail->unit_price) }}</td>
                <td class="right">{{ money_format_local($detail->subtotal) }}</td>
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
