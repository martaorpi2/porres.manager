<?php

namespace App\Services;

use App\Models\PaymentOrder;
use App\Models\SupplierInvoice;
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

        if ((int) $paymentOrder->purchase_order_id !== (int) $invoice->purchase_order_id) {
            throw new \InvalidArgumentException('La factura y la orden de pago deben pertenecer a la misma orden de compra.');
        }

        if (! $this->supplierMatchesPurchaseOrder($invoice)) {
            throw new \InvalidArgumentException('El proveedor de la factura no corresponde a la orden de compra.');
        }

        $poCurrency = $this->normalizeCurrency($paymentOrder->currency_code);
        $invCurrency = $this->normalizeCurrency($invoice->currency_code);
        if ($poCurrency !== $invCurrency) {
            throw new \InvalidArgumentException(
                'La moneda de la orden de pago (' . $poCurrency . ') no coincide con la de la factura (' . $invCurrency . ').'
            );
        }

        $invoice->loadMissing('purchaseOrder');
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

    protected function supplierMatchesPurchaseOrder(SupplierInvoice $invoice): bool
    {
        $po = $invoice->purchaseOrder;
        if (! $po) {
            return false;
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
