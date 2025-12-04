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
        Schema::table('purchase_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_requests', 'purchase_type')) {
                $table->enum('purchase_type', ['normal', 'rapida', 'directa'])->default('normal')->after('status')->comment('Tipo de compra: normal, rápida o directa');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_requests', 'purchase_type')) {
                $table->dropColumn('purchase_type');
            }
        });
    }
};
