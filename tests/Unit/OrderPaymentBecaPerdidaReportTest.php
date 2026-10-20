<?php

namespace Tests\Unit;

use App\Models\Eporres\Order;
use App\Support\Eporres\OrderPaymentBreakdown;
use App\Support\Eporres\OrderPaymentLines;
use App\Support\Eporres\ReportePagosDiaCriteria;
use Tests\TestCase;

class OrderPaymentBecaPerdidaReportTest extends TestCase
{
    public function test_pago_fuera_de_termino_devuelve_la_beca_a_la_cuota_pura(): void
    {
        $order = $this->makeOrder([
            'quota_amount' => 200000,
            'discount_amount' => 50000,
            'percentage_discount' => 25,
            'discount_reason' => ReportePagosDiaCriteria::DISCOUNT_REASON,
            'surcharge_amount' => 400,
            'amount_paid' => 200400,
            'payment_type' => 'BSE',
            'expirated_at' => '2026-08-10',
            'paid_at' => '2026-08-20',
        ]);

        $factura = OrderPaymentBreakdown::amounts($order);
        $this->assertSame(150000.0, $factura['net_pure']);
        $this->assertSame(50000.0, $factura['interest_other']);
        $this->assertFalse($factura['beca_perdida']);

        $reporte = OrderPaymentBreakdown::amountsForReporte($order);
        $this->assertSame(200000.0, $reporte['net_pure']);
        $this->assertSame(400.0, $reporte['interest_late_fee']);
        $this->assertSame(0.0, $reporte['interest_other']);
        $this->assertTrue($reporte['beca_perdida']);

        $lines = OrderPaymentLines::forOrder($order, true);
        $this->assertSame(200000.0, $lines[0]['order_pure']);
        $this->assertSame(400.0, $lines[0]['order_interest_late']);
        $this->assertSame(0.0, $lines[0]['interest_other']);
        $this->assertSame(200400.0, OrderPaymentLines::totalLinea($lines[0]));
        $this->assertTrue($lines[0]['beca_perdida']);

        $resumen = OrderPaymentLines::resumenPorMedio(collect([$order]));
        $this->assertEqualsWithDelta(200000.0, $resumen['BSE']['cuota_pura'], 0.001);
        $this->assertEqualsWithDelta(400.0, $resumen['BSE']['interes_mora'], 0.001);
        $this->assertEqualsWithDelta(0.0, $resumen['BSE']['otros_intereses'], 0.001);
        $this->assertEqualsWithDelta(200400.0, $resumen['BSE']['total'], 0.001);

        $this->assertSame('Beca 25% perdida', ReportePagosDiaCriteria::etiquetaBecaPerdida($order));
        $this->assertSame(
            'Beca 25% perdida. Se cobró la cuota original de $200.000,00 porque el beneficio se perdió por pago fuera de término.',
            ReportePagosDiaCriteria::notaReporte($order, collect(), true, 200000)
        );
    }

    public function test_pago_en_termino_conserva_la_cuota_con_beca(): void
    {
        $order = $this->makeOrder([
            'quota_amount' => 200000,
            'discount_amount' => 50000,
            'percentage_discount' => 25,
            'discount_reason' => ReportePagosDiaCriteria::DISCOUNT_REASON,
            'amount_paid' => 150000,
            'payment_type' => 'BSE',
            'expirated_at' => '2026-08-10',
            'paid_at' => '2026-08-10',
        ]);

        $reporte = OrderPaymentBreakdown::amountsForReporte($order);

        $this->assertSame(150000.0, $reporte['net_pure']);
        $this->assertSame(0.0, $reporte['interest_other']);
        $this->assertFalse($reporte['beca_perdida']);
        $this->assertSame(
            'Becas 25%',
            ReportePagosDiaCriteria::notaReporte($order, collect())
        );
    }

    public function test_fuera_de_termino_pero_cobro_con_beca_no_marca_cuota_original(): void
    {
        $order = $this->makeOrder([
            'quota_amount' => 200000,
            'discount_amount' => 50000,
            'percentage_discount' => 25,
            'discount_reason' => ReportePagosDiaCriteria::DISCOUNT_REASON,
            'surcharge_amount' => 400,
            'amount_paid' => 150400,
            'payment_type' => 'BSE',
            'expirated_at' => '2026-08-10',
            'paid_at' => '2026-08-20',
        ]);

        $reporte = OrderPaymentBreakdown::amountsForReporte($order);

        $this->assertSame(150000.0, $reporte['net_pure']);
        $this->assertSame(400.0, $reporte['interest_late_fee']);
        $this->assertSame(0.0, $reporte['interest_other']);
        $this->assertFalse($reporte['beca_perdida']);
    }

    public function test_recargo_de_tarjeta_sigue_en_otros_intereses(): void
    {
        $order = $this->makeOrder([
            'quota_amount' => 200000,
            'discount_amount' => 50000,
            'percentage_discount' => 25,
            'discount_reason' => ReportePagosDiaCriteria::DISCOUNT_REASON,
            'surcharge_amount' => 400,
            'surcharge_amountCard' => 3000,
            'amount_paid' => 203400,
            'payment_type' => 'Tarjeta de Crédito',
            'expirated_at' => '2026-08-10',
            'paid_at' => '2026-08-20',
        ]);

        $reporte = OrderPaymentBreakdown::amountsForReporte($order);

        $this->assertSame(200000.0, $reporte['net_pure']);
        $this->assertSame(400.0, $reporte['interest_late_fee']);
        $this->assertSame(3000.0, $reporte['interest_other']);
        $this->assertTrue($reporte['beca_perdida']);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function makeOrder(array $attrs): Order
    {
        $order = new Order();
        $order->forceFill(array_merge([
            'id' => 65319,
            'quota_number' => 8,
            'quota_amount' => 0,
            'amount_paid' => 0,
            'positive_balance' => 0,
            'negative_balance' => 0,
            'discount_amount' => 0,
            'percentage_discount' => 0,
            'grant_amount' => 0,
            'surcharge_amount' => 0,
            'surcharge_amountMP' => 0,
            'surcharge_amountCard' => 0,
            'payment_type' => 'BSE',
        ], $attrs));
        $order->syncOriginal();
        $order->setRelation('splitPayment', null);
        $order->setRelation('amortization', null);

        return $order;
    }
}
