<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_requests')) {
            return;
        }

        if (! Schema::hasColumn('purchase_requests', 'created_by')) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('requesting_user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        DB::table('purchase_requests')
            ->whereNull('created_by')
            ->update(['created_by' => DB::raw('requesting_user_id')]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_requests') || ! Schema::hasColumn('purchase_requests', 'created_by')) {
            return;
        }

        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
