<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Catálogo de Cuentas</title>
    <style>
        @page {
            margin: 28px 22px 32px 22px;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
            color: #000;
        }
        .catalog-head {
            text-align: center;
            margin-bottom: 8px;
            line-height: 1.25;
        }
        .catalog-head .title {
            font-size: 13px;
            font-weight: bold;
            color: #c72905;
        }
        .catalog-head .institute {
            font-size: 11px;
            font-weight: bold;
            margin-top: 2px;
            color: #c72905;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead {
            display: table-header-group;
        }
        th {
            font-size: 8px;
            font-weight: bold;
            text-align: left;
            color: #fff;
            background: #c72905;
            border: 1px solid #c72905;
            padding: 4px 3px;
        }
        td {
            padding: 2px 3px;
            vertical-align: top;
            border: 1px solid #c72905;
        }
        tr.shade td {
            background: #bfbfbf;
        }
        tr.tint td {
            background: #f5f5f5;
        }
        .code {
            display: inline-block;
            width: 62px;
        }
        .sums, .nature, .balance, .receives, .currency {
            white-space: nowrap;
        }
        .sums { width: 68px; }
        .nature { width: 58px; }
        .balance { width: 62px; }
        .receives { width: 40px; }
        .currency { width: 42px; }
    </style>
</head>
<body>
    <div class="catalog-head">
        <div class="title">Catálogo de Cuentas</div>
        <div>30675106947 - CAPITAL</div>
        <div>C.U.I.T.: 30675106947</div>
        <div>Teléfono: 6005517 Fax:</div>
        <div class="institute">INSTITUTO SAN MARTIN DE PORRES</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Cuenta</th>
                <th class="sums">Sumariza</th>
                <th class="nature">Tipo</th>
                <th class="balance">Saldo</th>
                <th class="receives">Recibe</th>
                <th class="currency">Moneda</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr class="{{ $loop->odd ? 'shade' : 'tint' }}">
                    <td><span class="code">{{ $row['code'] }}</span>{{ $row['name'] }}</td>
                    <td class="sums">{{ $row['sums_to'] }}</td>
                    <td class="nature">{{ $row['nature'] }}</td>
                    <td class="balance">{{ $row['balance'] }}</td>
                    <td class="receives">{{ $row['receives'] }}</td>
                    <td class="currency">{{ $row['currency'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
