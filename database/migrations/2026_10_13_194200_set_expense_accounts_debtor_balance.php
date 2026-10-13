<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('accounting_accounts')
            ->where('is_grouping', false)
            ->where('account_type', 'gasto')
            ->where(function ($query) {
                $query->whereNull('balance_nature')->orWhere('balance_nature', '<>', 'deudor');
            })
            ->update(['balance_nature' => 'deudor']);
    }

    public function down(): void
    {
        DB::table('accounting_accounts')
            ->where('is_grouping', false)
            ->where('account_type', 'gasto')
            ->where('balance_nature', 'deudor')
            ->update(['balance_nature' => 'acreedor']);
    }
};
