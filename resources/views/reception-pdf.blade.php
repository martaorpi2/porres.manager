<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de Recepción - {{ $reception->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 18px; margin: 0 0 10px 0; color: #28a745; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .muted { color: #666; }
        .box { border: 1px solid #ccc; padding: 10px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { padding: 6px 8px; border: 1px solid #ddd; text-align: left; }
        th { background: #f3f3f3; }
        .right { text-align: right; }
        .center { text-align: center; }
        .mb-4 { margin-bottom: 16px; }
        .status-box { 
            border: 2px solid #28a745; 
            padding: 10px; 
            text-align: center; 
            font-weight: bold; 
            font-size: 14px;
            margin: 12px 0;
            background-color: #d4edda;
        }
        .status-no { 
            border-color: #dc3545; 
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
        <h1>COMPROBANTE DE RECEPCIÓN</h1>
        <div class="muted">N.º {{ $reception->number }}</div>
    </div>

    <div class="box">
        <div><strong>Fecha de Recepción:</strong> {{ fmt_date($reception->date) }}</div>
        <div><strong>Estado:</strong> {{ $reception->according === 'Si' ? 'Conforme' : 'No Conforme' }}</div>
        <div><strong>Responsable:</strong> {{ $reception->user ? $reception->user->name : 'N/A' }}</div>
    </div>

    <div class="box">
        <div><strong>Orden de Compra:</strong> {{ $reception->purchase_order->number ?? 'N/A' }}</div>
        <div><strong>Fecha de Orden:</strong> {{ $reception->purchase_order ? fmt_date($reception->purchase_order->date) : 'N/A' }}</div>
        @if($reception->purchase_order)
        <div><strong>Proveedor(es):</strong> {{ $reception->purchase_order->supplier_display_name }}</div>
        @if($reception->purchase_order->suppliers->count() === 1)
        @php $sup = $reception->purchase_order->suppliers->first(); @endphp
        <div><strong>CUIT:</strong> {{ $sup->cuit }}</div>
        @if($sup->address)
        <div><strong>Dirección:</strong> {{ $sup->address }}</div>
        @endif
        @endif
        @endif
    </div>

    <div class="mb-4"><strong>Detalle de productos recibidos</strong></div>
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
            @if($reception->purchase_order && $reception->purchase_order->details)
                @foreach($reception->purchase_order->details as $index => $detail)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $detail->input->name ?? 'Producto' }}</td>
                    <td class="center">{{ $detail->quantity }}</td>
                    <td class="right">{{ money_format_local($detail->unit_price) }}</td>
                    <td class="right">{{ money_format_local($detail->subtotal) }}</td>
                </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="5" class="center">No hay productos registrados</td>
                </tr>
            @endif
        </tbody>
    </table>

    @if($reception->purchase_order)
    <div class="status-box {{ $reception->according === 'No' ? 'status-no' : '' }}">
        Estado de Recepción: {{ $reception->according === 'Si' ? 'CONFORME' : 'NO CONFORME' }}
    </div>
    @endif

    <div class="box">
        <div class="muted" style="margin-top: 8px; font-size: 10px;">
            Documento generado el {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html>

