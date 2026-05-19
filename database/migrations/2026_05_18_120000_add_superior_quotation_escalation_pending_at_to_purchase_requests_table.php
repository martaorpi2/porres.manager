<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_requests', 'superior_quotation_escalation_pending_at')) {
                $table->timestamp('superior_quotation_escalation_pending_at')->nullable()->after('admin_quotation_review_justification');
            }
        });

        if (! Schema::hasTable('purchase_request_events')) {
            return;
        }

        $eventTypes = [
            'administrator_superior_approval_requested',
            'administration_initial_review_pending_superior',
        ];

        $rows = DB::table('purchase_request_events')
            ->select('purchase_request_id', DB::raw('MAX(created_at) as last_at'))
            ->whereIn('event_type', $eventTypes)
            ->groupBy('purchase_request_id')
            ->get();

        foreach ($rows as $row) {
            DB::table('purchase_requests')
                ->where('id', $row->purchase_request_id)
                ->whereNull('superior_quotation_escalation_pending_at')
                ->update(['superior_quotation_escalation_pending_at' => $row->last_at]);
        }
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_requests', 'superior_quotation_escalation_pending_at')) {
                $table->dropColumn('superior_quotation_escalation_pending_at');
            }
        });
    }
};
