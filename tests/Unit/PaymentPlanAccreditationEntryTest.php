<?php

namespace Tests\Unit;

use App\Services\PaymentPlanAccreditationEntry;
use App\Services\QuotaPaymentAccounts;
use PHPUnit\Framework\TestCase;

class PaymentPlanAccreditationEntryTest extends TestCase
{
    public function test_accreditation_does_not_recognize_financing_again(): void
    {
        $this->assertSame([], PaymentPlanAccreditationEntry::financingLines(1000000));
    }

    public function test_there_is_no_financing_line_when_the_plan_installment_has_none(): void
    {
        $this->assertSame([], PaymentPlanAccreditationEntry::financingLines(0));
    }
}
