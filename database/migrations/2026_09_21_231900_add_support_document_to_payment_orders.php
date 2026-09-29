<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_orders')) {
            return;
        }

        Schema::table('payment_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_orders', 'support_document_kind')) {
                $table->string('support_document_kind', 16)->nullable()->after('billing_kind');
            }
            if (! Schema::hasColumn('payment_orders', 'supplier_invoice_id')) {
                $table->foreignId('supplier_invoice_id')
                    ->nullable()
                    ->after('support_document_kind')
                    ->constrained('supplier_invoices')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('payment_orders', 'remito_id')) {
                $table->foreignId('remito_id')
                    ->nullable()
                    ->after('supplier_invoice_id')
                    ->constrained('remitos')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_orders')) {
            return;
        }

        Schema::table('payment_orders', function (Blueprint $table) {
            if (Schema::hasColumn('payment_orders', 'remito_id')) {
                $table->dropConstrainedForeignId('remito_id');
            }
            if (Schema::hasColumn('payment_orders', 'supplier_invoice_id')) {
                $table->dropConstrainedForeignId('supplier_invoice_id');
            }
            if (Schema::hasColumn('payment_orders', 'support_document_kind')) {
                $table->dropColumn('support_document_kind');
            }
        });
    }
};
