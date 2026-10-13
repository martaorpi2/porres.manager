<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('accounting_entries')
            ->where('description', 'like', 'LIQUIDACION%')
            ->update([
                'description' => DB::raw("REPLACE(description, 'LIQUIDACION', 'ACREDITACION')"),
            ]);
    }

    public function down(): void
    {
        DB::table('accounting_entries')
            ->where(function ($query) {
                $query->where('description', 'like', 'ACREDITACION COBRANZA MERCADO PAGO%')
                    ->orWhere('description', 'like', 'ACREDITACION COBRANZA NARANJA X%')
                    ->orWhere('description', 'like', 'ACREDITACION COBRANZA SOL PAGO%')
                    ->orWhere('description', 'like', 'ACREDITACION COBRANZA QR%');
            })
            ->update([
                'description' => DB::raw("REPLACE(description, 'ACREDITACION', 'LIQUIDACION')"),
            ]);
    }
};
