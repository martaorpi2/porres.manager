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
        Schema::create('general_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('area_id')->nullable()->constrained('responsibility_areas')->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->enum('priority', ['Baja', 'Media', 'Alta', 'Urgente'])->default('Media');
            $table->json('attachments')->nullable();
            $table->enum('status', ['creada', 'revisada_area', 'archivada', 'convertida_a_compra'])->default('creada');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_requests');
    }
};
