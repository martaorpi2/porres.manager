<?php

namespace Tests\Unit;

use App\Services\PaymentPlanSplit;
use PHPUnit\Framework\TestCase;

class PaymentPlanSplitTest extends TestCase
{
    public function test_opening_debits_the_plan_for_quotas_late_fee_and_financing(): void
    {
        $split = PaymentPlanSplit::opening(400000, 20000, 30000);

        $this->assertSame(450000.0, $split['plan']);
        $this->assertSame(400000.0, $split['debtors']);
        $this->assertSame(20000.0, $split['late']);
        $this->assertSame(30000.0, $split['financing']);
    }

    public function test_collection_moves_financing_to_income_when_the_installment_is_paid(): void
    {
        $split = PaymentPlanSplit::collection(90000, 90000, 0, 6000);

        $this->assertSame(90000.0, $split['paid']);
        $this->assertSame(90000.0, $split['plan']);
        $this->assertSame(0.0, $split['late']);
        $this->assertSame(6000.0, $split['financing']);
        $this->assertSame(0.0, $split['surcharge']);
    }

    public function test_card_surcharge_is_separate_from_the_plan_installment(): void
    {
        $split = PaymentPlanSplit::collection(105000, 90000, 0, 6000);

        $this->assertSame(105000.0, $split['paid']);
        $this->assertSame(90000.0, $split['plan']);
        $this->assertSame(6000.0, $split['financing']);
        $this->assertSame(15000.0, $split['surcharge']);
        $this->assertSame(0.0, $split['late']);
    }

    public function test_late_fee_on_a_plan_installment_is_income_and_increases_the_collection(): void
    {
        $split = PaymentPlanSplit::collection(95000, 90000, 5000, 6000);

        $this->assertSame(95000.0, $split['paid']);
        $this->assertSame(90000.0, $split['plan']);
        $this->assertSame(5000.0, $split['late']);
        $this->assertSame(6000.0, $split['financing']);
        $this->assertSame(0.0, $split['surcharge']);
    }

    public function test_late_fee_and_card_surcharge_can_arrive_together(): void
    {
        $split = PaymentPlanSplit::collection(110000, 90000, 5000, 6000);

        $this->assertSame(90000.0, $split['plan']);
        $this->assertSame(5000.0, $split['late']);
        $this->assertSame(15000.0, $split['surcharge']);
        $this->assertSame(110000.0, round($split['plan'] + $split['late'] + $split['surcharge'], 2));
    }
}
