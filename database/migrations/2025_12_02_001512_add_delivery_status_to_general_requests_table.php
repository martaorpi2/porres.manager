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
        // Modificar el enum para agregar los nuevos estados de entrega
        DB::statement("ALTER TABLE general_requests MODIFY COLUMN status ENUM('creada', 'revisada_area', 'archivada', 'convertida_a_compra', 'entregada_parcialmente', 'entregada_totalmente') DEFAULT 'creada'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir el enum a su estado original
        DB::statement("ALTER TABLE general_requests MODIFY COLUMN status ENUM('creada', 'revisada_area', 'archivada', 'convertida_a_compra') DEFAULT 'creada'");
    }
};
