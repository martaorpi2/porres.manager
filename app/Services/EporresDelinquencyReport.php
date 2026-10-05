<?php

namespace App\Services;

use App\Models\Eporres\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EporresDelinquencyReport
{
    public const QUOTA_MIN = 1;

    public const QUOTA_MAX = 10;

    private const MATRICULA_EXPIRATED = ['2025-12-31', '2026-12-31'];

    /** @var array<int, string> */
    public const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    public function metrics(int $anio): array
    {
        $totalPaid = [];
        $totalPending = [];
        $countQuota = [];
        $countQuotaPaid = [];
        $totalInterestPaid = [];

        for ($i = 1; $i <= 12; $i++) {
            $countQuota[$i] = $this->countOrders($i, $anio);
            $countQuotaPaid[$i] = $this->countOrders($i, $anio, true);
            $totalPaid[$i] = ['paid' => $this->sum($i, $anio, 'quota_amount', true), 'pending' => 0];
            $totalInterestPaid[$i] = $this->interestPaid($i, $anio);
            $totalPending[$i] = ['pending' => $this->pendingAmount($i, $anio)];
        }

        $resumenPorCuota = [];
        for ($cuota = 1; $cuota <= 10; $cuota++) {
            $mes = $cuota + 2;
            $montoGenerado = $this->sum($mes, $anio, 'quota_amount');
            $montoPagadoCuota = $this->sum($mes, $anio, 'quota_amount', true);
            $montoPagadoReal = $this->sum($mes, $anio, 'amount_paid', true);
            $montoDescuentos = $this->sum($mes, $anio, 'discount_amount', true);
            $montoInteresMora = $this->sum($mes, $anio, 'surcharge_amount', true);
            $montoInteresMp = $this->sum($mes, $anio, 'surcharge_amountMP', true);
            $montoAdeudado = $totalPending[$mes]['pending'];
            $resumenPorCuota[] = [
                'cuota' => $cuota,
                'mes' => self::MESES[$mes],
                'mes_nro' => $mes,
                'total_generadas' => $countQuota[$mes],
                'total_pagadas' => $countQuotaPaid[$mes],
                'monto_generado' => $montoGenerado,
                'monto_pagado_cuota' => $montoPagadoCuota,
                'monto_pagado_real' => $montoPagadoReal,
                'monto_descuentos' => $montoDescuentos,
                'monto_pagado_interes_mora' => $montoInteresMora,
                'monto_pagado_interes_mp' => $montoInteresMp,
                'monto_diferencia_conciliacion' => $montoPagadoReal - $montoPagadoCuota - $montoInteresMora - $montoInteresMp + $montoDescuentos,
                'monto_adeudado' => $montoAdeudado,
            ];
        }

        return [
            'anio' => $anio,
            'count_quota' => $countQuota,
            'count_quota_paid' => $countQuotaPaid,
            'total_paid' => $totalPaid,
            'total_pending' => $totalPending,
            'total_interest_paid' => $totalInterestPaid,
            'resumenPorCuota' => $resumenPorCuota,
            'resumenMatriculaPagos' => $this->matriculaPorMes($anio),
            'deudoresPorTramoResumen' => $this->deudoresPorTramo($anio),
            'egresadosDeudoresCuotasCount' => $this->egresadosDeudoresCount($anio),
        ];
    }

    public function deudoresRows(int $anio, string|int $cuotas): array
    {
        $esMasDeSeis = $cuotas === 'mas-de-6';
        $query = $this->pendingStudentsQuery($anio);
        if ($esMasDeSeis) {
            $query->havingRaw('COUNT(*) > 6');
        } else {
            $n = (int) $cuotas;
            if ($n < 1 || $n > 6) {
                abort(404);
            }
            $query->havingRaw('COUNT(*) = ?', [$n]);
        }

        return $this->rowsForStudents($anio, $query->pluck('student_id'));
    }

    public function deudoresTodosRows(int $anio): array
    {
        $ids = $this->pendingStudentsQuery($anio)
            ->havingRaw('COUNT(*) >= 1')
            ->pluck('student_id');

        return $this->rowsForStudents($anio, $ids);
    }

    public function egresadosRows(int $anio): array
    {
        $idsEgreso = $this->studentIdsEgresados($anio);
        if ($idsEgreso->isEmpty()) {
            return [];
        }

        $studentIds = $this->pendingStudentsQuery($anio)
            ->whereIn('student_id', $idsEgreso)
            ->havingRaw('COUNT(*) >= 1')
            ->pluck('student_id');

        if ($studentIds->isEmpty()) {
            return [];
        }

        $fechas = DB::connection('eporres')->table('exam_students')
            ->select('student_id', DB::raw('MAX(date_approved) as ultima'))
            ->where('approved', 1)
            ->whereNotNull('date_approved')
            ->whereIn('student_id', $studentIds)
            ->groupBy('student_id')
            ->pluck('ultima', 'student_id');

        $rows = $this->rowsForStudents($anio, $studentIds, true);
        foreach ($rows as &$row) {
            $ultima = $fechas[$row['_student_id']] ?? null;
            $row['Última aprobación examen'] = $ultima ? date('d/m/Y', strtotime((string) $ultima)) : '';
        }
        unset($row);

        return $rows;
    }

    private function quotaQuery(int $month, int $year)
    {
        return Order::query()
            ->whereMonth('expirated_at', $month)
            ->whereYear('expirated_at', $year)
            ->whereBetween('quota_number', [self::QUOTA_MIN, self::QUOTA_MAX])
            ->where('student_id', '<>', 0);
    }

    private function countOrders(int $month, int $year, bool $paid = false): int
    {
        $query = $this->quotaQuery($month, $year);
        if ($paid) {
            $query->paid();
        }

        return (int) $query->count();
    }

    private function sum(int $month, int $year, string $column, bool $paid = false): float
    {
        $query = $this->quotaQuery($month, $year);
        if ($paid) {
            $query->paid();
        }

        return (float) ($query->sum($column) ?? 0);
    }

    private function interestPaid(int $month, int $year): float
    {
        $total = $this->quotaQuery($month, $year)
            ->paid()
            ->selectRaw('SUM(GREATEST(0, amount_paid - quota_amount)) AS total')
            ->value('total');

        return (float) ($total ?? 0);
    }

    private function pendingAmount(int $month, int $year): float
    {
        $total = $this->quotaQuery($month, $year)
            ->where('expirated_at', '<', date('Y-m-d'))
            ->where('state', Order::STATE_PENDING)
            ->sum('quota_amount');

        return (float) ($total ?? 0);
    }

    private function matriculaPorMes(int $year): array
    {
        $paidMatricula = Order::query()
            ->where('quota_number', 0)
            ->where('student_id', '<>', 0)
            ->where(function ($q) {
                $q->whereDate('expirated_at', self::MATRICULA_EXPIRATED[0])
                    ->orWhereDate('expirated_at', self::MATRICULA_EXPIRATED[1]);
            })
            ->paid()
            ->whereYear('paid_at', $year)
            ->whereNotNull('paid_at')
            ->get();

        $paidCuotasEneFeb = Order::query()
            ->whereBetween('quota_number', [self::QUOTA_MIN, self::QUOTA_MAX])
            ->where('student_id', '<>', 0)
            ->paid()
            ->whereYear('paid_at', $year)
            ->whereNotNull('paid_at')
            ->whereRaw('MONTH(paid_at) IN (1, 2)')
            ->get();

        $agg = [];
        for ($m = 1; $m <= 12; $m++) {
            $agg[$m] = [
                'mat' => ['cnt' => 0, 'sq' => 0.0, 'sap' => 0.0, 'smp' => 0.0],
                'cuo' => ['cnt' => 0, 'sq' => 0.0, 'sap' => 0.0, 'smp' => 0.0],
            ];
        }

        foreach ($paidMatricula as $order) {
            $m = (int) date('n', strtotime((string) $order->paid_at));
            if ($m < 1 || $m > 12) {
                continue;
            }
            $this->acumular($agg[$m]['mat'], $order);
        }
        foreach ($paidCuotasEneFeb as $order) {
            $m = (int) date('n', strtotime((string) $order->paid_at));
            if ($m !== 1 && $m !== 2) {
                continue;
            }
            $this->acumular($agg[$m]['cuo'], $order);
        }

        $out = [];
        for ($m = 1; $m <= 12; $m++) {
            $out[] = $this->filaMatricula($m, 'Matrícula', $agg[$m]['mat']);
            if ($m <= 2) {
                $out[] = $this->filaMatricula($m, 'Cuotas', $agg[$m]['cuo']);
            }
        }

        return $out;
    }

    private function acumular(array &$bucket, Order $order): void
    {
        $attrs = $order->getAttributes();
        $bucket['cnt']++;
        $bucket['sq'] += (float) ($attrs['quota_amount'] ?? 0);
        $bucket['sap'] += (float) ($attrs['amount_paid'] ?? 0);
        $bucket['smp'] += (float) ($attrs['surcharge_amountMP'] ?? 0);
    }

    private function filaMatricula(int $mes, string $concepto, array $bucket): array
    {
        $cuota = $bucket['sq'];
        $real = $bucket['sap'];
        $mp = $bucket['smp'];

        return [
            'nro_mes' => $mes,
            'mes' => self::MESES[$mes],
            'concepto' => $concepto,
            'total_pagadas' => $bucket['cnt'],
            'monto_pagado_cuota' => $cuota,
            'monto_pagado_real' => $real,
            'monto_pagado_interes_mp' => $mp,
            'monto_diferencia_conciliacion' => $real - $cuota - $mp,
        ];
    }

    private function deudoresPorTramo(int $anio): array
    {
        $counts = Order::query()
            ->fromSub(function ($q) use ($anio) {
                $q->from('orders')
                    ->select('student_id', DB::raw('COUNT(*) as cuotas_adeudadas'))
                    ->whereYear('expirated_at', $anio)
                    ->where('expirated_at', '<', date('Y-m-d'))
                    ->where('state', Order::STATE_PENDING)
                    ->whereBetween('quota_number', [self::QUOTA_MIN, self::QUOTA_MAX])
                    ->where('student_id', '<>', 0)
                    ->groupBy('student_id')
                    ->havingRaw('COUNT(*) BETWEEN 1 AND 6');
            }, 'deudores_por_alumno')
            ->select('cuotas_adeudadas', DB::raw('COUNT(*) as cantidad_alumnos'))
            ->groupBy('cuotas_adeudadas')
            ->pluck('cantidad_alumnos', 'cuotas_adeudadas');

        $resumen = [];
        for ($n = 1; $n <= 6; $n++) {
            $resumen[] = [
                'etiqueta' => (string) $n,
                'export_key' => (string) $n,
                'cantidad' => (int) ($counts[$n] ?? 0),
            ];
        }

        $masDeSeis = (int) Order::query()
            ->fromSub(function ($q) use ($anio) {
                $q->from('orders')
                    ->select('student_id')
                    ->whereYear('expirated_at', $anio)
                    ->where('expirated_at', '<', date('Y-m-d'))
                    ->where('state', Order::STATE_PENDING)
                    ->whereBetween('quota_number', [self::QUOTA_MIN, self::QUOTA_MAX])
                    ->where('student_id', '<>', 0)
                    ->groupBy('student_id')
                    ->havingRaw('COUNT(*) > 6');
            }, 'deudores_mas_6')
            ->count();

        $resumen[] = [
            'etiqueta' => 'Más de 6',
            'export_key' => 'mas-de-6',
            'cantidad' => $masDeSeis,
        ];

        return $resumen;
    }

    private function egresadosDeudoresCount(int $anio): int
    {
        $ids = $this->studentIdsEgresados($anio);
        if ($ids->isEmpty()) {
            return 0;
        }

        return (int) Order::query()
            ->fromSub(
                Order::query()
                    ->whereYear('expirated_at', $anio)
                    ->where('expirated_at', '<', date('Y-m-d'))
                    ->where('state', Order::STATE_PENDING)
                    ->whereBetween('quota_number', [self::QUOTA_MIN, self::QUOTA_MAX])
                    ->where('student_id', '<>', 0)
                    ->whereIn('student_id', $ids)
                    ->select('student_id')
                    ->groupBy('student_id'),
                'eg_deudores_cuotas'
            )
            ->count();
    }

    private function studentIdsEgresados(int $anio): Collection
    {
        return DB::connection('eporres')->table('exam_students')
            ->join('students', 'students.id', '=', 'exam_students.student_id')
            ->whereNull('students.deleted_at')
            ->where('students.status', 'Egresado')
            ->where('exam_students.approved', 1)
            ->whereNotNull('exam_students.date_approved')
            ->groupBy('exam_students.student_id')
            ->havingRaw('YEAR(MAX(exam_students.date_approved)) = ?', [$anio])
            ->pluck('exam_students.student_id');
    }

    private function pendingStudentsQuery(int $anio)
    {
        return Order::query()
            ->select('student_id')
            ->whereYear('expirated_at', $anio)
            ->where('expirated_at', '<', date('Y-m-d'))
            ->where('state', Order::STATE_PENDING)
            ->whereBetween('quota_number', [self::QUOTA_MIN, self::QUOTA_MAX])
            ->where('student_id', '<>', 0)
            ->groupBy('student_id');
    }

    private function rowsForStudents(int $anio, Collection $studentIds, bool $soloEgresados = false): array
    {
        if ($studentIds->isEmpty()) {
            return [];
        }

        $orders = Order::with('student.user', 'student.career')
            ->whereIn('student_id', $studentIds)
            ->whereYear('expirated_at', $anio)
            ->where('expirated_at', '<', date('Y-m-d'))
            ->whereBetween('quota_number', [self::QUOTA_MIN, self::QUOTA_MAX])
            ->where('state', Order::STATE_PENDING)
            ->get()
            ->groupBy('student_id');

        $data = [];
        foreach ($orders as $studentId => $grupo) {
            $student = $grupo->first()->student;
            if (! $student || ! $student->user) {
                continue;
            }
            if ($soloEgresados && $student->status !== 'Egresado') {
                continue;
            }
            $cuotas = $grupo->sortBy('expirated_at')->map(function ($order) {
                $mes = (int) date('n', strtotime((string) $order->expirated_at));

                return 'Cuota '.$order->quota_number.' ('.(self::MESES[$mes] ?? $mes).')';
            })->unique()->values()->implode(', ');

            $data[] = [
                '_student_id' => $studentId,
                'Apellido' => $student->last_name,
                'Nombre' => $student->first_name,
                'DNI' => $student->dni,
                'Carrera' => $student->career ? $student->career->short_name : '',
                'Correo Personal' => $student->user->email,
                'Teléfono celular' => $student->cell_phone ?? '',
                'Cuotas adeudadas' => $cuotas,
                'Monto adeudado' => round((float) $grupo->sum('quota_amount'), 2),
            ];
        }

        return $data;
    }
}
