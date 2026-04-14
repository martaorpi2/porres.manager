<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * generatePurchaseOrder() inserta supplier_id en oc_details.
 * Migración 2026_02_13_000000 puede no haberse aplicado en esta BD.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('oc_details')) {
            return;
        }

        if (Schema::hasColumn('oc_details', 'supplier_id')) {
            return;
        }

        Schema::table('oc_details', function (Blueprint $table) {
            $table->foreignId('supplier_id')
                ->nullable()
                ->after('purchase_order_id')
                ->constrained('suppliers')
                ->onDelete('restrict');
        });

        if (Schema::hasTable('purchase_orders') && Schema::hasColumn('purchase_orders', 'supplier_id')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement('UPDATE oc_details d INNER JOIN purchase_orders po ON d.purchase_order_id = po.id SET d.supplier_id = po.supplier_id WHERE d.supplier_id IS NULL AND po.supplier_id IS NOT NULL');
            } else {
                foreach (DB::table('purchase_orders')->select('id', 'supplier_id')->get() as $po) {
                    if ($po->supplier_id) {
                        DB::table('oc_details')->where('purchase_order_id', $po->id)->whereNull('supplier_id')->update(['supplier_id' => $po->supplier_id]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('oc_details') || ! Schema::hasColumn('oc_details', 'supplier_id')) {
            return;
        }

        Schema::table('oc_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};
