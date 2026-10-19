<?php

namespace Tests\Feature;

use App\Models\AccountingEntry;
use App\Models\QuotaAccountingBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JournalPlanCollectionDateTest extends TestCase
{
    public function test_the_collection_date_shows_the_plan_installment_next_to_the_regular_collection(): void
    {
        $user = User::query()->get()->first(fn (User $candidate) => $candidate->canViewAccounting() && ! $candidate->hasAdministradoraInstitucionRole());
        $this->assertNotNull($user);

        DB::beginTransaction();
        try {
            $this->postedBatch(
                'AS-2099-C404',
                AccountingEntry::KIND_QUOTA_COLLECTION,
                QuotaAccountingBatch::KIND_COLLECTION,
                'COBRANZA CUOTAS MERCADO PAGO A COBRAR',
            );
            $this->postedBatch(
                'AS-2099-P404',
                AccountingEntry::KIND_QUOTA_PLAN_COLLECTION,
                QuotaAccountingBatch::KIND_PLAN_COLLECTION,
                'COBRANZA PLAN DE PAGO MERCADO PAGO',
            );

            $page = $this->actingAs($user, 'backpack')->get('/admin/accounting-journal?collected=2099-11-04');
            $page->assertOk();
            $page->assertSee('COBRANZA CUOTAS MERCADO PAGO A COBRAR');
            $page->assertSee('COBRANZA PLAN DE PAGO MERCADO PAGO');

            $onlyPlan = $this->actingAs($user, 'backpack')->get('/admin/accounting-journal?collected=2099-11-04&kind='.AccountingEntry::KIND_QUOTA_PLAN_COLLECTION);
            $onlyPlan->assertOk();
            $onlyPlan->assertSee('COBRANZA PLAN DE PAGO MERCADO PAGO');
            $onlyPlan->assertDontSee('COBRANZA CUOTAS MERCADO PAGO A COBRAR');
        } finally {
            DB::rollBack();
        }
    }

    private function postedBatch(string $number, string $entryKind, string $batchKind, string $description): void
    {
        $batch = QuotaAccountingBatch::query()->create([
            'kind' => $batchKind,
            'batch_key' => 'journal-plan-date:'.$number,
            'entry_date' => '2099-11-04',
            'payment_type' => 'Mercado Pago',
        ]);
        $entry = AccountingEntry::query()->create([
            'entry_number' => $number,
            'date' => '2099-11-04',
            'kind' => $entryKind,
            'status' => AccountingEntry::STATUS_POSTED,
            'source_type' => $batch->getMorphClass(),
            'source_id' => $batch->id,
            'description' => $description,
        ]);
        $batch->update(['accounting_entry_id' => $entry->id]);
    }
}
