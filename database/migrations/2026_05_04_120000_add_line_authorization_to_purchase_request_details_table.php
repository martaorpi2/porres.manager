<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_request_details')) {
            return;
        }

        Schema::table('purchase_request_details', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_request_details', 'line_authorization_status')) {
                $table->string('line_authorization_status', 32)->default('pending')->after('status');
            }
            if (! Schema::hasColumn('purchase_request_details', 'line_authorization_rejection_reason')) {
                $table->text('line_authorization_rejection_reason')->nullable()->after('line_authorization_status');
            }
            if (! Schema::hasColumn('purchase_request_details', 'line_authorized_by')) {
                $table->foreignId('line_authorized_by')->nullable()->after('line_authorization_rejection_reason')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('purchase_request_details', 'line_authorized_at')) {
                $table->timestamp('line_authorized_at')->nullable()->after('line_authorized_by');
            }
        });

        // Solicitudes ya cerradas o aprobadas: considerar todas las líneas autorizadas para compra.
        if (Schema::hasColumn('purchase_request_details', 'line_authorization_status')) {
            DB::table('purchase_request_details as prd')
                ->join('purchase_requests as pr', 'prd.purchase_request_id', '=', 'pr.id')
                ->whereIn('pr.status', ['Aprobada', 'Completada'])
                ->update(['prd.line_authorization_status' => 'approved']);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_request_details')) {
            return;
        }

        Schema::table('purchase_request_details', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_request_details', 'line_authorized_at')) {
                $table->dropColumn('line_authorized_at');
            }
            if (Schema::hasColumn('purchase_request_details', 'line_authorized_by')) {
                $table->dropForeign(['line_authorized_by']);
                $table->dropColumn('line_authorized_by');
            }
            if (Schema::hasColumn('purchase_request_details', 'line_authorization_rejection_reason')) {
                $table->dropColumn('line_authorization_rejection_reason');
            }
            if (Schema::hasColumn('purchase_request_details', 'line_authorization_status')) {
                $table->dropColumn('line_authorization_status');
            }
        });
    }
};
