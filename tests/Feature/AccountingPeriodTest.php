<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class AccountingPeriodTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_accounting_menu_lists_period_before_the_journal(): void
    {
        $list = $this->actingAs($this->accountingUser(), 'backpack')->get('/admin/accounting-period');

        $list->assertOk();
        $list->assertSee('Fecha de inicio');
        $list->assertSee('Fecha de fin');
        $html = $list->getContent();
        $this->assertLessThan(strpos($html, 'Cuentas'), strpos($html, 'Periodo'));
        $this->assertLessThan(strpos($html, 'Libro diario'), strpos($html, 'Cuentas'));
    }

    public function test_create_form_asks_for_start_and_end_dates(): void
    {
        $create = $this->actingAs($this->accountingUser(), 'backpack')->get('/admin/accounting-period/create');

        $create->assertOk();
        $create->assertSee('Fecha de inicio');
        $create->assertSee('Fecha de fin');
        $create->assertSee('name="start_date"', false);
        $create->assertSee('name="end_date"', false);
    }

    public function test_accounting_user_can_register_a_period_and_reject_an_overlap(): void
    {
        $this->deleteProbePeriods();

        try {
            $stored = $this->actingAs($this->accountingUser(), 'backpack')->post('/admin/accounting-period', [
                'name' => 'Ejercicio prueba',
                'start_date' => '2098-01-01',
                'end_date' => '2098-12-31',
            ]);
            $stored->assertRedirect('/admin/accounting-period');
            $this->assertTrue(
                AccountingPeriod::query()
                    ->where('name', 'Ejercicio prueba')
                    ->whereDate('start_date', '2098-01-01')
                    ->whereDate('end_date', '2098-12-31')
                    ->exists()
            );

            $this->refreshApplication();
            $this->withoutMiddleware(ValidateCsrfToken::class);

            $overlap = $this->actingAs($this->accountingUser(), 'backpack')
                ->from('/admin/accounting-period/create')
                ->post('/admin/accounting-period', [
                    'start_date' => '2098-06-01',
                    'end_date' => '2098-06-30',
                ]);
            $overlap->assertRedirect('/admin/accounting-period/create');
            $overlap->assertSessionHasErrors('start_date');
        } finally {
            $this->deleteProbePeriods();
        }
    }

    private function accountingUser(): User
    {
        $user = User::query()
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', ['role_contabilidad', 'role_admin_sistema', 'role_admin_institucion']);
            })
            ->first();
        $this->assertNotNull($user);

        return $user;
    }

    private function deleteProbePeriods(): void
    {
        AccountingPeriod::query()
            ->whereDate('start_date', '>=', '2098-01-01')
            ->whereDate('end_date', '<=', '2099-12-31')
            ->delete();
    }
}
