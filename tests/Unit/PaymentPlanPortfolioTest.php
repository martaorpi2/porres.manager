<?php

namespace Tests\Unit;

use App\Models\Eporres\Order;
use App\Services\PaymentPlanPortfolio;
use PHPUnit\Framework\TestCase;

class PaymentPlanPortfolioTest extends TestCase
{
    public function test_active_plan_keeps_opening_amounts_and_unpaid_installments(): void
    {
        $report = PaymentPlanPortfolio::assemble(
            [$this->plan(7, 3, '2026-03-01 10:00:00')],
            [$this->student(3, 'García', 'Ana', '30111222')],
            [
                $this->pend(7, 1, 'Cuota', 1000),
                $this->pend(7, 1, 'Interes', 100),
                $this->pend(7, 2, 'cuota', 500),
                $this->pend(7, 0, 'Cuota', 999),
            ],
            [
                $this->financing(7, 80),
                $this->financing(7, 20),
            ],
            [
                $this->installment(7, 1000, Order::STATE_PAID, '2026-04-01'),
                $this->installment(7, 1000, Order::STATE_PENDING, null),
                $this->installment(7, 500, Order::STATE_PENDING, null),
            ],
        );

        $this->assertSame(1, $report['active']);
        $this->assertSame(1, $report['paid_installments']);
        $this->assertSame(3, $report['installments']);
        $this->assertSame(1500.0, $report['pending']);
        $this->assertSame(1500.0, $report['debt']);
        $this->assertSame(100.0, $report['late']);
        $this->assertSame(100.0, $report['financing']);
        $this->assertSame('García, Ana - 30111222', $report['plans'][0]['student']);
        $this->assertSame('01/03/2026', $report['plans'][0]['opened_on']);
        $this->assertSame(1, $report['plans'][0]['paid']);
        $this->assertSame(3, $report['plans'][0]['dues']);
    }

    public function test_a_paid_plan_stays_out_and_a_payment_date_counts_as_collected(): void
    {
        $report = PaymentPlanPortfolio::assemble(
            [
                $this->plan(2, 9, '2026-01-15'),
                $this->plan(4, 8, null),
            ],
            [
                $this->student(9, 'López', 'Bruno', ''),
                $this->student(8, 'Álvarez', 'Clara', '27999888'),
            ],
            [
                $this->pend(4, 10, 'interes', 40),
            ],
            [
                $this->financing(4, 15.5),
            ],
            [
                $this->installment(2, 800, Order::STATE_PAID, '2026-02-01'),
                $this->installment(2, 800, Order::STATE_PAID, '2026-03-01'),
                $this->installment(4, 700, Order::STATE_PENDING, '2026-05-02'),
                $this->installment(4, 650, Order::STATE_PENDING, null),
            ],
        );

        $this->assertSame(1, $report['active']);
        $this->assertSame(4, $report['plans'][0]['id']);
        $this->assertSame('Álvarez, Clara - 27999888', $report['plans'][0]['student']);
        $this->assertSame('—', $report['plans'][0]['opened_on']);
        $this->assertSame(1, $report['paid_installments']);
        $this->assertSame(2, $report['installments']);
        $this->assertSame(650.0, $report['pending']);
        $this->assertSame(0.0, $report['debt']);
        $this->assertSame(40.0, $report['late']);
        $this->assertSame(15.5, $report['financing']);
    }

    public function test_plans_are_ordered_by_student_and_an_empty_portfolio_is_zero(): void
    {
        $report = PaymentPlanPortfolio::assemble(
            [
                $this->plan(11, 1, '2026-06-01'),
                $this->plan(12, 2, '2026-06-02'),
            ],
            [
                $this->student(1, 'Zuluaga', 'Eva', '1'),
                $this->student(2, 'Acosta', 'Leo', '2'),
            ],
            [],
            [],
            [
                $this->installment(11, 10, Order::STATE_PENDING, null),
                $this->installment(12, 20, Order::STATE_PENDING, null),
            ],
        );

        $this->assertSame([12, 11], array_column($report['plans'], 'id'));
        $this->assertSame(30.0, $report['pending']);

        $closed = PaymentPlanPortfolio::assemble(
            [
                $this->plan(20, 1, '2025-08-08', 2),
                $this->plan(21, 2, '2026-07-24', 3),
            ],
            [
                $this->student(1, 'Pérez', 'Nora', '3'),
                $this->student(2, 'Sosa', 'Iván', '4'),
            ],
            [],
            [],
            [
                $this->installment(20, 400, Order::STATE_PENDING, null),
                $this->installment(21, 500, Order::STATE_PENDING, null),
            ],
        );
        $this->assertSame([], $closed['plans']);
        $this->assertSame(0, $closed['active']);
        $this->assertSame(0.0, $closed['pending']);

        $empty = PaymentPlanPortfolio::assemble([], [], [], [], []);
        $this->assertSame(0, $empty['active']);
        $this->assertSame(0, $empty['paid_installments']);
        $this->assertSame(0.0, $empty['pending']);
        $this->assertSame([], $empty['plans']);
    }

    public function test_each_plan_carries_the_eporres_list_and_its_orders(): void
    {
        $report = PaymentPlanPortfolio::assemble(
            [(object) [
                'id' => 93,
                'student_id' => 3,
                'plan_id' => 4,
                'dues' => 4,
                'amount_owed' => 817560,
                'current' => PaymentPlanPortfolio::ACTIVE,
                'created_at' => '2026-07-02',
            ]],
            [$this->student(3, 'García', 'Ana', '30111222')],
            [],
            [],
            [
                (object) [
                    'id' => 501,
                    'student_plan_id' => 93,
                    'quota_number' => 101,
                    'quota_amount' => 200000,
                    'amount_paid' => 200000,
                    'state' => Order::STATE_PAID,
                    'paid_at' => '2026-08-10',
                    'expirated_at' => '2026-08-14',
                    'concept' => 'Plan de pago',
                    'tariff_type' => 'Plan Pago',
                ],
                (object) [
                    'id' => 502,
                    'student_plan_id' => 93,
                    'quota_number' => 102,
                    'quota_amount' => 200000,
                    'amount_paid' => 0,
                    'state' => Order::STATE_PENDING,
                    'paid_at' => null,
                    'expirated_at' => '2026-09-10',
                    'concept' => 'Plan de pago',
                    'tariff_type' => 'Plan Pago',
                ],
            ],
            [(object) ['id' => 4, 'start_date' => '2026-06-18', 'end_date' => '2026-10-30']],
            [
                (object) [
                    'id' => 80,
                    'student_plan_id' => 93,
                    'quota_number' => 3,
                    'quota_amount' => 180000,
                    'amount_paid' => 0,
                    'state' => Order::STATE_PENDING,
                    'paid_at' => '0000-00-00',
                    'expirated_at' => '2026-05-10',
                    'concept' => 'Tecnicatura',
                    'tariff_type' => 'Arancel Mensual',
                ],
            ],
        );

        $plan = $report['plans'][0];
        $this->assertSame('18/06/2026 – 30/10/2026', $plan['period']);
        $this->assertSame('Activo', $plan['status']);
        $this->assertSame(4, $plan['agreed_dues']);
        $this->assertSame(817560.0, $plan['amount_owed']);
        $this->assertSame('3', $plan['originals'][0]['quota']);
        $this->assertSame('Tecnicatura', $plan['originals'][0]['concept']);
        $this->assertSame('Pendiente', $plan['originals'][0]['state']);
        $this->assertSame('1/4', $plan['schedule'][0]['quota']);
        $this->assertSame('Pagado', $plan['schedule'][0]['state']);
        $this->assertSame('2/4', $plan['schedule'][1]['quota']);
        $this->assertSame(200000.0, $plan['pending']);
    }

    private function plan(int $id, int $studentId, ?string $createdAt, int $current = PaymentPlanPortfolio::ACTIVE): object
    {
        return (object) [
            'id' => $id,
            'student_id' => $studentId,
            'created_at' => $createdAt,
            'current' => $current,
        ];
    }

    private function student(int $id, string $lastName, string $firstName, string $dni): object
    {
        return (object) [
            'id' => $id,
            'last_name' => $lastName,
            'first_name' => $firstName,
            'dni' => $dni,
        ];
    }

    private function pend(int $planId, int $orderId, string $type, float $amount): object
    {
        return (object) [
            'student_plan_id' => $planId,
            'order_id' => $orderId,
            'type' => $type,
            'amount' => $amount,
        ];
    }

    private function financing(int $planId, float $interest): object
    {
        return (object) [
            'student_plan_id' => $planId,
            'interest_amount' => $interest,
        ];
    }

    private function installment(int $planId, float $quota, string $state, ?string $paidAt): object
    {
        return (object) [
            'student_plan_id' => $planId,
            'quota_amount' => $quota,
            'state' => $state,
            'paid_at' => $paidAt,
        ];
    }
}
