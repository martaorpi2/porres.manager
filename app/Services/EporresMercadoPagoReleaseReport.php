<?php

namespace App\Services;

use App\Models\Eporres\MercadoPagoWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Liberación de fondos de Mercado Pago: último aviso por payment_id.
 */
class EporresMercadoPagoReleaseReport
{
    private const PER_PAGE = 50;

    /**
     * @return array{
     *   payments: LengthAwarePaginator,
     *   statusOptions: Collection,
     *   paymentId: string,
     *   orderId: string,
     *   fechaDesde: string,
     *   fechaHasta: string,
     *   releaseStatus: string,
     *   mpStatus: string,
     *   pendingCount: int,
     *   releasedCount: int,
     *   pendingAmount: float,
     *   releasedAmount: float
     * }
     */
    public function build(Request $request): array
    {
        $paymentId = trim((string) $request->get('payment_id', ''));
        $orderId = trim((string) $request->get('order_id', ''));
        $fechaDesde = trim((string) $request->get('fecha_desde', ''));
        $fechaHasta = trim((string) $request->get('fecha_hasta', ''));
        $releaseStatus = trim((string) $request->get('release_status', ''));
        $mpStatus = trim((string) $request->get('status', 'approved'));

        $rows = $this->latestRows($paymentId, $orderId);
        $statusOptions = $rows->pluck('mp_status')->filter()->unique()->sort()->values();
        $rows = $this->filterRows($rows, $mpStatus, $releaseStatus, $fechaDesde, $fechaHasta)
            ->sortByDesc(fn ($row) => $row->release_at ?: '')
            ->values();

        $pending = $rows->where('release_status', 'pending');
        $released = $rows->where('release_status', 'released');

        return [
            'payments' => $this->paginate($rows, $request),
            'statusOptions' => $statusOptions,
            'paymentId' => $paymentId,
            'orderId' => $orderId,
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta,
            'releaseStatus' => $releaseStatus,
            'mpStatus' => $mpStatus,
            'pendingCount' => $pending->count(),
            'releasedCount' => $released->count(),
            'pendingAmount' => (float) $pending->sum('amount'),
            'releasedAmount' => (float) $released->sum('amount'),
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    public function filterRows(
        Collection $rows,
        string $mpStatus,
        string $releaseStatus,
        string $fechaDesde,
        string $fechaHasta
    ): Collection {
        if ($mpStatus !== '') {
            $rows = $rows->filter(fn ($row) => $row->mp_status === $mpStatus);
        }
        if ($releaseStatus !== '') {
            $rows = $rows->filter(fn ($row) => $row->release_status === $releaseStatus);
        }
        if ($fechaDesde !== '') {
            $rows = $rows->filter(fn ($row) => $row->release_at && substr($row->release_at, 0, 10) >= $fechaDesde);
        }
        if ($fechaHasta !== '') {
            $rows = $rows->filter(fn ($row) => $row->release_at && substr($row->release_at, 0, 10) <= $fechaHasta);
        }

        return $rows->values();
    }

    public function rowFromEvent(MercadoPagoWebhookEvent $event): object
    {
        $releaseRaw = data_get($event->payload_snapshot, 'payment.money_release_date');
        $releaseAt = self::safeDateTime(
            $releaseRaw !== null && $releaseRaw !== '' ? (string) $releaseRaw : null
        );

        return (object) [
            'event' => $event,
            'release_at' => $releaseAt,
            'release_raw' => $releaseRaw,
            'release_status' => strtolower((string) data_get($event->payload_snapshot, 'payment.money_release_status', '')),
            'mp_status' => (string) ($event->status ?: data_get($event->payload_snapshot, 'payment.status', '')),
            'amount' => (float) $event->transaction_amount,
        ];
    }

    public static function safeDateTime(?string $dateValue): ?string
    {
        if ($dateValue === null || $dateValue === '') {
            return null;
        }

        $ts = strtotime($dateValue);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }

    /** @return Collection<int, object> */
    private function latestRows(string $paymentId, string $orderId): Collection
    {
        $base = MercadoPagoWebhookEvent::query();
        if ($paymentId !== '') {
            $base->where('payment_id', 'like', '%'.$paymentId.'%');
        }
        if ($orderId !== '') {
            $base->where('order_id', (int) $orderId);
        }

        $latestIds = (clone $base)
            ->selectRaw('MAX(id) as id')
            ->groupBy('payment_id')
            ->pluck('id');

        if ($latestIds->isEmpty()) {
            return collect();
        }

        return MercadoPagoWebhookEvent::query()
            ->with(['order.student'])
            ->whereIn('id', $latestIds)
            ->get()
            ->map(fn (MercadoPagoWebhookEvent $event) => $this->rowFromEvent($event));
    }

    /** @param  Collection<int, object>  $rows */
    private function paginate(Collection $rows, Request $request): LengthAwarePaginator
    {
        $page = max(1, (int) $request->get('page', 1));
        $payments = new LengthAwarePaginator(
            $rows->forPage($page, self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $page,
            [
                'path' => $request->url(),
                'pageName' => 'page',
            ]
        );
        $payments->appends($request->except('page'));

        return $payments;
    }
}
