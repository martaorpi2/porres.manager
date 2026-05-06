<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Permite que una misma orden de compra tenga líneas con distintos proveedores (uno por producto).
     */
    public function up(): void
    {
        if (! Schema::hasTable('oc_details') || ! Schema::hasTable('purchase_orders')) {
            return;
        }

        if (! Schema::hasColumn('oc_details', 'supplier_id')) {
            Schema::table('oc_details', function (Blueprint $table) {
                $table->foreignId('supplier_id')->nullable()->after('purchase_order_id')->constrained('suppliers')->onDelete('restrict');
            });

            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                DB::statement('UPDATE oc_details d INNER JOIN purchase_orders po ON d.purchase_order_id = po.id SET d.supplier_id = po.supplier_id');
            } else {
                foreach (DB::table('purchase_orders')->select('id', 'supplier_id')->get() as $po) {
                    DB::table('oc_details')->where('purchase_order_id', $po->id)->update(['supplier_id' => $po->supplier_id]);
                }
            }

            Schema::table('oc_details', function (Blueprint $table) {
                $table->foreignId('supplier_id')->nullable(false)->change();
            });
        } else {
            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                DB::statement('UPDATE oc_details d INNER JOIN purchase_orders po ON d.purchase_order_id = po.id SET d.supplier_id = COALESCE(d.supplier_id, po.supplier_id)');
            } else {
                foreach (DB::table('purchase_orders')->select('id', 'supplier_id')->get() as $po) {
                    DB::table('oc_details')
                        ->where('purchase_order_id', $po->id)
                        ->whereNull('supplier_id')
                        ->update(['supplier_id' => $po->supplier_id]);
                }
            }
        }

        if (Schema::hasColumn('purchase_orders', 'supplier_id')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->foreignId('supplier_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable(false)->change();
        });

        Schema::table('oc_details', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
        });
    }
};
