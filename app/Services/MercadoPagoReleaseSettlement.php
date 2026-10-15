<?php

namespace App\Services;

use App\Models\Eporres\MercadoPagoWebhookEvent;
use App\Models\Eporres\Order;
use Carbon\Carbon;

/**
 * Arma la acreditación de Mercado Pago con las liberaciones, una por fecha.
 * El cupón que no cierra, o que no tiene fecha de cobro, no entra al asiento.
 */
class MercadoPagoReleaseSettlement
{
    /**
     * @return array{
     *   groups: list<array<string, mixed>>,
     *   exclusions: list<array<string, mixed>>,
     *   skipped: array{pending_collection: int, not_collected: int, amount_mismatch: int, adjusted: int, missing_paid_at: int}
     * }
     */
    public function forMonth(Carbon $month): array
    {
        $from = $month->copy()->startOfMonth()->toDateString();
        $to = $month->copy()->endOfMonth()->toDateString();

        return $this->classify($this->payments(), $from, $to, $this->today());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exclusions(): array
    {
        return $this->classify($this->payments(), '0001-01-01', '9999-12-31', $this->today())['exclusions'];
    }

    /**
     * @param  list<array{payment_id?: string, paid_on?: ?string, payment?: array<string, mixed>}>  $rows
     * @return array{
     *   groups: list<array<string, mixed>>,
     *   exclusions: list<array<string, mixed>>,
     *   skipped: array{pending_collection: int, not_collected: int, amount_mismatch: int, adjusted: int, missing_paid_at: int}
     * }
     */
    public function classify(array $rows, string $from, string $to, ?string $asOf = null): array
    {
        $asOf = $asOf ?: $this->today();
        $buckets = [];
        $exclusions = [];
        $mismatch = 0;
        $missingPaid = 0;

        foreach ($rows as $row) {
            $payment = $row['payment'] ?? null;
            if (! is_array($payment)) {
                continue;
            }
            $inspected = MercadoPagoRelease::inspect($payment, $asOf);
            $reasons = $inspected['reasons'];
            $paidOn = isset($row['paid_on']) && is_string($row['paid_on']) && $row['paid_on'] !== ''
                ? $row['paid_on']
                : null;
            if (($inspected['ready'] || $reasons !== []) && $paidOn === null) {
                $reasons[] = 'missing_collected';
            }
            $releaseOn = $inspected['release_date'];
            if ($reasons !== []) {
                $exclusions[] = [
                    'payment_id' => (string) ($row['payment_id'] ?? ''),
                    'release_on' => $releaseOn,
                    'reasons' => $reasons,
                    'gross_cents' => (int) round($inspected['gross'] * 100),
                    'commission_cents' => (int) round($inspected['commission'] * 100),
                    'net_cents' => (int) round($inspected['net'] * 100),
                ];
                $inMonth = $releaseOn !== null && $releaseOn >= $from && $releaseOn <= $to;
                if ($inMonth && in_array('unbalanced', $reasons, true)) {
                    $mismatch++;
                } elseif ($inMonth && in_array('missing_collected', $reasons, true)) {
                    $missingPaid++;
                }

                continue;
            }
            if (! $inspected['ready'] || $releaseOn === null || $paidOn === null) {
                continue;
            }
            if ($releaseOn < $from || $releaseOn > $to) {
                continue;
            }

            $grossCents = (int) round($inspected['gross'] * 100);
            $netCents = (int) round($inspected['net'] * 100);
            $commissionCents = $grossCents - $netCents;
            if (! isset($buckets[$releaseOn])) {
                $buckets[$releaseOn] = [
                    'gross' => 0,
                    'net' => 0,
                    'commission' => 0,
                    'orders' => 0,
                    'collected' => [],
                ];
            }
            $buckets[$releaseOn]['gross'] += $grossCents;
            $buckets[$releaseOn]['net'] += $netCents;
            $buckets[$releaseOn]['commission'] += $commissionCents;
            $buckets[$releaseOn]['orders']++;
            $buckets[$releaseOn]['collected'][$paidOn] = ($buckets[$releaseOn]['collected'][$paidOn] ?? 0) + $grossCents;
        }

        ksort($buckets);
        $groups = [];
        foreach ($buckets as $date => $bucket) {
            ksort($bucket['collected']);
            $groups[] = [
                'key' => 'mp-release:'.$date,
                'date' => $date,
                'gross_cents' => $bucket['gross'],
                'commission_cents' => $bucket['commission'],
                'interest_cents' => 0,
                'net_cents' => $bucket['net'],
                'document' => $date,
                'collected_on' => array_keys($bucket['collected']),
                'collected_amounts' => $bucket['collected'],
                'orders' => $bucket['orders'],
            ];
        }

        return [
            'groups' => $groups,
            'exclusions' => $exclusions,
            'skipped' => [
                'pending_collection' => 0,
                'not_collected' => 0,
                'amount_mismatch' => $mismatch,
                'adjusted' => 0,
                'missing_paid_at' => $missingPaid,
            ],
        ];
    }

    /**
     * @return list<array{payment_id: string, paid_on: ?string, payment: array<string, mixed>}>
     */
    private function payments(): array
    {
        $ids = MercadoPagoWebhookEvent::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('payment_id')
            ->pluck('id');
        if ($ids->isEmpty()) {
            return [];
        }

        $events = MercadoPagoWebhookEvent::query()
            ->whereIn('id', $ids)
            ->get(['id', 'payment_id', 'order_id', 'payload_snapshot']);
        $orderIds = $events->pluck('order_id')->filter()->unique()->values();
        $paid = $orderIds->isEmpty()
            ? collect()
            : Order::query()->whereIn('id', $orderIds)->pluck('paid_at', 'id');

        $rows = [];
        foreach ($events as $event) {
            $snapshot = $event->payload_snapshot;
            if (! is_array($snapshot)) {
                continue;
            }
            $nested = $snapshot['payment'] ?? null;
            $payment = is_array($nested) ? $nested : $snapshot;
            $paidAt = $event->order_id ? ($paid[$event->order_id] ?? null) : null;
            $rows[] = [
                'payment_id' => (string) $event->payment_id,
                'paid_on' => $this->paidOn($paidAt),
                'payment' => $payment,
            ];
        }

        return $rows;
    }

    private function paidOn(mixed $paidAt): ?string
    {
        if ($paidAt === null || $paidAt === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $paidAt)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function today(): string
    {
        return Carbon::now('America/Argentina/Buenos_Aires')->toDateString();
    }
}
