<?php

use App\Models\AccountingAccount;
use App\Services\AccountingChartReport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_accounts', function (Blueprint $table) {
            $table->boolean('is_grouping')->default(false)->after('account_type');
        });

        $types = ['1' => 'activo', '2' => 'pasivo', '3' => 'patrimonio', '4' => 'ingreso', '5' => 'gasto'];
        foreach ((new AccountingChartReport())->defaultGroups() as $code => $group) {
            if (AccountingAccount::query()->where('code', $code)->exists()) {
                continue;
            }
            AccountingAccount::query()->create([
                'code' => $code,
                'name' => $group['name'],
                'account_type' => $types[substr((string) $code, 0, 1)] ?? null,
                'is_active' => true,
                'is_grouping' => true,
            ]);
        }
    }

    public function down(): void
    {
        AccountingAccount::query()->where('is_grouping', true)->delete();

        Schema::table('accounting_accounts', function (Blueprint $table) {
            $table->dropColumn('is_grouping');
        });
    }
};
