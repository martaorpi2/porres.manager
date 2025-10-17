<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Lista de Productos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Lista de Productos</h1>
        <p>Generado el: {{ date('d/m/Y H:i:s') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Categoría</th>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Unidad Med.</th>
                <th>Stock Mín.</th>
                <th>Fecha Vencimiento</th>
                <th>Ubicación</th>
                <th>% Utilización</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $product)
                <tr>
                    <td>{{ $product->category->name ?? 'N/A' }}</td>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->description }}</td>
                    <td>{{ $product->unit_measurement }}</td>
                    <td>{{ $product->minimum_stock }}</td>
                    <td>{{ $product->expiration_date ? $product->expiration_date->format('d/m/Y') : 'N/A' }}</td>
                    <td>{{ $product->location }}</td>
                    <td>{{ $product->utilization_percentage }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center;">No hay productos para mostrar</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>Total de productos: {{ $products->count() }}</p>
    </div>
</body>
</html>
