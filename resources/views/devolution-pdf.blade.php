<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de Devolución - {{ $devolution->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 18px; margin: 0 0 10px 0; color: #dc3545; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .muted { color: #666; }
        .box { border: 1px solid #ccc; padding: 10px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { padding: 6px 8px; border: 1px solid #ddd; text-align: left; }
        th { background: #f3f3f3; }
        .right { text-align: right; }
        .center { text-align: center; }
        .mb-4 { margin-bottom: 16px; }
        .reason-box { 
            border: 2px solid #dc3545; 
            padding: 10px; 
            margin: 12px 0;
            background-color: #f8d7da;
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
    @endphp
</head>
<body>
    <div class="watermark">porresManager - ISMP</div>
    <div class="header">
        <h1>COMPROBANTE DE DEVOLUCIÓN</h1>
        <div class="muted">N.º DEV-{{ str_pad($devolution->id, 4, '0', STR_PAD_LEFT) }}</div>
    </div>

    <div class="box">
        <div><strong>Fecha de Devolución:</strong> {{ fmt_date($devolution->date) }}</div>
        <div><strong>Usuario que devolvió:</strong> {{ $devolution->user ? $devolution->user->name : 'N/A' }}</div>
    </div>

    @if($devolution->reception)
    <div class="box">
        <div><strong>Recepción relacionada:</strong> {{ $devolution->reception->number }}</div>
        <div><strong>Fecha de Recepción:</strong> {{ fmt_date($devolution->reception->date) }}</div>
        <div><strong>Estado de Recepción:</strong> {{ $devolution->reception->according === 'Si' ? 'Conforme' : 'No Conforme' }}</div>
        @if($devolution->reception->purchase_order)
        <div><strong>Orden de Compra:</strong> {{ $devolution->reception->purchase_order->number }}</div>
        <div><strong>Fecha de Orden:</strong> {{ fmt_date($devolution->reception->purchase_order->date) }}</div>
        @if($devolution->reception->purchase_order->supplier)
        <div><strong>Proveedor:</strong> {{ $devolution->reception->purchase_order->supplier->company_name }}</div>
        <div><strong>CUIT:</strong> {{ $devolution->reception->purchase_order->supplier->cuit }}</div>
        @endif
        @endif
    </div>
    @endif

    <div class="reason-box">
        <div><strong>Motivo de la Devolución:</strong></div>
        <div style="margin-top: 8px;">{{ $devolution->reason }}</div>
    </div>

    @if($devolution->reception && $devolution->reception->purchase_order && $devolution->reception->purchase_order->details)
    <div class="mb-4"><strong>Detalle de productos de la recepción</strong></div>
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
            @foreach($devolution->reception->purchase_order->details as $index => $detail)
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
    @endif

    <div class="box">
        <div class="muted" style="margin-top: 8px; font-size: 10px;">
            Documento generado el {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html>

