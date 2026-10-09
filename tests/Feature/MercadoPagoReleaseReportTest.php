<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class MercadoPagoReleaseReportTest extends TestCase
{
    public function test_release_report_lists_payments_and_totals(): void
    {
        $user = User::query()
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', ['role_contabilidad', 'role_admin_sistema', 'role_admin_institucion']);
            })
            ->first();
        $this->assertNotNull($user);

        $response = $this->actingAs($user, 'backpack')->get('/admin/accounting-mercadopago-releases');

        $response->assertOk();
        $response->assertSee('Liberación Mercado Pago');
        $response->assertSee('Pendientes:');
        $response->assertSee('Liberados:');
        $response->assertSee('Fecha de liberación');
        $response->assertSee('Payment ID');
        $response->assertSee('accounting-mercadopago-releases', false);
    }

    public function test_release_report_filters_by_status(): void
    {
        $user = User::query()
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', ['role_contabilidad', 'role_admin_sistema', 'role_admin_institucion']);
            })
            ->first();
        $this->assertNotNull($user);

        $response = $this->actingAs($user, 'backpack')->get('/admin/accounting-mercadopago-releases?status=approved&release_status=pending');

        $response->assertOk();
        $response->assertSee('Pendiente');
        $response->assertDontSee('No hay pagos para los filtros seleccionados.');
    }
}
