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
        Schema::create('supplier_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->foreignId('rated_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->onDelete('set null');
            
            // Calificaciones por criterio (1-5)
            $table->tinyInteger('quality_rating')->comment('Calificación de calidad (1-5)');
            $table->tinyInteger('price_rating')->comment('Calificación de precio (1-5)');
            $table->tinyInteger('delivery_time_rating')->comment('Calificación de tiempo de entrega (1-5)');
            $table->tinyInteger('service_rating')->comment('Calificación de servicio al cliente (1-5)');
            $table->tinyInteger('overall_rating')->comment('Calificación general (1-5)');
            
            $table->text('comments')->nullable()->comment('Comentarios adicionales');
            $table->date('evaluation_date')->comment('Fecha de evaluación');
            
            $table->timestamps();
            
            // Índices
            $table->index('supplier_id');
            $table->index('evaluation_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_ratings');
    }
};

