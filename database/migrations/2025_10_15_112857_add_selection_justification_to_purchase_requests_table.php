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
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->text('selection_justification')->nullable()->after('selected_market_rate_id');
            $table->foreignId('selected_by')->nullable()->constrained('users')->nullOnDelete()->after('selection_justification');
            $table->timestamp('selected_at')->nullable()->after('selected_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropForeign(['selected_by']);
            $table->dropColumn(['selection_justification', 'selected_by', 'selected_at']);
        });
    }
};
