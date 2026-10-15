<?php

namespace App\Services\PaymentSettlement;

use App\Models\AccountingEntry;
use App\Models\QuotaAccountingBatch;
use App\Models\QuotaAccountingCollectionDate;

/**
 * Compara el debe de la cobranza de la fecha de cobro del archivo
 * con el haber de la acreditación que cierra esa fecha.
 * La fecha de acreditación puede ser otra: no se busca un asiento del mismo día.
 */
final class AccreditationCollectionAlert
{
    /**
     * @param  array<string, mixed>  $definition
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{menu: string, kind: string, collected_on: list<string>, accredited_on: list<string>, debe_cents: int|null, haber_cents: int}>
     */
    public function forRows(array $definition, array $rows): array
    {
        $paymentType = (string) ($definition['payment_type'] ?? '');
        $menu = (string) ($definition['menu'] ?? $paymentType);
        $byDate = [];

        foreach ($rows as $row) {
            if (! in_array((string) ($row['outcome'] ?? ''), ['ready', 'update'], true)) {
                continue;
            }
            $collected = (string) ($row['collected_on'] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $collected) !== 1) {
                continue;
            }
            if (! isset($byDate[$collected])) {
                $byDate[$collected] = ['haber' => 0, 'accredited' => []];
            }
            $byDate[$collected]['haber'] += (int) ($row['gross_cents'] ?? 0);
            $accredited = (string) ($row['date'] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $accredited) === 1) {
                $byDate[$collected]['accredited'][$accredited] = $accredited;
            }
        }

        ksort($byDate);
        $alerts = [];
        foreach ($byDate as $collected => $bucket) {
            $alert = $this->compare(
                $menu,
                [$collected],
                array_values($bucket['accredited']),
                $this->collectionDebitCents($paymentType, $collected),
                (int) $bucket['haber'],
            );
            if ($alert !== null) {
                $alerts[] = $alert;
            }
        }

        return $alerts;
    }

    /**
     * Casos que siguen abiertos: falta la cobranza o los importes todavía no coinciden.
     *
     * @return list<array{menu: string, kind: string, collected_on: list<string>, accredited_on: list<string>, debe_cents: int|null, haber_cents: int}>
     */
    public function pending(): array
    {
        $links = QuotaAccountingCollectionDate::query()
            ->whereHas('batch', function ($query) {
                $query->whereIn('kind', [
                    QuotaAccountingBatch::KIND_MP_SETTLEMENT,
                    QuotaAccountingBatch::KIND_NX_SETTLEMENT,
                    QuotaAccountingBatch::KIND_SOL_SETTLEMENT,
                    QuotaAccountingBatch::KIND_QR_SETTLEMENT,
                ])->whereHas('entry', fn ($entry) => $entry->where('status', AccountingEntry::STATUS_POSTED));
            })
            ->with(['batch.entry.lines'])
            ->get()
            ->groupBy('quota_accounting_batch_id');

        $perDate = [];
        $alerts = [];
        foreach ($links as $batchLinks) {
            $batch = $batchLinks->first()->batch;
            $entry = $batch?->entry;
            $definition = $batch === null ? null : PaymentSettlementChannels::forBatchKind((string) $batch->kind);
            if ($batch === null || $entry === null || $definition === null) {
                continue;
            }
            $paymentType = (string) ($batch->payment_type ?: $definition['payment_type']);
            $menu = (string) $definition['menu'];
            $accredited = $entry->date?->toDateString() ?? '';
            $known = $batchLinks->every(fn (QuotaAccountingCollectionDate $link) => $link->haber_cents !== null);
            if ($known) {
                foreach ($batchLinks as $link) {
                    $date = $link->collected_on?->toDateString();
                    if ($date === null) {
                        continue;
                    }
                    $this->addHaber($perDate, $paymentType, $menu, $date, (int) $link->haber_cents, $accredited);
                }

                continue;
            }

            $dates = $batchLinks
                ->map(fn (QuotaAccountingCollectionDate $link) => $link->collected_on?->toDateString())
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
            if ($dates === []) {
                continue;
            }
            if (count($dates) === 1) {
                $this->addHaber($perDate, $paymentType, $menu, $dates[0], $this->creditCents($entry), $accredited);

                continue;
            }
            $alert = $this->compare(
                $menu,
                $dates,
                $accredited === '' ? [] : [$accredited],
                $this->debitOfDates($paymentType, $dates),
                $this->creditCents($entry),
            );
            if ($alert !== null) {
                $alerts[] = $alert;
            }
        }

        ksort($perDate);
        foreach ($perDate as $bucket) {
            $alert = $this->compare(
                $bucket['menu'],
                [$bucket['collected']],
                array_values($bucket['accredited']),
                $this->collectionDebitCents($bucket['payment_type'], $bucket['collected']),
                $bucket['haber'],
            );
            if ($alert !== null) {
                $alerts[] = $alert;
            }
        }

        return $alerts;
    }

    public function sentence(array $case): string
    {
        $menu = (string) $case['menu'];
        $collected = $this->datesLabel($case['collected_on']);
        $accredited = $this->datesLabel($case['accredited_on']);
        $accreditedOf = $accredited !== '' ? ' del '.$accredited : '';
        if ($case['kind'] === 'missing') {
            return 'No se encontró el asiento de cobranza de '.$menu.' del '.$collected.', correspondiente a la acreditación'.$accreditedOf.'. No es posible verificar la coincidencia de los importes.';
        }

        $debe = '$'.$this->money((int) $case['debe_cents']);
        $haber = '$'.$this->money((int) $case['haber_cents']);
        $difference = '$'.$this->money(abs((int) $case['haber_cents'] - (int) $case['debe_cents']));
        if ($case['collected_on'] === $case['accredited_on'] && count($case['collected_on']) === 1) {
            return 'Los importes de cobranza y acreditación de '.$menu.' no coinciden para el '.$collected.'. Cobranza: '.$debe.'. Acreditación: '.$haber.'. Diferencia: '.$difference.'.';
        }

        return 'Diferencia de importes entre la cobranza y la acreditación de '.$menu.'. Cobranza del '.$collected.': '.$debe.'. Acreditación'.$accreditedOf.': '.$haber.'. Diferencia: '.$difference.'.';
    }

    /**
     * @param  list<array{menu: string, kind: string, collected_on: list<string>, accredited_on: list<string>, debe_cents: int|null, haber_cents: int}>  $cases
     * @return list<array{menu: string, count: int, amounts: list<array{when: string, debe: string, haber: string, difference: string}>, missing: list<string>}>
     */
    public function homeGroups(array $cases): array
    {
        $byMenu = [];
        foreach ($cases as $case) {
            $byMenu[(string) $case['menu']][] = $case;
        }

        $order = [];
        foreach (PaymentSettlementChannels::keys() as $channel) {
            $order[] = (string) PaymentSettlementChannels::get($channel)['menu'];
        }
        $menus = array_keys($byMenu);
        usort($menus, function (string $left, string $right) use ($order): int {
            $leftAt = array_search($left, $order, true);
            $rightAt = array_search($right, $order, true);

            return ($leftAt === false ? 99 : $leftAt) <=> ($rightAt === false ? 99 : $rightAt);
        });

        $groups = [];
        foreach ($menus as $menu) {
            $amounts = [];
            $missing = [];
            foreach ($byMenu[$menu] as $case) {
                if ($case['kind'] === 'missing') {
                    $missing[] = $this->sentence($case);

                    continue;
                }
                $diff = (int) $case['haber_cents'] - (int) $case['debe_cents'];
                $amounts[] = [
                    'when' => $this->whenLabel($case['collected_on'], $case['accredited_on']),
                    'debe' => '$'.$this->money((int) $case['debe_cents']),
                    'haber' => '$'.$this->money((int) $case['haber_cents']),
                    'difference' => ($diff > 0 ? '+' : '−').'$'.$this->money(abs($diff)),
                ];
            }
            $groups[] = [
                'menu' => $menu,
                'count' => count($amounts) + count($missing),
                'amounts' => $amounts,
                'missing' => $missing,
            ];
        }

        return $groups;
    }

    private function collectionDebitCents(string $paymentType, string $date): ?int
    {
        $batches = QuotaAccountingBatch::query()
            ->where('kind', QuotaAccountingBatch::KIND_COLLECTION)
            ->where('payment_type', $paymentType)
            ->whereDate('entry_date', $date)
            ->whereHas('entry', fn ($query) => $query->where('status', AccountingEntry::STATUS_POSTED))
            ->with('entry.lines')
            ->get();
        if ($batches->isEmpty()) {
            return null;
        }

        $cents = 0;
        foreach ($batches as $batch) {
            foreach ($batch->entry->lines as $line) {
                $cents += (int) round(((float) $line->debit) * 100);
            }
        }

        return $cents;
    }

    /**
     * @param  array<string, array{payment_type: string, menu: string, collected: string, haber: int, accredited: array<string, string>}>  $perDate
     */
    private function addHaber(array &$perDate, string $paymentType, string $menu, string $date, int $haber, string $accredited): void
    {
        $key = $paymentType.'|'.$date;
        if (! isset($perDate[$key])) {
            $perDate[$key] = [
                'payment_type' => $paymentType,
                'menu' => $menu,
                'collected' => $date,
                'haber' => 0,
                'accredited' => [],
            ];
        }
        $perDate[$key]['haber'] += $haber;
        if ($accredited !== '') {
            $perDate[$key]['accredited'][$accredited] = $accredited;
        }
    }

    /**
     * @param  list<string>  $dates
     */
    private function debitOfDates(string $paymentType, array $dates): ?int
    {
        $sum = 0;
        foreach ($dates as $date) {
            $debe = $this->collectionDebitCents($paymentType, $date);
            if ($debe === null) {
                return null;
            }
            $sum += $debe;
        }

        return $sum;
    }

    private function creditCents(AccountingEntry $entry): int
    {
        $cents = 0;
        foreach ($entry->lines as $line) {
            $cents += (int) round(((float) $line->credit) * 100);
        }

        return $cents;
    }

    /**
     * @param  list<string>  $collectedDates
     * @param  list<string>  $accreditedDates
     * @return array{menu: string, kind: string, collected_on: list<string>, accredited_on: list<string>, debe_cents: int|null, haber_cents: int}|null
     */
    private function compare(string $menu, array $collectedDates, array $accreditedDates, ?int $debe, int $haber): ?array
    {
        $collectedDates = array_values(array_unique($collectedDates));
        $accreditedDates = array_values(array_unique(array_filter($accreditedDates)));
        sort($collectedDates);
        sort($accreditedDates);
        if ($debe !== null && abs($debe - $haber) <= 2) {
            return null;
        }

        return [
            'menu' => $menu,
            'kind' => $debe === null ? 'missing' : 'amount',
            'collected_on' => $collectedDates,
            'accredited_on' => $accreditedDates,
            'debe_cents' => $debe,
            'haber_cents' => $haber,
        ];
    }

    /**
     * @param  list<string>  $collected
     * @param  list<string>  $accredited
     */
    private function whenLabel(array $collected, array $accredited): string
    {
        $collected = array_values(array_unique($collected));
        $accredited = array_values(array_unique(array_filter($accredited)));
        sort($collected);
        sort($accredited);
        if (count($collected) === 1 && $collected === $accredited) {
            return $this->dateLabel($collected[0]);
        }

        $year = count($accredited) === 1 ? substr($accredited[0], 0, 4) : null;
        $left = [];
        foreach ($collected as $date) {
            $left[] = $year !== null && str_starts_with($date, $year.'-')
                ? $this->dayMonth($date)
                : $this->dateLabel($date);
        }

        $right = $this->datesLabel($accredited);

        return $right === '' ? $this->joinLabels($left) : $this->joinLabels($left).' → '.$right;
    }

    private function dayMonth(string $date): string
    {
        $parts = explode('-', $date);

        return count($parts) === 3 ? $parts[2].'/'.$parts[1] : $date;
    }

    /**
     * @param  list<string>  $labels
     */
    private function joinLabels(array $labels): string
    {
        if (count($labels) < 2) {
            return $labels[0] ?? '';
        }
        $last = array_pop($labels);

        return implode(', ', $labels).' y '.$last;
    }

    /**
     * @param  list<string>  $dates
     */
    private function datesLabel(array $dates): string
    {
        $labels = [];
        foreach ($dates as $date) {
            $labels[$date] = $this->dateLabel($date);
        }
        ksort($labels);

        return implode(' y ', $labels);
    }

    private function dateLabel(string $date): string
    {
        $parts = explode('-', $date);

        return count($parts) === 3 ? $parts[2].'/'.$parts[1].'/'.$parts[0] : $date;
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.');
    }
}
