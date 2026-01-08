<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('general_requests', function (Blueprint $table) {
            $table->foreignId('analyzed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('analyzed_at')->nullable()->after('analyzed_by');
            $table->enum('analysis_status', ['pendiente', 'aprobada', 'rechazada', 'no_requerido'])->default('pendiente')->after('analyzed_at');
            $table->text('analysis_notes')->nullable()->after('analysis_status');
            $table->text('rejected_reason')->nullable()->after('analysis_notes');
        });

        // Agregar nuevo estado al enum de status
        DB::statement("ALTER TABLE general_requests MODIFY COLUMN status ENUM('creada', 'pendiente_analisis', 'revisada_area', 'archivada', 'sin_entrega', 'entregada_parcialmente', 'entregada_totalmente', 'rechazada_analista') DEFAULT 'creada'");

        // Marcar solicitudes existentes que no requieren análisis
        DB::statement("UPDATE general_requests SET analysis_status = 'no_requerido' WHERE status != 'creada' OR status IS NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_requests', function (Blueprint $table) {
            $table->dropForeign(['analyzed_by']);
            $table->dropColumn([
                'analyzed_by',
                'analyzed_at',
                'analysis_status',
                'analysis_notes',
                'rejected_reason'
            ]);
        });

        // Revertir el enum de status
        DB::statement("ALTER TABLE general_requests MODIFY COLUMN status ENUM('creada', 'revisada_area', 'archivada', 'sin_entrega', 'entregada_parcialmente', 'entregada_totalmente') DEFAULT 'creada'");
    }
};
