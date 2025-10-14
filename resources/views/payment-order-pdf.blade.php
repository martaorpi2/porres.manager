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
    </style>
    @php
        $po = $paymentOrder->purchase_order; // related purchase order
        $supplier = $po?->supplier;
        function money_format_local($value) { return '$ ' . number_format((float)$value, 2, ',', '.'); }
        function fmt_date($d) { return $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : ''; }
    @endphp
</head>
<body>
    <div class="header">
        <h1>ORDEN DE PAGO</h1>
        <div class="muted">N.º {{ $paymentOrder->payment_number }}</div>
    </div>

    <div class="box">
        <strong>Fecha:</strong> {{ fmt_date($paymentOrder->date) }}
    </div>

    <div class="box">
        <div><strong>Proveedor:</strong> {{ $supplier?->company_name }}</div>
        <div><strong>CUIT:</strong> {{ $supplier?->cuit }}</div>
    </div>

    <div class="box">
        <div><strong>Forma de pago:</strong> —</div>
        <div><strong>Banco:</strong> —</div>
        <div><strong>Fecha de pago efectivo:</strong> —</div>
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

    <div class="box">
        <div><strong>Total Orden de Pago:</strong> {{ money_format_local($paymentOrder->total_amount) }}</div>
        <div><strong>Estado:</strong> {{ $paymentOrder->status }}</div>
        <div><strong>Observaciones:</strong> {{ $paymentOrder->observations ?? '' }}</div>
    </div>
</body>
</html>


