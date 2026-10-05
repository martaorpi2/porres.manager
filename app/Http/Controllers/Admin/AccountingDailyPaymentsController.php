<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Services\EporresDailyPaymentsReport;
use App\Support\SpreadsheetDownload;
use Illuminate\Http\Request;

class AccountingDailyPaymentsController
{
    public function index(Request $request, EporresDailyPaymentsReport $report)
    {
        $this->authorizeAccounting();
        $filters = $this->filters($request);
        $data = $report->build(
            $filters['fechaDesde'],
            $filters['fechaHasta'],
            $filters['mediosPago'],
            $filters['tipoPago'],
            $filters['vista']
        );

        return view('admin.accounting.daily_payments', array_merge($data, [
            'fechaDesde' => $filters['fechaDesde'],
            'fechaHasta' => $filters['fechaHasta'],
        ]));
    }

    public function excel(Request $request, EporresDailyPaymentsReport $report)
    {
        $this->authorizeAccounting();
        $filters = $this->filters($request);
        $data = $report->build(
            $filters['fechaDesde'],
            $filters['fechaHasta'],
            $filters['mediosPago'],
            $filters['tipoPago'],
            $filters['vista']
        );

        return SpreadsheetDownload::fromRows(
            'reporte-pagos-dia-'.$data['vista'].'-'.$filters['fechaDesde'].'-'.$filters['fechaHasta'].'.xlsx',
            [
                'Comprobante', 'Estudiante', 'Carrera', 'Tipo', 'Nº cuota', 'Fecha de pago',
                'Medio de pago', 'Monto del medio', 'Importe cuota pura', 'Int. financiación plan',
                'Interés por mora', 'Otros intereses', 'Total línea', 'Observación / vínculo plan',
            ],
            $report->excelRows($data['lineas'], $data['relatedByPlanId'])
        );
    }

    /** @return array{fechaDesde: string, fechaHasta: string, mediosPago: list<string>, tipoPago: ?string, vista: string} */
    private function filters(Request $request): array
    {
        $hoy = date('Y-m-d');
        $desde = $this->dateOr($request->get('fecha_desde'), $hoy);
        $hasta = $this->dateOr($request->get('fecha_hasta'), $hoy);
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $raw = $request->get('medio_pago');
        $medios = [];
        if (is_array($raw)) {
            $medios = array_values(array_filter(array_map('strval', $raw), fn ($v) => $v !== ''));
        } elseif ($raw !== null && $raw !== '') {
            $medios = [(string) $raw];
        }

        return [
            'fechaDesde' => $desde,
            'fechaHasta' => $hasta,
            'mediosPago' => $medios,
            'tipoPago' => $request->get('tipo_pago'),
            'vista' => (string) $request->get('vista', 'cobros'),
        ];
    }

    private function dateOr(mixed $value, string $fallback): string
    {
        $value = (string) $value;
        $date = \DateTime::createFromFormat('Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : $fallback;
    }

    private function authorizeAccounting(): void
    {
        $user = backpack_user();
        if (! $user instanceof User || ! $user->canViewAccounting()) {
            abort(403, 'No tiene permiso para ver la contabilidad.');
        }
    }
}
