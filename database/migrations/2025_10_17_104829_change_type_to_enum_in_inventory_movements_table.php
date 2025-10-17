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
        // First, update any existing data to match our enum values
        // You may need to adjust these mappings based on your existing data
        DB::table('inventory_movements')->where('type', 'purchase')->update(['type' => 'compra']);
        DB::table('inventory_movements')->where('type', 'sale')->update(['type' => 'uso']);
        DB::table('inventory_movements')->where('type', 'adjustment')->update(['type' => 'desuso']);
        DB::table('inventory_movements')->where('type', 'spoilage')->update(['type' => 'baja']);
        DB::table('inventory_movements')->where('type', 'transfer')->update(['type' => 'uso']);
        
        // Set any other values to 'uso' as default
        DB::table('inventory_movements')->whereNotIn('type', ['uso', 'compra', 'desuso', 'baja'])->update(['type' => 'uso']);
        
        // Now change the column to enum
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->enum('type', ['uso', 'compra', 'desuso', 'baja'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->string('type')->change();
        });
    }
};
