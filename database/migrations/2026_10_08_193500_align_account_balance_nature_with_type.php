<?php

use App\Models\AccountingAccount;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        AccountingAccount::query()
            ->where('is_grouping', false)
            ->orderBy('id')
            ->each(function (AccountingAccount $account) {
                $nature = AccountingAccount::defaultBalanceNature($account->account_type);
                if ($nature !== null && $account->balance_nature !== $nature) {
                    $account->update(['balance_nature' => $nature]);
                }
            });
    }

    public function down(): void
    {
    }
};
