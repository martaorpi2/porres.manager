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
        // Agregar nuevos campos sin modificar el enum de status existente
        Schema::table('general_requests', function (Blueprint $table) {
            $table->boolean('is_converted')->default(false)->after('status');
            $table->enum('delivery_status', ['sin_entrega', 'entregada_parcialmente', 'entregada_totalmente'])
                  ->default('sin_entrega')
                  ->after('is_converted');
        });

        // Migrar datos existentes a los nuevos campos
        DB::statement("
            UPDATE general_requests 
            SET 
                is_converted = CASE 
                    WHEN status = 'convertida_a_compra' THEN 1 
                    ELSE 0 
                END,
                delivery_status = CASE 
                    WHEN status = 'entregada_totalmente' THEN 'entregada_totalmente'
                    WHEN status = 'entregada_parcialmente' THEN 'entregada_parcialmente'
                    ELSE 'sin_entrega'
                END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar los nuevos campos
        Schema::table('general_requests', function (Blueprint $table) {
            $table->dropColumn(['is_converted', 'delivery_status']);
        });
    }
};
