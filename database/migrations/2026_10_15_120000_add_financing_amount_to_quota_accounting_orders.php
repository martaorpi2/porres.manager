<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quota_accounting_orders', function (Blueprint $table) {
            $table->decimal('financing_amount', 14, 2)->default(0)->after('bank_amount');
        });
    }

    public function down(): void
    {
        Schema::table('quota_accounting_orders', function (Blueprint $table) {
            $table->dropColumn('financing_amount');
        });
    }
};
