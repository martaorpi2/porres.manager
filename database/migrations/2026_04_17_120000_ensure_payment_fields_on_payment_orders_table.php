<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instalaciones sin la migración 2026_01_07 fallan al guardar órdenes de pago (payment_date, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_orders')) {
            return;
        }

        if (! Schema::hasColumn('payment_orders', 'payment_method')) {
            Schema::table('payment_orders', function (Blueprint $table) {
                $table->string('payment_method')->nullable()->comment('Forma de pago');
            });
        }

        if (! Schema::hasColumn('payment_orders', 'bank')) {
            Schema::table('payment_orders', function (Blueprint $table) {
                $table->string('bank')->nullable()->comment('Banco');
            });
        }

        if (! Schema::hasColumn('payment_orders', 'payment_date')) {
            Schema::table('payment_orders', function (Blueprint $table) {
                $table->date('payment_date')->nullable()->comment('Fecha de pago');
            });
        }
    }

    public function down(): void
    {
        // Sin reversa: estas columnas pueden existir por migraciones previas;
        // eliminarlas aquí podría borrar datos en producción.
    }
};
