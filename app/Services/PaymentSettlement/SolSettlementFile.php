<?php

namespace App\Services\PaymentSettlement;

use App\Services\Pdf\PdfText;
use RuntimeException;

/**
 * Comprobante mensual de liquidación de Tarjeta Sol.
 */
final class SolSettlementFile
{
    public function __construct(private PdfText $pdf) {}

    /**
     * @return array{document: string, rows: list<array<string, mixed>>, groups: list<array<string, mixed>>}
     */
    public function read(string $path): array
    {
        $items = $this->pdf->items($path);
        $blob = implode("\n", array_column($items, 'text'));
        if (! str_contains(SettlementMoney::fold($blob), 'tarjetasol')) {
            throw new RuntimeException('El PDF no es una liquidación de Sol Pago.');
        }

        $document = null;
        $rows = [];
        $statedNet = null;
        $expectNet = false;
        foreach ($this->pdf->lines($items) as $line) {
            $document ??= $this->documentNumber($line);
            if ($statedNet === null && $this->isNetLabel($line) && ! $this->hasDailyLiquidation($line)) {
                $expectNet = true;
                $onLabel = $this->moneyOnLine($line);
                if ($onLabel !== null) {
                    $statedNet = $onLabel;
                    $expectNet = false;
                }
            } elseif ($expectNet) {
                if ($this->hasDailyLiquidation($line)) {
                    $expectNet = false;
                } else {
                    $onNext = $this->moneyOnLine($line);
                    if ($onNext !== null) {
                        $statedNet = $onNext;
                        $expectNet = false;
                    }
                }
            }
            $row = $this->liquidation($line);
            if ($row !== null) {
                $rows[] = $row;
            }
        }
        if ($document === null) {
            throw new RuntimeException('El PDF de Sol Pago no tiene número de liquidación.');
        }
        if ($rows === []) {
            throw new RuntimeException('El PDF de Sol Pago no tiene liquidaciones diarias.');
        }

        $seen = [];
        foreach ($rows as $row) {
            if (isset($seen[$row['liquidation']])) {
                continue;
            }
            $seen[$row['liquidation']] = $row;
        }
        $rows = array_values($seen);

        $groups = [];
        foreach ($rows as $index => $row) {
            $key = 'sol:'.$document.':'.$row['date'];
            $rows[$index]['group_key'] = $key;
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
            $groups[$key]['commission_cents'] += $row['commission_cents'];
            $groups[$key]['interest_cents'] += $row['interest_cents'];
            $groups[$key]['net_cents'] += $row['net_cents'];
        }

        if ($statedNet !== null) {
            $sumNet = array_sum(array_column($groups, 'net_cents'));
            $difference = $sumNet - $statedNet;
            if ($difference < 0) {
                throw new RuntimeException('El neto de Sol Pago no cierra con las liquidaciones diarias.');
            }
            if ($difference > 0) {
                $lastKey = array_key_last($groups);
                $groups[$lastKey]['net_cents'] -= $difference;
                $groups[$lastKey]['commission_cents'] += $difference;
            }
        }

        return [
            'document' => $document,
            'rows' => $rows,
            'groups' => array_values($groups),
        ];
    }

    /**
     * @param  list<array{x: float, y: float, text: string}>  $line
     */
    private function documentNumber(array $line): ?string
    {
        $talksAboutLiquidation = false;
        $number = null;
        foreach ($line as $item) {
            if (str_contains(SettlementMoney::fold($item['text']), 'liquidac')) {
                $talksAboutLiquidation = true;
            }
            if ($item['x'] > 400 && preg_match('/^\d{8}$/', $item['text']) === 1) {
                $number = $item['text'];
            }
        }

        return $talksAboutLiquidation ? $number : null;
    }

    /**
     * @param  list<array{x: float, y: float, text: string}>  $line
     */
    private function hasDailyLiquidation(array $line): bool
    {
        foreach ($line as $item) {
            if ($item['x'] < 60 && preg_match('/^\d{8}$/', $item['text']) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{x: float, y: float, text: string}>  $line
     */
    private function isNetLabel(array $line): bool
    {
        foreach ($line as $item) {
            if (SettlementMoney::fold($item['text']) === 'importeneto') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{x: float, y: float, text: string}>  $line
     */
    private function moneyOnLine(array $line): ?int
    {
        $amount = null;
        foreach ($line as $item) {
            if (SettlementMoney::isMoney($item['text'])) {
                $amount = SettlementMoney::cents($item['text']);
            }
        }

        return $amount;
    }

    /**
     * @param  list<array{x: float, y: float, text: string}>  $line
     * @return array<string, mixed>|null
     */
    private function liquidation(array $line): ?array
    {
        $liquidation = null;
        $paidAt = null;
        $presented = null;
        $amounts = [
            'gross' => 0,
            'arancel' => 0,
            'finance' => 0,
            'express' => 0,
            'promo' => 0,
            'other' => 0,
            'tax' => 0,
            'net' => 0,
        ];
        $seenAmount = false;
        foreach ($line as $item) {
            if ($item['x'] < 60 && preg_match('/^\d{8}$/', $item['text']) === 1) {
                $liquidation = $item['text'];
                continue;
            }
            $date = SettlementMoney::date($item['text']);
            if ($date !== null && strlen($item['text']) === 10) {
                if ($item['x'] < 110) {
                    $paidAt = $date;
                } else {
                    $presented = $date;
                }
                continue;
            }
            if (! SettlementMoney::isMoney($item['text'])) {
                continue;
            }
            $seenAmount = true;
            $bucket = $this->bucket($item['x']);
            if ($bucket !== null) {
                $amounts[$bucket] = SettlementMoney::cents($item['text']);
            }
        }
        if ($liquidation === null || $paidAt === null || ! $seenAmount) {
            return null;
        }

        $commission = $amounts['arancel'] + $amounts['express'] + $amounts['promo'] + $amounts['other'] + $amounts['tax'];
        $interest = $amounts['finance'];
        if ($amounts['net'] + $commission + $interest !== $amounts['gross']) {
            throw new RuntimeException('La liquidación '.$liquidation.' de Sol Pago no cierra.');
        }

        return [
            'liquidation' => $liquidation,
            'date' => $paidAt,
            'presented' => $presented,
            'gross_cents' => $amounts['gross'],
            'arancel_cents' => $amounts['arancel'],
            'interest_cents' => $interest,
            'tax_cents' => $amounts['tax'],
            'commission_cents' => $commission,
            'net_cents' => $amounts['net'],
        ];
    }

    private function bucket(float $x): ?string
    {
        return match (true) {
            $x < 220 => 'gross',
            $x < 260 => 'arancel',
            $x < 310 => 'finance',
            $x < 360 => 'express',
            $x < 420 => 'promo',
            $x < 460 => 'other',
            $x < 510 => 'tax',
            default => 'net',
        };
    }
}
