<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_orders') || ! Schema::hasColumn('payment_orders', 'status')) {
            return;
        }

        DB::table('payment_orders')->where('status', 'Aprobada')->update(['status' => 'Pendiente']);

        DB::statement("ALTER TABLE payment_orders MODIFY COLUMN status ENUM('Pendiente', 'Ejecutada', 'Anulada') DEFAULT 'Pendiente'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_orders') || ! Schema::hasColumn('payment_orders', 'status')) {
            return;
        }

        DB::statement("ALTER TABLE payment_orders MODIFY COLUMN status ENUM('Pendiente', 'Aprobada', 'Ejecutada', 'Anulada') DEFAULT 'Pendiente'");
    }
};
