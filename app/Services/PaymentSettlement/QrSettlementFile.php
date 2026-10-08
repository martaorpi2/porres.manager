<?php

namespace App\Services\PaymentSettlement;

use RuntimeException;

/**
 * Liquidación diaria de QR que emite First Data (archivo de texto).
 */
final class QrSettlementFile
{
    /**
     * @return array{document: string, rows: list<array<string, mixed>>, groups: list<array<string, mixed>>}
     */
    public function read(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            throw new RuntimeException('No se pudo leer el archivo de QR.');
        }
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }
        $raw = str_replace("\r\n", "\n", $raw);
        $raw = str_replace("\r", "\n", $raw);

        if (preg_match('/Liq\.?:\s*(\d+)/', $raw, $documentMatch) !== 1) {
            throw new RuntimeException('El archivo no tiene el número de liquidación QR.');
        }
        $document = $documentMatch[1];

        $rows = [];
        foreach (explode("\n", $raw) as $line) {
            $parsed = $this->operation($line);
            if ($parsed === null) {
                continue;
            }
            $parsed['group_key'] = 'qr:'.$document.':'.$parsed['date'];
            $rows[] = $parsed;
        }
        if ($rows === []) {
            throw new RuntimeException('El archivo de QR no tiene cupones.');
        }

        $totals = null;
        if (preg_match('/TOTAL:\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)/', $raw, $totalMatch) === 1) {
            $totals = [
                SettlementMoney::cents($totalMatch[1]),
                SettlementMoney::cents($totalMatch[2]),
                SettlementMoney::cents($totalMatch[3]),
                SettlementMoney::cents($totalMatch[4]),
            ];
        }

        $sumGross = 0;
        $sumArancel = 0;
        $sumTax = 0;
        $sumNet = 0;
        $groups = [];
        foreach ($rows as $row) {
            $sumGross += $row['gross_cents'];
            $sumArancel += $row['arancel_cents'];
            $sumTax += $row['tax_cents'];
            $sumNet += $row['net_cents'];
            $key = $row['group_key'];
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'key' => $key,
                    'date' => $row['date'],
                    'gross_cents' => 0,
                    'commission_cents' => 0,
                    'interest_cents' => 0,
                    'net_cents' => 0,
                    'document' => $document,
                ];
            }
            $groups[$key]['gross_cents'] += $row['gross_cents'];
            $groups[$key]['commission_cents'] += $row['arancel_cents'] + $row['tax_cents'];
            $groups[$key]['net_cents'] += $row['net_cents'];
        }

        if ($totals !== null && ($totals[0] !== $sumGross || $totals[1] !== $sumArancel || $totals[2] !== $sumTax || $totals[3] !== $sumNet)) {
            throw new RuntimeException('Los cupones de QR no cierran con el total del archivo.');
        }

        foreach ($groups as $group) {
            if ($group['gross_cents'] - $group['commission_cents'] !== $group['net_cents']) {
                throw new RuntimeException('El neto de QR no cierra con el bruto menos la comisión.');
            }
        }

        return [
            'document' => $document,
            'rows' => $rows,
            'groups' => array_values($groups),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function operation(string $line): ?array
    {
        if (preg_match('/^(\d+)\s+([A-Z]{3})\s+(\d{2}\.\d{2}\.\d{4})\s+(.+?)\s+(\d{10})\s+(\d+)\s+(\d+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+(\d+)\s*(.*)$/', trim($line), $match) !== 1) {
            return null;
        }
        if (! str_contains($match[4], 'QR')) {
            return null;
        }
        $date = SettlementMoney::date($match[3]);
        if ($date === null) {
            return null;
        }
        $gross = SettlementMoney::cents($match[8]);
        $arancel = SettlementMoney::cents($match[9]);
        $tax = SettlementMoney::cents($match[10]);
        $net = SettlementMoney::cents($match[11]);
        if ($gross - $arancel - $tax !== $net) {
            throw new RuntimeException('Un cupón de QR no cierra: bruto, arancel, IVA y neto.');
        }

        return [
            'coupon' => ltrim($match[7], '0') ?: '0',
            'date' => $date,
            'wallet' => trim($match[13]) !== '' ? trim($match[13]) : '—',
            'operation' => $match[12],
            'gross_cents' => $gross,
            'arancel_cents' => $arancel,
            'tax_cents' => $tax,
            'net_cents' => $net,
            'commission_cents' => $arancel + $tax,
        ];
    }
}
