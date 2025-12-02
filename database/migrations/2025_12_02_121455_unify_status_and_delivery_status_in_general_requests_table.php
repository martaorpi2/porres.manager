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
        // Primero, migrar los datos: si tiene delivery_status, usar ese; si no, usar status actual
        // Pero si status es 'archivada', mantenerlo (tiene prioridad)
        DB::statement("
            UPDATE general_requests 
            SET status = CASE 
                WHEN status = 'archivada' THEN 'archivada'
                WHEN delivery_status IS NOT NULL AND delivery_status != 'sin_entrega' THEN delivery_status
                WHEN status = 'convertida_a_compra' THEN 'revisada_area'
                ELSE status
            END
        ");
        
        // Modificar el enum de status para incluir todos los valores unificados
        // Eliminar 'convertida_a_compra' ya que se usa is_converted
        DB::statement("
            ALTER TABLE general_requests 
            MODIFY COLUMN status ENUM(
                'creada', 
                'revisada_area', 
                'archivada', 
                'sin_entrega', 
                'entregada_parcialmente', 
                'entregada_totalmente'
            ) DEFAULT 'creada'
        ");
        
        // Eliminar el campo delivery_status
        Schema::table('general_requests', function (Blueprint $table) {
            $table->dropColumn('delivery_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recrear el campo delivery_status
        Schema::table('general_requests', function (Blueprint $table) {
            $table->enum('delivery_status', ['sin_entrega', 'entregada_parcialmente', 'entregada_totalmente'])
                  ->default('sin_entrega')
                  ->after('is_converted');
        });
        
        // Migrar los datos de vuelta: extraer delivery_status del status
        DB::statement("
            UPDATE general_requests 
            SET 
                delivery_status = CASE 
                    WHEN status IN ('entregada_parcialmente', 'entregada_totalmente') THEN status
                    ELSE 'sin_entrega'
                END,
                status = CASE 
                    WHEN status IN ('entregada_parcialmente', 'entregada_totalmente') THEN 'revisada_area'
                    WHEN status = 'sin_entrega' THEN 'creada'
                    ELSE status
                END
        ");
        
        // Revertir el enum de status
        DB::statement("
            ALTER TABLE general_requests 
            MODIFY COLUMN status ENUM(
                'creada', 
                'revisada_area', 
                'archivada', 
                'convertida_a_compra'
            ) DEFAULT 'creada'
        ");
    }
};
