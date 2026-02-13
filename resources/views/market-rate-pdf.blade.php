<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización - {{ $marketRate->id }}</title>
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
        .quote-header {
            background: #e8f4fd;
            border: 2px solid #2196F3;
            padding: 15px;
            margin-bottom: 15px;
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
    <div class="quote-header">
        <div class="header">
            <h1 style="color: #1976D2;">COTIZACIÓN</h1>
            <div class="muted">N.º COT-{{ str_pad($marketRate->id, 4, '0', STR_PAD_LEFT) }}</div>
        </div>
    </div>

    <div class="box">
        <div><strong>Fecha de Cotización:</strong> {{ fmt_date($marketRate->date) }}</div>
        @if($marketRate->delivery_date)
        <div><strong>Fecha de entrega:</strong> {{ fmt_date($marketRate->delivery_date) }}</div>
        @endif
        @if($marketRate->payment_method)
        <div><strong>Forma de pago:</strong> {{ $marketRate->payment_method }}</div>
        @endif
        <div><strong>Monto Total:</strong> {{ money_format_local($marketRate->total_amount) }}</div>
    </div>

    <div class="box">
        <div><strong>Proveedor:</strong> {{ $marketRate->supplier->company_name }}</div>
        <div><strong>CUIT:</strong> {{ $marketRate->supplier->cuit }}</div>
        @if($marketRate->supplier->address)
        <div><strong>Dirección:</strong> {{ $marketRate->supplier->address }}</div>
        @endif
        @if($marketRate->supplier->contact)
        <div><strong>Contacto:</strong> {{ $marketRate->supplier->contact }}</div>
        @endif
    </div>

    <div class="box">
        <div><strong>Solicitud de Compra:</strong> {{ $marketRate->purchaseRequest->request_number ?? 'N/A' }}</div>
        <div><strong>Estado de la Solicitud:</strong> {{ $marketRate->purchaseRequest->status ?? 'Sin estado' }}</div>
        <div><strong>Validez de la Cotización:</strong> 30 días desde la fecha de emisión</div>
    </div>

    <div class="mb-4"><strong>Detalle de productos cotizados</strong></div>
    <table>
        <thead>
            <tr>
                <th class="center">Ítem</th>
                <th>Producto</th>
                <th class="center">Cantidad</th>
                <th class="right">Precio Unitario</th>
                <th class="right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @php $total = 0; @endphp
            @foreach($marketRate->quoteDetails as $index => $detail)
            @php 
                $subtotal = $detail->quantity * $detail->unit_price;
                $total += $subtotal;
            @endphp
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td>
                    <strong>{{ $detail->product->name ?? 'Producto no encontrado' }}</strong>
                    @if($detail->product && $detail->product->description)
                    <br><small class="muted">{{ $detail->product->description }}</small>
                    @endif
                </td>
                <td class="center">{{ $detail->quantity }}</td>
                <td class="right">{{ money_format_local($detail->unit_price) }}</td>
                <td class="right">{{ money_format_local($subtotal) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-box">
        Total de la Cotización: {{ money_format_local($total) }}
    </div>

    <div class="box">
        <div><strong>Condiciones Generales:</strong></div>
        <div>• Precios válidos por 30 días desde la fecha de emisión</div>
        <div>• Los precios incluyen IVA</div>
        @if($marketRate->delivery_date)
        <div>• Fecha de entrega: {{ fmt_date($marketRate->delivery_date) }}</div>
        @else
        <div>• Entrega según disponibilidad de stock</div>
        @endif
        <div>• Forma de pago: {{ $marketRate->payment_method ?: '30 días fecha factura' }}</div>
        <div class="muted" style="margin-top: 8px; font-size: 10px;">
            Documento generado el {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html>
