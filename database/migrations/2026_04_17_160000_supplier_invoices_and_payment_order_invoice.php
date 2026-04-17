<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_invoices')) {
            Schema::create('supplier_invoices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
                $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
                $table->string('invoice_number', 64);
                $table->date('invoice_date');
                $table->decimal('total_amount', 14, 2);
                $table->string('currency_code', 3)->default('ARS');
                $table->text('observations')->nullable();
                $table->timestamps();

                $table->unique(['purchase_order_id', 'supplier_id', 'invoice_number'], 'supplier_invoices_po_supplier_num_uq');
            });
        }

        if (! Schema::hasTable('payment_order_invoice')) {
            Schema::create('payment_order_invoice', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_order_id')->constrained('payment_orders')->cascadeOnDelete();
                $table->foreignId('supplier_invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
                $table->decimal('amount_applied', 14, 2);
                $table->date('imputed_at');
                $table->timestamps();

                $table->unique(['payment_order_id', 'supplier_invoice_id'], 'po_invoice_unique_pair');
            });
        }

        if (Schema::hasTable('payment_orders')) {
            if (! Schema::hasColumn('payment_orders', 'billing_kind')) {
                Schema::table('payment_orders', function (Blueprint $table) {
                    $table->string('billing_kind', 16)->default('normal')->after('total_amount');
                });
            }
            if (! Schema::hasColumn('payment_orders', 'currency_code')) {
                Schema::table('payment_orders', function (Blueprint $table) {
                    $table->string('currency_code', 3)->nullable()->after('billing_kind');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_order_invoice')) {
            Schema::dropIfExists('payment_order_invoice');
        }

        if (Schema::hasTable('supplier_invoices')) {
            Schema::dropIfExists('supplier_invoices');
        }

        if (Schema::hasTable('payment_orders')) {
            Schema::table('payment_orders', function (Blueprint $table) {
                if (Schema::hasColumn('payment_orders', 'currency_code')) {
                    $table->dropColumn('currency_code');
                }
                if (Schema::hasColumn('payment_orders', 'billing_kind')) {
                    $table->dropColumn('billing_kind');
                }
            });
        }
    }
};
