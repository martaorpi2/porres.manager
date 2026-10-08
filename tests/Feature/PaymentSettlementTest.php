<?php

namespace Tests\Feature;

use App\Models\AccountingEntry;
use App\Models\QuotaAccountingBatch;
use App\Models\User;
use App\Services\QuotaAccountingService;
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
        $qr->assertSee('una fila por día', false);
        $qr->assertSee('accept=".xlsx,.xls"', false);

        $this->actingAs($user, 'backpack')->get('/admin/accounting-settlement/otro')->assertNotFound();
    }

    public function test_qr_preview_lists_coupons_without_posting(): void
    {
        $user = $this->accountingUser();
        $headers = ['Número de operación', 'Fecha de acreditación', 'Estado', 'Cobro', 'Cargos e impuestos', 'Intereses', 'Total a recibir'];

        $preview = $this->actingAs($user, 'backpack')->post('/admin/accounting-settlement/qr/preview', [
            'archivo' => $this->excel('qr.xlsx', [
                $headers,
                ['1760', '02/10/2026', 'Aprobado', '1000', '8', '0', '992'],
                ['1761', '02/10/2026', 'Aprobado', '500', '4', '0', '496'],
            ]),
        ]);

        $preview->assertOk();
        $preview->assertSee('1760');
        $preview->assertSee('1761');
        $preview->assertSee('1.000,00');
        $preview->assertSee('Registrar asientos');
        $preview->assertSee('asiento listo');
        $this->assertFalse(
            QuotaAccountingBatch::query()->where('batch_key', 'qr:2026-10-02')->exists()
        );

        $naranjaPreview = $this->actingAs($user, 'backpack')->post('/admin/accounting-settlement/naranja/preview', [
            'archivo' => $this->excel('naranja.xlsx', [
                $headers,
                ['12266024', '14/09/2026', 'Aprobado', '1000', '15', '25', '960'],
                ['12266025', '15/09/2026', 'Aprobado', '200', '3', '5', '192'],
            ]),
        ]);
        $naranjaPreview->assertOk();
        $naranjaPreview->assertSee('12266024');
        $naranjaPreview->assertSee('25,00');
        $naranjaPreview->assertSee('960,00');
        $naranjaPreview->assertSee('asientos listos');

        $solPreview = $this->actingAs($user, 'backpack')->post('/admin/accounting-settlement/sol/preview', [
            'archivo' => $this->excel('sol.xlsx', [
                $headers,
                ['97885', '01/09/2026', 'Aprobado', '400', '20', '8', '372'],
                ['97886', '01/09/2026', 'Rechazado', '100', '0', '0', '100'],
            ]),
        ]);
        $solPreview->assertOk();
        $solPreview->assertSee('97885');
        $solPreview->assertSee('No aprobada');
        $solPreview->assertSee('asiento listo');
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
