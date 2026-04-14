<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * assignQuotations() actualiza selected_market_rate_id en purchase_request_details.
 * Si la migración 2026_02_13_100000 no corrió en esta BD, falla con "Unknown column".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_request_details')) {
            return;
        }

        if (Schema::hasColumn('purchase_request_details', 'selected_market_rate_id')) {
            return;
        }

        Schema::table('purchase_request_details', function (Blueprint $table) {
            $table->foreignId('selected_market_rate_id')
                ->nullable()
                ->constrained('market_rates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_request_details')) {
            return;
        }

        if (! Schema::hasColumn('purchase_request_details', 'selected_market_rate_id')) {
            return;
        }

        Schema::table('purchase_request_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('selected_market_rate_id');
        });
    }
};
