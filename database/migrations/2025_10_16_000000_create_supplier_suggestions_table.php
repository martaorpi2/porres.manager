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
        if (Schema::hasTable('supplier_suggestions')) {
            return;
        }

        Schema::create('supplier_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->foreignId('suggested_by')->constrained('users')->onDelete('cascade');
            $table->text('justification')->nullable();
            $table->timestamps();
            
            // Evitar sugerencias duplicadas del mismo usuario para la misma solicitud y proveedor
            $table->unique(['purchase_request_id', 'supplier_id', 'suggested_by'], 'unique_suggestion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_suggestions');
    }
};

