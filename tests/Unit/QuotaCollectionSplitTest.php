<?php

namespace Tests\Unit;

use App\Services\QuotaCollectionSplit;
use PHPUnit\Framework\TestCase;

class QuotaCollectionSplitTest extends TestCase
{
    public function test_accrual_uses_the_quota_amount(): void
    {
        $amount = QuotaCollectionSplit::accrualAmount((object) ['quota_amount' => '1500.50']);

        $this->assertSame(1500.50, $amount);
    }

    public function test_collection_posts_bank_for_quota_plus_late_fee(): void
    {
        $split = QuotaCollectionSplit::fromRow((object) [
            'quota_amount' => 1000,
            'amount_paid' => 1100,
            'surcharge_amount' => 100,
            'surcharge_amountMP' => 0,
            'surcharge_amountCard' => 0,
        ]);

        $this->assertSame(1000.0, $split['debtors']);
        $this->assertSame(100.0, $split['interest']);
        $this->assertSame(1100.0, $split['bank']);
        $this->assertSame(0.0, $split['unposted']);
    }

    public function test_payment_method_surcharge_stays_out_of_the_entry(): void
    {
        $split = QuotaCollectionSplit::fromRow((object) [
            'quota_amount' => 1000,
            'amount_paid' => 1200,
            'surcharge_amount' => 100,
            'surcharge_amountMP' => 100,
            'surcharge_amountCard' => 0,
        ]);

        $this->assertSame(1000.0, $split['debtors']);
        $this->assertSame(100.0, $split['interest']);
        $this->assertSame(1100.0, $split['bank']);
        $this->assertSame(100.0, $split['unposted']);
    }

    public function test_paid_amount_above_quota_without_stored_surcharge_is_not_interest(): void
    {
        $split = QuotaCollectionSplit::fromRow((object) [
            'quota_amount' => 1000,
            'amount_paid' => 1050,
            'surcharge_amount' => 0,
            'surcharge_amountMP' => 0,
            'surcharge_amountCard' => 0,
        ]);

        $this->assertSame(1000.0, $split['debtors']);
        $this->assertSame(0.0, $split['interest']);
        $this->assertSame(1000.0, $split['bank']);
        $this->assertSame(50.0, $split['unposted']);
    }
}
