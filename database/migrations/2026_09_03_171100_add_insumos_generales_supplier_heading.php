<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('suppliers_headings')
            ->where('name', 'Insumos Generales')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('suppliers_headings')->insert([
            'name' => 'Insumos Generales',
            'description' => 'Proveedores de insumos de uso general',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $heading = DB::table('suppliers_headings')->where('name', 'Insumos Generales')->first();

        if (! $heading) {
            return;
        }

        $inUse = DB::table('suppliers')->where('supplier_heading_id', $heading->id)->exists();
        if ($inUse) {
            return;
        }

        DB::table('suppliers_headings')->where('id', $heading->id)->delete();
    }
};
