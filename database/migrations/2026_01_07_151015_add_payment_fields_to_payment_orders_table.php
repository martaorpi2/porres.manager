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
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('observations')->comment('Forma de pago');
            $table->string('bank')->nullable()->after('payment_method')->comment('Banco');
            $table->date('payment_date')->nullable()->after('bank')->comment('Fecha de pago');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'bank', 'payment_date']);
        });
    }
};
