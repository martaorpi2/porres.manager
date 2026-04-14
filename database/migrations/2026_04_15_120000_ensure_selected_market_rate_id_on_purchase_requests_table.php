<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Algunas instalaciones no ejecutaron o fallaron en la migración que agrega
 * selected_market_rate_id a purchase_requests, lo que rompe assign-quotations.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_requests')) {
            return;
        }

        Schema::table('purchase_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_requests', 'attachments')) {
                $table->json('attachments')->nullable();
            }
        });

        if (! Schema::hasColumn('purchase_requests', 'selected_market_rate_id')) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                $table->foreignId('selected_market_rate_id')
                    ->nullable()
                    ->constrained('market_rates')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_requests')) {
            return;
        }

        if (Schema::hasColumn('purchase_requests', 'selected_market_rate_id')) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                $table->dropConstrainedForeignId('selected_market_rate_id');
            });
        }

        // No eliminamos `attachments` aquí: puede existir desde migraciones anteriores.
    }
};
