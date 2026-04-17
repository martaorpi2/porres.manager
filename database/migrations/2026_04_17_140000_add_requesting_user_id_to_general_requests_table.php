<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('general_requests', function (Blueprint $table) {
            $table->foreignId('requesting_user_id')
                ->nullable()
                ->after('created_by')
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Usuario que solicita cuando quien registra es compras u otro operador');
        });
    }

    public function down(): void
    {
        Schema::table('general_requests', function (Blueprint $table) {
            $table->dropForeign(['requesting_user_id']);
            $table->dropColumn('requesting_user_id');
        });
    }
};
