<?php

namespace Tests\Unit;

use App\Services\MercadoPagoSalesSheet;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MercadoPagoSalesSheetTest extends TestCase
{
    public function test_reads_approved_amounts_from_the_sales_report(): void
    {
        $path = $this->workbook([
            ['Venta'],
            ['Número de operación', 'Fecha de la compra', 'Estado', 'Descripción del estado', 'Cobro', 'Cargos e impuestos', 'Anulaciones y reembolsos', 'Resumen', 'Total a recibir'],
            ['175714227356', '26/08/2026', 'Aprobado', 'Podrás usar el dinero a partir del 5 de septiembre.', '$ 220.440,00', '-$ 11.003,08', '$ 0,00', '', '$ 209.436,92'],
            ['175494092162', '24/08/2026', 'Rechazado por el banco pagador', '', '$ 308.625,25', '$ 0,00', '$ 0,00', '', '$ 0,00'],
            ['', '', '', '', '6.608.370,17', '-314.446,42', '0', '0', '5.985.298,50'],
        ]);

        $rows = (new MercadoPagoSalesSheet)->read($path);

        $this->assertCount(2, $rows);
        $this->assertSame('2026-08-26', $rows[0]['date']);
        $this->assertSame(22044000, $rows[0]['gross_cents']);
        $this->assertSame(1100308, $rows[0]['commission_cents']);
        $this->assertSame(20943692, $rows[0]['net_cents']);
        $this->assertSame('2026-08-24', $rows[1]['date']);
        $this->assertSame(30862525, $rows[1]['gross_cents']);
        $this->assertSame(0, $rows[0]['interest_cents']);
    }

    public function test_reads_an_optional_interest_column(): void
    {
        $path = $this->workbook([
            ['Fecha de acreditación', 'Cobro', 'Cargos e impuestos', 'Intereses', 'Total a recibir'],
            ['14/09/2026', '1000', '15', '25', '960'],
        ]);

        $rows = (new MercadoPagoSalesSheet)->read($path);

        $this->assertSame(1500, $rows[0]['commission_cents']);
        $this->assertSame(2500, $rows[0]['interest_cents']);
        $this->assertSame(96000, $rows[0]['net_cents']);
    }

    public function test_reads_the_collection_date_separately_from_the_accreditation_date(): void
    {
        $path = $this->workbook([
            ['Fecha de cobro', 'Fecha de acreditación', 'Cobro', 'Cargos e impuestos', 'Total a recibir'],
            ['01/10/2026', '02/10/2026', '1000', '8', '992'],
        ]);

        $rows = (new MercadoPagoSalesSheet)->read($path, true);

        $this->assertSame('2026-10-01', $rows[0]['collected_on']);
        $this->assertSame('2026-10-02', $rows[0]['date']);
    }

    public function test_settlement_read_requires_the_collection_date_column(): void
    {
        $path = $this->workbook([
            ['Fecha de acreditación', 'Cobro', 'Cargos e impuestos', 'Total a recibir'],
            ['02/10/2026', '1000', '8', '992'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fecha de cobro');

        (new MercadoPagoSalesSheet)->read($path, true);
    }

    public function test_rejects_a_workbook_without_the_sales_columns(): void
    {
        $path = $this->workbook([
            ['Fecha', 'Importe'],
            ['01/08/2026', '100'],
        ]);

        $this->expectException(RuntimeException::class);

        (new MercadoPagoSalesSheet)->read($path);
    }

    /**
     * @param  list<list<string>>  $grid
     */
    private function workbook(array $grid): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($grid, null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'mp-sales-');
        $target = $path.'.xlsx';
        rename($path, $target);
        (new Xlsx($spreadsheet))->save($target);

        return $target;
    }
}
