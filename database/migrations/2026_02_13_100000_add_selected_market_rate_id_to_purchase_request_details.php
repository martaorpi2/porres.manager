<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Permite asignar por producto qué cotización usar al generar la OC.
     */
    public function up(): void
    {
        Schema::table('purchase_request_details', function (Blueprint $table) {
            $table->foreignId('selected_market_rate_id')->nullable()->after('product_id')->constrained('market_rates')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_request_details', function (Blueprint $table) {
            $table->dropForeign(['selected_market_rate_id']);
        });
    }
};
