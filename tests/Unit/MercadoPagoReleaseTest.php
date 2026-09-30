<?php

namespace Tests\Unit;

use App\Services\MercadoPagoRelease;
use PHPUnit\Framework\TestCase;

class MercadoPagoReleaseTest extends TestCase
{
    public function test_released_payment_splits_net_and_commission(): void
    {
        $release = MercadoPagoRelease::fromPayment([
            'money_release_status' => 'released',
            'money_release_date' => '2026-09-05T23:30:00.000-04:00',
            'transaction_amount' => 220000,
            'transaction_details' => ['net_received_amount' => 209018.89],
            'charges_details' => [
                [
                    'name' => 'mercadopago_fee',
                    'accounts' => ['from' => 'collector', 'to' => 'mp'],
                    'amounts' => ['original' => 10981.11],
                ],
                [
                    'name' => 'financing_fee',
                    'accounts' => ['from' => 'payer', 'to' => 'mp'],
                    'amounts' => ['original' => 53570],
                ],
            ],
        ]);

        $this->assertSame(220000.0, $release['gross']);
        $this->assertSame(209018.89, $release['net']);
        $this->assertSame(10981.11, $release['commission']);
        $this->assertSame('2026-09-06', $release['release_date']);
    }

    public function test_pending_release_is_ignored(): void
    {
        $release = MercadoPagoRelease::fromPayment([
            'money_release_status' => 'pending',
            'money_release_date' => '2026-09-18T09:33:39.000-04:00',
            'transaction_amount' => 220000,
            'transaction_details' => ['net_received_amount' => 209018.89],
            'charges_details' => [[
                'name' => 'mercadopago_fee',
                'accounts' => ['from' => 'collector'],
                'amounts' => ['original' => 10981.11],
            ]],
        ]);

        $this->assertNull($release);
    }

    public function test_pending_release_counts_once_the_date_has_arrived(): void
    {
        $release = MercadoPagoRelease::fromPayment([
            'money_release_status' => 'pending',
            'money_release_date' => '2026-09-18T09:33:39.000-04:00',
            'transaction_amount' => 220000,
            'transaction_details' => ['net_received_amount' => 209018.89],
            'charges_details' => [[
                'name' => 'mercadopago_fee',
                'accounts' => ['from' => 'collector'],
                'amounts' => ['original' => 10981.11],
            ]],
        ], '2026-09-18');

        $this->assertSame('2026-09-18', $release['release_date']);
        $this->assertSame(10981.11, $release['commission']);
    }

    public function test_fee_that_does_not_explain_the_net_is_ignored(): void
    {
        $release = MercadoPagoRelease::fromPayment([
            'money_release_status' => 'released',
            'money_release_date' => '2026-09-05T10:05:34.000-04:00',
            'transaction_amount' => 220000,
            'transaction_details' => ['net_received_amount' => 200000],
            'charges_details' => [[
                'name' => 'mercadopago_fee',
                'accounts' => ['from' => 'collector'],
                'amounts' => ['original' => 1000],
            ]],
        ]);

        $this->assertNull($release);
    }
}
