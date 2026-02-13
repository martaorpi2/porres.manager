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
        Schema::table('receptions', function (Blueprint $table) {
            $table->enum('conformidad_estado', ['Si', 'No'])->default('No')->after('date')->comment('Conformidad de estado de la mercadería');
            $table->enum('conformidad_cantidad', ['Si', 'No'])->default('No')->after('conformidad_estado')->comment('Conformidad de cantidad');
            $table->enum('conformidad_factura', ['Si', 'No'])->default('No')->after('conformidad_cantidad')->comment('Conformidad de factura recibida');
        });
        // Mantener conforme las recepciones que ya estaban conformes
        \DB::table('receptions')->where('according', 'Si')->update([
            'conformidad_estado' => 'Si',
            'conformidad_cantidad' => 'Si',
            'conformidad_factura' => 'Si',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receptions', function (Blueprint $table) {
            $table->dropColumn(['conformidad_estado', 'conformidad_cantidad', 'conformidad_factura']);
        });
    }
};
