<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quota_accounting_collection_dates', function (Blueprint $table) {
            $table->unsignedBigInteger('haber_cents')->nullable()->after('collected_on');
        });
    }

    public function down(): void
    {
        Schema::table('quota_accounting_collection_dates', function (Blueprint $table) {
            $table->dropColumn('haber_cents');
        });
    }
};
