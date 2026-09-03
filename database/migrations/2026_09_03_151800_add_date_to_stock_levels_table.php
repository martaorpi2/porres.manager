<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_levels') || Schema::hasColumn('stock_levels', 'date')) {
            return;
        }

        Schema::table('stock_levels', function (Blueprint $table) {
            $table->date('date')->nullable()->after('quantity');
        });

        DB::table('stock_levels')
            ->whereNull('date')
            ->update(['date' => DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('stock_levels') || ! Schema::hasColumn('stock_levels', 'date')) {
            return;
        }

        Schema::table('stock_levels', function (Blueprint $table) {
            $table->dropColumn('date');
        });
    }
};
