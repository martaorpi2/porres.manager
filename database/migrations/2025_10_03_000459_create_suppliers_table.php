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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('cuit', 20)->unique();
            $table->string('address', 255)->nullable();
            $table->string('contact', 150)->nullable();
            $table->unsignedBigInteger('supplier_heading_id'); // FK hacia rubros
            $table->timestamps();

            $table->foreign('supplier_heading_id')->references('id')->on('suppliers_headings')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
