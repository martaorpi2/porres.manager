<?php

namespace Tests\Unit;

use App\Services\PaymentPlanAccreditationEntry;
use App\Services\QuotaPaymentAccounts;
use PHPUnit\Framework\TestCase;

class PaymentPlanAccreditationEntryTest extends TestCase
{
    public function test_financing_is_recognized_on_the_accreditation(): void
    {
        $lines = PaymentPlanAccreditationEntry::financingLines(1000000);

        $this->assertSame([
            [QuotaPaymentAccounts::PLAN_FINANCING_DEFERRED, 1000000, 0, 'Interés financiación a devengar'],
            [QuotaPaymentAccounts::PLAN_FINANCING_INCOME, 0, 1000000, 'Intereses ganados por financiación'],
        ], array_map(fn (array $line) => [$line['code'], $line['debit'], $line['credit'], $line['memo']], $lines));
    }

    public function test_there_is_no_financing_line_when_the_plan_installment_has_none(): void
    {
        $this->assertSame([], PaymentPlanAccreditationEntry::financingLines(0));
    }
}
