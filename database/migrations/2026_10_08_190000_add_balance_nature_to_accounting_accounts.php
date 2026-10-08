<?php

use App\Models\AccountingAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_accounts', function (Blueprint $table) {
            $table->string('balance_nature', 20)->nullable()->after('account_type');
        });

        AccountingAccount::query()
            ->where('is_grouping', false)
            ->orderBy('id')
            ->each(function (AccountingAccount $account) {
                $nature = AccountingAccount::defaultBalanceNature($account->account_type);
                if ($nature !== null) {
                    $account->update(['balance_nature' => $nature]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('accounting_accounts', function (Blueprint $table) {
            $table->dropColumn('balance_nature');
        });
    }
};
