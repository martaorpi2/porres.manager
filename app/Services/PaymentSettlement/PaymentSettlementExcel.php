<?php

namespace App\Services\PaymentSettlement;

use App\Services\MercadoPagoSalesSheet;

/**
 * Lee el Excel común de acreditaciones y arma un asiento por fecha.
 */
final class PaymentSettlementExcel
{
    public function __construct(private MercadoPagoSalesSheet $sheet) {}

    /**
     * @return array{rows: list<array<string, mixed>>, groups: list<array<string, mixed>>}
     */
    public function preview(string $channel, string $path): array
    {
        $definition = PaymentSettlementChannels::get($channel);
        $rows = [];
        $buckets = [];

        foreach ($this->sheet->read($path) as $sale) {
            $row = $this->classify($definition, $sale);
            $date = (string) ($row['date'] ?? '');
            $row['group_key'] = $row['outcome'] === 'ready' && $date !== '' ? $channel.':'.$date : '';
            if ($row['outcome'] === 'ready') {
                $buckets[$row['group_key']][] = $row;
            }
            $rows[] = $row;
        }

        $groups = [];
        foreach ($buckets as $key => $items) {
            $groups[] = [
                'key' => $key,
                'date' => $items[0]['date'],
                'gross_cents' => array_sum(array_column($items, 'gross_cents')),
                'commission_cents' => array_sum(array_column($items, 'commission_cents')),
                'interest_cents' => array_sum(array_column($items, 'interest_cents')),
                'net_cents' => array_sum(array_column($items, 'net_cents')),
                'document' => $this->displayDate((string) $items[0]['date']),
            ];
        }

        return ['rows' => $rows, 'groups' => $groups];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array{operation: string, date: ?string, status: string, gross_cents: int, commission_cents: int, interest_cents: int, net_cents: int}  $sale
     * @return array<string, mixed>
     */
    private function classify(array $definition, array $sale): array
    {
        $row = [
            'operation' => $sale['operation'],
            'date' => $sale['date'],
            'status' => $sale['status'],
            'gross_cents' => $sale['gross_cents'],
            'commission_cents' => $sale['commission_cents'],
            'interest_cents' => $sale['interest_cents'],
            'net_cents' => $sale['net_cents'],
            'outcome' => 'ready',
            'detail' => '',
        ];

        if ($this->normalize($sale['status']) !== 'aprobado') {
            $row['outcome'] = 'not_approved';
            $row['detail'] = 'Solo se registran las filas aprobadas.';

            return $row;
        }

        if ($sale['date'] === null) {
            $row['outcome'] = 'invalid';
            $row['detail'] = 'Falta la fecha de acreditación.';

            return $row;
        }

        if ($definition['interest'] === null && $sale['interest_cents'] !== 0) {
            $row['outcome'] = 'unbalanced';
            $row['detail'] = $definition['menu'].' no usa intereses. Dejá esa columna en 0.';

            return $row;
        }

        $difference = abs(($sale['net_cents'] + $sale['commission_cents'] + $sale['interest_cents']) - $sale['gross_cents']);
        if ($sale['gross_cents'] < 1 || $difference > 2) {
            $row['outcome'] = 'unbalanced';
            $row['detail'] = 'El cobro no cierra con cargos, intereses y total a recibir.';

            return $row;
        }

        $row['detail'] = 'Se incluye en el asiento del '.$this->displayDate($sale['date']).'.';

        return $row;
    }

    private function displayDate(string $date): string
    {
        $parts = explode('-', $date);

        return count($parts) === 3 ? $parts[2].'/'.$parts[1].'/'.$parts[0] : $date;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        ]);
    }
}
