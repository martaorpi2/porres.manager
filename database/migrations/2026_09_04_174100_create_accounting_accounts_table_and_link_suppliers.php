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
        Schema::create('accounting_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->foreignId('accounting_account_id')
                ->nullable()
                ->after('supplier_heading_id')
                ->constrained('accounting_accounts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accounting_account_id');
        });

        Schema::dropIfExists('accounting_accounts');
    }
};
