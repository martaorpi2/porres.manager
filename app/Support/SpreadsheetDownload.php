<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SpreadsheetDownload
{
    /**
     * @param  list<string>  $headings
     * @param  list<list<mixed>>  $rows
     */
    public static function fromRows(string $filename, array $headings, array $rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray(array_merge([$headings], $rows));

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $assocRows
     * @param  list<string>  $columns
     */
    public static function fromAssoc(string $filename, array $columns, array $assocRows): StreamedResponse
    {
        $rows = [];
        foreach ($assocRows as $row) {
            $line = [];
            foreach ($columns as $column) {
                $line[] = $row[$column] ?? '';
            }
            $rows[] = $line;
        }

        return self::fromRows($filename, $columns, $rows);
    }
}
