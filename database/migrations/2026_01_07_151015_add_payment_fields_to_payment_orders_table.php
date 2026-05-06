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
        if (! Schema::hasTable('payment_orders')) {
            return;
        }

        Schema::table('payment_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_orders', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('observations')->comment('Forma de pago');
            }
            if (! Schema::hasColumn('payment_orders', 'bank')) {
                $table->string('bank')->nullable()->after('payment_method')->comment('Banco');
            }
            if (! Schema::hasColumn('payment_orders', 'payment_date')) {
                $table->date('payment_date')->nullable()->after('bank')->comment('Fecha de pago');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('payment_orders')) {
            return;
        }

        Schema::table('payment_orders', function (Blueprint $table) {
            $cols = collect(['payment_method', 'bank', 'payment_date'])
                ->filter(fn ($c) => Schema::hasColumn('payment_orders', $c))
                ->all();
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
