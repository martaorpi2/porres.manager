<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quota_accounting_collection_dates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quota_accounting_batch_id');
            $table->date('collected_on');
            $table->timestamps();

            $table->unique(['quota_accounting_batch_id', 'collected_on'], 'qacd_batch_date_uq');
            $table->foreign('quota_accounting_batch_id', 'qacd_batch_fk')
                ->references('id')
                ->on('quota_accounting_batches')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quota_accounting_collection_dates');
    }
};
