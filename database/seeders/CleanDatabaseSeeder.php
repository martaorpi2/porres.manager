<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Limpia todas las tablas excepto users
     */
    public function run(): void
    {
        // Desactivar verificación de claves foráneas temporalmente
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Lista de tablas a limpiar (excluyendo users y tablas del sistema)
        $tablesToClean = [
            'applications',
            'categories',
            'devolutions',
            'inventory_movements',
            'inputs',
            'locations',
            'market_rates',
            'oc_details',
            'op_details',
            'payment_orders',
            'products',
            'purchase_order_details',
            'purchase_orders',
            'quote_details',
            'receptions',
            'request_details',
            'sectors',
            'stock_levels',
            'suppliers',
            'suppliers_headings',
            'suppliers_sectors',
        ];

        foreach ($tablesToClean as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
                $this->command->info("Tabla {$table} limpiada exitosamente.");
            }
        }

        // Reactivar verificación de claves foráneas
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info('Base de datos limpiada exitosamente (excepto tabla users).');
    }
}
