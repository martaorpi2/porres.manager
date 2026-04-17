<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_invoices')) {
            return;
        }

        if (Schema::hasColumn('supplier_invoices', 'purchase_order_id')) {
            Schema::table('supplier_invoices', function (Blueprint $table) {
                $table->dropForeign(['purchase_order_id']);
            });
            Schema::table('supplier_invoices', function (Blueprint $table) {
                $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->cascadeOnDelete();
            });
        }

        if (! Schema::hasColumn('supplier_invoices', 'attachment')) {
            Schema::table('supplier_invoices', function (Blueprint $table) {
                $table->string('attachment')->nullable()->after('observations');
            });
        }
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
