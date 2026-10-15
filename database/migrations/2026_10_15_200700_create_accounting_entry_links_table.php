<?php

use App\Services\AccountingEntryLinks;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_entry_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_entry_id')->constrained('accounting_entries')->cascadeOnDelete();
            $table->foreignId('related_entry_id')->constrained('accounting_entries')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['accounting_entry_id', 'related_entry_id'], 'accounting_entry_links_pair_uq');
        });

        app(AccountingEntryLinks::class)->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_entry_links');
    }
};
