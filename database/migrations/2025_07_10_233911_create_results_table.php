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
        Schema::create('results', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('analysis_order_id');
            $table->unsignedBigInteger('determination_id');

            $table->decimal('result_value', 10, 2)->nullable();
            $table->date('date');
            $table->boolean('validated')->default(false);
            $table->text('observations')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('analysis_order_id')
                  ->references('id')
                  ->on('analysis_orders')
                  ->onDelete('cascade');

            $table->foreign('determination_id')
                  ->references('id')
                  ->on('determinations')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
