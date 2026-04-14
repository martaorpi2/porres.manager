<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bases sin 2026_02_13_* no tienen conformidades ni ARCA/comprobante en receptions → el CRUD no puede guardar esos datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('receptions')) {
            return;
        }

        if (! Schema::hasColumn('receptions', 'conformidad_estado')) {
            Schema::table('receptions', function (Blueprint $table) {
                $table->enum('conformidad_estado', ['Si', 'No'])->default('No')->after('date');
            });
        }
        if (! Schema::hasColumn('receptions', 'conformidad_cantidad')) {
            Schema::table('receptions', function (Blueprint $table) {
                $table->enum('conformidad_cantidad', ['Si', 'No'])->default('No')->after('conformidad_estado');
            });
        }
        if (! Schema::hasColumn('receptions', 'conformidad_factura')) {
            Schema::table('receptions', function (Blueprint $table) {
                $table->enum('conformidad_factura', ['Si', 'No'])->default('No')->after('conformidad_cantidad');
            });
        }

        if (! Schema::hasColumn('receptions', 'corroborado_por_arca_at')) {
            Schema::table('receptions', function (Blueprint $table) {
                $after = Schema::hasColumn('receptions', 'conformidad_factura') ? 'conformidad_factura' : 'date';
                $table->timestamp('corroborado_por_arca_at')->nullable()->after($after);
            });
        }
        if (! Schema::hasColumn('receptions', 'corroborado_por_arca_by_id')) {
            Schema::table('receptions', function (Blueprint $table) {
                $table->foreignId('corroborado_por_arca_by_id')->nullable()->after('corroborado_por_arca_at')->constrained('users')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('receptions', 'comprobante_valido_at')) {
            Schema::table('receptions', function (Blueprint $table) {
                $table->timestamp('comprobante_valido_at')->nullable()->after('corroborado_por_arca_by_id');
            });
        }
        if (! Schema::hasColumn('receptions', 'comprobante_valido_by_id')) {
            Schema::table('receptions', function (Blueprint $table) {
                $table->foreignId('comprobante_valido_by_id')->nullable()->after('comprobante_valido_at')->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Sin reversa: columnas pueden existir por migraciones previas.
    }
};
