<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Plan de cuentas</title>
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
</head>
<body>
    <div class="watermark">porresManager - ISMP</div>
    <div class="header">
        <h1>Plan de cuentas</h1>
        <p>Generado el: {{ date('d/m/Y H:i:s') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Activa</th>
            </tr>
        </thead>
        <tbody>
            @forelse($accounts as $account)
                <tr>
                    <td>{{ $account->code }}</td>
                    <td>{{ $account->name }}</td>
                    <td>{{ $account->type_label }}</td>
                    <td>{{ $account->is_active ? 'Sí' : 'No' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center;">No hay cuentas para mostrar</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>Total de cuentas: {{ $accounts->count() }}</p>
    </div>
</body>
</html>
