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
            if (!Schema::hasColumn('purchase_requests', 'is_direct_purchase')) {
                $table->boolean('is_direct_purchase')->default(false)->after('requires_admin_approval')->comment('Indica si es una compra directa (único proveedor por especialidad)');
            }
            if (!Schema::hasColumn('purchase_requests', 'direct_purchase_justification')) {
                $table->text('direct_purchase_justification')->nullable()->after('is_direct_purchase')->comment('Justificación de la compra directa por parte del responsable de área o compras');
            }
            if (!Schema::hasColumn('purchase_requests', 'direct_purchase_supplier_id')) {
                $table->unsignedBigInteger('direct_purchase_supplier_id')->nullable()->after('direct_purchase_justification')->comment('Proveedor único para la compra directa');
            }
            if (!Schema::hasColumn('purchase_requests', 'direct_purchase_authorization_requested')) {
                $table->boolean('direct_purchase_authorization_requested')->default(false)->after('direct_purchase_supplier_id')->comment('Indica si se ha solicitado autorización para la compra directa');
            }
            if (!Schema::hasColumn('purchase_requests', 'direct_purchase_authorization_requested_by')) {
                $table->unsignedBigInteger('direct_purchase_authorization_requested_by')->nullable()->after('direct_purchase_authorization_requested')->comment('Usuario que solicitó la autorización');
            }
            if (!Schema::hasColumn('purchase_requests', 'direct_purchase_authorization_requested_at')) {
                $table->timestamp('direct_purchase_authorization_requested_at')->nullable()->after('direct_purchase_authorization_requested_by')->comment('Fecha y hora de la solicitud de autorización');
            }
            if (!Schema::hasColumn('purchase_requests', 'direct_purchase_authorized_by')) {
                $table->unsignedBigInteger('direct_purchase_authorized_by')->nullable()->after('direct_purchase_authorization_requested_at')->comment('Usuario que autorizó la compra directa');
            }
            if (!Schema::hasColumn('purchase_requests', 'direct_purchase_authorized_at')) {
                $table->timestamp('direct_purchase_authorized_at')->nullable()->after('direct_purchase_authorized_by')->comment('Fecha y hora de la autorización');
            }
            if (!Schema::hasColumn('purchase_requests', 'direct_purchase_authorization_rejected')) {
                $table->boolean('direct_purchase_authorization_rejected')->default(false)->after('direct_purchase_authorized_at')->comment('Indica si la autorización fue rechazada');
            }
            if (!Schema::hasColumn('purchase_requests', 'direct_purchase_authorization_rejection_reason')) {
                $table->text('direct_purchase_authorization_rejection_reason')->nullable()->after('direct_purchase_authorization_rejected')->comment('Razón del rechazo de la autorización');
            }
        });
        
        // Agregar foreign keys con nombres cortos (solo si no existen)
        Schema::table('purchase_requests', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_requests', 'direct_purchase_supplier_id')) {
                try {
                    $table->foreign('direct_purchase_supplier_id', 'pr_dp_supplier_fk')->references('id')->on('suppliers')->onDelete('set null');
                } catch (\Exception $e) {
                    // Foreign key ya existe, ignorar
                }
            }
            if (Schema::hasColumn('purchase_requests', 'direct_purchase_authorization_requested_by')) {
                try {
                    $table->foreign('direct_purchase_authorization_requested_by', 'pr_dp_req_by_fk')->references('id')->on('users')->onDelete('set null');
                } catch (\Exception $e) {
                    // Foreign key ya existe, ignorar
                }
            }
            if (Schema::hasColumn('purchase_requests', 'direct_purchase_authorized_by')) {
                try {
                    $table->foreign('direct_purchase_authorized_by', 'pr_dp_auth_by_fk')->references('id')->on('users')->onDelete('set null');
                } catch (\Exception $e) {
                    // Foreign key ya existe, ignorar
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropForeign('pr_dp_supplier_fk');
            $table->dropForeign('pr_dp_req_by_fk');
            $table->dropForeign('pr_dp_auth_by_fk');
            $table->dropColumn([
                'is_direct_purchase',
                'direct_purchase_justification',
                'direct_purchase_supplier_id',
                'direct_purchase_authorization_requested',
                'direct_purchase_authorization_requested_by',
                'direct_purchase_authorization_requested_at',
                'direct_purchase_authorized_by',
                'direct_purchase_authorized_at',
                'direct_purchase_authorization_rejected',
                'direct_purchase_authorization_rejection_reason',
            ]);
        });
    }
};
