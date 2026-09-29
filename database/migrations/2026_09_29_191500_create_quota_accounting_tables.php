<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quota_accounting_batches', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 24);
            $table->string('batch_key', 120)->unique();
            $table->char('period', 7)->nullable()->index();
            $table->date('entry_date');
            $table->string('payment_type')->nullable();
            $table->foreignId('accounting_entry_id')->nullable()->constrained('accounting_entries')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('quota_accounting_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quota_accounting_batch_id')->constrained('quota_accounting_batches')->cascadeOnDelete();
            $table->string('role', 24);
            $table->unsignedBigInteger('eporres_order_id');
            $table->decimal('debtors_amount', 14, 2)->default(0);
            $table->decimal('interest_amount', 14, 2)->default(0);
            $table->decimal('bank_amount', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['role', 'eporres_order_id']);
            $table->index('eporres_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quota_accounting_orders');
        Schema::dropIfExists('quota_accounting_batches');
    }
};
