<?php

namespace Tests\Unit;

use App\Services\PaymentSettlement\NaranjaSettlementFile;
use App\Services\PaymentSettlement\QrSettlementFile;
use App\Services\PaymentSettlement\SolSettlementFile;
use App\Services\Pdf\PdfText;
use PHPUnit\Framework\TestCase;

class PaymentSettlementFileTest extends TestCase
{
    public function test_reads_the_qr_liquidation_and_its_total(): void
    {
        $parsed = (new QrSettlementFile)->read(__DIR__.'/../fixtures/settlements/qr-38221597.txt');

        $this->assertSame('0038221597', $parsed['document']);
        $this->assertCount(18, $parsed['rows']);
        $this->assertSame('1760', $parsed['rows'][0]['coupon']);
        $this->assertSame('2026-10-01', $parsed['rows'][0]['date']);
        $this->assertSame('CLARO PAY', $parsed['rows'][0]['wallet']);
        $this->assertSame(21480000, $parsed['rows'][0]['gross_cents']);
        $this->assertSame(171840, $parsed['rows'][0]['arancel_cents']);
        $this->assertSame(36086, $parsed['rows'][0]['tax_cents']);
        $this->assertSame(21272074, $parsed['rows'][0]['net_cents']);
        $this->assertCount(1, $parsed['groups']);
        $this->assertSame(333428000, $parsed['groups'][0]['gross_cents']);
        $this->assertSame(3227581, $parsed['groups'][0]['commission_cents']);
        $this->assertSame(330200419, $parsed['groups'][0]['net_cents']);
        $this->assertSame('qr:0038221597:2026-10-01', $parsed['groups'][0]['key']);
    }

    public function test_reads_a_second_qr_day(): void
    {
        $parsed = (new QrSettlementFile)->read(__DIR__.'/../fixtures/settlements/qr-38337626.txt');

        $this->assertSame('0038337626', $parsed['document']);
        $this->assertCount(7, $parsed['rows']);
        $this->assertSame('2026-10-02', $parsed['groups'][0]['date']);
        $this->assertSame(134880000, $parsed['groups'][0]['gross_cents']);
        $this->assertSame(133574362, $parsed['groups'][0]['net_cents']);
    }

    public function test_reads_the_naranja_liquidation(): void
    {
        $parsed = (new NaranjaSettlementFile(new PdfText))->read(__DIR__.'/../fixtures/settlements/naranja-12266024.pdf');

        $this->assertSame('M-0632-12266024', $parsed['document']);
        $this->assertCount(14, $parsed['rows']);
        $this->assertSame('2026-07-28', $parsed['rows'][0]['purchase_date']);
        $this->assertSame('69485715-0051', $parsed['rows'][0]['terminal']);
        $this->assertSame(42027284, $parsed['rows'][0]['gross_cents']);
        $this->assertSame(420273, $parsed['rows'][0]['arancel_cents']);
        $this->assertCount(1, $parsed['groups']);
        $this->assertSame('2026-09-14', $parsed['groups'][0]['date']);
        $this->assertSame(561401883, $parsed['groups'][0]['gross_cents']);
        $this->assertSame(16272927, $parsed['groups'][0]['commission_cents']);
        $this->assertSame(45142688, $parsed['groups'][0]['interest_cents']);
        $this->assertSame(499986268, $parsed['groups'][0]['net_cents']);
    }

    public function test_reads_the_sol_monthly_liquidation(): void
    {
        $parsed = (new SolSettlementFile(new PdfText))->read(__DIR__.'/../fixtures/settlements/sol-liqmes.pdf');

        $this->assertSame('00097885', $parsed['document']);
        $this->assertCount(11, $parsed['rows']);
        $this->assertSame('05074666', $parsed['rows'][0]['liquidation']);
        $this->assertSame('2026-09-01', $parsed['rows'][0]['date']);
        $this->assertSame('2026-08-03', $parsed['rows'][0]['presented']);
        $this->assertSame(40132400, $parsed['rows'][0]['gross_cents']);
        $this->assertSame(37218788, $parsed['rows'][0]['net_cents']);

        $gross = array_sum(array_column($parsed['groups'], 'gross_cents'));
        $net = array_sum(array_column($parsed['groups'], 'net_cents'));
        $this->assertSame(393044400, $gross);
        $this->assertSame(360254393, $net);
        $this->assertGreaterThan(1, count($parsed['groups']));
    }
}
