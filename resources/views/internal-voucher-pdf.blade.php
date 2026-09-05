<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $voucher->document_title }} {{ $voucher->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 18px; margin: 0 0 4px 0; }
        .header { margin-bottom: 12px; }
        .muted { color: #666; }
        .box { border: 1px solid #ccc; padding: 10px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px 8px; border: 1px solid #ddd; text-align: left; }
        th { background: #f3f3f3; width: 32%; }
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
        if (! function_exists('ci_money')) {
            function ci_money($value) { return '$ ' . number_format((float)$value, 2, ',', '.'); }
        }
        if (! function_exists('ci_date')) {
            function ci_date($d) { return $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : ''; }
        }
    @endphp
</head>
<body>
    <div class="watermark">porresManager - ISMP</div>
    @if($voucher->status === \App\Models\InternalVoucher::STATUS_ANULADO)
    <div class="watermark" style="color: rgba(200, 0, 0, 0.25); font-size: 72px;">ANULADO</div>
    @endif

    <div class="header">
        <h1>{{ $voucher->document_title }}</h1>
        <div class="muted">N.º {{ $voucher->number }}</div>
    </div>

    <table>
        <tr>
            <th>Fecha</th>
            <td>{{ ci_date($voucher->date) }}</td>
        </tr>
        <tr>
            <th>Tipo</th>
            <td>{{ $voucher->type_label }}</td>
        </tr>
        <tr>
            <th>Motivo</th>
            <td>{{ $voucher->motive_label }}</td>
        </tr>
        <tr>
            <th>Concepto</th>
            <td>{{ $voucher->concept }}</td>
        </tr>
        <tr>
            <th>Beneficiario</th>
            <td>{{ $voucher->beneficiary }}</td>
        </tr>
        <tr>
            <th>Importe</th>
            <td>{{ ci_money($voucher->amount) }} {{ $voucher->currency_code ?: 'ARS' }}</td>
        </tr>
        <tr>
            <th>Cuenta contable</th>
            <td>{{ $voucher->accountingAccount?->identifying_label ?? '—' }}</td>
        </tr>
        <tr>
            <th>Medio de pago</th>
            <td>{{ $voucher->payment_method ?? '—' }}</td>
        </tr>
        <tr>
            <th>Autorizado por</th>
            <td>{{ $voucher->authorizingUser?->name ?? '—' }}</td>
        </tr>
        @if($voucher->purchaseOrder)
        <tr>
            <th>Orden de compra</th>
            <td>{{ $voucher->purchaseOrder->number }}</td>
        </tr>
        @endif
        @if($voucher->paymentOrder)
        <tr>
            <th>Orden de pago</th>
            <td>{{ $voucher->paymentOrder->payment_number }}</td>
        </tr>
        @endif
        <tr>
            <th>Estado</th>
            <td>{{ $voucher->status }}</td>
        </tr>
        @if($voucher->observations)
        <tr>
            <th>Observaciones</th>
            <td>{{ $voucher->observations }}</td>
        </tr>
        @endif
        @if($voucher->status === \App\Models\InternalVoucher::STATUS_ANULADO)
        <tr>
            <th>Motivo de anulación</th>
            <td>{{ $voucher->annulment_reason }}</td>
        </tr>
        <tr>
            <th>Anulado el</th>
            <td>{{ $voucher->annulled_at ? $voucher->annulled_at->format('d/m/Y H:i') : '—' }}{{ $voucher->annulledBy ? ' por '.$voucher->annulledBy->name : '' }}</td>
        </tr>
        @endif
    </table>

    <div class="total-box">
        Total: {{ ci_money($voucher->amount) }}
    </div>
</body>
</html>
