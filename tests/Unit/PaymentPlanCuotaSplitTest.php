<?php

namespace Tests\Unit;

use App\Services\PaymentPlanCuotaSplit;
use PHPUnit\Framework\TestCase;

class PaymentPlanCuotaSplitTest extends TestCase
{
    public function test_each_installment_capital_keeps_the_opening_mix_and_the_last_one_absorbs_cents(): void
    {
        $shares = PaymentPlanCuotaSplit::impute([
            ['order_id' => 1, 'quota_number' => 1, 'capital' => 10.00],
            ['order_id' => 2, 'quota_number' => 2, 'capital' => 10.00],
            ['order_id' => 3, 'quota_number' => 3, 'capital' => 10.01],
        ], 20.00, 10.01);

        $this->assertSame(6.66, $shares[1]['pure']);
        $this->assertSame(3.34, $shares[1]['capitalized']);
        $this->assertSame(6.66, $shares[2]['pure']);
        $this->assertSame(3.34, $shares[2]['capitalized']);
        $this->assertSame(6.68, $shares[3]['pure']);
        $this->assertSame(3.33, $shares[3]['capitalized']);
        $this->assertSame(20.00, round($shares[1]['pure'] + $shares[2]['pure'] + $shares[3]['pure'], 2));
        $this->assertSame(10.01, round($shares[1]['capitalized'] + $shares[2]['capitalized'] + $shares[3]['capitalized'], 2));
    }

    public function test_variable_financing_capital_is_imputed_in_the_same_proportion(): void
    {
        $shares = PaymentPlanCuotaSplit::impute([
            ['order_id' => 11, 'quota_number' => 1, 'capital' => 163171.03],
            ['order_id' => 12, 'quota_number' => 2, 'capital' => 163850.90],
            ['order_id' => 13, 'quota_number' => 3, 'capital' => 164533.62],
            ['order_id' => 14, 'quota_number' => 4, 'capital' => 165219.17],
            ['order_id' => 15, 'quota_number' => 5, 'capital' => 165907.59],
            ['order_id' => 16, 'quota_number' => 6, 'capital' => 166598.87],
        ], 800000.00, 189281.18);

        $pure = 0.0;
        $capitalized = 0.0;
        $previous = 0.0;
        foreach ([11, 12, 13, 14, 15, 16] as $orderId) {
            $this->assertGreaterThan($previous, $shares[$orderId]['pure']);
            $previous = $shares[$orderId]['pure'];
            $pure += $shares[$orderId]['pure'];
            $capitalized += $shares[$orderId]['capitalized'];
        }

        $this->assertSame(800000.00, round($pure, 2));
        $this->assertSame(189281.18, round($capitalized, 2));
        $this->assertSame(163171.03, round($shares[11]['pure'] + $shares[11]['capitalized'], 2));
        $this->assertSame(166598.87, round($shares[16]['pure'] + $shares[16]['capitalized'], 2));
    }

    public function test_without_capitalized_interest_the_whole_capital_is_original_quota(): void
    {
        $shares = PaymentPlanCuotaSplit::impute([
            ['order_id' => 4, 'quota_number' => 2, 'capital' => 40],
            ['order_id' => 5, 'quota_number' => 1, 'capital' => 60],
        ], 100, 0);

        $this->assertSame(60.0, $shares[5]['pure']);
        $this->assertSame(0.0, $shares[5]['capitalized']);
        $this->assertSame(40.0, $shares[4]['pure']);
        $this->assertSame(0.0, $shares[4]['capitalized']);
    }

    public function test_opening_adds_quota_and_capitalized_interest_and_skips_loose_rows(): void
    {
        $opening = PaymentPlanCuotaSplit::opening([
            (object) ['order_id' => 1, 'type' => 'Cuota', 'amount' => 1000],
            (object) ['order_id' => 1, 'type' => 'Interes', 'amount' => 80],
            (object) ['order_id' => 2, 'type' => 'interes', 'amount' => 20],
            (object) ['order_id' => 0, 'type' => 'Cuota', 'amount' => 999],
        ]);

        $this->assertSame(1000.0, $opening['debt']);
        $this->assertSame(100.0, $opening['late']);
    }
}
