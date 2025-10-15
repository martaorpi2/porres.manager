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
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->date('request_date');
            $table->enum('status', ['Pendiente', 'Aprobada', 'Rechazada', 'En Proceso', 'Completada'])->default('Pendiente');
            $table->enum('priority', ['Baja', 'Media', 'Alta', 'Urgente'])->default('Media');
            $table->text('justification')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('responsibility_area_id')->constrained('responsibility_areas')->onDelete('cascade');
            $table->foreignId('requesting_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('approved_date')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
