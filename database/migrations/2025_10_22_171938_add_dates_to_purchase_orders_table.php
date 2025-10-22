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
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->date('issue_date')->nullable()->after('date')->comment('Fecha de emisión de la orden de compra');
            $table->date('estimated_delivery_date')->nullable()->after('issue_date')->comment('Fecha estimada de entrega');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['issue_date', 'estimated_delivery_date']);
        });
    }
};
