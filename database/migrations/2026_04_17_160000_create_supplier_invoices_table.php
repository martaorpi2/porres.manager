<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('invoice_number', 64);
            $table->string('invoice_type', 32)->nullable()->comment('Ej: A, B, C, Crédito fiscal');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('amount_total', 14, 2);
            $table->decimal('tax_amount', 14, 2)->nullable();
            $table->string('currency', 8)->default('ARS');
            $table->text('notes')->nullable();
            $table->string('attachment')->nullable()->comment('Ruta en disco public');
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['supplier_id', 'invoice_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};
