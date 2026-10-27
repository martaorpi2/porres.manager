<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\AccountingEntry;
use App\Models\AccountingPeriod;
use App\Models\User;
use RuntimeException;
use Tests\TestCase;

class AccountingJournalCreateTest extends TestCase
{
    public function test_a_manual_entry_is_posted_and_can_change_its_date(): void
    {
        $user = User::query()->get()->first(fn (User $candidate) => $candidate->canViewAccounting() && ! $candidate->hasAdministradoraInstitucionRole());
        $this->assertNotNull($user);

        $accounts = AccountingAccount::query()
            ->where('is_grouping', false)
            ->where('is_active', true)
            ->orderBy('id')
            ->limit(2)
            ->get();
        $this->assertCount(2, $accounts);

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $description = 'ASIENTO MANUAL '.substr(uniqid(), -8);
        $entry = null;

        try {
            $page = $this->actingAs($user, 'backpack')->get('/admin/accounting-journal/create');
            $page->assertOk();
            $page->assertSee('Nuevo asiento');
            $page->assertSee('name="date"', false);
            $page->assertSee('name="description"', false);
            $page->assertSeeInOrder(['Agregar cuenta', 'Totales', 'Guardar']);
            $page->assertSee('id="save-entry" disabled', false);
            $page->assertSee('save.disabled = !balanced', false);

            $deudor = AccountingAccount::query()
                ->where('is_grouping', false)
                ->where('is_active', true)
                ->where('balance_nature', 'deudor')
                ->orderBy('id')
                ->first();
            $gasto = AccountingAccount::query()
                ->where('is_grouping', false)
                ->where('is_active', true)
                ->where('account_type', 'gasto')
                ->orderBy('id')
                ->first();
            $acreedor = AccountingAccount::query()
                ->where('is_grouping', false)
                ->where('is_active', true)
                ->where('balance_nature', 'acreedor')
                ->orderBy('id')
                ->first();
            $this->assertNotNull($deudor);
            $this->assertNotNull($gasto);
            $this->assertSame('deudor', $gasto->balance_nature);
            $this->assertNotNull($acreedor);
            $page->assertSee('value="'.$deudor->id.'" data-side="debit"', false);
            $page->assertSee('value="'.$gasto->id.'" data-side="debit"', false);
            $page->assertSee('value="'.$acreedor->id.'" data-side="credit"', false);
            $page->assertSee('Saldo deudor: cargá el importe en el debe.', false);
            $page->assertSee('Saldo acreedor: cargá el importe en el haber.', false);

            $created = $this->actingAs($user, 'backpack')->post('/admin/accounting-journal', [
                'date' => '2099-03-15',
                'description' => '  '.$description.'  ',
                'lines' => [
                    ['accounting_account_id' => $accounts[0]->id, 'debit' => '150,50', 'credit' => '0'],
                    ['accounting_account_id' => $accounts[1]->id, 'debit' => '0', 'credit' => '150,50'],
                ],
            ]);

            $entry = AccountingEntry::query()->where('description', $description)->first();
            $this->assertNotNull($entry);
            $created->assertRedirect('/admin/accounting-journal?'.http_build_query([
                'from' => '2099-03-15',
                'to' => '2099-03-15',
                'kind' => AccountingEntry::KIND_MANUAL,
            ]));

            $this->assertSame(AccountingEntry::KIND_MANUAL, $entry->kind);
            $this->assertSame(AccountingEntry::STATUS_POSTED, $entry->status);
            $this->assertNull($entry->source_type);
            $this->assertNull($entry->source_id);
            $this->assertTrue($entry->manually_adjusted);
            $this->assertSame($user->id, $entry->created_by_id);
            $this->assertSame('2099-03-15', $entry->date->toDateString());
            $this->assertMatchesRegularExpression('/^AS-\d{4}-\d{3}$/', $entry->entry_number);
            $this->assertNotContains(AccountingEntry::KIND_MANUAL, AccountingEntry::quotaKinds());
            $this->assertCount(2, $entry->lines);
            $this->assertSame('150.50', number_format((float) $entry->lines[0]->debit, 2, '.', ''));
            $this->assertSame('150.50', number_format((float) $entry->lines[1]->credit, 2, '.', ''));

            $journal = $this->actingAs($user, 'backpack')->get('/admin/accounting-journal?'.http_build_query([
                'from' => '2099-03-15',
                'to' => '2099-03-15',
                'kind' => AccountingEntry::KIND_MANUAL,
            ]));
            $journal->assertOk();
            $journal->assertSee($entry->entry_number);
            $journal->assertSee($description);
            $journal->assertSee('Asiento manual');

            $edited = $this->actingAs($user, 'backpack')->put('/admin/accounting-journal/'.$entry->id, [
                'date' => '2099-03-16',
                'description' => $description,
                'lines' => [
                    ['id' => $entry->lines[0]->id, 'accounting_account_id' => $accounts[0]->id, 'debit' => '150,50', 'credit' => '0'],
                    ['id' => $entry->lines[1]->id, 'accounting_account_id' => $accounts[1]->id, 'debit' => '0', 'credit' => '150,50'],
                ],
            ]);
            $edited->assertRedirect();
            $this->assertSame('2099-03-16', $entry->fresh()->date->toDateString());
        } finally {
            if ($entry !== null) {
                $entry->lines()->delete();
                $entry->delete();
            }
        }
    }

    public function test_an_unbalanced_manual_entry_is_not_stored(): void
    {
        $user = User::query()->get()->first(fn (User $candidate) => $candidate->canViewAccounting() && ! $candidate->hasAdministradoraInstitucionRole());
        $this->assertNotNull($user);

        $accounts = AccountingAccount::query()
            ->where('is_grouping', false)
            ->where('is_active', true)
            ->orderBy('id')
            ->limit(2)
            ->get();
        $this->assertCount(2, $accounts);

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $description = 'ASIENTO MANUAL RECHAZADO '.substr(uniqid(), -8);

        $rejected = $this->actingAs($user, 'backpack')
            ->from('/admin/accounting-journal/create')
            ->post('/admin/accounting-journal', [
                'date' => '2099-03-15',
                'description' => $description,
                'lines' => [
                    ['accounting_account_id' => $accounts[0]->id, 'debit' => '10,00', 'credit' => '0'],
                    ['accounting_account_id' => $accounts[1]->id, 'debit' => '0', 'credit' => '9,00'],
                ],
            ]);

        $rejected->assertRedirect('/admin/accounting-journal/create');
        $rejected->assertSessionHasErrors('lines');
        $this->assertFalse(AccountingEntry::query()->where('description', $description)->exists());
    }

    public function test_the_journal_opens_on_the_current_month(): void
    {
        $user = User::query()->get()->first(fn (User $candidate) => $candidate->canViewAccounting() && ! $candidate->hasAdministradoraInstitucionRole());
        $this->assertNotNull($user);

        $from = now()->startOfMonth()->toDateString();
        $to = now()->endOfMonth()->toDateString();
        $page = $this->actingAs($user, 'backpack')->get('/admin/accounting-journal');
        $page->assertOk();
        $page->assertSee('Ver inconsistencias');
        $page->assertSee(backpack_url('dashboard').'#accreditation-reconciliation-section', false);
        $page->assertSee('value="'.$from.'"', false);
        $page->assertSee('value="'.$to.'"', false);

        $entry = AccountingEntry::query()->create([
            'entry_number' => 'AS-T-'.substr(uniqid(), -8),
            'date' => '2099-01-15',
            'kind' => AccountingEntry::KIND_MANUAL,
            'status' => AccountingEntry::STATUS_POSTED,
            'source_type' => 'journal-month-test',
            'source_id' => 0,
            'description' => 'FUERA DEL MES EN CURSO',
        ]);
        try {
            $hidden = $this->actingAs($user, 'backpack')->get('/admin/accounting-journal');
            $hidden->assertOk();
            $hidden->assertDontSee('FUERA DEL MES EN CURSO');
            $shown = $this->actingAs($user, 'backpack')->get('/admin/accounting-journal?from=2099-01-01&to=2099-01-31');
            $shown->assertOk();
            $shown->assertSee('FUERA DEL MES EN CURSO');
        } finally {
            $entry->delete();
        }
    }

    public function test_a_manual_entry_outside_an_open_period_is_rejected(): void
    {
        $user = User::query()->get()->first(fn (User $candidate) => $candidate->canViewAccounting() && ! $candidate->hasAdministradoraInstitucionRole());
        $this->assertNotNull($user);

        $accounts = AccountingAccount::query()
            ->where('is_grouping', false)
            ->where('is_active', true)
            ->orderBy('id')
            ->limit(2)
            ->get();
        $this->assertCount(2, $accounts);
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->assertNull(AccountingPeriod::entryDateError('2026-01-01'));
        $this->assertNull(AccountingPeriod::entryDateError('2026-12-31'));
        $outside = AccountingPeriod::entryDateError('2027-01-01');
        $this->assertNotNull($outside);
        $this->assertStringContainsString('01/01/2026 al 31/12/2026', $outside);

        $description = 'ASIENTO FUERA DE PERIODO '.substr(uniqid(), -8);
        $entry = null;

        try {
            $page = $this->actingAs($user, 'backpack')->get('/admin/accounting-journal/create');
            $page->assertOk();
            $page->assertSee('los períodos abiertos', false);
            $page->assertSee('01/01/2026 al 31/12/2026', false);

            $rejected = $this->actingAs($user, 'backpack')
                ->from('/admin/accounting-journal/create')
                ->post('/admin/accounting-journal', [
                    'date' => '2027-06-01',
                    'description' => $description,
                    'lines' => [
                        ['accounting_account_id' => $accounts[0]->id, 'debit' => '10,00', 'credit' => '0'],
                        ['accounting_account_id' => $accounts[1]->id, 'debit' => '0', 'credit' => '10,00'],
                    ],
                ]);
            $rejected->assertRedirect('/admin/accounting-journal/create');
            $rejected->assertSessionHasErrors('date');
            $this->assertStringContainsString('01/01/2026 al 31/12/2026', session('errors')->get('date')[0]);
            $this->assertFalse(AccountingEntry::query()->where('description', $description)->exists());

            $stored = $this->actingAs($user, 'backpack')->post('/admin/accounting-journal', [
                'date' => '2026-06-15',
                'description' => $description,
                'lines' => [
                    ['accounting_account_id' => $accounts[0]->id, 'debit' => '10,00', 'credit' => '0'],
                    ['accounting_account_id' => $accounts[1]->id, 'debit' => '0', 'credit' => '10,00'],
                ],
            ]);
            $entry = AccountingEntry::query()->where('description', $description)->first();
            $this->assertNotNull($entry);
            $stored->assertRedirect();

            $moved = $this->actingAs($user, 'backpack')
                ->from('/admin/accounting-journal/'.$entry->id.'/edit')
                ->put('/admin/accounting-journal/'.$entry->id, [
                    'date' => '2025-12-31',
                    'description' => $description,
                    'lines' => [
                        ['id' => $entry->lines[0]->id, 'accounting_account_id' => $accounts[0]->id, 'debit' => '10,00', 'credit' => '0'],
                        ['id' => $entry->lines[1]->id, 'accounting_account_id' => $accounts[1]->id, 'debit' => '0', 'credit' => '10,00'],
                    ],
                ]);
            $moved->assertRedirect('/admin/accounting-journal/'.$entry->id.'/edit');
            $moved->assertSessionHasErrors('date');
            $this->assertSame('2026-06-15', $entry->fresh()->date->toDateString());

            $blocked = false;
            try {
                AccountingEntry::query()->create([
                    'entry_number' => 'AS-X-'.substr(uniqid(), -8),
                    'date' => '2027-02-01',
                    'kind' => AccountingEntry::KIND_MANUAL,
                    'status' => AccountingEntry::STATUS_POSTED,
                    'description' => $description.' DIRECTO',
                ]);
            } catch (RuntimeException $exception) {
                $blocked = str_contains($exception->getMessage(), '01/01/2026 al 31/12/2026');
            }
            $this->assertTrue($blocked);
            $this->assertFalse(AccountingEntry::query()->where('description', $description.' DIRECTO')->exists());
        } finally {
            if ($entry !== null) {
                $entry->lines()->delete();
                $entry->delete();
            }
        }
    }
}
