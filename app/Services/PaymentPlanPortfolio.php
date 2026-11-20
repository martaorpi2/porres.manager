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
 * Cuota pura y mora son lo que falta cobrar del capital, en la proporción del alta.
 * La financiación es el interés de las cuotas del plan que siguen pendientes.
 */
final class PaymentPlanPortfolio
{
    /** Vigente. El mismo valor que usa ePorres en students_plans.current. */
    public const ACTIVE = 1;

    /** Caído por mora. */
    public const FALLEN = 2;

    /** Completo. */
    public const COMPLETE = 4;

    /** Reemplazado por un plan nuevo. */
    public const REPLACED = 5;

    /**
     * @return array{
     *     active: int,
     *     fallen: int,
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
            $report = self::assemble([], [], [], [], []);

            return $this->withStatusCounts($report);
        }

        $installments = $this->linkedOrders($activeIds, true);
        $originals = $this->linkedOrders($activeIds, false);

        $plans = DB::connection('eporres')->table('students_plans')
            ->whereIn('id', $activeIds)
            ->where('current', self::ACTIVE)
            ->get(['id', 'student_id', 'plan_id', 'created_at', 'dues', 'amount_owed', 'current']);

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
            ->get(['student_plan_id', 'order_id', 'quota_number', 'quota_amount', 'interest_amount', 'capital']);

        $catalogIds = $plans
            ->pluck('plan_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $catalogs = $catalogIds === []
            ? collect()
            : DB::connection('eporres')->table('payment_plans')
                ->whereIn('id', $catalogIds)
                ->get(['id', 'start_date', 'end_date']);

        $report = self::assemble($plans, $students, $pend, $amortizations, $installments, $catalogs, $originals);

        return $this->withStatusCounts($report);
    }

    /**
     * @param  list<int>  $planIds
     */
    private function linkedOrders(array $planIds, bool $planInstallments)
    {
        $query = DB::connection('eporres')->table('orders as o')
            ->leftJoin('tariff_categories as tc', 'tc.id', '=', 'o.tariff_category_id')
            ->whereIn('o.student_plan_id', $planIds)
            ->where(function ($query) {
                $query->whereNull('o.estado')->orWhere('o.estado', 1);
            });
        if ($planInstallments) {
            $query->where('o.quota_number', '>=', ReportePagosDiaCriteria::PLAN_QUOTA_MIN);
        } else {
            $query->where('o.quota_number', '<', ReportePagosDiaCriteria::PLAN_QUOTA_MIN);
        }

        return $query
            ->orderBy('o.quota_number')
            ->orderBy('o.id')
            ->get([
                'o.id',
                'o.student_plan_id',
                'o.quota_number',
                'o.quota_amount',
                'o.amount_paid',
                'o.surcharge_amount',
                'o.state',
                'o.paid_at',
                'o.expirated_at',
                'tc.type as tariff_type',
                'tc.description as concept',
            ]);
    }

    /** @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function withStatusCounts(array $report): array
    {
        $counts = DB::connection('eporres')->table('students_plans')
            ->selectRaw('current, COUNT(*) as total')
            ->whereIn('current', [self::FALLEN, self::COMPLETE, self::REPLACED])
            ->groupBy('current')
            ->pluck('total', 'current');

        $report['fallen'] = (int) ($counts[self::FALLEN] ?? 0);
        $report['completed'] = (int) ($counts[self::COMPLETE] ?? 0);
        $report['replaced'] = (int) ($counts[self::REPLACED] ?? 0);

        return $report;
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
    public static function assemble(iterable $plans, iterable $students, iterable $pend, iterable $amortizations, iterable $installments, iterable $catalogs = [], iterable $originals = []): array
    {
        $plansById = [];
        foreach ($plans as $plan) {
            $plansById[(int) $plan->id] = $plan;
        }
        $studentsById = [];
        foreach ($students as $student) {
            $studentsById[(int) $student->id] = $student;
        }
        $catalogsById = [];
        foreach ($catalogs as $catalog) {
            $catalogsById[(int) $catalog->id] = $catalog;
        }
        $originalsByPlan = [];
        foreach ($originals as $order) {
            $planId = (int) $order->student_plan_id;
            if ($planId > 0) {
                $originalsByPlan[$planId][] = $order;
            }
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
        $amortByOrder = [];
        foreach ($amortizations as $row) {
            $orderId = (int) ($row->order_id ?? 0);
            if ($orderId < 1) {
                continue;
            }
            $slot = $amortByOrder[$orderId] ?? ['interest' => 0.0, 'capital' => null];
            $slot['interest'] += (float) ($row->interest_amount ?? 0);
            if (property_exists($row, 'capital')) {
                $slot['capital'] = (float) ($slot['capital'] ?? 0) + (float) $row->capital;
            }
            $amortByOrder[$orderId] = $slot;
        }

        $schedule = [];
        foreach ($installments as $order) {
            $planId = (int) $order->student_plan_id;
            if ($planId < 1) {
                continue;
            }
            $schedule[$planId] ??= ['paid' => 0, 'dues' => 0, 'pending' => 0.0, 'financing_due' => 0.0, 'surcharge_due' => 0.0, 'orders' => []];
            if (property_exists($order, 'quota_number')) {
                $schedule[$planId]['orders'][] = $order;
            }
            $schedule[$planId]['dues']++;
            if (self::isPaid($order)) {
                $schedule[$planId]['paid']++;

                continue;
            }
            $orderId = (int) ($order->id ?? 0);
            $schedule[$planId]['financing_due'] += (float) ($amortByOrder[$orderId]['interest'] ?? 0);
            $schedule[$planId]['surcharge_due'] += (float) ($order->surcharge_amount ?? 0);
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
            $owed = self::owed(
                $counts['pending'],
                $counts['financing_due'],
                $counts['surcharge_due'],
                $split['debt'],
                $split['late'],
            );
            $agreed = (int) ($plan->dues ?? 0);
            $catalog = $catalogsById[(int) ($plan->plan_id ?? 0)] ?? null;
            $schedule = self::scheduleLines($counts['orders'], $amortByOrder, $split['debt'], $split['late'], $agreed);
            $rows[] = [
                'id' => $planId,
                'student' => self::studentName($student),
                'opened_on' => self::date($plan->created_at ?? null),
                'period' => self::period($catalog),
                'status' => self::statusLabel((int) ($plan->current ?? 0)),
                'agreed_dues' => $agreed > 0 ? $agreed : $counts['dues'],
                'amount_owed' => round((float) ($plan->amount_owed ?? 0), 2),
                'paid' => $counts['paid'],
                'dues' => $counts['dues'],
                'debt' => $owed['debt'],
                'late' => $owed['late'],
                'financing' => $owed['financing'],
                'pending' => $owed['pending'],
                'originals' => array_map(fn (object $order) => self::orderLine($order, $agreed), $originalsByPlan[$planId] ?? []),
                'schedule' => $schedule,
                'paid_plan' => round(array_sum(array_column($schedule, 'paid_plan')), 2),
                'pure_plan' => round(array_sum(array_column($schedule, 'pure_plan')), 2),
                'late_plan' => round(array_sum(array_column($schedule, 'late_plan')), 2),
                'financing_plan' => round(array_sum(array_column($schedule, 'financing_plan')), 2),
                'method_plan' => round(array_sum(array_column($schedule, 'method_plan')), 2),
                'impute_pure' => round(array_sum(array_column($schedule, 'impute_pure')), 2),
                'impute_capitalized' => round(array_sum(array_column($schedule, 'impute_capitalized')), 2),
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
            'agreed_dues' => array_sum(array_column($rows, 'agreed_dues')),
            'amount_owed' => round(array_sum(array_column($rows, 'amount_owed')), 2),
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

        return $paidAt !== null && $paidAt !== '' && ! str_starts_with((string) $paidAt, '0000-00-00');
    }

    /**
     * @return array{debt: float, late: float, financing: float, pending: float}
     */
    private static function owed(float $pendingQuota, float $financing, float $surcharge, float $openingDebt, float $openingLate): array
    {
        $pendingQuota = round(max(0, $pendingQuota), 2);
        $financing = round(min(max(0, $financing), $pendingQuota), 2);
        $capital = round($pendingQuota - $financing, 2);
        $base = round($openingDebt + $openingLate, 2);
        if ($base > 0) {
            $debt = round($capital * ($openingDebt / $base), 2);
            $late = round($capital - $debt, 2);
        } else {
            $debt = $capital;
            $late = 0.0;
        }
        $late = round($late + max(0, $surcharge), 2);

        return [
            'debt' => $debt,
            'late' => $late,
            'financing' => $financing,
            'pending' => round($debt + $late + $financing, 2),
        ];
    }

    private static function statusLabel(int $current): string
    {
        return match ($current) {
            self::ACTIVE => 'Activo',
            self::FALLEN => 'Caído por mora',
            3 => 'Solicitud pendiente',
            4 => 'Completo',
            5 => 'Reemplazado',
            default => 'Inactivo',
        };
    }

    private static function period(?object $catalog): string
    {
        if ($catalog === null) {
            return '—';
        }

        return self::date($catalog->start_date ?? null).' – '.self::date($catalog->end_date ?? null);
    }

    /**
     * @param  list<object>  $orders
     * @param  array<int, array{interest: float, capital: float|null}>  $amortByOrder
     * @return list<array<string, mixed>>
     */
    private static function scheduleLines(array $orders, array $amortByOrder, float $openingDebt, float $openingLate, int $agreedDues): array
    {
        $cuotas = [];
        foreach ($orders as $order) {
            $id = (int) ($order->id ?? 0);
            $amort = $amortByOrder[$id] ?? ['interest' => 0.0, 'capital' => null];
            $quota = round((float) ($order->quota_amount ?? 0), 2);
            $financing = round((float) $amort['interest'], 2);
            $capital = $amort['capital'] === null
                ? round(max(0, $quota - $financing), 2)
                : round(max(0, (float) $amort['capital']), 2);
            $cuotas[] = [
                'order' => $order,
                'order_id' => $id,
                'quota_number' => (int) ($order->quota_number ?? 0),
                'capital' => $capital,
                'financing' => $financing,
            ];
        }

        $shares = PaymentPlanCuotaSplit::impute(array_map(fn (array $cuota) => [
            'order_id' => $cuota['order_id'],
            'quota_number' => $cuota['quota_number'],
            'capital' => $cuota['capital'],
        ], $cuotas), $openingDebt, $openingLate);

        $lines = [];
        foreach ($cuotas as $cuota) {
            $share = $shares[$cuota['order_id']] ?? ['pure' => $cuota['capital'], 'capitalized' => 0.0];
            $paid = round((float) ($cuota['order']->amount_paid ?? 0), 2);
            $late = round(max(0, (float) ($cuota['order']->surcharge_amount ?? 0)), 2);
            $collected = PaymentPlanSplit::collection($paid, (float) ($cuota['order']->quota_amount ?? 0), $late, $cuota['financing']);
            $lines[] = array_merge(self::orderLine($cuota['order'], $agreedDues), [
                'quota_number' => $cuota['quota_number'],
                'paid_plan' => $collected['paid'],
                'pure_plan' => $cuota['capital'],
                'late_plan' => $late,
                'financing_plan' => $cuota['financing'],
                'method_plan' => $collected['surcharge'],
                'impute_pure' => $share['pure'],
                'impute_capitalized' => $share['capitalized'],
            ]);
        }

        return $lines;
    }

    /**
     * @return array{id: int|string, quota: string, concept: string, due_on: string, amount: float, paid_amount: float, state: string, paid_on: string}
     */
    private static function orderLine(object $order, int $agreedDues): array
    {
        $quota = (int) ($order->quota_number ?? 0);
        $isPlan = $quota >= ReportePagosDiaCriteria::PLAN_QUOTA_MIN;
        if ($isPlan) {
            $number = (string) max(1, $quota - ReportePagosDiaCriteria::PLAN_QUOTA_MIN);
            $label = $agreedDues > 0 ? $number.'/'.$agreedDues : $number;
        } elseif ($quota === 0) {
            $label = 'Matrícula';
        } else {
            $label = (string) $quota;
        }
        $concept = trim((string) ($order->concept ?? ''));
        if ($concept === '') {
            $concept = trim((string) ($order->tariff_type ?? ''));
        }
        if ($concept === '') {
            $concept = $isPlan ? 'Cuota de plan' : 'Cuota';
        }

        return [
            'id' => $order->id ?? '—',
            'quota' => $label,
            'concept' => $concept,
            'due_on' => self::date($order->expirated_at ?? null),
            'amount' => round((float) ($order->quota_amount ?? 0), 2),
            'paid_amount' => round((float) ($order->amount_paid ?? 0), 2),
            'state' => self::stateLabel($order),
            'paid_on' => self::isPaid($order) ? self::date($order->paid_at ?? null) : '—',
        ];
    }

    private static function stateLabel(object $order): string
    {
        if (self::isPaid($order)) {
            return 'Pagado';
        }

        return match ((string) ($order->state ?? '')) {
            'App\\States\\Order\\Cancelled' => 'Cancelado',
            default => 'Pendiente',
        };
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
        if ($value === null || $value === '' || str_starts_with((string) $value, '0000-00-00')) {
            return '—';
        }

        return Carbon::parse($value)->format('d/m/Y');
    }
}
