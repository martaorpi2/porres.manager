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
        Schema::table('market_rates', function (Blueprint $table) {
            if (! Schema::hasColumn('market_rates', 'validity_term')) {
                $table->string('validity_term', 255)->nullable()->after('payment_method');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('market_rates', function (Blueprint $table) {
            if (Schema::hasColumn('market_rates', 'validity_term')) {
                $table->dropColumn('validity_term');
            }
        });
    }
};
