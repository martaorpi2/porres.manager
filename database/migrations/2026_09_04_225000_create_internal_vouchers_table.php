<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('internal_vouchers')) {
            return;
        }

        Schema::create('internal_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->date('date');
            $table->string('type', 20);
            $table->string('motive', 40);
            $table->text('concept');
            $table->string('beneficiary');
            $table->decimal('amount', 14, 2);
            $table->string('currency_code', 3)->default('ARS');
            $table->foreignId('accounting_account_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->string('payment_method')->nullable();
            $table->foreignId('authorizing_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('payment_order_id')->nullable()->constrained('payment_orders')->nullOnDelete();
            $table->string('status', 20)->default('Emitido');
            $table->text('observations')->nullable();
            $table->string('attachment')->nullable();
            $table->timestamp('annulled_at')->nullable();
            $table->text('annulment_reason')->nullable();
            $table->foreignId('annulled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_vouchers');
    }
};
