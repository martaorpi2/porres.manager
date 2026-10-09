<?php

use App\Models\AccountingAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (AccountingAccount::query()->where('code', '11202006')->exists()) {
            return;
        }

        $attributes = [
            'code' => '11202006',
            'name' => 'QR A COBRAR',
            'account_type' => 'activo',
            'is_active' => true,
            'is_grouping' => false,
        ];
        if (Schema::hasColumn('accounting_accounts', 'balance_nature')) {
            $attributes['balance_nature'] = 'deudor';
        }

        AccountingAccount::query()->create($attributes);
    }

    public function down(): void
    {
        AccountingAccount::query()->where('code', '11202006')->delete();
    }
};
