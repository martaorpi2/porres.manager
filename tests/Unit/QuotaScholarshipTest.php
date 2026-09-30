<?php

namespace Tests\Unit;

use App\Services\QuotaScholarship;
use PHPUnit\Framework\TestCase;

class QuotaScholarshipTest extends TestCase
{
    public function test_tariff_reduction_is_the_scholarship(): void
    {
        $amount = QuotaScholarship::amount((object) [
            'quota_amount' => 200000,
            'grant_amount' => 0,
            'discount_amount' => 50000,
            'discount_reason' => 'Reducción arancelaria por razones económicas',
        ]);

        $this->assertSame(50000.0, $amount);
    }

    public function test_legacy_grant_amount_is_the_scholarship(): void
    {
        $amount = QuotaScholarship::amount((object) [
            'quota_amount' => 1500,
            'grant_amount' => 400,
            'discount_amount' => 100,
            'discount_reason' => '',
        ]);

        $this->assertSame(400.0, $amount);
    }

    public function test_scholarship_does_not_exceed_the_quota(): void
    {
        $amount = QuotaScholarship::amount((object) [
            'quota_amount' => 1000,
            'grant_amount' => 0,
            'discount_amount' => 1500,
            'discount_reason' => 'Reducción arancelaria excepcional (art. 14)',
        ]);

        $this->assertSame(1000.0, $amount);
    }
}
