<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Recepciones marcadas conforme solo con las 3 conformidades (regla antigua) pasan a No hasta cumplir ARCA + comprobante.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('receptions')) {
            return;
        }

        $cols = ['conformidad_estado', 'conformidad_cantidad', 'conformidad_factura', 'corroborado_por_arca_at', 'comprobante_valido_at'];
        foreach ($cols as $c) {
            if (! \Illuminate\Support\Facades\Schema::hasColumn('receptions', $c)) {
                return;
            }
        }

        DB::table('receptions')
            ->where('according', 'Si')
            ->where(function ($q) {
                $q->where('conformidad_estado', '!=', 'Si')
                    ->orWhere('conformidad_cantidad', '!=', 'Si')
                    ->orWhere('conformidad_factura', '!=', 'Si')
                    ->orWhereNull('corroborado_por_arca_at')
                    ->orWhereNull('comprobante_valido_at');
            })
            ->update(['according' => 'No']);
    }

    public function down(): void
    {
        // Sin reversa
    }
};
