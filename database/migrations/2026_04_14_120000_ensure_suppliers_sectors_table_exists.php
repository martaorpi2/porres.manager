<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repara entornos donde `migrations` marca la migración original como corrida
     * pero la tabla `suppliers_sectors` no existe (restore, fallo parcial, etc.).
     */
    public function up(): void
    {
        if (Schema::hasTable('suppliers_sectors')) {
            return;
        }

        Schema::create('suppliers_sectors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('sector_id');
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
            $table->foreign('sector_id')->references('id')->on('sectors')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        // No eliminar: podría borrar datos en bases ya correctas.
    }
};
