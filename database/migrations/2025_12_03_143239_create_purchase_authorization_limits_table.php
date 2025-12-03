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
        Schema::create('purchase_authorization_limits', function (Blueprint $table) {
            $table->id();
            $table->string('role_name')->unique()->comment('Nombre del rol (ej: role_responsable_compras)');
            $table->string('role_display_name')->comment('Nombre para mostrar del rol');
            $table->decimal('limit_amount', 15, 2)->comment('Límite de monto en pesos para autorización automática');
            $table->text('description')->nullable()->comment('Descripción del límite');
            $table->boolean('is_active')->default(true)->comment('Indica si el límite está activo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_authorization_limits');
    }
};
