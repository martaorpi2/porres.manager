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
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->timestamp('annulled_at')->nullable()->after('status');
            $table->text('annulment_reason')->nullable()->after('annulled_at');
            $table->foreignId('annulled_by_id')->nullable()->after('annulment_reason')->constrained('users')->nullOnDelete();
        });

        \DB::statement("ALTER TABLE payment_orders MODIFY COLUMN status ENUM('Pendiente', 'Aprobada', 'Ejecutada', 'Anulada') DEFAULT 'Pendiente'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \DB::statement("ALTER TABLE payment_orders MODIFY COLUMN status ENUM('Pendiente', 'Aprobada', 'Ejecutada') DEFAULT 'Pendiente'");

        Schema::table('payment_orders', function (Blueprint $table) {
            $table->dropForeign(['annulled_by_id']);
            $table->dropColumn(['annulled_at', 'annulment_reason', 'annulled_by_id']);
        });
    }
};
