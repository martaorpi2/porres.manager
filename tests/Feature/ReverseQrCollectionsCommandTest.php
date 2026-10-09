<?php

namespace Tests\Feature;

use App\Models\AccountingEntry;
use Tests\TestCase;

class ReverseQrCollectionsCommandTest extends TestCase
{
    public function test_dry_run_does_not_change_posted_qr_collections(): void
    {
        $postedBefore = AccountingEntry::query()
            ->where('status', AccountingEntry::STATUS_POSTED)
            ->where('description', 'COBRANZA CUOTAS QR')
            ->count();

        $this->artisan('accounting:reverse-qr-collections', ['--dry-run' => true])
            ->assertSuccessful();

        $postedAfter = AccountingEntry::query()
            ->where('status', AccountingEntry::STATUS_POSTED)
            ->where('description', 'COBRANZA CUOTAS QR')
            ->count();

        $this->assertSame($postedBefore, $postedAfter);
    }
}
