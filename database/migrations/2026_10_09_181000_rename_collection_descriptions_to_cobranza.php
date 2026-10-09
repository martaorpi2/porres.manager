<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('accounting_entries')
            ->where('kind', 'quota_collection')
            ->where('description', 'like', 'ACREDITACION COBRANZA CUOTAS %')
            ->update([
                'description' => DB::raw("REPLACE(description, 'ACREDITACION COBRANZA CUOTAS ', 'COBRANZA CUOTAS ')"),
            ]);
    }

    public function down(): void
    {
        DB::table('accounting_entries')
            ->where('kind', 'quota_collection')
            ->where('description', 'like', 'COBRANZA CUOTAS %')
            ->where('description', 'not like', 'COBRANZA CUOTAS MERCADO PAGO A COBRAR%')
            ->update([
                'description' => DB::raw("REPLACE(description, 'COBRANZA CUOTAS ', 'ACREDITACION COBRANZA CUOTAS ')"),
            ]);
    }
};
