<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
        h1 { font-size: 16px; color: #881f1f; margin: 0 0 8px; }
        h2 { font-size: 12px; margin: 14px 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 3px 4px; }
        th { background: #881f1f; color: #fff; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h1>Pago / Morosidad — Año {{ $anio }}</h1>
    <h2>Resumen por cuota</h2>
    <table>
        <thead>
            <tr>
                <th>Cuota</th><th>Mes</th><th>Generadas</th><th>Pagadas</th>
                <th>Cuota pura</th><th>Pagado cuota</th><th>Pagado real</th>
                <th>Descuentos</th><th>Mora</th><th>Interés MP</th><th>Otros</th><th>Adeudado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($resumenPorCuota as $row)
                <tr>
                    <td>{{ $row['cuota'] }}</td>
                    <td>{{ $row['mes'] }}</td>
                    <td class="right">{{ number_format($row['total_generadas'], 0, ',', '.') }}</td>
                    <td class="right">{{ number_format($row['total_pagadas'], 0, ',', '.') }}</td>
                    <td class="right">{{ number_format($row['monto_generado'], 2, ',', '.') }}</td>
                    <td class="right">{{ number_format($row['monto_pagado_cuota'], 2, ',', '.') }}</td>
                    <td class="right">{{ number_format($row['monto_pagado_real'], 2, ',', '.') }}</td>
                    <td class="right">{{ number_format($row['monto_descuentos'], 2, ',', '.') }}</td>
                    <td class="right">{{ number_format($row['monto_pagado_interes_mora'], 2, ',', '.') }}</td>
                    <td class="right">{{ number_format($row['monto_pagado_interes_mp'], 2, ',', '.') }}</td>
                    <td class="right">{{ number_format($row['monto_diferencia_conciliacion'], 2, ',', '.') }}</td>
                    <td class="right">{{ number_format($row['monto_adeudado'], 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Deudores por cantidad de cuotas</h2>
    <table>
        <thead><tr><th>Cuotas adeudadas</th><th>Alumnos</th></tr></thead>
        <tbody>
            @foreach($deudoresPorTramoResumen as $fila)
                <tr>
                    <td>{{ $fila['etiqueta'] }}</td>
                    <td class="right">{{ number_format($fila['cantidad'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr>
                <td>Egresados del año con cuota pendiente</td>
                <td class="right">{{ number_format($egresadosDeudoresCuotasCount, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
