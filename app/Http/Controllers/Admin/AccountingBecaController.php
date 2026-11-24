<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Services\EporresTariffReductionReport;
use App\Support\SpreadsheetDownload;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AccountingBecaController
{
    public function index(Request $request, EporresTariffReductionReport $report)
    {
        $this->authorizeAccounting();
        $year = $this->year($request, $report);
        $hoy = Carbon::now()->toDateString();
        $data = $report->becas($year, $hoy);

        return view('admin.accounting.becas', [
            'year' => $year,
            'years' => $report->years(),
            'hoy' => Carbon::parse($hoy)->format('d/m/Y'),
            'activas' => $data['activas'],
            'perdidas' => $data['perdidas'],
            'alumnosActivos' => $this->alumnos($data['activas']),
            'alumnosPerdidos' => $this->alumnos($data['perdidas']),
        ]);
    }

    public function excel(Request $request, EporresTariffReductionReport $report)
    {
        $this->authorizeAccounting();
        $year = $this->year($request, $report);
        $data = $report->becas($year);
        $out = [];
        foreach ($data['activas'] as $row) {
            $out[] = $this->excelRow($row);
        }
        $out[] = ['PERDIERON LA BECA'];
        foreach ($data['perdidas'] as $row) {
            $out[] = $this->excelRow($row);
        }

        return SpreadsheetDownload::fromRows(
            'informe-becas-'.$year.'.xlsx',
            ['Nº', 'Alumno', 'Carrera', 'Cuota', 'Cuota original', '% beca', 'Monto beca', 'Saldo a favor', 'Pagó', 'Situación', 'Perdió la beca', 'Motivo'],
            $out
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<mixed>
     */
    private function excelRow(array $row): array
    {
        return [
            $row['nro'],
            $row['alumno'],
            $row['carrera'],
            $row['cuota'],
            $row['cuota_original'],
            $row['porcentaje'],
            $row['monto_beca'],
            $row['saldo_a_favor'],
            $row['pago'],
            $row['situacion_label'],
            ! empty($row['perdio_la_beca']) ? 'Sí' : '',
            $row['motivo'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function alumnos(array $rows): int
    {
        return count(array_unique(array_map(fn (array $row) => (string) $row['student_id'], $rows)));
    }

    private function year(Request $request, EporresTariffReductionReport $report): int
    {
        $year = (int) $request->get('year', (int) date('Y'));
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
