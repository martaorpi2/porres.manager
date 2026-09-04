<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('remitos')) {
            Schema::create('remitos', function (Blueprint $table) {
                $table->id();
                $table->string('number', 64);
                $table->date('date');
                $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
                $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
                $table->text('observations')->nullable();
                $table->string('attachment')->nullable();
                $table->timestamps();

                $table->unique(['supplier_id', 'number'], 'remitos_supplier_number_uq');
            });
        }

        if (Schema::hasTable('stock_levels')) {
            Schema::table('stock_levels', function (Blueprint $table) {
                if (! Schema::hasColumn('stock_levels', 'document_kind')) {
                    $table->string('document_kind', 16)->nullable()->after('date');
                }
                if (! Schema::hasColumn('stock_levels', 'supplier_invoice_id')) {
                    $table->foreignId('supplier_invoice_id')
                        ->nullable()
                        ->after('document_kind')
                        ->constrained('supplier_invoices')
                        ->restrictOnDelete();
                }
                if (! Schema::hasColumn('stock_levels', 'remito_id')) {
                    $table->foreignId('remito_id')
                        ->nullable()
                        ->after('supplier_invoice_id')
                        ->constrained('remitos')
                        ->restrictOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_levels')) {
            Schema::table('stock_levels', function (Blueprint $table) {
                if (Schema::hasColumn('stock_levels', 'remito_id')) {
                    $table->dropConstrainedForeignId('remito_id');
                }
                if (Schema::hasColumn('stock_levels', 'supplier_invoice_id')) {
                    $table->dropConstrainedForeignId('supplier_invoice_id');
                }
                if (Schema::hasColumn('stock_levels', 'document_kind')) {
                    $table->dropColumn('document_kind');
                }
            });
        }

        Schema::dropIfExists('remitos');
    }
};
