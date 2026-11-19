<?php

namespace App\Services;

use App\Models\Eporres\Order;
use App\Support\Eporres\ReportePagosDiaCriteria;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cartera de planes de pago vigentes.
 * En ePorres el plan vigente es students_plans.current = 1.
 * El 2 es el plan caído (expirado por mora) y el 3 es una solicitud todavía pendiente.
 * Además tiene que quedar alguna cuota del plan sin cobrar.
 * Cuota pura, mora y financiación son el armado del alta. El saldo es lo que falta cobrar.
 */
final class PaymentPlanPortfolio
{
    /** Vigente. El mismo valor que usa ePorres en students_plans.current. */
    public const ACTIVE = 1;

    /**
     * @return array{
     *     active: int,
     *     paid_installments: int,
     *     installments: int,
     *     pending: float,
     *     debt: float,
     *     late: float,
     *     financing: float,
     *     plans: list<array{id: int, student: string, opened_on: string, paid: int, dues: int, debt: float, late: float, financing: float, pending: float}>
     * }
     */
    public function report(): array
    {
        $activeIds = $this->activePlanIds();
        if ($activeIds === []) {
            return self::assemble([], [], [], [], []);
        }

        $installments = Order::query()
            ->whereIn('student_plan_id', $activeIds)
            ->where('quota_number', '>=', ReportePagosDiaCriteria::PLAN_QUOTA_MIN)
            ->where(function ($query) {
                $query->whereNull('estado')->orWhere('estado', 1);
            })
            ->get(['id', 'student_plan_id', 'quota_amount', 'state', 'paid_at']);

        $plans = DB::connection('eporres')->table('students_plans')
            ->whereIn('id', $activeIds)
            ->where('current', self::ACTIVE)
            ->get(['id', 'student_id', 'created_at', 'dues', 'current']);

        $studentIds = $plans
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $students = $studentIds === []
            ? collect()
            : DB::connection('eporres')->table('students')
                ->whereIn('id', $studentIds)
                ->get(['id', 'last_name', 'first_name', 'dni']);

        $pend = DB::connection('eporres')->table('orders_pend_plan')
            ->whereIn('student_plan_id', $activeIds)
            ->get(['student_plan_id', 'order_id', 'type', 'amount']);

        $amortizations = DB::connection('eporres')->table('amortizations')
            ->whereIn('student_plan_id', $activeIds)
            ->get(['student_plan_id', 'interest_amount']);

        return self::assemble($plans, $students, $pend, $amortizations, $installments);
    }

    /**
     * @param  iterable<int, object>  $plans
     * @param  iterable<int, object>  $students
     * @param  iterable<int, object>  $pend
     * @param  iterable<int, object>  $amortizations
     * @param  iterable<int, object>  $installments
     * @return array{
     *     active: int,
     *     paid_installments: int,
     *     installments: int,
     *     pending: float,
     *     debt: float,
     *     late: float,
     *     financing: float,
     *     plans: list<array{id: int, student: string, opened_on: string, paid: int, dues: int, debt: float, late: float, financing: float, pending: float}>
     * }
     */
    public static function assemble(iterable $plans, iterable $students, iterable $pend, iterable $amortizations, iterable $installments): array
    {
        $plansById = [];
        foreach ($plans as $plan) {
            $plansById[(int) $plan->id] = $plan;
        }
        $studentsById = [];
        foreach ($students as $student) {
            $studentsById[(int) $student->id] = $student;
        }

        $parts = [];
        foreach ($pend as $row) {
            $planId = (int) $row->student_plan_id;
            $orderId = (int) $row->order_id;
            if ($planId < 1 || $orderId < 1) {
                continue;
            }
            $parts[$planId] ??= ['debt' => 0.0, 'late' => 0.0, 'financing' => 0.0];
            if (strcasecmp((string) $row->type, 'Interes') === 0) {
                $parts[$planId]['late'] += (float) $row->amount;
            } else {
                $parts[$planId]['debt'] += (float) $row->amount;
            }
        }
        foreach ($amortizations as $row) {
            $planId = (int) $row->student_plan_id;
            if ($planId < 1) {
                continue;
            }
            $parts[$planId] ??= ['debt' => 0.0, 'late' => 0.0, 'financing' => 0.0];
            $parts[$planId]['financing'] += (float) ($row->interest_amount ?? 0);
        }

        $schedule = [];
        foreach ($installments as $order) {
            $planId = (int) $order->student_plan_id;
            if ($planId < 1) {
                continue;
            }
            $schedule[$planId] ??= ['paid' => 0, 'dues' => 0, 'pending' => 0.0];
            $schedule[$planId]['dues']++;
            if (self::isPaid($order)) {
                $schedule[$planId]['paid']++;

                continue;
            }
            $schedule[$planId]['pending'] += (float) $order->quota_amount;
        }

        $rows = [];
        foreach ($schedule as $planId => $counts) {
            if ($counts['dues'] === $counts['paid']) {
                continue;
            }
            $plan = $plansById[$planId] ?? null;
            if ($plan === null || (int) ($plan->current ?? 0) !== self::ACTIVE) {
                continue;
            }
            $student = $studentsById[(int) ($plan->student_id ?? 0)] ?? null;
            $split = $parts[$planId] ?? ['debt' => 0.0, 'late' => 0.0, 'financing' => 0.0];
            $rows[] = [
                'id' => $planId,
                'student' => self::studentName($student),
                'opened_on' => self::date($plan->created_at ?? null),
                'paid' => $counts['paid'],
                'dues' => $counts['dues'],
                'debt' => round($split['debt'], 2),
                'late' => round($split['late'], 2),
                'financing' => round($split['financing'], 2),
                'pending' => round($counts['pending'], 2),
            ];
        }

        usort($rows, function (array $a, array $b): int {
            $name = strcasecmp($a['student'], $b['student']);

            return $name !== 0 ? $name : ($a['id'] <=> $b['id']);
        });

        return [
            'active' => count($rows),
            'paid_installments' => array_sum(array_column($rows, 'paid')),
            'installments' => array_sum(array_column($rows, 'dues')),
            'pending' => round(array_sum(array_column($rows, 'pending')), 2),
            'debt' => round(array_sum(array_column($rows, 'debt')), 2),
            'late' => round(array_sum(array_column($rows, 'late')), 2),
            'financing' => round(array_sum(array_column($rows, 'financing')), 2),
            'plans' => $rows,
        ];
    }

    /** @return list<int> */
    private function activePlanIds(): array
    {
        return Order::query()
            ->where('quota_number', '>=', ReportePagosDiaCriteria::PLAN_QUOTA_MIN)
            ->whereIn('student_plan_id', function ($query) {
                $query->select('id')->from('students_plans')->where('current', self::ACTIVE);
            })
            ->where(function ($query) {
                $query->whereNull('estado')->orWhere('estado', 1);
            })
            ->where(function ($query) {
                $query->whereNull('state')->orWhere('state', '!=', Order::STATE_PAID);
            })
            ->whereNull('paid_at')
            ->distinct()
            ->pluck('student_plan_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private static function isPaid(object $order): bool
    {
        if ((string) ($order->state ?? '') === Order::STATE_PAID) {
            return true;
        }

        $paidAt = $order->paid_at ?? null;

        return $paidAt !== null && $paidAt !== '';
    }

    private static function studentName(?object $student): string
    {
        if ($student === null) {
            return '—';
        }

        return trim($student->last_name.', '.$student->first_name.' - '.($student->dni ?: 's/d'), ' ,-');
    }

    private static function date(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return Carbon::parse($value)->format('d/m/Y');
    }
}
