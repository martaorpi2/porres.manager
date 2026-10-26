<?php

namespace Tests\Unit;

use App\Services\PaymentPlanCollectionEntry;
use App\Services\QuotaPaymentAccounts;
use PHPUnit\Framework\TestCase;

class PaymentPlanCollectionEntryTest extends TestCase
{
    public function test_an_on_time_qr_plan_collection_is_the_receivable_against_the_plan(): void
    {
        $lines = PaymentPlanCollectionEntry::lines('QR', 9000000, 0);

        $this->assertSame([
            ['code' => QuotaPaymentAccounts::QR_RECEIVABLE, 'debit' => 9000000, 'credit' => 0, 'memo' => 'QR a cobrar'],
            ['code' => QuotaPaymentAccounts::PLAN, 'debit' => 0, 'credit' => 9000000, 'memo' => 'Plan de pago deudores por cuota'],
        ], $lines);
    }

    public function test_a_late_qr_plan_collection_adds_late_interest_on_the_credit_side(): void
    {
        $lines = PaymentPlanCollectionEntry::lines('QR', 9000000, 500000);

        $this->assertSame([
            ['code' => QuotaPaymentAccounts::QR_RECEIVABLE, 'debit' => 9500000, 'credit' => 0, 'memo' => 'QR a cobrar'],
            ['code' => QuotaPaymentAccounts::PLAN, 'debit' => 0, 'credit' => 9000000, 'memo' => 'Plan de pago deudores por cuota'],
            ['code' => QuotaPaymentAccounts::LATE_INTEREST, 'debit' => 0, 'credit' => 500000, 'memo' => 'Intereses por mora'],
        ], $lines);
    }

    public function test_a_qr_plan_collection_matches_the_on_time_and_late_models(): void
    {
        $onTime = PaymentPlanCollectionEntry::lines('QR', 11000000, 0);
        $this->assertSame([
            ['code' => QuotaPaymentAccounts::QR_RECEIVABLE, 'debit' => 11000000, 'credit' => 0, 'memo' => 'QR a cobrar'],
            ['code' => QuotaPaymentAccounts::PLAN, 'debit' => 0, 'credit' => 11000000, 'memo' => 'Plan de pago deudores por cuota'],
        ], $onTime);

        $late = PaymentPlanCollectionEntry::lines('QR', 11000000, 500000);
        $this->assertSame([
            ['code' => QuotaPaymentAccounts::QR_RECEIVABLE, 'debit' => 11500000, 'credit' => 0, 'memo' => 'QR a cobrar'],
            ['code' => QuotaPaymentAccounts::PLAN, 'debit' => 0, 'credit' => 11000000, 'memo' => 'Plan de pago deudores por cuota'],
            ['code' => QuotaPaymentAccounts::LATE_INTEREST, 'debit' => 0, 'credit' => 500000, 'memo' => 'Intereses por mora'],
        ], $late);
    }

    public function test_mercado_pago_plan_collection_includes_financing_and_the_payment_method_interest(): void
    {
        $lines = PaymentPlanCollectionEntry::lines('Mercado Pago', 12471187, 0, 256600, 1247119);

        $this->assertSame([
            ['code' => QuotaPaymentAccounts::MP_RECEIVABLE, 'debit' => 13718306, 'credit' => 0, 'memo' => 'Mercado Pago a cobrar'],
            ['code' => QuotaPaymentAccounts::PLAN_FINANCING_DEFERRED, 'debit' => 256600, 'credit' => 0, 'memo' => 'Interés financiación a devengar'],
            ['code' => QuotaPaymentAccounts::PLAN, 'debit' => 0, 'credit' => 12471187, 'memo' => 'Plan de pago deudores por cuota'],
            ['code' => QuotaPaymentAccounts::PLAN_FINANCING_INCOME, 'debit' => 0, 'credit' => 256600, 'memo' => 'Intereses ganados por financiación'],
            ['code' => QuotaPaymentAccounts::MP_SURCHARGE_INCOME, 'debit' => 0, 'credit' => 1247119, 'memo' => 'Recargo Mercado Pago'],
        ], $lines);
    }
}
