<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Services\QuotaAccountingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlanAccountLookupTest extends TestCase
{
    public function test_plan_collections_post_when_the_accounts_exist_under_another_code(): void
    {
        DB::beginTransaction();

        try {
            AccountingAccount::query()->whereIn('code', ['11204000', '11205000'])->delete();
            AccountingAccount::query()->create([
                'code' => '99112040',
                'name' => 'PLAN DE PAGO DS X CUOTAS',
                'account_type' => 'activo',
                'is_active' => true,
                'is_grouping' => false,
            ]);
            AccountingAccount::query()->create([
                'code' => '99112050',
                'name' => 'Interés financiación plan de pago a devengar',
                'account_type' => 'activo',
                'is_active' => true,
                'is_grouping' => false,
            ]);

            $result = app(QuotaAccountingService::class)->postMonth(Carbon::parse('2026-08-01'), true);

            $this->assertSame('2026-08', $result['month']);
            $this->assertContains('2026-08-01', array_column($result['plan_collections']['groups'], 'date'));
        } finally {
            DB::rollBack();
        }
    }

    public function test_a_missing_plan_account_names_the_account_in_the_error(): void
    {
        DB::beginTransaction();

        try {
            AccountingAccount::query()->whereIn('code', ['11204000', '11205000'])->delete();
            AccountingAccount::query()
                ->whereIn('name', ['PLAN DE PAGO DS X CUOTAS', 'INTERES FINANCIACION PLAN DE PAGO A DEVENGAR'])
                ->delete();

            $this->expectExceptionMessage('PLAN DE PAGO DS X CUOTAS');
            app(QuotaAccountingService::class)->postMonth(Carbon::parse('2099-01-01'), true);
        } finally {
            DB::rollBack();
        }
    }
}
