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
        $rows = [];
        $buckets = [];

        foreach ($this->sheet->read($path, true) as $sale) {
            $row = $this->classify($sale);
            $date = (string) ($row['date'] ?? '');
            $row['group_key'] = $row['outcome'] === 'ready' && $date !== '' ? $channel.':'.$date : '';
            if ($row['outcome'] === 'ready') {
                $buckets[$row['group_key']][] = $row;
            }
            $rows[] = $row;
        }

        $groups = [];
        foreach ($buckets as $key => $items) {
            $collectedOn = array_values(array_unique(array_map(
                fn (array $item) => (string) $item['collected_on'],
                $items,
            )));
            sort($collectedOn);
            $groups[] = [
                'key' => $key,
                'date' => $items[0]['date'],
                'collected_on' => $collectedOn,
                'gross_cents' => array_sum(array_column($items, 'gross_cents')),
                'commission_cents' => array_sum(array_column($items, 'commission_cents')),
                'interest_cents' => array_sum(array_column($items, 'interest_cents')),
                'net_cents' => array_sum(array_column($items, 'net_cents')),
                'document' => implode(', ', array_map(
                    fn (string $date) => $this->displayDate($date),
                    $collectedOn,
                )),
            ];
        }

        return ['rows' => $rows, 'groups' => $groups];
    }

    /**
     * @param  array{date: string, collected_on: string|null, gross_cents: int, commission_cents: int, interest_cents: int, net_cents: int}  $sale
     * @return array<string, mixed>
     */
    private function classify(array $sale): array
    {
        $row = [
            'date' => $sale['date'],
            'collected_on' => $sale['collected_on'],
            'gross_cents' => $sale['gross_cents'],
            'commission_cents' => $sale['commission_cents'],
            'interest_cents' => $sale['interest_cents'],
            'net_cents' => $sale['net_cents'],
            'outcome' => 'ready',
            'detail' => '',
        ];

        if ($sale['date'] === null) {
            $row['outcome'] = 'invalid';
            $row['detail'] = 'Falta la fecha de acreditación.';

            return $row;
        }

        if ($sale['collected_on'] === null) {
            $row['outcome'] = 'invalid';
            $row['detail'] = 'Falta la fecha de cobro.';

            return $row;
        }

        $difference = abs(($sale['net_cents'] + $sale['commission_cents'] + $sale['interest_cents']) - $sale['gross_cents']);
        if ($sale['gross_cents'] < 1 || $difference > 2) {
            $row['outcome'] = 'unbalanced';
            $row['detail'] = 'El cobro no cierra con cargos, intereses y total a recibir.';

            return $row;
        }

        $row['detail'] = 'Se incluye en el asiento del '.$this->displayDate($sale['date']).'. Cobro del '.$this->displayDate((string) $sale['collected_on']).'.';

        return $row;
    }

    private function displayDate(string $date): string
    {
        $parts = explode('-', $date);

        return count($parts) === 3 ? $parts[2].'/'.$parts[1].'/'.$parts[0] : $date;
    }
}
