<?php

namespace Tests\Feature;

use App\Models\AccountingEntry;
use App\Models\QuotaAccountingBatch;
use App\Models\User;
use App\Services\QuotaAccountingService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
        $chooser->assertSee('Registración de Cobranzas');
        $chooser->assertSee('Forma de pago');
        $chooser->assertSee('Mercado Pago');
        $chooser->assertSee('Naranja X');
        $chooser->assertSee('Sol Pago');
        $chooser->assertSee('QR');
        $chooser->assertSee('El nombre es estricto');
        $chooser->assertSee('Fecha de cobro');
        $chooser->assertSee('Fecha de acreditación');
        $chooser->assertDontSee('Fecha de la compra');
        $chooser->assertDontSee('tiene que quedar en 0');
        $chooser->assertSee('alert alert-info', false);
        $chooser->assertDontSee('name="archivo"', false);

        $naranja = $this->actingAs($user, 'backpack')->get('/admin/accounting-settlement/naranja');
        $naranja->assertOk();
        $naranja->assertSee('Registración de Cobranzas');
        $naranja->assertSee('Forma de pago');
        $naranja->assertSee('Ver acreditación');
        $naranja->assertDontSee('El asiento queda así');
        $naranja->assertSee('accounting-settlement/sol', false);
        $naranja->assertSee('accounting-settlement/mercadopago', false);

        $mp = $this->actingAs($user, 'backpack')->get('/admin/accounting-settlement/mercadopago');
        $mp->assertOk();
        $mp->assertSee('Ver acreditación');
        $mp->assertDontSee('El asiento queda así');
        $mp->assertDontSee('cupón pagado en ePorres', false);

        $sol = $this->actingAs($user, 'backpack')->get('/admin/accounting-settlement/sol');
        $sol->assertOk();
        $sol->assertSee('Ver acreditación');
        $sol->assertDontSee('El asiento queda así');
        $sol->assertDontSee('Tarjeta Sol a cobrar');

        $qr = $this->actingAs($user, 'backpack')->get('/admin/accounting-settlement/qr');
        $qr->assertOk();
        $qr->assertSee('Ver acreditación');
        $qr->assertDontSee('El asiento queda así');
        $qr->assertDontSee('Una fila por cupón', false);
        $qr->assertSee('accept=".xlsx,.xls"', false);

        $this->actingAs($user, 'backpack')->get('/admin/accounting-settlement/otro')->assertNotFound();
    }

    public function test_qr_preview_lists_coupons_without_posting(): void
    {
        $user = $this->accountingUser();
        $headers = ['Fecha de cobro', 'Fecha de acreditación', 'Cobro', 'Cargos e impuestos', 'Intereses', 'Total a recibir'];

        $preview = $this->actingAs($user, 'backpack')->post('/admin/accounting-settlement/qr/preview', [
            'archivo' => $this->excel('qr.xlsx', [
                $headers,
                ['01/10/2026', '02/10/2026', '1000', '8', '0', '992'],
                ['01/10/2026', '02/10/2026', '500', '4', '0', '496'],
            ]),
        ]);

        $preview->assertOk();
        $preview->assertSee('01/10/2026');
        $preview->assertSee('02/10/2026');
        $preview->assertSee('Cobro del 01/10/2026');
        $preview->assertSee('1.000,00');
        $preview->assertSee('Registrar asientos');
        $preview->assertSee('asiento listo');
        $this->assertFalse(
            QuotaAccountingBatch::query()->where('batch_key', 'qr:2026-10-02')->exists()
        );

        $naranjaPreview = $this->actingAs($user, 'backpack')->post('/admin/accounting-settlement/naranja/preview', [
            'archivo' => $this->excel('naranja.xlsx', [
                $headers,
                ['12/09/2026', '14/09/2026', '1000', '15', '25', '960'],
                ['13/09/2026', '15/09/2026', '200', '3', '5', '192'],
            ]),
        ]);
        $naranjaPreview->assertOk();
        $naranjaPreview->assertSee('14/09/2026');
        $naranjaPreview->assertSee('25,00');
        $naranjaPreview->assertSee('960,00');
        $naranjaPreview->assertSee('asientos listos');

        $solPreview = $this->actingAs($user, 'backpack')->post('/admin/accounting-settlement/sol/preview', [
            'archivo' => $this->excel('sol.xlsx', [
                $headers,
                ['30/08/2026', '01/09/2026', '400', '20', '8', '372'],
                ['30/08/2026', '01/09/2026', '100', '0', '0', '100'],
            ]),
        ]);
        $solPreview->assertOk();
        $solPreview->assertSee('01/09/2026');
        $solPreview->assertSee('400,00');
        $solPreview->assertDontSee('No aprobada');
        $solPreview->assertSee('asiento listo');

        $mpPreview = $this->actingAs($user, 'backpack')->post('/admin/accounting-settlement/mercadopago/preview', [
            'archivo' => $this->excel('mp.xlsx', [
                $headers,
                ['01/10/2026', '02/10/2026', '1000', '30', '0', '970'],
                ['01/10/2026', '02/10/2026', '500', '15', '0', '485'],
            ]),
        ]);
        $mpPreview->assertOk();
        $mpPreview->assertSee('02/10/2026');
        $mpPreview->assertSee('Se registra');
        $mpPreview->assertSee('asiento listo');
        $mpPreview->assertDontSee('ePorres', false);
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
            $this->assertSame('11202006', $lines[2]->account->code);
            $this->assertSame('1000.00', $lines[2]->credit);
            $this->assertSame('ACREDITACION COBRANZA QR TESTPROBE', $entries[0]->description);
            DB::rollBack();
        } finally {
            if ($entryId !== null && AccountingEntry::query()->whereKey($entryId)->exists()) {
                AccountingEntry::query()->whereKey($entryId)->delete();
            }
            QuotaAccountingBatch::query()->where('batch_key', 'qr:testprobe:2099-01-01')->delete();
        }

        $this->assertFalse(QuotaAccountingBatch::query()->where('batch_key', 'qr:testprobe:2099-01-01')->exists());
    }

    public function test_mercadopago_settlement_credits_the_receivable(): void
    {
        $entryId = null;
        DB::beginTransaction();
        try {
            $entries = app(QuotaAccountingService::class)->postImportedPaymentSettlements('mercadopago', [[
                'key' => 'mercadopago:2099-01-03',
                'date' => '2099-01-03',
                'gross_cents' => 100000,
                'commission_cents' => 3000,
                'interest_cents' => 2000,
                'net_cents' => 95000,
                'document' => '03/01/2099',
                'collected_on' => ['2099-01-01', '2099-01-02'],
            ]]);
            $this->assertCount(1, $entries);
            $entryId = $entries[0]->id;
            $lines = $entries[0]->lines()->with('account')->orderBy('id')->get();
            $this->assertSame(
                ['11104000', '52309000', '52312000', '11202005'],
                $lines->map(fn ($line) => $line->account->code)->all()
            );
            $this->assertSame('950.00', $lines[0]->debit);
            $this->assertSame('30.00', $lines[1]->debit);
            $this->assertSame('20.00', $lines[2]->debit);
            $this->assertSame('1000.00', $lines[3]->credit);
            $this->assertSame('ACREDITACION COBRANZA MERCADO PAGO 01/01/2099, 02/01/2099', $entries[0]->description);
            $batch = QuotaAccountingBatch::query()->where('batch_key', 'mercadopago:2099-01-03')->first();
            $this->assertNotNull($batch);
            $this->assertSame(0, $batch->orders()->count());
            $this->assertSame(
                ['2099-01-01', '2099-01-02'],
                $batch->collectionDates()->orderBy('collected_on')->pluck('collected_on')->map(fn ($date) => $date->toDateString())->all()
            );
            DB::rollBack();
        } finally {
            if ($entryId !== null && AccountingEntry::query()->whereKey($entryId)->exists()) {
                AccountingEntry::query()->whereKey($entryId)->delete();
            }
            QuotaAccountingBatch::query()->where('batch_key', 'mercadopago:2099-01-03')->delete();
        }
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

    public function test_mercadopago_file_replaces_the_webhook_settlement_of_the_same_day(): void
    {
        $webhookEntryId = null;
        $fileEntryId = null;
        DB::beginTransaction();
        try {
            $batch = QuotaAccountingBatch::query()->create([
                'kind' => QuotaAccountingBatch::KIND_MP_SETTLEMENT,
                'batch_key' => 'mp-settlement:2099-01-03:1:testdup',
                'entry_date' => '2099-01-03',
                'payment_type' => 'Mercado Pago',
            ]);
            $webhook = AccountingEntry::query()->create([
                'entry_number' => 'AS-2099-901',
                'date' => '2099-01-03',
                'kind' => AccountingEntry::KIND_QUOTA_MP_SETTLEMENT,
                'status' => AccountingEntry::STATUS_POSTED,
                'source_type' => $batch->getMorphClass(),
                'source_id' => $batch->id,
                'description' => 'ACREDITACION COBRANZA MERCADO PAGO',
            ]);
            $batch->update(['accounting_entry_id' => $webhook->id]);
            $webhookEntryId = $webhook->id;

            $entries = app(QuotaAccountingService::class)->postImportedPaymentSettlements('mercadopago', [[
                'key' => 'mercadopago:2099-01-03',
                'date' => '2099-01-03',
                'gross_cents' => 13718306,
                'commission_cents' => 684738,
                'interest_cents' => 0,
                'net_cents' => 13033568,
                'document' => '01/01/2099',
                'collected_on' => ['2099-01-01'],
            ]]);

            $this->assertCount(1, $entries);
            $fileEntryId = $entries[0]->id;
            $this->assertSame(AccountingEntry::STATUS_POSTED, $entries[0]->fresh()->status);
            $this->assertFalse(AccountingEntry::query()->whereKey($webhookEntryId)->exists());
            $this->assertFalse(QuotaAccountingBatch::query()->where('batch_key', 'mp-settlement:2099-01-03:1:testdup')->exists());
            $this->assertTrue(QuotaAccountingBatch::query()->where('batch_key', 'mercadopago:2099-01-03')->exists());
            DB::rollBack();
        } finally {
            if ($webhookEntryId !== null && AccountingEntry::query()->whereKey($webhookEntryId)->exists()) {
                AccountingEntry::query()->whereKey($webhookEntryId)->delete();
            }
            if ($fileEntryId !== null && AccountingEntry::query()->whereKey($fileEntryId)->exists()) {
                AccountingEntry::query()->whereKey($fileEntryId)->delete();
            }
            QuotaAccountingBatch::query()->whereIn('batch_key', [
                'mp-settlement:2099-01-03:1:testdup',
                'mercadopago:2099-01-03',
            ])->delete();
        }
    }

    public function test_refresh_removes_accreditations_that_did_not_come_from_a_file(): void
    {
        $automaticIds = [];
        $fileEntryId = null;
        DB::beginTransaction();
        try {
            foreach ([
                [QuotaAccountingBatch::KIND_QR_SETTLEMENT, AccountingEntry::KIND_QUOTA_QR_SETTLEMENT, 'qr-from-eporres:2099-04-02', 'AS-2099-902'],
                [QuotaAccountingBatch::KIND_NX_SETTLEMENT, AccountingEntry::KIND_QUOTA_NX_SETTLEMENT, 'naranja-from-eporres:2099-04-02', 'AS-2099-903'],
                [QuotaAccountingBatch::KIND_SOL_SETTLEMENT, AccountingEntry::KIND_QUOTA_SOL_SETTLEMENT, 'sol-from-eporres:2099-04-02', 'AS-2099-904'],
                [QuotaAccountingBatch::KIND_MP_SETTLEMENT, AccountingEntry::KIND_QUOTA_MP_SETTLEMENT, 'mp-settlement:2099-04-02:1:auto', 'AS-2099-905'],
            ] as [$batchKind, $entryKind, $key, $number]) {
                $automaticIds[] = $this->postedAccreditation($batchKind, $entryKind, $key, $number, '2099-04-02');
            }
            $fileEntryId = $this->postedAccreditation(
                QuotaAccountingBatch::KIND_QR_SETTLEMENT,
                AccountingEntry::KIND_QUOTA_QR_SETTLEMENT,
                'qr:2099-04-03',
                'AS-2099-906',
                '2099-04-03',
            );

            app(QuotaAccountingService::class)->postMonth(Carbon::parse('2099-04-01'));

            foreach ($automaticIds as $id) {
                $this->assertFalse(AccountingEntry::query()->whereKey($id)->exists());
            }
            $this->assertTrue(AccountingEntry::query()->whereKey($fileEntryId)->exists());
            $this->assertTrue(QuotaAccountingBatch::query()->where('batch_key', 'qr:2099-04-03')->exists());
            DB::rollBack();
        } finally {
            AccountingEntry::query()->whereIn('entry_number', [
                'AS-2099-902', 'AS-2099-903', 'AS-2099-904', 'AS-2099-905', 'AS-2099-906',
            ])->delete();
            QuotaAccountingBatch::query()->whereIn('batch_key', [
                'qr-from-eporres:2099-04-02',
                'naranja-from-eporres:2099-04-02',
                'sol-from-eporres:2099-04-02',
                'mp-settlement:2099-04-02:1:auto',
                'qr:2099-04-03',
            ])->delete();
        }
    }

    private function postedAccreditation(string $batchKind, string $entryKind, string $key, string $number, string $date): int
    {
        $batch = QuotaAccountingBatch::query()->create([
            'kind' => $batchKind,
            'batch_key' => $key,
            'entry_date' => $date,
            'payment_type' => 'Mercado Pago',
        ]);
        $entry = AccountingEntry::query()->create([
            'entry_number' => $number,
            'date' => $date,
            'kind' => $entryKind,
            'status' => AccountingEntry::STATUS_POSTED,
            'source_type' => $batch->getMorphClass(),
            'source_id' => $batch->id,
            'description' => 'ACREDITACION DE PRUEBA',
        ]);
        $batch->update(['accounting_entry_id' => $entry->id]);

        return (int) $entry->id;
    }

    /**
     * @param  list<list<string>>  $grid
     */
    private function excel(string $name, array $grid): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($grid, null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'acred-');
        $target = $path.'.xlsx';
        rename($path, $target);
        (new Xlsx($spreadsheet))->save($target);

        return new UploadedFile($target, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
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
