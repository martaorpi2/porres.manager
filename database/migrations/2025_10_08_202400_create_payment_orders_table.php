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
        Schema::create('payment_orders', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique();
            $table->date('date');
            $table->decimal('total_amount', 12, 2);
            $table->enum('status', ['pending', 'approved', 'executed'])->default('pending');
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->onDelete('cascade');
            $table->foreignId('authorizing_user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });

        // Detalles de las órdenes de pago
        Schema::create('op_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_order_id')->constrained('payment_orders')->onDelete('cascade');
            $table->enum('concept', ['advance', 'residue', 'partiality']);
            $table->decimal('amount', 12, 2);
            $table->string('method_payment');
            $table->date('expiration_date')->nullable();
            $table->date('actual_payment_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('op_details');
        Schema::dropIfExists('payment_orders');
    }
};
