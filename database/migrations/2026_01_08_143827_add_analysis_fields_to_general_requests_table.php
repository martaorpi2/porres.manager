<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('general_requests')) {
            return;
        }

        $needsColumns = ! Schema::hasColumn('general_requests', 'analyzed_by')
            || ! Schema::hasColumn('general_requests', 'analyzed_at')
            || ! Schema::hasColumn('general_requests', 'analysis_status')
            || ! Schema::hasColumn('general_requests', 'analysis_notes')
            || ! Schema::hasColumn('general_requests', 'rejected_reason');

        if ($needsColumns) {
            Schema::table('general_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('general_requests', 'analyzed_by')) {
                    $table->foreignId('analyzed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('general_requests', 'analyzed_at')) {
                    $table->timestamp('analyzed_at')->nullable()->after('analyzed_by');
                }
                if (! Schema::hasColumn('general_requests', 'analysis_status')) {
                    $table->enum('analysis_status', ['pendiente', 'aprobada', 'rechazada', 'no_requerido'])->default('pendiente')->after('analyzed_at');
                }
                if (! Schema::hasColumn('general_requests', 'analysis_notes')) {
                    $table->text('analysis_notes')->nullable()->after('analysis_status');
                }
                if (! Schema::hasColumn('general_requests', 'rejected_reason')) {
                    $table->text('rejected_reason')->nullable()->after('analysis_notes');
                }
            });
        }

        if (! Schema::hasColumn('general_requests', 'analysis_status')) {
            return;
        }

        try {
            DB::statement("ALTER TABLE general_requests MODIFY COLUMN status ENUM('creada', 'pendiente_analisis', 'revisada_area', 'archivada', 'sin_entrega', 'entregada_parcialmente', 'entregada_totalmente', 'rechazada_analista') DEFAULT 'creada'");
        } catch (\Throwable) {
            //
        }

        try {
            DB::statement("UPDATE general_requests SET analysis_status = 'no_requerido' WHERE status != 'creada' OR status IS NULL");
        } catch (\Throwable) {
            //
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('general_requests')) {
            return;
        }

        if (Schema::hasColumn('general_requests', 'analyzed_by')) {
            try {
                Schema::table('general_requests', function (Blueprint $table) {
                    $table->dropForeign(['analyzed_by']);
                });
            } catch (\Throwable) {
                //
            }
        }

        $drops = [];
        foreach (['analyzed_by', 'analyzed_at', 'analysis_status', 'analysis_notes', 'rejected_reason'] as $col) {
            if (Schema::hasColumn('general_requests', $col)) {
                $drops[] = $col;
            }
        }
        if ($drops !== []) {
            Schema::table('general_requests', function (Blueprint $table) use ($drops) {
                $table->dropColumn($drops);
            });
        }

        try {
            DB::statement("ALTER TABLE general_requests MODIFY COLUMN status ENUM('creada', 'revisada_area', 'archivada', 'sin_entrega', 'entregada_parcialmente', 'entregada_totalmente') DEFAULT 'creada'");
        } catch (\Throwable) {
            //
        }
    }
};
