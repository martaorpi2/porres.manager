<?php

namespace App\Services;

use App\Models\PaymentOrder;
use App\Models\SupplierInvoice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PaymentOrderInvoiceImputationService
{
    /**
     * Imputa (o incrementa) el monto de una orden de pago sobre una factura de proveedor.
     *
     * @throws \InvalidArgumentException reglas de negocio / validación
     */
    public function apply(PaymentOrder $paymentOrder, SupplierInvoice $invoice, float $amount, \DateTimeInterface|string $imputedAt): void
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('El monto a imputar debe ser mayor a cero.');
        }

        if ($paymentOrder->status === 'Anulada') {
            throw new \InvalidArgumentException('No se puede imputar una orden de pago anulada.');
        }

        $this->assertCanLink($paymentOrder, $invoice);

        $poCurrency = $this->normalizeCurrency($paymentOrder->currency_code);
        $invCurrency = $this->normalizeCurrency($invoice->currency_code);
        if ($poCurrency !== $invCurrency) {
            throw new \InvalidArgumentException(
                'La moneda de la orden de pago (' . $poCurrency . ') no coincide con la de la factura (' . $invCurrency . ').'
            );
        }

        $openInvoice = $invoice->openBalance();
        if ($amount - $openInvoice > 0.01) {
            throw new \InvalidArgumentException(
                'No se puede imputar más que el saldo pendiente de la factura ($' . number_format($openInvoice, 2) . ').'
            );
        }

        $capacity = $this->remainingImputableCapacityOnPaymentOrder($paymentOrder);
        if ($amount - $capacity > 0.01) {
            throw new \InvalidArgumentException(
                'El monto supera lo imputable desde esta orden de pago ($' . number_format($capacity, 2) . ' disponible).'
            );
        }

        $imputedDate = $imputedAt instanceof \DateTimeInterface
            ? \Carbon\Carbon::instance($imputedAt)->toDateString()
            : (string) $imputedAt;

        DB::transaction(function () use ($paymentOrder, $invoice, $amount, $imputedDate): void {
            $existing = $paymentOrder->supplierInvoices()
                ->where('supplier_invoices.id', $invoice->id)
                ->first();

            if ($existing) {
                $newApplied = round((float) $existing->pivot->amount_applied + $amount, 2);
                $paymentOrder->supplierInvoices()->updateExistingPivot($invoice->id, [
                    'amount_applied' => $newApplied,
                    'imputed_at' => $imputedDate,
                ]);
            } else {
                $paymentOrder->supplierInvoices()->attach($invoice->id, [
                    'amount_applied' => $amount,
                    'imputed_at' => $imputedDate,
                ]);
            }
        });
    }

    public function availableAnticipoBalance(PaymentOrder $paymentOrder): float
    {
        if ($paymentOrder->status === 'Anulada' || $paymentOrder->billing_kind !== 'anticipo') {
            return 0.0;
        }

        return $this->remainingImputableCapacityOnPaymentOrder($paymentOrder);
    }

    /**
     * Facturas imputables a esta OP: misma moneda y mismo proveedor.
     * Si ambos documentos tienen OC, deben coincidir; si no, no se exige compra.
     *
     * @return Collection<int, SupplierInvoice>
     */
    public function candidateInvoicesForPaymentOrder(PaymentOrder $paymentOrder): Collection
    {
        if ($paymentOrder->status === 'Anulada') {
            return collect();
        }
        if ($this->remainingImputableCapacityOnPaymentOrder($paymentOrder) < 0.01) {
            return collect();
        }

        $supplierId = $paymentOrder->resolvedSupplierId();
        $query = SupplierInvoice::query()->with(['supplier', 'purchaseOrder']);
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        return $query
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->get()
            ->filter(function (SupplierInvoice $invoice) use ($paymentOrder) {
                if (! $this->currenciesMatch($paymentOrder->currency_code, $invoice->currency_code)) {
                    return false;
                }
                if ($invoice->openBalance() < 0.01) {
                    return false;
                }

                try {
                    $this->assertCanLink($paymentOrder, $invoice);
                } catch (\InvalidArgumentException) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    /**
     * @return Collection<int, PaymentOrder>
     */
    public function candidatePaymentOrdersForInvoice(SupplierInvoice $invoice): Collection
    {
        if ($invoice->openBalance() < 0.01) {
            return collect();
        }

        return PaymentOrder::query()
            ->with(['purchase_order', 'supplier'])
            ->where('status', '!=', 'Anulada')
            ->orderByDesc('id')
            ->get()
            ->filter(function (PaymentOrder $po) use ($invoice) {
                if (! $this->currenciesMatch($po->currency_code, $invoice->currency_code)) {
                    return false;
                }
                if ($this->remainingImputableCapacityOnPaymentOrder($po) < 0.01) {
                    return false;
                }
                try {
                    $this->assertCanLink($po, $invoice);
                } catch (\InvalidArgumentException) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    /**
     * Capacidad no imputada a facturas de esta OP (anticipo disponible o saldo de OP normal aún no aplicado a comprobantes).
     */
    public function remainingImputableCapacityOnPaymentOrder(PaymentOrder $paymentOrder): float
    {
        if ($paymentOrder->status === 'Anulada') {
            return 0.0;
        }

        $imputed = (float) DB::table('payment_order_invoice')
            ->where('payment_order_id', $paymentOrder->id)
            ->sum('amount_applied');

        return round(max(0, (float) $paymentOrder->total_amount - $imputed), 2);
    }

    public function assertCanLink(PaymentOrder $paymentOrder, SupplierInvoice $invoice): void
    {
        $opOc = $paymentOrder->purchase_order_id ? (int) $paymentOrder->purchase_order_id : null;
        $invOc = $invoice->purchase_order_id ? (int) $invoice->purchase_order_id : null;
        if ($opOc && $invOc && $opOc !== $invOc) {
            throw new \InvalidArgumentException('La factura y la orden de pago pertenecen a distintas órdenes de compra.');
        }

        $opSupplier = $paymentOrder->resolvedSupplierId();
        if ($opSupplier && (int) $invoice->supplier_id !== (int) $opSupplier) {
            throw new \InvalidArgumentException('El proveedor de la factura no coincide con el de la orden de pago.');
        }

        if ($invOc) {
            $invoice->loadMissing('purchaseOrder');
            if ($invoice->purchaseOrder && ! $this->supplierMatchesPurchaseOrder($invoice)) {
                throw new \InvalidArgumentException('El proveedor de la factura no corresponde a la orden de compra.');
            }
        }
    }

    protected function supplierMatchesPurchaseOrder(SupplierInvoice $invoice): bool
    {
        $po = $invoice->purchaseOrder;
        if (! $po) {
            return true;
        }
        if ($po->supplier_id && (int) $po->supplier_id === (int) $invoice->supplier_id) {
            return true;
        }

        return $po->details()->where('supplier_id', $invoice->supplier_id)->exists();
    }

    public function currenciesMatch(?string $a, ?string $b): bool
    {
        return $this->normalizeCurrency($a) === $this->normalizeCurrency($b);
    }

    protected function normalizeCurrency(?string $code): string
    {
        $c = strtoupper(trim((string) $code));

        return $c === '' ? 'ARS' : $c;
    }
}
