<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\AccountingEntry;
use App\Models\User;
use Tests\TestCase;

class AccountingJournalEditTest extends TestCase
{
    public function test_editing_an_entry_can_change_its_description(): void
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

        $entry = AccountingEntry::query()->create([
            'entry_number' => 'AS-T-'.substr(uniqid(), -8),
            'date' => '2026-10-08',
            'kind' => AccountingEntry::KIND_OUTFLOW,
            'status' => AccountingEntry::STATUS_POSTED,
            'source_type' => 'journal-edit-test',
            'source_id' => 0,
            'description' => 'DESCRIPCION ORIGINAL',
        ]);
        $entry->lines()->create([
            'accounting_account_id' => $accounts[0]->id,
            'debit' => '10.00',
            'credit' => '0.00',
        ]);
        $entry->lines()->create([
            'accounting_account_id' => $accounts[1]->id,
            'debit' => '0.00',
            'credit' => '10.00',
        ]);

        try {
            $page = $this->actingAs($user, 'backpack')->get('/admin/accounting-journal/'.$entry->id.'/edit');
            $page->assertOk();
            $page->assertSee('id="entry-description"', false);
            $page->assertSee('value="DESCRIPCION ORIGINAL"', false);

            $updated = $this->actingAs($user, 'backpack')->put('/admin/accounting-journal/'.$entry->id, [
                'description' => '  DESCRIPCION MODIFICADA  ',
                'lines' => [
                    ['accounting_account_id' => $accounts[0]->id, 'debit' => '10,00', 'credit' => '0'],
                    ['accounting_account_id' => $accounts[1]->id, 'debit' => '0', 'credit' => '10,00'],
                ],
            ]);
            $updated->assertRedirect();
            $this->assertSame('DESCRIPCION MODIFICADA', $entry->fresh()->description);
            $this->assertTrue((bool) $entry->fresh()->manually_adjusted);

            $blank = $this->actingAs($user, 'backpack')
                ->from('/admin/accounting-journal/'.$entry->id.'/edit')
                ->put('/admin/accounting-journal/'.$entry->id, [
                    'description' => '   ',
                    'lines' => [
                        ['accounting_account_id' => $accounts[0]->id, 'debit' => '10,00', 'credit' => '0'],
                        ['accounting_account_id' => $accounts[1]->id, 'debit' => '0', 'credit' => '10,00'],
                    ],
                ]);
            $blank->assertRedirect('/admin/accounting-journal/'.$entry->id.'/edit');
            $blank->assertSessionHasErrors('description');
            $this->assertSame('DESCRIPCION MODIFICADA', $entry->fresh()->description);
        } finally {
            $entry->lines()->delete();
            $entry->delete();
        }
    }
}
