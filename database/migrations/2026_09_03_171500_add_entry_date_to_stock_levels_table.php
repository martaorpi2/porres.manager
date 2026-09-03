<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La columna canónica es `date` (migración 2026_09_03_151800).
     * Si en algún entorno se creó `entry_date`, se unifica hacia `date`.
     */
    public function up(): void
    {
        if (! Schema::hasTable('stock_levels')) {
            return;
        }

        $hasDate = Schema::hasColumn('stock_levels', 'date');
        $hasEntryDate = Schema::hasColumn('stock_levels', 'entry_date');

        if ($hasEntryDate && ! $hasDate) {
            Schema::table('stock_levels', function (Blueprint $table) {
                $table->date('date')->nullable()->after('quantity');
            });

            DB::table('stock_levels')
                ->whereNull('date')
                ->update(['date' => DB::raw('entry_date')]);

            Schema::table('stock_levels', function (Blueprint $table) {
                $table->dropColumn('entry_date');
            });

            return;
        }

        if ($hasEntryDate && $hasDate) {
            DB::table('stock_levels')
                ->whereNull('date')
                ->whereNotNull('entry_date')
                ->update(['date' => DB::raw('entry_date')]);

            Schema::table('stock_levels', function (Blueprint $table) {
                $table->dropColumn('entry_date');
            });
        }
    }

    public function down(): void
    {
        // No se recrea entry_date: el campo vigente es date.
    }
};
