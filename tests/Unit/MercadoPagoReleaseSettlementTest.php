<?php

namespace Tests\Unit;

use App\Services\MercadoPagoReleaseSettlement;
use App\Services\PaymentSettlement\AccreditationCollectionAlert;
use PHPUnit\Framework\TestCase;

class MercadoPagoReleaseSettlementTest extends TestCase
{
    public function test_coupons_of_the_same_release_date_become_one_entry(): void
    {
        $result = (new MercadoPagoReleaseSettlement)->classify([
            $this->row('10', '2099-08-10', $this->payment('2099-08-20', 100, 97, 3)),
            $this->row('11', '2099-08-11', $this->payment('2099-08-20', 200, 190, 10)),
        ], '2099-08-01', '2099-08-31', '2099-08-31');

        $this->assertCount(1, $result['groups']);
        $group = $result['groups'][0];
        $this->assertSame('mp-release:2099-08-20', $group['key']);
        $this->assertSame(2, $group['orders']);
        $this->assertSame(30000, $group['gross_cents']);
        $this->assertSame(28700, $group['net_cents']);
        $this->assertSame(1300, $group['commission_cents']);
        $this->assertSame(0, $group['interest_cents']);
        $this->assertSame(['2099-08-10' => 10000, '2099-08-11' => 20000], $group['collected_amounts']);
        $this->assertSame([], $result['exclusions']);
    }

    public function test_a_payment_that_does_not_balance_or_has_no_collection_date_stays_out(): void
    {
        $result = (new MercadoPagoReleaseSettlement)->classify([
            $this->row('20', null, $this->payment('2099-08-20', 100, 90, 5)),
            $this->row('21', null, $this->payment('2099-08-20', 50, 48, 2)),
            $this->row('22', '2099-09-01', [
                'money_release_status' => 'pending',
                'money_release_date' => '2099-09-01T10:00:00-03:00',
                'transaction_amount' => 80,
                'transaction_details' => ['net_received_amount' => 77],
                'charges_details' => [[
                    'name' => 'mercadopago_fee',
                    'accounts' => ['from' => 'collector'],
                    'amounts' => ['original' => 3],
                ]],
            ]),
        ], '2099-08-01', '2099-08-31', '2099-08-31');

        $this->assertSame([], $result['groups']);
        $this->assertSame(1, $result['skipped']['amount_mismatch']);
        $this->assertSame(1, $result['skipped']['missing_paid_at']);
        $this->assertSame(['20', '21'], array_column($result['exclusions'], 'payment_id'));
        $this->assertSame(['unbalanced', 'missing_collected'], $result['exclusions'][0]['reasons']);
        $this->assertSame(['missing_collected'], $result['exclusions'][1]['reasons']);

        $alert = new AccreditationCollectionAlert;
        $groups = $alert->homeGroups([
            [
                'menu' => 'Mercado Pago',
                'kind' => 'excluded',
                'collected_on' => [],
                'accredited_on' => ['2099-08-20'],
                'debe_cents' => null,
                'haber_cents' => 0,
                'payment_id' => '20',
                'reasons' => ['unbalanced', 'missing_collected'],
                'gross_cents' => 10000,
                'commission_cents' => 500,
                'net_cents' => 9000,
            ],
            [
                'menu' => 'Mercado Pago',
                'kind' => 'mixed',
                'collected_on' => ['2099-08-10', '2099-08-11'],
                'accredited_on' => ['2099-08-20'],
                'debe_cents' => null,
                'haber_cents' => 0,
            ],
        ]);

        $this->assertSame(
            'El pago 20 no entra al asiento de acreditación de Mercado Pago del 20/08/2099: el neto más la comisión no cierran con el cobro y el cupón no tiene fecha de cobro. Cobro: $100,00. Comisión: $5,00. Neto: $90,00.',
            $groups[0]['excluded'][0]
        );
        $this->assertSame(
            'La acreditación de Mercado Pago del 20/08/2099 incluye cupones cobrados el 10/08/2099 y el 11/08/2099.',
            $groups[0]['mixed'][0]
        );
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array{payment_id: string, paid_on: ?string, payment: array<string, mixed>}
     */
    private function row(string $id, ?string $paidOn, array $payment): array
    {
        return [
            'payment_id' => $id,
            'paid_on' => $paidOn,
            'payment' => $payment,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payment(string $date, float $gross, float $net, float $fee): array
    {
        return [
            'money_release_status' => 'released',
            'money_release_date' => $date.'T10:00:00-03:00',
            'transaction_amount' => $gross,
            'transaction_details' => ['net_received_amount' => $net],
            'charges_details' => [[
                'name' => 'mercadopago_fee',
                'accounts' => ['from' => 'collector'],
                'amounts' => ['original' => $fee],
            ]],
        ];
    }
}
