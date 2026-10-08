<?php

namespace Tests\Feature;

use App\Models\AccountingEntry;
use App\Models\QuotaAccountingBatch;
use App\Models\User;
use App\Services\QuotaAccountingService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentSettlementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_settlement_pages_explain_the_journal_entry(): void
    {
        $user = $this->accountingUser();

        $chooser = $this->actingAs($user, 'backpack')->get('/admin/accounting-settlement');
        $chooser->assertOk();
        $chooser->assertSee('Acreditaciones');
        $chooser->assertSee('Forma de pago');
        $chooser->assertSee('Mercado Pago');
        $chooser->assertSee('Naranja X');
        $chooser->assertSee('Sol Pago');
        $chooser->assertSee('QR');
        $chooser->assertDontSee('name="archivo"', false);

        $naranja = $this->actingAs($user, 'backpack')->get('/admin/accounting-settlement/naranja');
        $naranja->assertOk();
        $naranja->assertSee('Acreditaciones');
        $naranja->assertSee('Forma de pago');
        $naranja->assertSee('Tarjeta Naranja a cobrar');
        $naranja->assertSee('accounting-settlement/sol', false);
        $naranja->assertSee('accounting-mercadopago', false);

        $sol = $this->actingAs($user, 'backpack')->get('/admin/accounting-settlement/sol');
        $sol->assertOk();
        $sol->assertSee('Tarjeta Sol a cobrar');

        $qr = $this->actingAs($user, 'backpack')->get('/admin/accounting-settlement/qr');
        $qr->assertOk();
        $qr->assertSee('Deudores por cuotas');
        $qr->assertSee('Comisiones cobranzas QR');
        $qr->assertSee('accept=".txt"', false);

        $this->actingAs($user, 'backpack')->get('/admin/accounting-settlement/otro')->assertNotFound();
    }

    public function test_qr_preview_lists_coupons_without_posting(): void
    {
        $user = $this->accountingUser();
        $file = new UploadedFile(
            base_path('tests/fixtures/settlements/qr-38337626.txt'),
            '38337626.txt',
            'text/plain',
            null,
            true,
        );

        $preview = $this->actingAs($user, 'backpack')->post('/admin/accounting-settlement/qr/preview', [
            'archivo' => $file,
        ]);

        $preview->assertOk();
        $preview->assertSee('0038337626');
        $preview->assertSee('NARANJA X');
        $preview->assertSee('1.348.800,00');
        $preview->assertSee('Registrar asientos');
        $this->assertFalse(
            QuotaAccountingBatch::query()->where('batch_key', 'qr:0038337626:2026-10-02')->exists()
        );

        $naranja = new UploadedFile(
            base_path('tests/fixtures/settlements/naranja-12266024.pdf'),
            'naranja.pdf',
            'application/pdf',
            null,
            true,
        );
        $naranjaPreview = $this->actingAs($user, 'backpack')->post('/admin/accounting-settlement/naranja/preview', [
            'archivo' => $naranja,
        ]);
        $naranjaPreview->assertOk();
        $naranjaPreview->assertSee('M-0632-12266024');
        $naranjaPreview->assertSee('4.999.862,68');
        $naranjaPreview->assertSee('Registrar asientos');

        $sol = new UploadedFile(
            base_path('tests/fixtures/settlements/sol-liqmes.pdf'),
            'sol.pdf',
            'application/pdf',
            null,
            true,
        );
        $solPreview = $this->actingAs($user, 'backpack')->post('/admin/accounting-settlement/sol/preview', [
            'archivo' => $sol,
        ]);
        $solPreview->assertOk();
        $solPreview->assertSee('00097885');
        $solPreview->assertSee('3.602.543,93');
        $solPreview->assertSee('05074666');
    }

    public function test_qr_settlement_posts_the_fee_against_banco_bse(): void
    {
        $entryId = null;
        DB::beginTransaction();
        try {
            $entries = app(QuotaAccountingService::class)->postImportedPaymentSettlements('qr', [[
                'key' => 'qr:testprobe:2099-01-01',
                'date' => '2099-01-01',
                'gross_cents' => 100000,
                'commission_cents' => 800,
                'interest_cents' => 0,
                'net_cents' => 99200,
                'document' => 'TESTPROBE',
            ]]);
            $this->assertCount(1, $entries);
            $entryId = $entries[0]->id;
            $lines = $entries[0]->lines()->with('account')->orderBy('id')->get();
            $this->assertCount(3, $lines);
            $this->assertSame('11102002', $lines[0]->account->code);
            $this->assertSame('992.00', $lines[0]->debit);
            $this->assertSame('52320000', $lines[1]->account->code);
            $this->assertSame('8.00', $lines[1]->debit);
            $this->assertSame('11201000', $lines[2]->account->code);
            $this->assertSame('1000.00', $lines[2]->credit);
            $this->assertSame('LIQUIDACION COBRANZA QR TESTPROBE', $entries[0]->description);
            DB::rollBack();
        } finally {
            if ($entryId !== null && AccountingEntry::query()->whereKey($entryId)->exists()) {
                AccountingEntry::query()->whereKey($entryId)->delete();
            }
            QuotaAccountingBatch::query()->where('batch_key', 'qr:testprobe:2099-01-01')->delete();
        }

        $this->assertFalse(QuotaAccountingBatch::query()->where('batch_key', 'qr:testprobe:2099-01-01')->exists());
    }

    public function test_naranja_settlement_credits_the_receivable(): void
    {
        $entryId = null;
        DB::beginTransaction();
        try {
            $entries = app(QuotaAccountingService::class)->postImportedPaymentSettlements('naranja', [[
                'key' => 'naranja:TEST-PROBE',
                'date' => '2099-01-02',
                'gross_cents' => 100000,
                'commission_cents' => 1500,
                'interest_cents' => 2500,
                'net_cents' => 96000,
                'document' => 'TEST-PROBE',
            ]]);
            $this->assertCount(1, $entries);
            $entryId = $entries[0]->id;
            $lines = $entries[0]->lines()->with('account')->orderBy('id')->get();
            $this->assertSame(
                ['11102002', '52306000', '52312000', '11202003'],
                $lines->map(fn ($line) => $line->account->code)->all()
            );
            $this->assertSame('960.00', $lines[0]->debit);
            $this->assertSame('15.00', $lines[1]->debit);
            $this->assertSame('25.00', $lines[2]->debit);
            $this->assertSame('1000.00', $lines[3]->credit);
            DB::rollBack();
        } finally {
            if ($entryId !== null && AccountingEntry::query()->whereKey($entryId)->exists()) {
                AccountingEntry::query()->whereKey($entryId)->delete();
            }
            QuotaAccountingBatch::query()->where('batch_key', 'naranja:TEST-PROBE')->delete();
        }
    }

    private function accountingUser(): User
    {
        $user = User::query()
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', ['role_contabilidad', 'role_admin_sistema', 'role_admin_institucion']);
            })
            ->first();
        $this->assertNotNull($user);

        return $user;
    }
}
