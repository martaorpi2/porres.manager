<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden de Compra - {{ $purchaseOrder->number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 12px;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .order-info {
            margin-bottom: 20px;
        }
        .order-number {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .supplier-info {
            margin-bottom: 20px;
            border: 1px solid #ddd;
            padding: 15px;
            background-color: #f9f9f9;
        }
        .supplier-info h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .supplier-details {
            margin-bottom: 5px;
        }
        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .products-table th,
        .products-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .products-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .products-table .number {
            text-align: center;
            width: 50px;
        }
        .products-table .quantity {
            text-align: center;
            width: 80px;
        }
        .products-table .price {
            text-align: right;
            width: 120px;
        }
        .products-table .subtotal {
            text-align: right;
            width: 120px;
        }
        .total-section {
            text-align: right;
            margin-bottom: 20px;
        }
        .total-amount {
            font-size: 16px;
            font-weight: bold;
            border: 2px solid #333;
            padding: 10px;
            display: inline-block;
        }
        .status-section {
            margin-bottom: 20px;
        }
        .status {
            font-weight: bold;
            padding: 5px 10px;
            background-color: #e7f3ff;
            border: 1px solid #b3d9ff;
            display: inline-block;
        }
        .observations {
            margin-top: 20px;
            padding: 10px;
            background-color: #f0f0f0;
            border-left: 4px solid #333;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">ORDEN DE COMPRA</div>
    </div>

    <div class="order-info">
        <div class="order-number">N.º {{ $purchaseOrder->number }}</div>
        <div><strong>Fecha:</strong> {{ $purchaseOrder->date->format('d/m/Y') }}</div>
    </div>

    <div class="supplier-info">
        <h3>Proveedor: {{ $purchaseOrder->supplier->company_name }}</h3>
        <div class="supplier-details"><strong>CUIT:</strong> {{ $purchaseOrder->supplier->cuit }}</div>
        @if($purchaseOrder->supplier->address)
        <div class="supplier-details"><strong>Dirección:</strong> {{ $purchaseOrder->supplier->address }}</div>
        @endif
        <div class="supplier-details"><strong>Condiciones de pago:</strong> 30 días fecha factura</div>
        <div class="supplier-details"><strong>Entrega estimada:</strong> {{ $purchaseOrder->date->copy()->addDays(8)->format('d/m/Y') }}</div>
    </div>

    <h3>Detalle de productos/servicios</h3>
    <table class="products-table">
        <thead>
            <tr>
                <th class="number">Ítem</th>
                <th>Descripción</th>
                <th class="quantity">Cantidad</th>
                <th class="price">Precio Unitario</th>
                <th class="subtotal">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($purchaseOrder->details as $index => $detail)
            <tr>
                <td class="number">{{ $index + 1 }}</td>
                <td>{{ $detail->input->name ?? 'Producto' }}</td>
                <td class="quantity">{{ $detail->quantity }}</td>
                <td class="price">$ {{ number_format($detail->unit_price, 2, ',', '.') }}</td>
                <td class="subtotal">$ {{ number_format($detail->subtotal, 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-section">
        <div class="total-amount">
            Total de la Orden de Compra: $ {{ number_format($purchaseOrder->total, 2, ',', '.') }}
        </div>
    </div>

    <div class="status-section">
        <strong>Estado:</strong> <span class="status">{{ $purchaseOrder->status }}</span>
    </div>

    <div class="observations">
        <strong>Observaciones:</strong> Entrega en área de Sistemas, 2.º piso.
    </div>

    <div class="footer">
        <p>Documento generado el {{ now()->format('d/m/Y H:i') }}</p>
    </div>
</body>
</html>
