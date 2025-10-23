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
        // Limpiar datos existentes en market_rates que no tienen purchase_request válido
        DB::table('market_rates')->whereNull('purchase_request_id')->delete();
        
        // Obtener el primer purchase_request disponible
        $firstPurchaseRequest = DB::table('purchase_requests')->first();
        
        if ($firstPurchaseRequest) {
            // Actualizar todos los market_rates para que apunten al primer purchase_request
            DB::table('market_rates')->update(['purchase_request_id' => $firstPurchaseRequest->id]);
        }

        Schema::table('market_rates', function (Blueprint $table) {
            // Hacer la columna no nullable
            $table->unsignedBigInteger('purchase_request_id')->nullable(false)->change();
            
            // Agregar restricción de clave foránea
            $table->foreign('purchase_request_id')->references('id')->on('purchase_requests')->onDelete('cascade');
            
            // Eliminar application_id si existe
            if (Schema::hasColumn('market_rates', 'application_id')) {
                $table->dropForeign(['application_id']);
                $table->dropColumn('application_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('market_rates', function (Blueprint $table) {
            if (Schema::hasColumn('market_rates', 'purchase_request_id')) {
                $table->dropForeign(['purchase_request_id']);
                $table->dropColumn('purchase_request_id');
            }
            
            $table->foreignId('application_id')->constrained('applications')->onDelete('cascade');
        });
    }
};