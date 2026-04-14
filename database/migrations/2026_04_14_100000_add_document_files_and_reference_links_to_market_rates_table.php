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
            if (! Schema::hasColumn('market_rates', 'document_files')) {
                $table->json('document_files')->nullable()->after('total_amount');
            }
            if (! Schema::hasColumn('market_rates', 'reference_links')) {
                $table->text('reference_links')->nullable()->after('document_files');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('market_rates', function (Blueprint $table) {
            if (Schema::hasColumn('market_rates', 'reference_links')) {
                $table->dropColumn('reference_links');
            }
            if (Schema::hasColumn('market_rates', 'document_files')) {
                $table->dropColumn('document_files');
            }
        });
    }
};
