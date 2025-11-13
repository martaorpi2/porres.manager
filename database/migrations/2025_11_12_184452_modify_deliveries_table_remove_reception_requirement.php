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
        Schema::table('deliveries', function (Blueprint $table) {
            // Hacer reception_id nullable (ya no es obligatorio)
            $table->foreignId('reception_id')->nullable()->change();
            
            // Hacer general_request_id nullable
            $table->foreignId('general_request_id')->nullable()->change();
            
            // Agregar purchase_request_id como nullable
            $table->foreignId('purchase_request_id')->nullable()->after('general_request_id')
                ->constrained('purchase_requests')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            // Eliminar purchase_request_id
            $table->dropForeign(['purchase_request_id']);
            $table->dropColumn('purchase_request_id');
            
            // Revertir a no nullable (si es necesario)
            // Nota: Esto puede fallar si hay registros con valores null
            // $table->foreignId('general_request_id')->nullable(false)->change();
            // $table->foreignId('reception_id')->nullable(false)->change();
        });
    }
};
