<?php

namespace App\Services;

use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use RuntimeException;

/**
 * Lee el Excel de acreditaciones.
 * Usa fecha de cobro, fecha de acreditación, cobro, cargos y total a recibir.
 */
final class MercadoPagoSalesSheet
{
    /**
     * @return list<array{date: string, collected_on: string|null, gross_cents: int, commission_cents: int, interest_cents: int, net_cents: int}>
     */
    public function read(string $path, bool $requireCollectedOn = false): array
    {
        try {
            $workbook = IOFactory::load($path);
        } catch (\Throwable $exception) {
            throw new RuntimeException('No se pudo leer el Excel de Mercado Pago.');
        }

        foreach ($workbook->getAllSheets() as $sheet) {
            $parsed = $this->readSheet($sheet, $requireCollectedOn);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        $columns = 'fecha de acreditación, cobro, cargos e impuestos y total a recibir';
        if ($requireCollectedOn) {
            $columns = 'fecha de cobro, '.$columns;
        }

        throw new RuntimeException('El Excel no tiene las columnas de acreditación: '.$columns.'.');
    }

    /**
     * @return list<array{date: string, collected_on: string|null, gross_cents: int, commission_cents: int, interest_cents: int, net_cents: int}>|null
     */
    private function readSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, bool $requireCollectedOn): ?array
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
            if (isset($found['date'], $found['gross'], $found['commission'], $found['net'])
                && (! $requireCollectedOn || isset($found['collected_on']))) {
                $headerRow = $row;
                $columns = $found;
                break;
            }
        }

        if ($headerRow === null) {
            return null;
        }

        $rows = [];
        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $cells = $sheet->rangeToArray('A'.$row.':'.$highestColumn.$row, null, true, false)[0];
            $date = $this->date($cells[$columns['date']] ?? null);
            $collectedOn = isset($columns['collected_on']) ? $this->date($cells[$columns['collected_on']] ?? null) : null;
            $gross = $this->cents($cells[$columns['gross']] ?? null);
            $commission = abs($this->cents($cells[$columns['commission']] ?? null));
            $interest = isset($columns['interest']) ? abs($this->cents($cells[$columns['interest']] ?? null)) : 0;
            $net = $this->cents($cells[$columns['net']] ?? null);
            if ($date === null || ($gross === 0 && $commission === 0 && $interest === 0 && $net === 0)) {
                continue;
            }
            $rows[] = [
                'date' => $date,
                'collected_on' => $collectedOn,
                'gross_cents' => $gross,
                'commission_cents' => $commission,
                'interest_cents' => $interest,
                'net_cents' => $net,
            ];
        }

        return $rows;
    }

    private function headerKey(mixed $value): ?string
    {
        $text = $this->normalize((string) $value);

        return match ($text) {
            'fecha de acreditacion' => 'date',
            'fecha de la compra' => 'date',
            'fecha de cobro' => 'collected_on',
            'cobro' => 'gross',
            'cargos e impuestos' => 'commission',
            'intereses' => 'interest',
            'interes' => 'interest',
            'total a recibir' => 'net',
            default => null,
        };
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
