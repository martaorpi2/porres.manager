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
        $this->assertSame('175714227356', $rows[0]['operation']);
        $this->assertSame('2026-08-26', $rows[0]['date']);
        $this->assertSame('Aprobado', $rows[0]['status']);
        $this->assertSame(22044000, $rows[0]['gross_cents']);
        $this->assertSame(1100308, $rows[0]['commission_cents']);
        $this->assertSame(20943692, $rows[0]['net_cents']);
        $this->assertSame('175494092162', $rows[1]['operation']);
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
