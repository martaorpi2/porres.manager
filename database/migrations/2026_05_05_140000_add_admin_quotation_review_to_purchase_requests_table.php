<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_requests')) {
            return;
        }

        Schema::table('purchase_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_requests', 'admin_quotation_reviewed_at')) {
                $table->timestamp('admin_quotation_reviewed_at')->nullable()->after('approval_justification');
            }
            if (! Schema::hasColumn('purchase_requests', 'admin_quotation_reviewed_by')) {
                $table->foreignId('admin_quotation_reviewed_by')->nullable()->after('admin_quotation_reviewed_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_requests')) {
            return;
        }

        Schema::table('purchase_requests', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_requests', 'admin_quotation_reviewed_by')) {
                $table->dropForeign(['admin_quotation_reviewed_by']);
                $table->dropColumn('admin_quotation_reviewed_by');
            }
            if (Schema::hasColumn('purchase_requests', 'admin_quotation_reviewed_at')) {
                $table->dropColumn('admin_quotation_reviewed_at');
            }
        });
    }
};
