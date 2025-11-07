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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reception_id')->constrained('receptions')->onDelete('cascade');
            $table->foreignId('general_request_id')->constrained('general_requests')->onDelete('cascade');
            $table->date('delivery_date');
            $table->foreignId('delivered_by')->constrained('users')->onDelete('cascade')->comment('Responsable del depósito que entrega');
            $table->foreignId('received_by')->constrained('users')->onDelete('cascade')->comment('Usuario que hizo la solicitud general');
            $table->text('observations')->nullable();
            $table->enum('status', ['pendiente', 'entregada', 'cancelada'])->default('pendiente');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
