<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Services\EporresDelinquencyReport;
use App\Support\SpreadsheetDownload;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class AccountingDelinquencyController
{
    public function index(Request $request, EporresDelinquencyReport $report, ?int $anio = null)
    {
        $this->authorizeAccounting();
        $anio = $this->year($anio ?? (int) date('Y'));

        return view('admin.accounting.delinquency', array_merge(
            $report->metrics($anio),
            ['years' => $this->years()]
        ));
    }

    public function pdf(EporresDelinquencyReport $report, int $anio)
    {
        $this->authorizeAccounting();
        $anio = $this->year($anio);
        $data = $report->metrics($anio);
        $pdf = Pdf::loadView('admin.accounting.delinquency_pdf', $data)->setPaper('a4', 'landscape');

        return $pdf->stream('Pago-Morosidad-'.$anio.'.pdf');
    }

    public function exportResumen(EporresDelinquencyReport $report, int $anio)
    {
        $this->authorizeAccounting();
        $anio = $this->year($anio);
        $rows = $report->metrics($anio)['resumenPorCuota'];
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                $row['cuota'],
                $row['mes'],
                $row['total_generadas'],
                $row['total_pagadas'],
                $this->money($row['monto_generado']),
                $this->money($row['monto_pagado_cuota']),
                $this->money($row['monto_pagado_real']),
                $this->money($row['monto_descuentos']),
                $this->money($row['monto_pagado_interes_mora']),
                $this->money($row['monto_pagado_interes_mp']),
                $this->money($row['monto_diferencia_conciliacion']),
                $this->money($row['monto_adeudado']),
            ];
        }
        $out[] = [
            'TOTAL', '',
            array_sum(array_column($rows, 'total_generadas')),
            array_sum(array_column($rows, 'total_pagadas')),
            $this->money(array_sum(array_column($rows, 'monto_generado'))),
            $this->money(array_sum(array_column($rows, 'monto_pagado_cuota'))),
            $this->money(array_sum(array_column($rows, 'monto_pagado_real'))),
            $this->money(array_sum(array_column($rows, 'monto_descuentos'))),
            $this->money(array_sum(array_column($rows, 'monto_pagado_interes_mora'))),
            $this->money(array_sum(array_column($rows, 'monto_pagado_interes_mp'))),
            $this->money(array_sum(array_column($rows, 'monto_diferencia_conciliacion'))),
            $this->money(array_sum(array_column($rows, 'monto_adeudado'))),
        ];

        return SpreadsheetDownload::fromRows('resumen_por_cuota_'.$anio.'.xlsx', [
            'CUOTA', 'MES', 'TOTAL GENERADAS', 'TOTAL PAGADAS',
            'MONTO CUOTA PURA (GENERADO)', 'MONTO PAGADO (CUOTA PURA)', 'MONTO PAGADO REAL',
            'DESCUENTOS', 'INTERES MORA', 'INTERES MERCADO PAGO', 'OTROS INTERESES', 'MONTO ADEUDADO',
        ], $out);
    }

    public function exportMatricula(EporresDelinquencyReport $report, int $anio)
    {
        $this->authorizeAccounting();
        $anio = $this->year($anio);
        $rows = $report->metrics($anio)['resumenMatriculaPagos'];
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                $row['nro_mes'],
                $row['mes'],
                $row['concepto'],
                $row['total_pagadas'],
                $this->money($row['monto_pagado_cuota']),
                $this->money($row['monto_pagado_real']),
                $this->money($row['monto_pagado_interes_mp']),
                $this->money($row['monto_diferencia_conciliacion']),
            ];
        }
        $out[] = [
            'TOTAL', '', '',
            array_sum(array_column($rows, 'total_pagadas')),
            $this->money(array_sum(array_column($rows, 'monto_pagado_cuota'))),
            $this->money(array_sum(array_column($rows, 'monto_pagado_real'))),
            $this->money(array_sum(array_column($rows, 'monto_pagado_interes_mp'))),
            $this->money(array_sum(array_column($rows, 'monto_diferencia_conciliacion'))),
        ];

        return SpreadsheetDownload::fromRows('resumen_matricula_pagos_'.$anio.'.xlsx', [
            'Nº MES', 'MES', 'CONCEPTO', 'TOTAL PAGADAS',
            'MONTO PAGADO (CUOTA PURA)', 'MONTO PAGADO REAL', 'INTERES MERCADO PAGO', 'OTROS INTERESES',
        ], $out);
    }

    public function exportDeudores(EporresDelinquencyReport $report, int $anio)
    {
        $this->authorizeAccounting();
        $anio = $this->year($anio);

        return SpreadsheetDownload::fromAssoc(
            'deudores_'.$anio.'.xlsx',
            ['Apellido', 'Nombre', 'DNI', 'Carrera', 'Correo Personal', 'Teléfono celular', 'Cuotas adeudadas', 'Monto adeudado'],
            $report->deudoresTodosRows($anio)
        );
    }

    public function exportDeudoresTramo(EporresDelinquencyReport $report, int $anio, string $cuotas)
    {
        $this->authorizeAccounting();
        $anio = $this->year($anio);
        $filename = $cuotas === 'mas-de-6'
            ? 'deudores_mas_de_6_cuotas_'.$anio.'.xlsx'
            : 'deudores_'.$cuotas.'_cuota'.((int) $cuotas === 1 ? '' : 's').'_'.$anio.'.xlsx';

        return SpreadsheetDownload::fromAssoc(
            $filename,
            ['Apellido', 'Nombre', 'DNI', 'Carrera', 'Correo Personal', 'Teléfono celular', 'Cuotas adeudadas', 'Monto adeudado'],
            $report->deudoresRows($anio, $cuotas)
        );
    }

    public function exportEgresados(EporresDelinquencyReport $report, int $anio)
    {
        $this->authorizeAccounting();
        $anio = $this->year($anio);

        return SpreadsheetDownload::fromAssoc(
            'deudores_egresados_cuotas_'.$anio.'.xlsx',
            ['Apellido', 'Nombre', 'DNI', 'Carrera', 'Última aprobación examen', 'Correo Personal', 'Teléfono celular', 'Cuotas adeudadas', 'Monto adeudado'],
            $report->egresadosRows($anio)
        );
    }

    private function year(int $anio): int
    {
        if ($anio < 2021 || $anio > (int) date('Y') + 1) {
            abort(404);
        }

        return $anio;
    }

    /** @return list<int> */
    private function years(): array
    {
        $hasta = max((int) date('Y'), 2026);
        $years = [];
        for ($y = 2021; $y <= $hasta; $y++) {
            $years[] = $y;
        }

        return $years;
    }

    private function money(float $amount): string
    {
        return '$'.number_format($amount, 2, ',', '.');
    }

    private function authorizeAccounting(): void
    {
        $user = backpack_user();
        if (! $user instanceof User || ! $user->canViewAccounting()) {
            abort(403, 'No tiene permiso para ver la contabilidad.');
        }
    }
}
