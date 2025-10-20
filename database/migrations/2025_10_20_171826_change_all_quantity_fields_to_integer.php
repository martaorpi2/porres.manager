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
        // Cambiar quantity en stock_levels_table si aún es decimal
        if (Schema::hasColumn('stock_levels', 'quantity')) {
            Schema::table('stock_levels', function (Blueprint $table) {
                $table->integer('quantity')->default(0)->change();
            });
        }

        // Cambiar quantity en inventory_movements_table si aún es decimal
        if (Schema::hasColumn('inventory_movements', 'quantity')) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                $table->integer('quantity')->change();
            });
        }

        // Cambiar quantity en quote_details_table si aún es decimal
        if (Schema::hasColumn('quote_details', 'quantity')) {
            Schema::table('quote_details', function (Blueprint $table) {
                $table->integer('quantity')->default(1)->change();
            });
        }

        // Cambiar quantity en request_details_table si aún es decimal
        if (Schema::hasColumn('request_details', 'quantity')) {
            Schema::table('request_details', function (Blueprint $table) {
                $table->integer('quantity')->default(1)->change();
            });
        }

        // Cambiar quantity en purchase_orders_table si aún es decimal
        if (Schema::hasColumn('purchase_orders', 'quantity')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->integer('quantity')->change();
            });
        }

        // Cambiar requested_quantity en purchase_request_details_table si aún es decimal
        if (Schema::hasColumn('purchase_request_details', 'requested_quantity')) {
            Schema::table('purchase_request_details', function (Blueprint $table) {
                $table->integer('requested_quantity')->change();
            });
        }

        // Cambiar requested_quantity en general_request_details_table si aún es decimal
        if (Schema::hasColumn('general_request_details', 'requested_quantity')) {
            Schema::table('general_request_details', function (Blueprint $table) {
                $table->integer('requested_quantity')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir cambios si es necesario
        if (Schema::hasColumn('stock_levels', 'quantity')) {
            Schema::table('stock_levels', function (Blueprint $table) {
                $table->decimal('quantity', 10, 2)->default(0)->change();
            });
        }

        if (Schema::hasColumn('inventory_movements', 'quantity')) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                $table->decimal('quantity', 10, 2)->change();
            });
        }

        if (Schema::hasColumn('quote_details', 'quantity')) {
            Schema::table('quote_details', function (Blueprint $table) {
                $table->decimal('quantity', 10, 2)->default(1)->change();
            });
        }

        if (Schema::hasColumn('request_details', 'quantity')) {
            Schema::table('request_details', function (Blueprint $table) {
                $table->decimal('quantity', 10, 2)->default(1)->change();
            });
        }

        if (Schema::hasColumn('purchase_orders', 'quantity')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->decimal('quantity', 10, 2)->change();
            });
        }

        if (Schema::hasColumn('purchase_request_details', 'requested_quantity')) {
            Schema::table('purchase_request_details', function (Blueprint $table) {
                $table->decimal('requested_quantity', 10, 2)->change();
            });
        }

        if (Schema::hasColumn('general_request_details', 'requested_quantity')) {
            Schema::table('general_request_details', function (Blueprint $table) {
                $table->decimal('requested_quantity', 10, 2)->change();
            });
        }
    }
};