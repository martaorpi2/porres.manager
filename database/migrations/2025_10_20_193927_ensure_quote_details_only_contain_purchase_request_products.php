<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Primero, eliminar quote_details que no correspondan a productos en purchase_request_details
        DB::statement("
            DELETE qd FROM quote_details qd 
            WHERE qd.product_id NOT IN (
                SELECT DISTINCT prd.product_id 
                FROM purchase_request_details prd
            )
        ");

        // Eliminar market_rates que no tengan quote_details después de la limpieza
        DB::statement("
            DELETE mr FROM market_rates mr 
            WHERE mr.id NOT IN (
                SELECT DISTINCT qd.market_rate_id 
                FROM quote_details qd
            )
        ");

        // Agregar índice para mejorar performance de la consulta
        Schema::table('quote_details', function (Blueprint $table) {
            $table->index(['product_id'], 'idx_quote_details_product_id');
        });

        // Agregar índice en purchase_request_details para mejorar performance
        Schema::table('purchase_request_details', function (Blueprint $table) {
            $table->index(['product_id'], 'idx_purchase_request_details_product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover los índices agregados
        Schema::table('quote_details', function (Blueprint $table) {
            $table->dropIndex('idx_quote_details_product_id');
        });

        Schema::table('purchase_request_details', function (Blueprint $table) {
            $table->dropIndex('idx_purchase_request_details_product_id');
        });

        // Nota: No podemos revertir la eliminación de datos ya que se perdieron
        // En un entorno de producción, se recomendaría hacer backup antes de ejecutar esta migración
    }
};