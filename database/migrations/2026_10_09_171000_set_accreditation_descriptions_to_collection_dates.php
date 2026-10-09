<?php

use App\Services\QuotaAccountingService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(QuotaAccountingService::class)->syncAccreditationDescriptions();
    }

    public function down(): void
    {
    }
};
