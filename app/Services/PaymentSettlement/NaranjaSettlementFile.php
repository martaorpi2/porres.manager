<?php

namespace App\Services\PaymentSettlement;

use App\Services\Pdf\PdfText;
use RuntimeException;

/**
 * Liquidación de cupones de Tarjeta Naranja (Naranja X).
 */
final class NaranjaSettlementFile
{
    /** @var list<string> */
    private const NEGATIVE = ['DEV', 'CCO', 'DEBMOR'];

    public function __construct(private PdfText $pdf) {}

    /**
     * @return array{document: string, rows: list<array<string, mixed>>, groups: list<array<string, mixed>>}
     */
    public function read(string $path): array
    {
        $items = $this->pdf->items($path);
        $blob = implode("\n", array_column($items, 'text'));
        if (! str_contains($blob, 'Tarjeta Naranja')) {
            throw new RuntimeException('El PDF no es una liquidación de Naranja X.');
        }
        if (preg_match('/M-\d{3,5}-\d{5,}/', $blob, $documentMatch) !== 1) {
            throw new RuntimeException('El PDF de Naranja X no tiene número de liquidación.');
        }
        $document = $documentMatch[0];
        if (preg_match('/Pago\s+(\d{2}\/\d{2}\/\d{4})/', $blob, $paidMatch) !== 1) {
            throw new RuntimeException('El PDF de Naranja X no tiene fecha de pago.');
        }
        $paidAt = SettlementMoney::date($paidMatch[1]);
        if ($paidAt === null) {
            throw new RuntimeException('La fecha de pago de Naranja X es inválida.');
        }

        $iva = null;
        $rows = [];
        foreach ($this->pdf->lines($items) as $line) {
            $ivaOnLine = $this->iva($line);
            if ($ivaOnLine !== null) {
                $iva = $ivaOnLine;
            }
            $row = $this->coupon($line);
            if ($row !== null) {
                $rows[] = $row;
            }
        }
        if ($rows === []) {
            throw new RuntimeException('El PDF de Naranja X no tiene cupones liquidados.');
        }
        if ($iva === null) {
            throw new RuntimeException('El PDF de Naranja X no tiene el IVA de la liquidación.');
        }

        $gross = 0;
        $arancel = 0;
        $interest = 0;
        $bonus = 0;
        foreach ($rows as $row) {
            $gross += $row['gross_cents'];
            $arancel += $row['arancel_cents'];
            $interest += $row['interest_cents'];
            $bonus += $row['bonus_cents'];
        }
        $commission = $arancel + $iva - $bonus;
        $net = $gross - $commission - $interest;
        if ($commission < 0 || $interest < 0 || $net < 0) {
            throw new RuntimeException('La liquidación de Naranja X no cierra con el neto acreditado.');
        }

        $amounts = [];
        foreach ($items as $item) {
            if (SettlementMoney::isMoney($item['text'])) {
                $amounts[] = SettlementMoney::cents($item['text']);
            }
        }
        if (! in_array($net, $amounts, true) || ! in_array($gross, $amounts, true)) {
            throw new RuntimeException('El neto de Naranja X no coincide con el total del PDF.');
        }

        $key = 'naranja:'.$document;
        foreach ($rows as $index => $row) {
            $rows[$index]['group_key'] = $key;
            $rows[$index]['paid_at'] = $paidAt;
        }

        return [
            'document' => $document,
            'rows' => $rows,
            'groups' => [[
                'key' => $key,
                'date' => $paidAt,
                'gross_cents' => $gross,
                'commission_cents' => $commission,
                'interest_cents' => $interest,
                'net_cents' => $net,
                'document' => $document,
            ]],
        ];
    }

    /**
     * @param  list<array{x: float, y: float, text: string}>  $line
     */
    private function iva(array $line): ?int
    {
        $label = false;
        $amount = null;
        foreach ($line as $item) {
            if (SettlementMoney::fold($item['text']) === 'iva') {
                $label = true;
            }
            if (SettlementMoney::isMoney($item['text'])) {
                $amount = SettlementMoney::cents($item['text']);
            }
        }

        return $label ? $amount : null;
    }

    /**
     * @param  list<array{x: float, y: float, text: string}>  $line
     * @return array<string, mixed>|null
     */
    private function coupon(array $line): ?array
    {
        $date = null;
        $terminal = null;
        $operation = null;
        $moneys = [];
        $numbers = [];
        $planMark = '';
        foreach ($line as $item) {
            $text = $item['text'];
            $parsedDate = SettlementMoney::date($text);
            if ($parsedDate !== null && strlen($text) === 8) {
                $date = $parsedDate;
                continue;
            }
            if (preg_match('/^\d{6,}-\d+$/', $text) === 1) {
                $terminal = $text;
                continue;
            }
            if (preg_match('/^(VTA|DEV|CCO|REVCCO|DEBMOR|PROPIN|CORT)$/', $text) === 1) {
                $operation = $text;
                continue;
            }
            if (SettlementMoney::isMoney($text)) {
                $moneys[] = ['x' => $item['x'], 'cents' => SettlementMoney::cents($text)];
                continue;
            }
            if (preg_match('/^\d+$/', $text) === 1) {
                $numbers[] = ['x' => $item['x'], 'value' => $text];
                continue;
            }
            if ($text === 'z') {
                $planMark = ' z';
            }
        }
        if ($date === null || $terminal === null || $operation === null || count($moneys) < 4) {
            return null;
        }
        usort($moneys, fn (array $a, array $b) => $a['x'] <=> $b['x']);
        usort($numbers, fn (array $a, array $b) => $a['x'] <=> $b['x']);
        $sign = in_array($operation, self::NEGATIVE, true) ? -1 : 1;
        $coupon = $numbers[0]['value'] ?? '';
        $count = isset($numbers[1]) ? (int) $numbers[1]['value'] : 1;
        $plan = ($numbers[2]['value'] ?? '').$planMark;

        return [
            'purchase_date' => $date,
            'terminal' => $terminal,
            'coupon' => $coupon,
            'coupons' => $count,
            'plan' => trim($plan),
            'operation' => $operation,
            'gross_cents' => $sign * $moneys[0]['cents'],
            'arancel_cents' => $sign * $moneys[1]['cents'],
            'interest_cents' => $sign * $moneys[2]['cents'],
            'bonus_cents' => $sign * $moneys[3]['cents'],
        ];
    }
}
