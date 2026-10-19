<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\AccountingEntry;
use App\Models\QuotaAccountingBatch;
use App\Services\AccountingEntryLinks;
use App\Services\PaymentSettlement\AccreditationCollectionAlert;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccreditationPlanCollectionTest extends TestCase
{
    public function test_an_accreditation_matches_quota_collections_plus_plan_collections(): void
    {
        $receivable = AccountingAccount::query()->where('code', '11202005')->where('is_grouping', false)->first();
        $other = AccountingAccount::query()->where('is_grouping', false)->where('code', '!=', '11202005')->first();
        $this->assertNotNull($receivable);
        $this->assertNotNull($other);

        DB::beginTransaction();
        try {
            $regular = $this->collection(
                'collection',
                AccountingEntry::KIND_QUOTA_COLLECTION,
                'AS-2099-C804',
                'COBRANZA CUOTAS MERCADO PAGO',
                [['account' => $receivable, 'debit' => '427020.00']],
            );
            $accreditation = $this->accreditation();

            $open = $this->openOn('2099-08-04');
            $this->assertCount(1, $open);
            $this->assertSame(42702000, $open[0]['debe_cents']);
            $this->assertSame(52814306, $open[0]['haber_cents']);
            $this->assertSame([$regular->id], $open[0]['collection_entry_ids']);

            $plan = $this->collection(
                'plan_collection',
                AccountingEntry::KIND_QUOTA_PLAN_COLLECTION,
                'AS-2099-P804',
                'COBRANZA PLAN DE PAGO MERCADO PAGO',
                [
                    ['account' => $receivable, 'debit' => '101123.06'],
                    ['account' => $other, 'debit' => '1891.50'],
                ],
            );

            $this->assertSame([], $this->openOn('2099-08-04'));

            app(AccountingEntryLinks::class)->attachCollection($plan->batch);
            $this->assertEqualsCanonicalizing(
                [$regular->id, $plan->id],
                $accreditation->fresh()->relatedEntries()->pluck('accounting_entries.id')->map(fn ($id) => (int) $id)->all(),
            );
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function openOn(string $date): array
    {
        return array_values(array_filter(
            (new AccreditationCollectionAlert)->pending(),
            fn (array $case) => in_array($date, $case['collected_on'], true)
        ));
    }

    /**
     * @param  list<array{account: AccountingAccount, debit: string}>  $debits
     */
    private function collection(string $batchKind, string $entryKind, string $number, string $description, array $debits): AccountingEntry
    {
        $batch = QuotaAccountingBatch::query()->create([
            'kind' => $batchKind,
            'batch_key' => 'plan-alert:'.$number,
            'entry_date' => '2099-08-04',
            'payment_type' => 'Mercado Pago',
        ]);
        $entry = AccountingEntry::query()->create([
            'entry_number' => $number,
            'date' => '2099-08-04',
            'kind' => $entryKind,
            'status' => AccountingEntry::STATUS_POSTED,
            'source_type' => $batch->getMorphClass(),
            'source_id' => $batch->id,
            'description' => $description,
        ]);
        foreach ($debits as $debit) {
            $entry->lines()->create([
                'accounting_account_id' => $debit['account']->id,
                'debit' => $debit['debit'],
                'credit' => '0.00',
            ]);
        }
        $batch->update(['accounting_entry_id' => $entry->id]);
        $entry->setRelation('batch', $batch->fresh());

        return $entry;
    }

    private function accreditation(): AccountingEntry
    {
        $batch = QuotaAccountingBatch::query()->create([
            'kind' => QuotaAccountingBatch::KIND_MP_SETTLEMENT,
            'batch_key' => 'plan-alert:mercadopago:2099-08-14',
            'entry_date' => '2099-08-14',
            'payment_type' => 'Mercado Pago',
        ]);
        $entry = AccountingEntry::query()->create([
            'entry_number' => 'AS-2099-A814',
            'date' => '2099-08-14',
            'kind' => AccountingEntry::KIND_QUOTA_MP_SETTLEMENT,
            'status' => AccountingEntry::STATUS_POSTED,
            'source_type' => $batch->getMorphClass(),
            'source_id' => $batch->id,
            'description' => 'ACREDITACION MERCADO PAGO',
        ]);
        $account = AccountingAccount::query()->where('code', '11202005')->first();
        $entry->lines()->create([
            'accounting_account_id' => $account->id,
            'debit' => '0.00',
            'credit' => '528143.06',
        ]);
        $batch->update(['accounting_entry_id' => $entry->id]);
        $batch->collectionDates()->create(['collected_on' => '2099-08-04']);

        return $entry;
    }
}
