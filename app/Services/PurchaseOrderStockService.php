<?php

namespace App\Services;

use App\Models\Input;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\SupplierInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseOrderStockService
{
    /**
     * Suma al stock las cantidades de la orden de compra, una sola vez.
     * La recepción y la factura pueden dispararlo; el segundo documento no vuelve a sumar.
     *
     * @return 'posted'|'already'|'empty'|'no_location'|'missing'
     */
    public function receiveOnce(int $purchaseOrderId, string $sourceLabel): string
    {
        try {
            return DB::transaction(function () use ($purchaseOrderId, $sourceLabel) {
                $order = PurchaseOrder::query()->whereKey($purchaseOrderId)->lockForUpdate()->first();
                if (! $order) {
                    return 'missing';
                }

                $reference = 'OC-STOCK-'.$order->id;
                if (InventoryMovement::query()->where('reference', $reference)->exists()) {
                    return 'already';
                }

                $invoiceIds = SupplierInvoice::query()
                    ->where('purchase_order_id', $order->id)
                    ->pluck('id');
                if ($invoiceIds->isNotEmpty()
                    && StockLevel::query()->whereIn('supplier_invoice_id', $invoiceIds)->exists()) {
                    return 'already';
                }

                $order->load(['details.input', 'purchaseRequest.responsibilityArea']);
                $location = $this->locationFor($order);
                if (! $location) {
                    return 'no_location';
                }

                $posted = false;
                $userId = backpack_user()?->id;

                foreach ($order->details as $detail) {
                    $quantity = (int) $detail->quantity;
                    if ($quantity <= 0 || ! $detail->input) {
                        continue;
                    }

                    $product = $this->productFromInput($detail->input);
                    if (! $product) {
                        continue;
                    }

                    $stockLevel = StockLevel::query()->firstOrCreate(
                        [
                            'product_id' => $product->id,
                            'location_id' => $location->id,
                        ],
                        [
                            'quantity' => 0,
                            'last_updated_by' => $userId,
                        ]
                    );

                    $stockLevel->quantity = (int) $stockLevel->quantity + $quantity;
                    $stockLevel->last_updated_by = $userId;
                    $stockLevel->save();

                    InventoryMovement::query()->create([
                        'product_id' => $product->id,
                        'location_id' => $location->id,
                        'quantity' => $quantity,
                        'type' => 'compra',
                        'reference' => $reference,
                        'user_id' => $userId,
                        'notes' => 'Ingreso de stock por '.$sourceLabel.' ('.($order->number ?? 'OC-'.$order->id).')',
                    ]);

                    $posted = true;
                }

                return $posted ? 'posted' : 'empty';
            });
        } catch (\Throwable $e) {
            Log::error('No se pudo ingresar el stock de la orden de compra', [
                'purchase_order_id' => $purchaseOrderId,
                'source' => $sourceLabel,
                'error' => $e->getMessage(),
            ]);

            return 'empty';
        }
    }

    private function locationFor(PurchaseOrder $order): ?Location
    {
        $areaName = $order->purchaseRequest?->responsibilityArea?->name;
        $map = [
            'Informática' => 'Informática',
            'Mantenimiento' => 'Mantenimiento',
            'Salud' => 'Insumos de Salud',
            'Insumos de Salud' => 'Insumos de Salud',
            'Insumos Generales' => 'Insumos Generales',
        ];
        $locationName = $areaName ? ($map[$areaName] ?? $areaName) : 'Insumos Generales';

        return Location::query()->where('name', $locationName)->first()
            ?? Location::query()->where('name', 'Insumos Generales')->first();
    }

    private function productFromInput(Input $input): ?Product
    {
        $product = Product::query()->where('name', $input->name)->first();
        if ($product) {
            return $product;
        }

        try {
            return Product::query()->create([
                'name' => $input->name,
                'description' => $input->description,
                'unit_measurement' => $input->unit ?? 'unidad',
                'minimum_stock' => 0,
                'category_id' => 1,
            ]);
        } catch (\Throwable $e) {
            Log::error('No se pudo crear el producto al ingresar stock', [
                'input_id' => $input->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
