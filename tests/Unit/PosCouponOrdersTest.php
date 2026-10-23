<?php

namespace Tests\Unit;

use App\Services\PosCouponOrders;
use PHPUnit\Framework\TestCase;

class PosCouponOrdersTest extends TestCase
{
    public function test_the_pos_coupon_ignores_leading_zeros(): void
    {
        $this->assertSame('4512', PosCouponOrders::normalize('004512'));
        $this->assertSame('4512', PosCouponOrders::normalize('4512'));
        $this->assertSame('', PosCouponOrders::normalize('  '));
        $this->assertSame('0', PosCouponOrders::normalize('000'));
    }

    public function test_card_and_qr_are_pos_payments_and_plan_installments_stay_apart(): void
    {
        $this->assertTrue(PosCouponOrders::isPos('QR'));
        $this->assertTrue(PosCouponOrders::isPos('Tarjeta de Débito'));
        $this->assertTrue(PosCouponOrders::isPos('Tarjeta de Crédito'));
        $this->assertFalse(PosCouponOrders::isPos('Mercado Pago'));
        $this->assertFalse(PosCouponOrders::isPlanInstallment(3));
        $this->assertTrue(PosCouponOrders::isPlanInstallment(101));
    }
}
