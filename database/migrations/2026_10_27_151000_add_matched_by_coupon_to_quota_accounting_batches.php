<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quota_accounting_batches', function (Blueprint $table) {
            $table->boolean('matched_by_coupon')->default(false)->after('payment_type');
        });
    }

    public function down(): void
    {
        Schema::table('quota_accounting_batches', function (Blueprint $table) {
            $table->dropColumn('matched_by_coupon');
        });
    }
};
