<?php

namespace App\Services;

use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use RuntimeException;

/**
 * Lee el Excel de ventas de Mercado Pago.
 * Usa número de operación, fecha de compra, estado, cobro, cargos y total a recibir.
 */
final class MercadoPagoSalesSheet
{
    /**
     * @return list<array{operation: string, date: ?string, status: string, gross_cents: int, commission_cents: int, net_cents: int}>
     */
    public function read(string $path): array
    {
        try {
            $workbook = IOFactory::load($path);
        } catch (\Throwable $exception) {
            throw new RuntimeException('No se pudo leer el Excel de Mercado Pago.');
        }

        foreach ($workbook->getAllSheets() as $sheet) {
            $parsed = $this->readSheet($sheet);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        throw new RuntimeException('El Excel no tiene las columnas de ventas de Mercado Pago: número de operación, estado, cobro, cargos e impuestos y total a recibir.');
    }

    /**
     * @return list<array{operation: string, date: ?string, status: string, gross_cents: int, commission_cents: int, net_cents: int}>|null
     */
    private function readSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): ?array
    {
        $headerRow = null;
        $columns = [];
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $scanUntil = min($highestRow, 15);

        for ($row = 1; $row <= $scanUntil; $row++) {
            $found = [];
            foreach ($sheet->rangeToArray('A'.$row.':'.$highestColumn.$row, null, true, false)[0] as $index => $value) {
                $key = $this->headerKey($value);
                if ($key !== null) {
                    $found[$key] = $index;
                }
            }
            if (isset($found['operation'], $found['status'], $found['gross'], $found['commission'], $found['net'])) {
                $headerRow = $row;
                $columns = $found;
                break;
            }
        }

        if ($headerRow === null) {
            return null;
        }

        $rows = [];
        $seen = [];
        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $cells = $sheet->rangeToArray('A'.$row.':'.$highestColumn.$row, null, true, false)[0];
            $operation = $this->operation($cells[$columns['operation']] ?? null);
            if ($operation === null || isset($seen[$operation])) {
                continue;
            }
            $seen[$operation] = true;
            $rows[] = [
                'operation' => $operation,
                'date' => isset($columns['date']) ? $this->date($cells[$columns['date']] ?? null) : null,
                'status' => trim((string) ($cells[$columns['status']] ?? '')),
                'gross_cents' => $this->cents($cells[$columns['gross']] ?? null),
                'commission_cents' => abs($this->cents($cells[$columns['commission']] ?? null)),
                'net_cents' => $this->cents($cells[$columns['net']] ?? null),
            ];
        }

        return $rows;
    }

    private function headerKey(mixed $value): ?string
    {
        $text = $this->normalize((string) $value);

        return match ($text) {
            'numero de operacion' => 'operation',
            'fecha de la compra' => 'date',
            'estado' => 'status',
            'cobro' => 'gross',
            'cargos e impuestos' => 'commission',
            'total a recibir' => 'net',
            default => null,
        };
    }

    private function operation(mixed $value): ?string
    {
        if (is_int($value)) {
            return $value > 0 ? (string) $value : null;
        }
        if (is_float($value)) {
            $whole = sprintf('%.0f', $value);

            return ctype_digit($whole) ? $whole : null;
        }

        $digits = preg_replace('/\D+/', '', trim((string) $value)) ?? '';

        return $digits !== '' ? $digits : null;
    }

    private function date(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_numeric($value)) {
            try {
                return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        $text = trim((string) $value);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $text, $match) === 1) {
            return $match[1].'-'.$match[2].'-'.$match[3];
        }
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $text, $match) !== 1) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', (int) $match[3], (int) $match[2], (int) $match[1]);
    }

    private function cents(mixed $value): int
    {
        if (is_int($value) || is_float($value)) {
            return (int) round(((float) $value) * 100);
        }

        $text = trim((string) $value);
        if ($text === '') {
            return 0;
        }

        $text = str_replace(['$', ' '], '', $text);
        $negative = str_contains($text, '-');
        $text = str_replace('-', '', $text);
        if (str_contains($text, ',') && str_contains($text, '.')) {
            $text = str_replace('.', '', $text);
            $text = str_replace(',', '.', $text);
        } elseif (str_contains($text, ',')) {
            $text = str_replace(',', '.', $text);
        }
        if (! is_numeric($text)) {
            return 0;
        }

        $cents = (int) round(((float) $text) * 100);

        return $negative ? -$cents : $cents;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
        ]);

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }
}
