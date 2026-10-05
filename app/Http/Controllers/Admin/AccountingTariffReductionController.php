<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Services\EporresTariffReductionReport;
use App\Support\SpreadsheetDownload;
use Illuminate\Http\Request;

class AccountingTariffReductionController
{
    public function index(Request $request, EporresTariffReductionReport $report)
    {
        $this->authorizeAccounting();
        $year = $this->year($request, $report);
        $soloCero = $request->boolean('solo_cero');
        $soloNoAplicada = $request->boolean('solo_no_aplicada');
        $rows = $report->rows($year, $soloCero, $soloNoAplicada);

        return view('admin.accounting.tariff_reductions', [
            'year' => $year,
            'years' => $report->years(),
            'rows' => $rows,
            'soloCero' => $soloCero,
            'soloNoAplicada' => $soloNoAplicada,
            'totalCupones' => count($rows),
            'totalCero' => count(array_filter($rows, fn (array $row) => $row['saldo_cero'])),
            'totalNoAplicada' => count(array_filter($rows, fn (array $row) => ! $row['aplicada'])),
        ]);
    }

    public function excel(Request $request, EporresTariffReductionReport $report)
    {
        $this->authorizeAccounting();
        $year = $this->year($request, $report);
        $rows = $report->rows($year, $request->boolean('solo_cero'), $request->boolean('solo_no_aplicada'));
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                $row['nro'],
                $row['alumno'],
                $row['carrera'],
                $row['cuota'],
                $row['cuota_original'],
                $row['porcentaje'],
                $row['monto_beca'],
                $row['saldo_a_favor'],
                $row['pago'],
                $row['motivo'],
            ];
        }

        return SpreadsheetDownload::fromRows(
            'reporte-reducciones-arancelarias-'.$year.'.xlsx',
            ['Nº', 'Alumno', 'Carrera', 'Cuota', 'Cuota original', '% beca', 'Monto beca', 'Saldo a favor', 'Pagó', 'Motivo'],
            $out
        );
    }

    private function year(Request $request, EporresTariffReductionReport $report): int
    {
        $default = (int) date('Y');
        $year = (int) $request->get('year', $default);
        if (! in_array($year, $report->years(), true)) {
            return $report->years()[0];
        }

        return $year;
    }

    private function authorizeAccounting(): void
    {
        $user = backpack_user();
        if (! $user instanceof User || ! $user->canViewAccounting()) {
            abort(403, 'No tiene permiso para ver la contabilidad.');
        }
    }
}
