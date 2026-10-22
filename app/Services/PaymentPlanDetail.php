<?php

namespace App\Services;

use App\Models\Eporres\Order;
use App\Support\Eporres\ReportePagosDiaCriteria;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Detalle de un plan de pago: cuotas que debía, cuotas del plan y totales.
 */
final class PaymentPlanDetail
{
    /**
     * @param  list<array{id: int, debtors: float, interest: float, financing: float, bank: float}>  $stored
     * @return array{plans: list<array<string, mixed>>, missing: list<array{id: int, debtors: float, interest: float, financing: float, bank: float}>}
     */
    public static function present(array $stored): array
    {
        if ($stored === []) {
            return ['plans' => [], 'missing' => []];
        }

        $orderIds = array_values(array_unique(array_map(fn (array $order) => (int) $order['id'], $stored)));
        $planIdsByOrder = self::planIdsByOrder($orderIds);
        $planIds = array_values(array_unique(array_merge(...array_values($planIdsByOrder) ?: [[]])));
        $missing = [];
        foreach ($stored as $order) {
            if (($planIdsByOrder[$order['id']] ?? []) === []) {
                $missing[] = $order;
            }
        }
        if ($planIds === []) {
            return ['plans' => [], 'missing' => $missing];
        }

        $plans = DB::connection('eporres')->table('students_plans')
            ->whereIn('id', $planIds)
            ->orderBy('id')
            ->get()
            ->keyBy(fn ($plan) => (int) $plan->id);
        $students = DB::connection('eporres')->table('students')
            ->whereIn('id', $plans->pluck('student_id')->filter()->unique()->all() ?: [0])
            ->get(['id', 'last_name', 'first_name', 'dni'])
            ->keyBy(fn ($student) => (int) $student->id);
        $pend = DB::connection('eporres')->table('orders_pend_plan')
            ->whereIn('student_plan_id', $planIds)
            ->orderBy('order_id')
            ->get();
        $debtOrders = Order::query()
            ->whereIn('id', $pend->pluck('order_id')->filter()->unique()->all() ?: [0])
            ->get()
            ->keyBy('id');
        $amortizations = DB::connection('eporres')->table('amortizations')
            ->whereIn('student_plan_id', $planIds)
            ->get(['student_plan_id', 'order_id', 'interest_amount']);
        $installments = Order::query()
            ->whereIn('student_plan_id', $planIds)
            ->where('quota_number', '>=', ReportePagosDiaCriteria::PLAN_QUOTA_MIN)
            ->orderBy('quota_number')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Order $order) => (int) $order->student_plan_id);

        $built = [];
        foreach ($planIds as $planId) {
            $plan = $plans->get($planId);
            if ($plan === null) {
                continue;
            }
            $student = $students->get((int) $plan->student_id);
            $debts = self::debts(
                $pend->where('student_plan_id', $planId),
                $debtOrders,
                $amortizations->where('student_plan_id', $planId),
            );
            $schedule = self::schedule($installments->get($planId, collect()), $plan);

            $built[] = [
                'id' => $planId,
                'student' => self::studentName($student),
                'opened_on' => self::date($plan->created_at ?? null),
                'dues' => (int) ($plan->dues ?? 0),
                'debts' => $debts['rows'],
                'debt' => $debts['debt'],
                'late' => $debts['late'],
                'financing' => $debts['financing'],
                'total' => $debts['total'],
                'installments' => $schedule['rows'],
                'installment_total' => $schedule['amount'],
                'paid_total' => $schedule['paid'],
                'pending_total' => $schedule['pending'],
            ];
        }

        return ['plans' => $built, 'missing' => $missing];
    }

    /**
     * @param  list<int>  $orderIds
     * @return array<int, list<int>>
     */
    private static function planIdsByOrder(array $orderIds): array
    {
        $map = [];
        foreach ($orderIds as $orderId) {
            $map[$orderId] = [];
        }

        $orders = DB::connection('eporres')->table('orders')
            ->whereIn('id', $orderIds)
            ->get(['id', 'student_plan_id']);
        foreach ($orders as $order) {
            $planId = (int) $order->student_plan_id;
            if ($planId > 0) {
                $map[(int) $order->id][] = $planId;
            }
        }

        $pend = DB::connection('eporres')->table('orders_pend_plan')
            ->whereIn('order_id', $orderIds)
            ->get(['order_id', 'student_plan_id']);
        foreach ($pend as $row) {
            $planId = (int) $row->student_plan_id;
            if ($planId > 0) {
                $map[(int) $row->order_id][] = $planId;
            }
        }

        foreach ($map as $orderId => $planIds) {
            $map[$orderId] = array_values(array_unique($planIds));
        }

        return $map;
    }

    /**
     * @param  iterable<int, object>  $pend
     * @param  iterable<int, object>  $amortizations
     * @return array{rows: list<array<string, mixed>>, debt: float, late: float, financing: float, total: float}
     */
    private static function debts(iterable $pend, $orders, iterable $amortizations): array
    {
        $byOrder = [];
        foreach ($pend as $row) {
            $orderId = (int) $row->order_id;
            if ($orderId < 1) {
                continue;
            }
            $byOrder[$orderId] ??= ['debt' => 0.0, 'late' => 0.0];
            if (strcasecmp((string) $row->type, 'Interes') === 0) {
                $byOrder[$orderId]['late'] += (float) $row->amount;
            } else {
                $byOrder[$orderId]['debt'] += (float) $row->amount;
            }
        }

        $financing = [];
        $looseFinancing = 0.0;
        foreach ($amortizations as $row) {
            $orderId = (int) $row->order_id;
            $interest = (float) $row->interest_amount;
            if ($orderId > 0 && isset($byOrder[$orderId])) {
                $financing[$orderId] = ($financing[$orderId] ?? 0.0) + $interest;

                continue;
            }
            $looseFinancing += $interest;
        }

        ksort($byOrder);
        $rows = [];
        $debt = 0.0;
        $late = 0.0;
        $financingTotal = 0.0;
        foreach ($byOrder as $orderId => $parts) {
            $order = $orders->get($orderId);
            $rowFinancing = round($financing[$orderId] ?? 0.0, 2);
            $rowDebt = round($parts['debt'], 2);
            $rowLate = round($parts['late'], 2);
            $rows[] = [
                'id' => $orderId,
                'concept' => self::concept($order),
                'quota' => $order ? ReportePagosDiaCriteria::labelNumeroCuota($order) : '—',
                'debt' => $rowDebt,
                'late' => $rowLate,
                'financing' => $rowFinancing,
                'total' => round($rowDebt + $rowLate + $rowFinancing, 2),
            ];
            $debt += $rowDebt;
            $late += $rowLate;
            $financingTotal += $rowFinancing;
        }
        $looseFinancing = round($looseFinancing, 2);
        if ($looseFinancing > 0) {
            $rows[] = [
                'id' => '—',
                'concept' => 'Financiación del plan',
                'quota' => '—',
                'debt' => 0.0,
                'late' => 0.0,
                'financing' => $looseFinancing,
                'total' => $looseFinancing,
            ];
            $financingTotal += $looseFinancing;
        }

        return [
            'rows' => $rows,
            'debt' => round($debt, 2),
            'late' => round($late, 2),
            'financing' => round($financingTotal, 2),
            'total' => round($debt + $late + $financingTotal, 2),
        ];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, amount: float, paid: float, pending: float}
     */
    private static function schedule($installments, object $plan): array
    {
        $rows = [];
        $amount = 0.0;
        $paid = 0.0;
        $pending = 0.0;
        foreach ($installments as $order) {
            $quota = round((float) $order->quota_amount, 2);
            $collected = round((float) $order->amount_paid, 2);
            $isPaid = (string) $order->state === Order::STATE_PAID || $order->paid_at !== null;
            $number = max(1, (int) $order->quota_number - ReportePagosDiaCriteria::PLAN_QUOTA_MIN);
            $dues = (int) ($plan->dues ?? 0);
            $rows[] = [
                'number' => $dues > 0 ? $number.'/'.$dues : (string) $number,
                'due_on' => self::date($order->expirated_at),
                'amount' => $quota,
                'late' => round((float) ($order->surcharge_amount ?? 0), 2),
                'paid' => $collected,
                'state' => $isPaid ? 'Pagada' : 'Pendiente',
                'paid_on' => self::date($order->paid_at),
                'payment_type' => trim((string) ($order->payment_type ?? '')) ?: '—',
            ];
            $amount += $quota;
            $paid += $collected;
            if (! $isPaid) {
                $pending += $quota;
            }
        }

        return [
            'rows' => $rows,
            'amount' => round($amount, 2),
            'paid' => round($paid, 2),
            'pending' => round($pending, 2),
        ];
    }

    private static function concept(?Order $order): string
    {
        if ($order === null) {
            return 'Cuota';
        }
        $quota = (int) $order->quota_number;
        if ($quota === 0) {
            return 'Matrícula';
        }
        if ($quota >= ReportePagosDiaCriteria::PLAN_QUOTA_MIN) {
            return 'Cuota de plan';
        }

        return 'Cuota';
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
