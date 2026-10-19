<?php

namespace Tests\Feature;

use App\Services\PaymentSettlement\AccreditationCollectionAlert;
use Tests\TestCase;

class AccreditationReconciliationHomeTest extends TestCase
{
    public function test_alerts_before_august_2026_stay_in_a_closed_group(): void
    {
        $groups = (new AccreditationCollectionAlert)->homeGroups([
            $this->amount('2026-07-15', '2026-08-05', 10000, 9000),
            $this->amount('2026-06-02', '2026-06-03', 5000, 4000),
            $this->amount('2026-08-01', '2026-08-01', 8000, 7000),
        ]);

        $html = view('admin.dashboard.inc.accreditation_reconciliation', [
            'accreditationAmountAlerts' => $groups,
        ])->render();

        $open = strpos($html, 'id="accreditation-reconciliation-section"');
        $older = strpos($html, '<details class="reconciliation-earlier">');
        $this->assertNotFalse($open);
        $this->assertNotFalse($older);
        $this->assertLessThan($older, strpos($html, '01/08/2026', $open));
        $this->assertGreaterThan($older, strpos($html, '15/07 → 05/08/2026'));
        $this->assertGreaterThan($older, strpos($html, '02/06 → 03/06/2026'));
        $this->assertStringContainsString('Cobros anteriores al 01/08/2026 (2)', $html);
        $this->assertStringContainsString('color: white;">1</span>', $html);
        $this->assertStringNotContainsString('<details class="reconciliation-earlier" open', $html);
    }

    /**
     * @return array<string, mixed>
     */
    private function amount(string $collected, string $accredited, int $debe, int $haber): array
    {
        return [
            'menu' => 'QR',
            'kind' => 'amount',
            'collected_on' => [$collected],
            'accredited_on' => [$accredited],
            'debe_cents' => $debe,
            'haber_cents' => $haber,
            'payment_type' => 'QR',
            'collection_entry_ids' => [],
            'accreditation_entry_ids' => [],
        ];
    }
}
