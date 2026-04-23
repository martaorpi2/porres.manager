<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_invoices')) {
            return;
        }

        if (Schema::hasColumn('supplier_invoices', 'purchase_order_id')) {
            $this->dropPurchaseOrderForeignKeysIfAny();

            Schema::table('supplier_invoices', function (Blueprint $table) {
                $table->unsignedBigInteger('purchase_order_id')->nullable()->change();
            });

            $this->ensurePurchaseOrderForeignKey();
        }

        if (! Schema::hasColumn('supplier_invoices', 'attachment')) {
            Schema::table('supplier_invoices', function (Blueprint $table) {
                $table->string('attachment')->nullable()->after('observations');
            });
        }
    }

    /**
     * La migración original usaba foreignId() de nuevo tras dropForeign, lo que intentaba
     * crear la columna duplicada. Aquí solo se altera la columna existente.
     */
    protected function dropPurchaseOrderForeignKeysIfAny(): void
    {
        $conn = Schema::getConnection();
        $db = $conn->getDatabaseName();
        $rows = $conn->select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$db, 'supplier_invoices', 'purchase_order_id']
        );
        foreach ($rows as $row) {
            $name = str_replace('`', '``', (string) $row->CONSTRAINT_NAME);
            DB::statement('ALTER TABLE `supplier_invoices` DROP FOREIGN KEY `'.$name.'`');
        }
    }

    protected function ensurePurchaseOrderForeignKey(): void
    {
        $conn = Schema::getConnection();
        $db = $conn->getDatabaseName();
        $count = (int) ($conn->selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME = ?',
            [$db, 'supplier_invoices', 'purchase_order_id', 'purchase_orders']
        )->c ?? 0);
        if ($count > 0) {
            return;
        }

        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->foreign('purchase_order_id')
                ->references('id')
                ->on('purchase_orders')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('supplier_invoices')) {
            return;
        }

        if (Schema::hasColumn('supplier_invoices', 'attachment')) {
            Schema::table('supplier_invoices', function (Blueprint $table) {
                $table->dropColumn('attachment');
            });
        }
    }
};
