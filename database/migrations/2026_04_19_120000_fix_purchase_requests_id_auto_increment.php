<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_requests') || ! Schema::hasColumn('purchase_requests', 'id')) {
            return;
        }

        $column = DB::selectOne("
            SELECT COLUMN_KEY AS column_key, EXTRA AS extra
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'purchase_requests'
              AND COLUMN_NAME = 'id'
            LIMIT 1
        ");

        if (! $column) {
            return;
        }

        $hasPrimaryKey = strtoupper((string) $column->column_key) === 'PRI';
        $hasAutoIncrement = str_contains(strtolower((string) $column->extra), 'auto_increment');

        if (! $hasPrimaryKey) {
            DB::statement('ALTER TABLE `purchase_requests` ADD PRIMARY KEY (`id`)');
        }

        if (! $hasAutoIncrement) {
            DB::statement('ALTER TABLE `purchase_requests` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        }
    }

    public function down(): void
    {
        // Reparación de esquema: no revertir automáticamente para evitar romper inserts.
    }
};
