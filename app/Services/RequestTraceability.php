<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\GeneralRequest;
use App\Models\PaymentOrder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Reception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class RequestTraceability
{
    /**
     * Pasos compactos de la misma cadena que el inicio (solicitud general → compra → OC → recepción → pago).
     *
     * @return array<int, array<string, mixed>>
     */
    public function stepsFor(Model $entry): array
    {
        $currentKind = $this->kindOf($entry);
        $currentId = (int) $entry->getKey();
        $anchor = $this->anchor($entry);

        if ($anchor instanceof GeneralRequest) {
            $general = $this->loadGeneral((int) $anchor->getKey());

            return $general ? $this->stepsFromGeneral($general, $currentKind, $currentId) : [];
        }

        if ($anchor instanceof PurchaseRequest) {
            $purchase = $this->loadPurchase((int) $anchor->getKey());

            if (! $purchase) {
                return [];
            }

            return $this->appendLooseDeliveries(
                $this->stepsFromPurchases(collect([$purchase]), $currentKind, $currentId),
                null,
                [(int) $purchase->id],
                $currentKind,
                $currentId
            );
        }

        return [];
    }

    private function kindOf(Model $entry): string
    {
        return match (true) {
            $entry instanceof GeneralRequest => 'general',
            $entry instanceof PurchaseRequest => 'purchase',
            $entry instanceof PurchaseOrder => 'order',
            $entry instanceof Reception => 'reception',
            $entry instanceof PaymentOrder => 'payment',
            $entry instanceof Delivery => 'delivery',
            default => '',
        };
    }

    private function anchor(Model $entry): GeneralRequest|PurchaseRequest|null
    {
        if ($entry instanceof GeneralRequest) {
            return $entry;
        }

        if ($entry instanceof PurchaseRequest) {
            if ($entry->converted_from_general_request_id) {
                return GeneralRequest::query()->find($entry->converted_from_general_request_id) ?? $entry;
            }

            return $entry;
        }

        if ($entry instanceof PurchaseOrder) {
            $entry->loadMissing('purchaseRequest');

            return $entry->purchaseRequest ? $this->anchor($entry->purchaseRequest) : null;
        }

        if ($entry instanceof Reception) {
            $entry->loadMissing('purchase_order.purchaseRequest');

            return $entry->purchase_order ? $this->anchor($entry->purchase_order) : null;
        }

        if ($entry instanceof PaymentOrder) {
            $entry->loadMissing('purchase_order.purchaseRequest');

            return $entry->purchase_order ? $this->anchor($entry->purchase_order) : null;
        }

        if ($entry instanceof Delivery) {
            $entry->loadMissing(['generalRequest', 'purchaseRequest', 'reception.purchase_order.purchaseRequest']);
            if ($entry->generalRequest) {
                return $entry->generalRequest;
            }
            if ($entry->purchaseRequest) {
                return $this->anchor($entry->purchaseRequest);
            }
            if ($entry->reception?->purchase_order) {
                return $this->anchor($entry->reception->purchase_order);
            }
        }

        return null;
    }

    private function loadGeneral(int $id): ?GeneralRequest
    {
        return GeneralRequest::query()
            ->with([
                'createdBy',
                'purchaseRequests.selectedMarketRate.supplier',
            ])
            ->find($id);
    }

    private function loadPurchase(int $id): ?PurchaseRequest
    {
        return PurchaseRequest::query()
            ->with(['selectedMarketRate.supplier'])
            ->find($id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stepsFromGeneral(GeneralRequest $general, string $currentKind, int $currentId): array
    {
        $status = (string) ($general->status ?? '');
        $badges = [self::generalStatusLabel($status)];
        if ($general->is_converted) {
            $badges[] = 'Convertida';
        }

        $steps = [[
            'label' => 'Solicitud general',
            'code' => $general->number ?? 'SG-'.$general->id,
            'url' => backpack_url('general-request/'.$general->id.'/show'),
            'meta' => trim(implode(' · ', array_filter([
                $general->title,
                $general->createdBy->name ?? null,
                $general->created_at ? $general->created_at->format('d/m/Y H:i') : null,
            ]))),
            'badges' => $badges,
            'current' => $currentKind === 'general' && (int) $general->id === $currentId,
            'depth' => 0,
        ]];

        $steps = array_merge($steps, $this->stepsFromPurchases($general->purchaseRequests, $currentKind, $currentId));

        return $this->appendLooseDeliveries(
            $steps,
            (int) $general->id,
            $general->purchaseRequests->pluck('id')->map(fn ($id) => (int) $id)->all(),
            $currentKind,
            $currentId
        );
    }

    /**
     * @param  Collection<int, PurchaseRequest>  $purchaseRequests
     * @return array<int, array<string, mixed>>
     */
    private function stepsFromPurchases(Collection $purchaseRequests, string $currentKind, int $currentId): array
    {
        $steps = [];

        foreach ($purchaseRequests as $purchaseRequest) {
            $meta = array_filter([
                $purchaseRequest->status,
                $purchaseRequest->request_date ? $purchaseRequest->request_date->format('d/m/Y') : ($purchaseRequest->created_at ? $purchaseRequest->created_at->format('d/m/Y') : null),
            ]);
            if ($purchaseRequest->selectedMarketRate && $purchaseRequest->selectedMarketRate->supplier) {
                $meta[] = $purchaseRequest->selectedMarketRate->supplier->name
                    ?? $purchaseRequest->selectedMarketRate->supplier->company_name
                    ?? null;
            }

            $steps[] = [
                'label' => 'Solicitud de compra',
                'code' => $purchaseRequest->request_number ?? 'SC-'.$purchaseRequest->id,
                'url' => backpack_url('purchase-request/'.$purchaseRequest->id.'/show'),
                'meta' => implode(' · ', array_filter($meta)),
                'badges' => [],
                'current' => $currentKind === 'purchase' && (int) $purchaseRequest->id === $currentId,
                'depth' => 0,
            ];

            foreach ($this->purchaseOrders((int) $purchaseRequest->id) as $purchaseOrder) {
                $steps = array_merge($steps, $this->stepsFromPurchaseOrder($purchaseOrder, $currentKind, $currentId));
            }
        }

        return $steps;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stepsFromPurchaseOrder(PurchaseOrder $purchaseOrder, string $currentKind, int $currentId): array
    {
        $meta = array_filter([
            $purchaseOrder->supplier_display_name,
            $purchaseOrder->status,
            $purchaseOrder->date ? $purchaseOrder->date->format('d/m/Y') : null,
            $purchaseOrder->total !== null ? '$'.number_format((float) $purchaseOrder->total, 2) : null,
        ]);

        $steps = [[
            'label' => 'Orden de compra',
            'code' => $purchaseOrder->number ?? 'OC-'.$purchaseOrder->id,
            'url' => backpack_url('purchase-order/'.$purchaseOrder->id.'/show'),
            'meta' => implode(' · ', $meta),
            'badges' => [],
            'current' => $currentKind === 'order' && (int) $purchaseOrder->id === $currentId,
            'depth' => 1,
        ]];

        foreach ($purchaseOrder->receptions as $reception) {
            $steps[] = [
                'label' => 'Recepción',
                'code' => $reception->number ?? 'REC-'.$reception->id,
                'url' => backpack_url('reception/'.$reception->id.'/show'),
                'meta' => implode(' · ', array_filter([
                    $reception->created_at ? $reception->created_at->format('d/m/Y H:i') : null,
                    $reception->user->name ?? null,
                    ($reception->according ?? '') === 'Si' ? 'Conforme' : null,
                ])),
                'badges' => [],
                'current' => $currentKind === 'reception' && (int) $reception->id === $currentId,
                'depth' => 1,
            ];

            foreach ($reception->devolutions as $devolution) {
                $steps[] = [
                    'label' => 'Devolución',
                    'code' => 'DEV-'.$devolution->id,
                    'url' => backpack_url('devolution/'.$devolution->id.'/show'),
                    'meta' => implode(' · ', array_filter([
                        $devolution->created_at ? $devolution->created_at->format('d/m/Y H:i') : null,
                        $devolution->user->name ?? null,
                    ])),
                    'badges' => [],
                    'current' => false,
                    'depth' => 2,
                ];
            }

            foreach ($reception->deliveries as $delivery) {
                $steps[] = [
                    'label' => 'Entrega',
                    'code' => $delivery->number ?? 'ENT-'.$delivery->id,
                    'url' => backpack_url('delivery/'.$delivery->id.'/show'),
                    'meta' => implode(' · ', array_filter([
                        $delivery->delivery_date ? $delivery->delivery_date->format('d/m/Y') : null,
                        $delivery->status ? ucfirst(str_replace('_', ' ', (string) $delivery->status)) : null,
                    ])),
                    'badges' => [],
                    'current' => $currentKind === 'delivery' && (int) $delivery->id === $currentId,
                    'depth' => 2,
                ];
            }
        }

        foreach ($purchaseOrder->paymentOrders as $paymentOrder) {
            $currency = strtoupper(trim((string) ($paymentOrder->currency_code ?? '')));
            $steps[] = [
                'label' => 'Orden de pago',
                'code' => $paymentOrder->payment_number ?? 'OP-'.$paymentOrder->id,
                'url' => backpack_url('payment-order/'.$paymentOrder->id.'/show'),
                'meta' => implode(' · ', array_filter([
                    $paymentOrder->date ? $paymentOrder->date->format('d/m/Y') : null,
                    '$'.number_format((float) ($paymentOrder->total_amount ?? 0), 2),
                    $currency !== '' ? $currency : 'ARS',
                    $paymentOrder->dashboard_payment_status_label,
                ])),
                'badges' => [],
                'current' => $currentKind === 'payment' && (int) $paymentOrder->id === $currentId,
                'depth' => 1,
            ];
        }

        return $steps;
    }

    /**
     * Entregas de la solicitud que no cuelgan de una recepción.
     *
     * @param  array<int, array<string, mixed>>  $steps
     * @param  array<int, int>  $purchaseIds
     * @return array<int, array<string, mixed>>
     */
    private function appendLooseDeliveries(array $steps, ?int $generalId, array $purchaseIds, string $currentKind, int $currentId): array
    {
        if (! $generalId && $purchaseIds === []) {
            return $steps;
        }

        $urls = array_column($steps, 'url');
        $deliveries = Delivery::query()
            ->where(function ($query) use ($generalId, $purchaseIds) {
                if ($generalId) {
                    $query->orWhere('general_request_id', $generalId);
                }
                if ($purchaseIds !== []) {
                    $query->orWhereIn('purchase_request_id', $purchaseIds);
                }
            })
            ->orderBy('id')
            ->get();

        foreach ($deliveries as $delivery) {
            $url = backpack_url('delivery/'.$delivery->id.'/show');
            if (in_array($url, $urls, true)) {
                continue;
            }

            $steps[] = [
                'label' => 'Entrega',
                'code' => $delivery->number ?? 'ENT-'.$delivery->id,
                'url' => $url,
                'meta' => implode(' · ', array_filter([
                    $delivery->delivery_date ? $delivery->delivery_date->format('d/m/Y') : null,
                    $delivery->status ? ucfirst(str_replace('_', ' ', (string) $delivery->status)) : null,
                ])),
                'badges' => [],
                'current' => $currentKind === 'delivery' && (int) $delivery->id === $currentId,
                'depth' => 1,
            ];
            $urls[] = $url;
        }

        return $steps;
    }

    /**
     * @return Collection<int, PurchaseOrder>
     */
    private function purchaseOrders(int $purchaseRequestId): Collection
    {
        return PurchaseOrder::query()
            ->where('purchase_request_id', $purchaseRequestId)
            ->with([
                'supplier',
                'details.supplier',
                'paymentOrders.user',
                'receptions' => function ($query) {
                    $query->with([
                        'user',
                        'devolutions.user',
                        'deliveries',
                    ])->orderBy('created_at');
                },
            ])
            ->orderBy('id')
            ->get();
    }

    private static function generalStatusLabel(string $status): string
    {
        return match ($status) {
            'creada' => 'Creada',
            'pendiente_analisis' => 'Pendiente análisis',
            'revisada_area' => 'Revisada por área',
            'archivada' => 'Archivada',
            'sin_entrega' => 'Sin entrega',
            'entregada_parcialmente' => 'Entregada parcialmente',
            'entregada_totalmente' => 'Entregada totalmente',
            'rechazada_analista' => 'Rechazada analista',
            default => $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : 'Sin estado',
        };
    }
}
