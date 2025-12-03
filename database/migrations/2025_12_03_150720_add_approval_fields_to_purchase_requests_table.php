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
            $table->boolean('requires_admin_approval')->default(false)->after('status')->comment('Indica si requiere aprobación del administrador del instituto');
            $table->text('approval_justification')->nullable()->after('approved_date')->comment('Justificación de la aprobación');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn(['requires_admin_approval', 'approval_justification']);
        });
    }
};
