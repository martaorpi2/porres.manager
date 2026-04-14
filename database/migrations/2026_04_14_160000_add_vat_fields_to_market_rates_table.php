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
            if (! Schema::hasColumn('market_rates', 'vat_amount')) {
                $table->decimal('vat_amount', 12, 2)->nullable()->after('total_amount');
            }
            if (! Schema::hasColumn('market_rates', 'total_amount_with_vat')) {
                $table->decimal('total_amount_with_vat', 12, 2)->nullable()->after('vat_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('market_rates', function (Blueprint $table) {
            if (Schema::hasColumn('market_rates', 'total_amount_with_vat')) {
                $table->dropColumn('total_amount_with_vat');
            }
            if (Schema::hasColumn('market_rates', 'vat_amount')) {
                $table->dropColumn('vat_amount');
            }
        });
    }
};
