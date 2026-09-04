<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_invoices') || Schema::hasColumn('supplier_invoices', 'accounting_account_id')) {
            return;
        }

        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->foreignId('accounting_account_id')
                ->nullable()
                ->after('supplier_id')
                ->constrained('accounting_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('supplier_invoices') || ! Schema::hasColumn('supplier_invoices', 'accounting_account_id')) {
            return;
        }

        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accounting_account_id');
        });
    }
};
