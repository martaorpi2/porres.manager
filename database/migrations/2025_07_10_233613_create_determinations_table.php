<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('determinations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();                  // Código único de determinación
            $table->string('name');                            // Nombre de la determinación
            $table->string('unit')->nullable();                // Unidad (ej: mg/dL)
            $table->string('biochemical_unit')->nullable();    // Unidad bioquímica opcional
            $table->decimal('ref_value_min', 8, 2)->nullable();// Valor de referencia mínimo
            $table->decimal('ref_value_max', 8, 2)->nullable();// Valor de referencia máximo
            $table->decimal('price', 10, 2)->nullable();       // Precio
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('determinations');
    }
};
