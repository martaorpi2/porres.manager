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
        Schema::create('purchase_request_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->integer('requested_quantity');
            $table->text('specifications')->nullable();
            $table->text('justification')->nullable();
            $table->decimal('estimated_unit_price', 10, 2)->nullable();
            $table->decimal('estimated_total', 10, 2)->nullable();
            $table->enum('status', ['Pendiente', 'Aprobada', 'Rechazada', 'En Cotización', 'Comprada'])->default('Pendiente');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_request_details');
    }
};
