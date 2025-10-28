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
        // Obtener el primer purchase_request disponible
        $firstPurchaseRequest = DB::table('purchase_requests')->first();
        
        if (!$firstPurchaseRequest) {
            // Si no hay purchase_requests, crear uno vacío o saltar la migración
            return;
        }

        // 1. Primero agregar la nueva columna purchase_request_id como nullable
        if (!Schema::hasColumn('market_rates', 'purchase_request_id')) {
            Schema::table('market_rates', function (Blueprint $table) {
                $table->unsignedBigInteger('purchase_request_id')->nullable();
            });
        }
        
        // 2. Poblar todos los registros con el primer purchase_request (si hay datos)
        $hasData = DB::table('market_rates')->exists();
        if ($hasData) {
            DB::table('market_rates')->update(['purchase_request_id' => $firstPurchaseRequest->id]);
        }
        
        Schema::table('market_rates', function (Blueprint $table) {
            // 3. Eliminar application_id si existe (antes de hacer purchase_request_id no nullable)
            if (Schema::hasColumn('market_rates', 'application_id')) {
                // Eliminar el foreign key primero
                $table->dropForeign(['application_id']);
                $table->dropColumn('application_id');
            }
        });
        
        // 4. Hacer la columna purchase_request_id no nullable
        if (Schema::hasColumn('market_rates', 'purchase_request_id')) {
            // Usar DB::statement para alterar la columna
            DB::statement('ALTER TABLE `market_rates` MODIFY COLUMN `purchase_request_id` BIGINT UNSIGNED NOT NULL');
            
            // 5. Agregar restricción de clave foránea
            Schema::table('market_rates', function (Blueprint $table) {
                $table->foreign('purchase_request_id')->references('id')->on('purchase_requests')->onDelete('cascade');
            });
        }
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
        });
        
        Schema::table('market_rates', function (Blueprint $table) {
            // Recrear application_id si fue eliminado
            if (!Schema::hasColumn('market_rates', 'application_id')) {
                $table->foreignId('application_id')->constrained('applications')->onDelete('cascade');
            }
        });
    }
};