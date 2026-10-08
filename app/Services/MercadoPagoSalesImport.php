<?php

namespace App\Services;

use App\Models\QuotaAccountingBatch;
use App\Models\QuotaAccountingOrder;
use Illuminate\Support\Facades\DB;

/**
 * Cruza las ventas de Mercado Pago con la orden cobrada en ePorres.
 */
final class MercadoPagoSalesImport
{
    private const PAID_STATE = 'App\\States\\Order\\Paid';

    public function __construct(private MercadoPagoSalesSheet $sheet) {}

    /**
     * @return array{rows: list<array<string, mixed>>, ready: list<array{date: string, eporres_order_id: int, gross_cents: int, commission_cents: int, net_cents: int}>}
     */
    public function preview(string $path): array
    {
        $sales = $this->sheet->read($path);
        $operations = array_column($sales, 'operation');
        $orders = $operations === []
            ? collect()
            : DB::connection('eporres')->table('orders')
                ->whereIn('payment_id', $operations)
                ->get([
                    'id',
                    'payment_id',
                    'payment_type',
                    'amount_paid',
                    'paid_at',
                    'state',
                    'quota_number',
                    'type',
                ])
                ->groupBy(fn ($order) => (string) $order->payment_id);

        $orderIds = $orders->flatten()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $settled = $orderIds === []
            ? collect()
            : QuotaAccountingOrder::query()
                ->where('role', QuotaAccountingBatch::KIND_MP_SETTLEMENT)
                ->whereIn('eporres_order_id', $orderIds)
                ->with('batch.entry')
                ->get()
                ->keyBy(fn (QuotaAccountingOrder $order) => (int) $order->eporres_order_id);

        $rows = [];
        $ready = [];
        foreach ($sales as $sale) {
            $matched = $orders->get($sale['operation'], collect());
            $classified = $this->classify($sale, $matched->all(), $settled);
            $rows[] = $classified['row'];
            foreach ($classified['ready'] as $item) {
                $ready[] = $item;
            }
        }

        return ['rows' => $rows, 'ready' => $ready];
    }

    /**
     * @param  list<object>  $orders
     * @param  \Illuminate\Support\Collection<int, QuotaAccountingOrder>  $settled
     * @param  array{operation: string, date: ?string, status: string, gross_cents: int, commission_cents: int, interest_cents?: int, net_cents: int}  $sale
     * @return array{row: array<string, mixed>, ready: list<array{date: string, eporres_order_id: int, gross_cents: int, commission_cents: int, net_cents: int}>}
     */
    private function classify(array $sale, array $orders, $settled): array
    {
        $base = [
            'operation' => $sale['operation'],
            'date' => $sale['date'],
            'status' => $sale['status'],
            'gross_cents' => $sale['gross_cents'],
            'commission_cents' => $sale['commission_cents'],
            'net_cents' => $sale['net_cents'],
            'order_label' => null,
            'entry_number' => null,
            'outcome' => 'unmatched',
            'detail' => 'No hay una orden de ePorres con este número de operación.',
        ];

        if ($this->normalize($sale['status']) !== 'aprobado') {
            $base['outcome'] = 'not_approved';
            $base['detail'] = 'Solo se registran las operaciones aprobadas.';

            return ['row' => $base, 'ready' => []];
        }

        if (($sale['interest_cents'] ?? 0) !== 0) {
            $base['outcome'] = 'unbalanced';
            $base['detail'] = 'Mercado Pago no usa intereses. Dejá esa columna en 0.';

            return ['row' => $base, 'ready' => []];
        }

        if ($sale['gross_cents'] < 1 || abs(($sale['net_cents'] + $sale['commission_cents']) - $sale['gross_cents']) > 2) {
            $base['outcome'] = 'unbalanced';
            $base['detail'] = 'El neto más la comisión no cierra con el cobro.';

            return ['row' => $base, 'ready' => []];
        }

        $paid = array_values(array_filter(
            $orders,
            fn ($order) => trim((string) $order->payment_type) === 'Mercado Pago'
                && (string) $order->state === self::PAID_STATE
        ));

        if ($paid === []) {
            if ($orders !== []) {
                $base['outcome'] = 'not_collected';
                $base['detail'] = 'La operación no corresponde a una cuota paga de Mercado Pago en ePorres.';
            }

            return ['row' => $base, 'ready' => []];
        }

        $paidCents = 0;
        foreach ($paid as $order) {
            $paidCents += (int) round(((float) $order->amount_paid) * 100);
        }
        if (abs($paidCents - $sale['gross_cents']) > 2) {
            $base['outcome'] = 'amount_mismatch';
            $base['order_label'] = $this->orderLabel($paid);
            $base['detail'] = 'El cobro del Excel no coincide con lo pagado en ePorres.';

            return ['row' => $base, 'ready' => []];
        }

        $entryNumbers = [];
        foreach ($paid as $order) {
            $existing = $settled->get((int) $order->id);
            $number = $existing?->batch?->entry?->entry_number;
            if ($number) {
                $entryNumbers[] = $number;
            }
        }
        if ($entryNumbers !== []) {
            $base['outcome'] = 'already_posted';
            $base['order_label'] = $this->orderLabel($paid);
            $base['entry_number'] = implode(', ', array_unique($entryNumbers));
            $base['detail'] = 'Esta orden ya tiene asiento de liquidación.';

            return ['row' => $base, 'ready' => []];
        }

        $shares = $this->shares($paid, $sale['gross_cents'], $sale['commission_cents'], $sale['net_cents']);
        $ready = [];
        $dates = [];
        foreach ($shares as $index => $share) {
            $date = $sale['date'] ?? $this->orderDate($paid[$index]);
            if ($date === null) {
                $base['outcome'] = 'invalid';
                $base['order_label'] = $this->orderLabel($paid);
                $base['detail'] = 'Falta la fecha de acreditación y la orden no tiene fecha de pago.';

                return ['row' => $base, 'ready' => []];
            }
            $dates[] = $date;
            $ready[] = [
                'date' => $date,
                'eporres_order_id' => $share['eporres_order_id'],
                'gross_cents' => $share['gross_cents'],
                'commission_cents' => $share['commission_cents'],
                'net_cents' => $share['net_cents'],
            ];
        }

        $uniqueDates = array_values(array_unique($dates));
        $base['date'] = count($uniqueDates) === 1 ? $uniqueDates[0] : $sale['date'];
        $base['outcome'] = 'ready';
        $base['order_label'] = $this->orderLabel($paid);
        $shownDates = implode(' y ', array_map(fn (string $date) => $this->displayDate($date), $uniqueDates));
        $base['detail'] = 'Se registra en el asiento del '.$shownDates.'.';

        return ['row' => $base, 'ready' => $ready];
    }

    /**
     * @param  list<object>  $orders
     * @return list<array{eporres_order_id: int, gross_cents: int, commission_cents: int, net_cents: int}>
     */
    private function shares(array $orders, int $gross, int $commission, int $net): array
    {
        $count = count($orders);
        $paid = [];
        $sum = 0;
        foreach ($orders as $order) {
            $cents = (int) round(((float) $order->amount_paid) * 100);
            $paid[] = $cents;
            $sum += $cents;
        }
        if ($sum < 1) {
            $sum = $gross;
        }

        $shares = [];
        $leftGross = $gross;
        $leftCommission = $commission;
        $leftNet = $net;
        foreach ($orders as $index => $order) {
            if ($index === $count - 1) {
                $orderGross = $leftGross;
                $orderCommission = $leftCommission;
                $orderNet = $leftNet;
            } else {
                $orderGross = $paid[$index];
                $orderCommission = (int) round($commission * $paid[$index] / $sum);
                $orderNet = $orderGross - $orderCommission;
                $leftGross -= $orderGross;
                $leftCommission -= $orderCommission;
                $leftNet -= $orderNet;
            }
            $shares[] = [
                'eporres_order_id' => (int) $order->id,
                'gross_cents' => $orderGross,
                'commission_cents' => $orderCommission,
                'net_cents' => $orderNet,
            ];
        }

        return $shares;
    }

    /**
     * @param  list<object>  $orders
     */
    private function orderLabel(array $orders): string
    {
        $labels = [];
        foreach ($orders as $order) {
            $quota = (int) $order->quota_number;
            $kind = str_contains((string) $order->type, 'ExtraOrder') ? 'extra' : 'cuota '.$quota;
            $labels[] = 'Orden '.$order->id.' · '.$kind;
        }

        return implode(', ', $labels);
    }

    private function orderDate(object $order): ?string
    {
        $paidAt = trim((string) ($order->paid_at ?? ''));
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $paidAt, $match) !== 1) {
            return null;
        }

        return $match[1];
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
