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
            $table->timestamp('corroborado_por_arca_at')->nullable()->after('annulled_by_id')->comment('Corroborado por ARCA (tesorería)');
            $table->foreignId('corroborado_por_arca_by_id')->nullable()->after('corroborado_por_arca_at')->constrained('users')->nullOnDelete();
            $table->timestamp('comprobante_valido_at')->nullable()->after('corroborado_por_arca_by_id')->comment('Comprobante válido (solo contabilidad, una vez corroborado por ARCA)');
            $table->foreignId('comprobante_valido_by_id')->nullable()->after('comprobante_valido_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->dropForeign(['corroborado_por_arca_by_id']);
            $table->dropForeign(['comprobante_valido_by_id']);
            $table->dropColumn([
                'corroborado_por_arca_at',
                'corroborado_por_arca_by_id',
                'comprobante_valido_at',
                'comprobante_valido_by_id',
            ]);
        });
    }
};
