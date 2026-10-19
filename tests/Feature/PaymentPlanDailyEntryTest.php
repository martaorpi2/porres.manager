<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\AccountingEntry;
use App\Models\QuotaAccountingBatch;
use App\Services\QuotaAccountingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class PaymentPlanDailyEntryTest extends TestCase
{
    public function test_plans_opened_the_same_day_share_one_entry(): void
    {
        $planAccount = $this->account('11204000', 'PLAN DE PAGO DS X CUOTAS');
        $debtors = AccountingAccount::query()->where('code', '11201000')->where('is_grouping', false)->first();
        $late = AccountingAccount::query()->where('code', '41201000')->where('is_grouping', false)->first();
        $this->assertNotNull($debtors);
        $this->assertNotNull($late);

        DB::connection('eporres')->beginTransaction();
        DB::beginTransaction();
        try {
            $sample = DB::connection('eporres')->table('students_plans')->first();
            $this->assertNotNull($sample);
            $firstPlan = $this->plan($sample->student_id, $sample->plan_id, '2037-08-04 10:00:00');
            $secondPlan = $this->plan($sample->student_id, $sample->plan_id, '2037-08-04 18:00:00');
            $this->pend($sample->student_id, $firstPlan, 990000001, 'Cuota', 100);
            $this->pend($sample->student_id, $firstPlan, 990000001, 'Interes', 10);
            $this->pend($sample->student_id, $secondPlan, 990000002, 'Cuota', 200);
            $this->pend($sample->student_id, $secondPlan, 990000002, 'Interes', 20);

            $kept = $this->openingEntry('AS-2037-P1', 'plan:test-a', 'ALTA PLAN DE PAGO 1');
            $dropped = $this->openingEntry('AS-2037-P2', 'plan:test-b', 'ALTA PLAN DE PAGO 2');

            $accounts = [
                '11204000' => $planAccount,
                '11201000' => $debtors,
                '41201000' => $late,
            ];
            $first = $this->postPlans($accounts);
            $day = $this->day($first, '2037-08-04');
            $this->assertNotNull($day);
            $this->assertSame(2, $day['plans']);
            $this->assertSame(330.0, $day['plan']);
            $this->assertSame(300.0, $day['debtors']);
            $this->assertSame(30.0, $day['late']);
            $this->assertSame($kept->entry_number, $day['entry_number']);

            $posted = QuotaAccountingBatch::query()
                ->where('kind', QuotaAccountingBatch::KIND_PLAN)
                ->whereDate('entry_date', '2037-08-04')
                ->whereHas('entry', fn ($query) => $query->where('status', AccountingEntry::STATUS_POSTED))
                ->with('entry.lines')
                ->get();
            $this->assertCount(1, $posted);
            $this->assertSame('ALTA PLAN DE PAGO', $posted[0]->entry->description);
            $this->assertSame('330.00', $posted[0]->entry->lines->firstWhere('accounting_account_id', $planAccount->id)->debit);
            $this->assertSame(AccountingEntry::STATUS_REVERSED, $dropped->fresh()->status);

            $second = $this->postPlans($accounts);
            $this->assertNull($this->day($second, '2037-08-04'));
        } finally {
            DB::rollBack();
            DB::connection('eporres')->rollBack();
        }
    }

    /**
     * @param  array<string, AccountingAccount>  $accounts
     * @return array<string, mixed>
     */
    private function postPlans(array $accounts): array
    {
        $service = app(QuotaAccountingService::class);
        $method = new ReflectionMethod($service, 'postPaymentPlans');
        $method->setAccessible(true);

        return $method->invoke($service, Carbon::parse('2037-08-01'), $accounts, false);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>|null
     */
    private function day(array $result, string $date): ?array
    {
        foreach (array_merge($result['groups'], $result['updated']) as $item) {
            if ($item['date'] === $date) {
                return $item;
            }
        }

        return null;
    }

    private function account(string $code, string $name): AccountingAccount
    {
        $account = AccountingAccount::query()->where('code', $code)->first();
        if ($account !== null) {
            return $account;
        }

        return AccountingAccount::query()->create([
            'code' => $code,
            'name' => $name,
            'account_type' => 'activo',
            'is_active' => true,
            'is_grouping' => false,
        ]);
    }

    private function plan(int $studentId, int $planId, string $createdAt): int
    {
        return (int) DB::connection('eporres')->table('students_plans')->insertGetId([
            'student_id' => $studentId,
            'plan_id' => $planId,
            'dues' => 2,
            'current' => 1,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function pend(int $studentId, int $planId, int $orderId, string $type, float $amount): void
    {
        DB::connection('eporres')->table('orders_pend_plan')->insert([
            'student_id' => $studentId,
            'order_id' => $orderId,
            'student_plan_id' => $planId,
            'amount' => $amount,
            'type' => $type,
            'state' => 'Pendiente',
            'created_at' => '2037-08-04 10:00:00',
            'updated_at' => '2037-08-04 10:00:00',
        ]);
    }

    private function openingEntry(string $number, string $key, string $description): AccountingEntry
    {
        $batch = QuotaAccountingBatch::query()->create([
            'kind' => QuotaAccountingBatch::KIND_PLAN,
            'batch_key' => $key,
            'entry_date' => '2037-08-04',
            'payment_type' => null,
        ]);
        $entry = AccountingEntry::query()->create([
            'entry_number' => $number,
            'date' => '2037-08-04',
            'kind' => AccountingEntry::KIND_QUOTA_PLAN,
            'status' => AccountingEntry::STATUS_POSTED,
            'source_type' => $batch->getMorphClass(),
            'source_id' => $batch->id,
            'description' => $description,
        ]);
        $batch->update(['accounting_entry_id' => $entry->id]);

        return $entry;
    }
}
