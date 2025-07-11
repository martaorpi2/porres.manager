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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('dni')->unique();
            $table->string('last_name');
            $table->string('first_name');
            $table->date('birth_date');
            $table->enum('gender', ['F', 'M', 'Otro']);
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->unique()->nullable();

            // La columna debe existir antes de agregar la FK
            $table->unsignedBigInteger('social_work_id')->nullable();

            $table->string('affiliate_number')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->foreign('social_work_id')
                ->references('id')
                ->on('social_works')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
