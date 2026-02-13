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
        \DB::statement("ALTER TABLE purchase_requests MODIFY COLUMN purchase_type ENUM('normal', 'rapida', 'directa', 'internet') DEFAULT 'normal' COMMENT 'Tipo de compra: normal, rápida, directa o internet (ej. Mercado Libre)'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \DB::statement("ALTER TABLE purchase_requests MODIFY COLUMN purchase_type ENUM('normal', 'rapida', 'directa') DEFAULT 'normal'");
    }
};
