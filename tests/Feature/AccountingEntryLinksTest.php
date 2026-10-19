<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\AccountingEntry;
use App\Models\QuotaAccountingBatch;
use App\Models\User;
use App\Services\AccountingEntryLinks;
use App\Services\QuotaAccountingService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountingEntryLinksTest extends TestCase
{
    public function test_a_manual_entry_can_cite_several_posted_entries(): void
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

        $suffix = substr(uniqid(), -8);
        $first = $this->entry('AS-L1-'.$suffix, '2099-05-10', AccountingEntry::KIND_MANUAL, 'COBRANZA DE PRUEBA '.$suffix);
        $second = $this->entry('AS-L2-'.$suffix, '2099-05-11', AccountingEntry::KIND_MANUAL, 'OTRA COBRANZA '.$suffix);
        $outflow = $this->entry('AS-L3-'.$suffix, '2099-05-12', AccountingEntry::KIND_OUTFLOW, 'EGRESO '.$suffix);
        $created = null;

        try {
            $page = $this->actingAs($user, 'backpack')->get('/admin/accounting-journal/create');
            $page->assertOk();
            $page->assertSee('Asientos relacionados');
            $page->assertSee('se pueden relacionar varios');
            $page->assertSee('related_ids_present', false);

            $description = 'ASIENTO CON VINCULOS '.$suffix;
            $stored = $this->actingAs($user, 'backpack')->post('/admin/accounting-journal', [
                'date' => '2099-05-20',
                'description' => $description,
                'related_ids_present' => '1',
                'related_ids' => [$first->id, $second->id, $first->id],
                'lines' => [
                    ['accounting_account_id' => $accounts[0]->id, 'debit' => '10,00', 'credit' => '0'],
                    ['accounting_account_id' => $accounts[1]->id, 'debit' => '0', 'credit' => '10,00'],
                ],
            ]);
            $created = AccountingEntry::query()->where('description', $description)->first();
            $this->assertNotNull($created);
            $stored->assertRedirect();
            $this->assertEqualsCanonicalizing(
                [$first->id, $second->id],
                $created->relatedEntries()->pluck('accounting_entries.id')->all()
            );

            $show = $this->actingAs($user, 'backpack')->get('/admin/accounting-journal/'.$created->id);
            $show->assertOk();
            $show->assertSee('Relacionado con');
            $show->assertSee($first->entry_number.' (10/05/2099)');
            $show->assertSee($second->entry_number.' (11/05/2099)');

            $cited = $this->actingAs($user, 'backpack')->get('/admin/accounting-journal/'.$first->id);
            $cited->assertOk();
            $cited->assertSee('Citado por');
            $cited->assertSee($created->entry_number.' (20/05/2099)');

            $journal = $this->actingAs($user, 'backpack')->get('/admin/accounting-journal?'.http_build_query([
                'from' => '2099-05-10',
                'to' => '2099-05-20',
            ]));
            $journal->assertOk();
            $journal->assertSee('Relacionado con');
            $journal->assertSee('Citado por');

            $edit = $this->actingAs($user, 'backpack')->get('/admin/accounting-journal/'.$created->id.'/edit');
            $edit->assertOk();
            $edit->assertSee($first->relationLabel());
            $edit->assertSee('name="related_ids[]"', false);

            $updated = $this->actingAs($user, 'backpack')->put('/admin/accounting-journal/'.$created->id, [
                'date' => '2099-05-20',
                'description' => $description,
                'related_ids_present' => '1',
                'related_ids' => [$second->id],
                'lines' => [
                    ['accounting_account_id' => $accounts[0]->id, 'debit' => '10,00', 'credit' => '0'],
                    ['accounting_account_id' => $accounts[1]->id, 'debit' => '0', 'credit' => '10,00'],
                ],
            ]);
            $updated->assertRedirect();
            $this->assertSame([$second->id], $created->fresh()->relatedEntries()->pluck('accounting_entries.id')->all());

            $self = $this->actingAs($user, 'backpack')
                ->from('/admin/accounting-journal/'.$created->id.'/edit')
                ->put('/admin/accounting-journal/'.$created->id, [
                    'date' => '2099-05-20',
                    'description' => $description,
                    'related_ids_present' => '1',
                    'related_ids' => [$created->id],
                    'lines' => [
                        ['accounting_account_id' => $accounts[0]->id, 'debit' => '10,00', 'credit' => '0'],
                        ['accounting_account_id' => $accounts[1]->id, 'debit' => '0', 'credit' => '10,00'],
                    ],
                ]);
            $self->assertSessionHasErrors('related_ids');
            $this->assertSame([$second->id], $created->fresh()->relatedEntries()->pluck('accounting_entries.id')->all());

            $rejected = $this->actingAs($user, 'backpack')
                ->from('/admin/accounting-journal/create')
                ->post('/admin/accounting-journal', [
                    'date' => '2099-05-21',
                    'description' => 'NO DEBE GUARDARSE '.$suffix,
                    'related_ids_present' => '1',
                    'related_ids' => [$outflow->id],
                    'lines' => [
                        ['accounting_account_id' => $accounts[0]->id, 'debit' => '10,00', 'credit' => '0'],
                        ['accounting_account_id' => $accounts[1]->id, 'debit' => '0', 'credit' => '10,00'],
                    ],
                ]);
            $rejected->assertSessionHasErrors('related_ids');
            $this->assertNull(AccountingEntry::query()->where('description', 'NO DEBE GUARDARSE '.$suffix)->first());

            $search = $this->actingAs($user, 'backpack')->getJson('/admin/accounting-journal/search?'.http_build_query([
                'q' => $first->entry_number,
                'exclude' => $created->id,
            ]));
            $search->assertOk();
            $search->assertJsonFragment(['id' => $first->id, 'label' => $first->relationLabel()]);
        } finally {
            AccountingEntry::query()->where('description', 'like', '%'.$suffix)->each(function (AccountingEntry $entry) {
                $entry->lines()->delete();
                $entry->delete();
            });
        }
    }

    public function test_an_accreditation_links_collections_from_different_dates(): void
    {
        $suffix = substr(uniqid(), -8);
        $may = $this->collection('2099-06-10', 'Mercado Pago', 'AS-C1-'.$suffix);
        $june = $this->collection('2099-06-11', 'Mercado Pago', 'AS-C2-'.$suffix);
        $other = $this->collection('2099-06-10', 'Tarjeta Naranja', 'AS-C3-'.$suffix);
        $entryId = null;

        DB::beginTransaction();
        try {
            $entries = app(QuotaAccountingService::class)->postImportedPaymentSettlements('mercadopago', [[
                'key' => 'mercadopago:2099-06-20:'.$suffix,
                'date' => '2099-06-20',
                'gross_cents' => 100000,
                'commission_cents' => 0,
                'interest_cents' => 0,
                'net_cents' => 100000,
                'document' => '20/06/2099',
                'collected_on' => ['2099-06-10', '2099-06-11'],
            ]]);
            $this->assertCount(1, $entries);
            $entryId = $entries[0]->id;
            $this->assertEqualsCanonicalizing(
                [$may->id, $june->id],
                $entries[0]->relatedEntries()->pluck('accounting_entries.id')->all()
            );
            $this->assertFalse($other->citingEntries()->where('accounting_entries.id', $entryId)->exists());

            $entries[0]->update(['manually_adjusted' => true]);
            $entries[0]->relatedEntries()->sync([$may->id]);
            app(QuotaAccountingService::class)->linkImportedCollectionDates([[
                'key' => 'mercadopago:2099-06-20:'.$suffix,
                'collected_on' => ['2099-06-11'],
            ]]);
            $this->assertSame([$may->id], $entries[0]->fresh()->relatedEntries()->pluck('accounting_entries.id')->all());

            $later = $this->collection('2099-06-12', 'Mercado Pago', 'AS-C4-'.$suffix);
            $batch = QuotaAccountingBatch::query()->where('batch_key', 'mercadopago:2099-06-20:'.$suffix)->first();
            $batch->collectionDates()->create(['collected_on' => '2099-06-12']);
            $entries[0]->update(['manually_adjusted' => false]);
            app(AccountingEntryLinks::class)->attachCollection($later->batch);
            $this->assertEqualsCanonicalizing(
                [$june->id, $later->id],
                $entries[0]->fresh()->relatedEntries()->pluck('accounting_entries.id')->map(fn ($id) => (int) $id)->all()
            );

            $found = AccountingEntryLinks::search($suffix, null, 'Mercado Pago');
            $this->assertSame(
                [$later->id, $june->id, $may->id, $other->id],
                $found->pluck('id')->map(fn ($id) => (int) $id)->all()
            );
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if ($entryId !== null) {
                AccountingEntry::query()->whereKey($entryId)->delete();
            }
            QuotaAccountingBatch::query()->where('batch_key', 'mercadopago:2099-06-20:'.$suffix)->delete();
            AccountingEntry::query()->where('entry_number', 'like', 'AS-C_-'.$suffix)->each(function (AccountingEntry $entry) {
                $entry->batch?->collectionDates()->delete();
                $entry->batch?->delete();
                $entry->delete();
            });
        }
    }

    private function entry(string $number, string $date, string $kind, string $description): AccountingEntry
    {
        return AccountingEntry::query()->create([
            'entry_number' => $number,
            'date' => $date,
            'kind' => $kind,
            'status' => AccountingEntry::STATUS_POSTED,
            'source_type' => null,
            'source_id' => null,
            'description' => $description,
        ]);
    }

    private function collection(string $date, string $paymentType, string $number): AccountingEntry
    {
        $batch = QuotaAccountingBatch::query()->create([
            'kind' => QuotaAccountingBatch::KIND_COLLECTION,
            'batch_key' => 'collection-link:'.$number,
            'entry_date' => $date,
            'payment_type' => $paymentType,
        ]);
        $entry = AccountingEntry::query()->create([
            'entry_number' => $number,
            'date' => $date,
            'kind' => AccountingEntry::KIND_QUOTA_COLLECTION,
            'status' => AccountingEntry::STATUS_POSTED,
            'source_type' => $batch->getMorphClass(),
            'source_id' => $batch->id,
            'description' => 'COBRANZA '.$paymentType.' '.$date,
        ]);
        $batch->update(['accounting_entry_id' => $entry->id]);
        $entry->setRelation('batch', $batch->fresh());

        return $entry;
    }
}
