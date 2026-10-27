<?php

use App\Services\AccountingEntryLinks;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_entry_links', function (Blueprint $table) {
            $table->boolean('automatic')->default(false)->after('related_entry_id');
        });

        app(AccountingEntryLinks::class)->markAutomaticLinks();
    }

    public function down(): void
    {
        Schema::table('accounting_entry_links', function (Blueprint $table) {
            $table->dropColumn('automatic');
        });
    }
};
