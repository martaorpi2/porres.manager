<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->date('entry_date')->nullable()->after('quantity');
        });

        DB::table('stock_levels')
            ->whereNull('entry_date')
            ->update(['entry_date' => DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->dropColumn('entry_date');
        });
    }
};
