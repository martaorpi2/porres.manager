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
        Schema::table('devolutions', function (Blueprint $table) {
            $table->dropColumn('amount_returned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devolutions', function (Blueprint $table) {
            $table->decimal('amount_returned', 12, 2)->nullable();
        });
    }
};
