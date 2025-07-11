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
        Schema::create('samples', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('patient_id');
            $table->date('date');
            $table->enum('type', [
                'sangre',
                'orina',
                'orina 24hs',
                'fecal',
                'saliva',
                'isopado',
                'exudado vaginal',
                'otro'
            ]);
            $table->text('observations')->nullable();

            $table->timestamps();

            // Foreign Key constraint
            $table->foreign('patient_id')
                  ->references('id')
                  ->on('patients')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('samples');
    }
};
