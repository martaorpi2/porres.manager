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
        // Verificar si la columna existe antes de eliminarla
        $columns = DB::select('SHOW COLUMNS FROM devolutions');
        $columnExists = false;
        foreach ($columns as $column) {
            if ($column->Field === 'amount_returned') {
                $columnExists = true;
                break;
            }
        }
        
        if ($columnExists) {
            Schema::table('devolutions', function (Blueprint $table) {
                $table->dropColumn('amount_returned');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devolutions', function (Blueprint $table) {
            $table->decimal('amount_returned', 12, 2)->nullable();
        });
    }
};
