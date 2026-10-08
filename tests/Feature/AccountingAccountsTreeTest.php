<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\User;
use Tests\TestCase;

class AccountingAccountsTreeTest extends TestCase
{
    public function test_accounts_page_shows_the_chart_as_a_tree_and_saves_from_it(): void
    {
        $user = User::query()->get()->first(fn (User $candidate) => $candidate->canViewAccounting() && ! $candidate->hasAdministradoraInstitucionRole());
        $this->assertNotNull($user);

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $page = $this->actingAs($user, 'backpack')->get('/admin/accounting-account');
        $page->assertOk();
        $page->assertSee('Plan de cuentas');
        $page->assertSee('Agregar rubro');
        $page->assertSee('Agregar subrubro');
        $page->assertSee('Tipo de saldo');
        $page->assertSee('Quitar');
        $page->assertSee('Mover las cuentas hijas a otro padre');
        $page->assertSee('Eliminar también las cuentas hijas');
        $page->assertSee('id="account-action"', false);
        $page->assertDontSee('id="mode-create"', false);
        $page->assertDontSee('id="mode-edit"', false);
        $page->assertSee('ACTIVO');
        $page->assertSee('class="account-children" hidden', false);

        $html = $page->getContent();
        $alta = strpos($html, 'accounting-period');
        $cuentas = strpos($html, 'accounting-account');
        $diario = strpos($html, 'accounting-journal');
        $this->assertNotFalse($alta);
        $this->assertNotFalse($cuentas);
        $this->assertNotFalse($diario);
        $this->assertLessThan($cuentas, $alta);
        $this->assertLessThan($diario, $cuentas);

        AccountingAccount::query()->where('code', '11101999')->delete();

        try {
            $created = $this->actingAs($user, 'backpack')->post('/admin/accounting-account/guardar', [
                'code' => '11101999',
                'name' => 'CUENTA PRUEBA ARBOL',
                'account_type' => 'activo',
                'balance_nature' => 'deudor',
                'is_active' => '1',
                'parent_code' => '11101000',
            ]);
            $created->assertRedirect('/admin/accounting-account?code=11101999');

            $withNew = $this->actingAs($user, 'backpack')->get('/admin/accounting-account?code=11101999');
            $withNew->assertOk();
            $withNew->assertSee('CUENTA PRUEBA ARBOL');

            $createdAccount = AccountingAccount::query()->where('code', '11101999')->first();
            $this->assertNotNull($createdAccount);
            $this->assertSame('deudor', $createdAccount->balance_nature);
            $this->assertFalse((bool) $createdAccount->is_grouping);

            $updated = $this->actingAs($user, 'backpack')->post('/admin/accounting-account/guardar', [
                'id' => $createdAccount->id,
                'code' => '11101999',
                'name' => 'CUENTA PRUEBA MODIFICADA',
                'account_type' => 'activo',
                'balance_nature' => 'acreedor',
                'is_active' => '1',
                'parent_code' => '11101000',
            ]);
            $updated->assertRedirect('/admin/accounting-account?code=11101999');
            $this->assertSame('CUENTA PRUEBA MODIFICADA', $createdAccount->fresh()->name);
            $this->assertSame('acreedor', $createdAccount->fresh()->balance_nature);

            $grouped = $this->actingAs($user, 'backpack')->from('/admin/accounting-account')->post('/admin/accounting-account/guardar', [
                'code' => '10000000',
                'name' => 'NO DEBERIA GUARDARSE',
                'account_type' => 'activo',
                'balance_nature' => 'deudor',
                'is_active' => '1',
            ]);
            $grouped->assertRedirect('/admin/accounting-account');
            $grouped->assertSessionHasErrors('code');
            $this->assertTrue(
                AccountingAccount::query()->where('code', '10000000')->where('is_grouping', true)->exists()
            );
        } finally {
            AccountingAccount::query()->where('code', '11101999')->delete();
        }
    }

    public function test_changing_or_removing_a_parent_asks_what_to_do_with_children(): void
    {
        $user = User::query()->get()->first(fn (User $candidate) => $candidate->canViewAccounting() && ! $candidate->hasAdministradoraInstitucionRole());
        $this->assertNotNull($user);
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $codes = ['60000000', '60100000', '70000000', '70100000', '80000000', '80100000', '90000000', '90100000'];
        AccountingAccount::query()->whereIn('code', $codes)->delete();

        try {
            $parent = AccountingAccount::query()->create([
                'code' => '70000000',
                'name' => 'RUBRO PRUEBA',
                'account_type' => 'activo',
                'is_active' => true,
                'is_grouping' => true,
            ]);
            AccountingAccount::query()->create([
                'code' => '70100000',
                'name' => 'HIJA PRUEBA',
                'account_type' => 'activo',
                'is_active' => true,
                'is_grouping' => false,
            ]);

            $renamed = $this->actingAs($user, 'backpack')->post('/admin/accounting-account/guardar', [
                'id' => $parent->id,
                'code' => '80000000',
                'name' => 'RUBRO PRUEBA',
                'account_type' => 'activo',
                'is_active' => '1',
            ]);
            $renamed->assertRedirect('/admin/accounting-account?code=80000000');
            $this->assertSame('80100000', AccountingAccount::query()->where('name', 'HIJA PRUEBA')->value('code'));

            AccountingAccount::query()->create([
                'code' => '60000000',
                'name' => 'OTRO RUBRO',
                'account_type' => 'activo',
                'is_active' => true,
                'is_grouping' => true,
            ]);
            $parent->refresh();
            $moved = $this->actingAs($user, 'backpack')->post('/admin/accounting-account/quitar', [
                'id' => $parent->id,
                'children_action' => 'move',
                'target_parent_code' => '60000000',
            ]);
            $moved->assertRedirect('/admin/accounting-account?code=60000000');
            $this->assertNull(AccountingAccount::query()->where('code', '80000000')->first());
            $this->assertSame('60100000', AccountingAccount::query()->where('name', 'HIJA PRUEBA')->value('code'));

            $doomed = AccountingAccount::query()->create([
                'code' => '90000000',
                'name' => 'RUBRO A BORRAR',
                'account_type' => 'activo',
                'is_active' => true,
                'is_grouping' => true,
            ]);
            AccountingAccount::query()->create([
                'code' => '90100000',
                'name' => 'HIJA A BORRAR',
                'account_type' => 'activo',
                'is_active' => true,
                'is_grouping' => false,
            ]);
            $deleted = $this->actingAs($user, 'backpack')->post('/admin/accounting-account/quitar', [
                'id' => $doomed->id,
                'children_action' => 'delete',
            ]);
            $deleted->assertRedirect();
            $this->assertNull(AccountingAccount::query()->where('name', 'HIJA A BORRAR')->first());
            $this->assertNull(AccountingAccount::query()->where('code', '90000000')->first());
        } finally {
            AccountingAccount::query()->whereIn('code', $codes)->orWhereIn('name', [
                'RUBRO PRUEBA',
                'HIJA PRUEBA',
                'OTRO RUBRO',
                'RUBRO A BORRAR',
                'HIJA A BORRAR',
            ])->delete();
        }
    }

    public function test_a_last_level_account_does_not_accept_a_child(): void
    {
        $user = User::query()->get()->first(fn (User $candidate) => $candidate->canViewAccounting() && ! $candidate->hasAdministradoraInstitucionRole());
        $this->assertNotNull($user);
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        AccountingAccount::query()->whereIn('code', ['70000000', '70100000', '70100001'])->delete();

        try {
            AccountingAccount::query()->create([
                'code' => '70000000',
                'name' => 'RUBRO PRUEBA',
                'account_type' => 'activo',
                'is_active' => true,
                'is_grouping' => true,
            ]);
            AccountingAccount::query()->create([
                'code' => '70100000',
                'name' => 'HIJA PRUEBA',
                'account_type' => 'activo',
                'is_active' => true,
                'is_grouping' => false,
            ]);

            $blocked = $this->actingAs($user, 'backpack')->from('/admin/accounting-account')->post('/admin/accounting-account/guardar', [
                'code' => '70100001',
                'name' => 'NO DEBE COLGAR',
                'account_type' => 'activo',
                'balance_nature' => 'deudor',
                'is_active' => '1',
                'parent_code' => '70100000',
            ]);
            $blocked->assertRedirect('/admin/accounting-account');
            $blocked->assertSessionHasErrors('code');
            $this->assertNull(AccountingAccount::query()->where('code', '70100001')->first());
        } finally {
            AccountingAccount::query()->whereIn('code', ['70000000', '70100000', '70100001'])->delete();
        }
    }

    public function test_can_create_a_rubro_a_subrubro_and_requires_balance_nature_on_accounts(): void
    {
        $user = User::query()->get()->first(fn (User $candidate) => $candidate->canViewAccounting() && ! $candidate->hasAdministradoraInstitucionRole());
        $this->assertNotNull($user);
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $codes = ['61000000', '61100000', '61100001'];
        AccountingAccount::query()->whereIn('code', $codes)->delete();

        try {
            $rubro = $this->actingAs($user, 'backpack')->post('/admin/accounting-account/guardar', [
                'code' => '61000000',
                'name' => 'RUBRO NUEVO PRUEBA',
                'account_type' => 'activo',
                'is_grouping' => '1',
                'is_active' => '1',
            ]);
            $rubro->assertRedirect('/admin/accounting-account?code=61000000');
            $storedRubro = AccountingAccount::query()->where('code', '61000000')->first();
            $this->assertNotNull($storedRubro);
            $this->assertTrue((bool) $storedRubro->is_grouping);
            $this->assertNull($storedRubro->balance_nature);

            $subrubro = $this->actingAs($user, 'backpack')->post('/admin/accounting-account/guardar', [
                'code' => '61100000',
                'name' => 'SUBRUBRO NUEVO PRUEBA',
                'account_type' => 'activo',
                'is_grouping' => '1',
                'is_active' => '1',
                'parent_code' => '61000000',
            ]);
            $subrubro->assertRedirect('/admin/accounting-account?code=61100000');
            $this->assertTrue(
                (bool) AccountingAccount::query()->where('code', '61100000')->value('is_grouping')
            );

            $missingNature = $this->actingAs($user, 'backpack')->from('/admin/accounting-account')->post('/admin/accounting-account/guardar', [
                'code' => '61100001',
                'name' => 'CUENTA SIN SALDO',
                'account_type' => 'activo',
                'is_active' => '1',
                'parent_code' => '61100000',
            ]);
            $missingNature->assertRedirect('/admin/accounting-account');
            $missingNature->assertSessionHasErrors('balance_nature');
            $this->assertNull(AccountingAccount::query()->where('code', '61100001')->first());

            $cuenta = $this->actingAs($user, 'backpack')->post('/admin/accounting-account/guardar', [
                'code' => '61100001',
                'name' => 'CUENTA CON SALDO',
                'account_type' => 'activo',
                'balance_nature' => 'acreedor',
                'is_active' => '1',
                'parent_code' => '61100000',
            ]);
            $cuenta->assertRedirect('/admin/accounting-account?code=61100001');
            $stored = AccountingAccount::query()->where('code', '61100001')->first();
            $this->assertNotNull($stored);
            $this->assertFalse((bool) $stored->is_grouping);
            $this->assertSame('acreedor', $stored->balance_nature);

            $page = $this->actingAs($user, 'backpack')->get('/admin/accounting-account?code=61100001');
            $page->assertOk();
            $page->assertSee('CUENTA CON SALDO');
            $page->assertSee('data-balance-nature="acreedor"', false);
        } finally {
            AccountingAccount::query()->whereIn('code', $codes)->orWhereIn('name', [
                'RUBRO NUEVO PRUEBA',
                'SUBRUBRO NUEVO PRUEBA',
                'CUENTA SIN SALDO',
                'CUENTA CON SALDO',
            ])->delete();
        }
    }
}
