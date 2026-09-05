<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\AccountingEntry;
use App\Models\FundMovement;
use App\Models\InternalVoucher;
use App\Models\PaymentOrder;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Asientos de egreso/ingreso: se generan al confirmar un movimiento de fondos (Egreso).
 * La OP ejecutada crea o actualiza ese egreso; el comprobante interno puede usarse como respaldo.
 */
class AccountingOutflowService
{
    public function chartIsLoaded(): bool
    {
        return AccountingAccount::query()->where('is_active', true)->exists();
    }

    public function suggestedImputationAccountId(?PurchaseOrder $purchaseOrder, ?int $supplierId = null): ?int
    {
        if ($purchaseOrder) {
            $fromInvoice = $purchaseOrder->supplierInvoices()
                ->whereNotNull('accounting_account_id')
                ->orderByDesc('id')
                ->value('accounting_account_id');
            if ($fromInvoice) {
                return (int) $fromInvoice;
            }
            $supplierId = $supplierId ?: ($purchaseOrder->supplier_id ? (int) $purchaseOrder->supplier_id : null);
        }

        if ($supplierId) {
            $fromSupplier = Supplier::query()
                ->whereKey($supplierId)
                ->value('accounting_account_id');
            if ($fromSupplier) {
                return (int) $fromSupplier;
            }
        }

        return null;
    }

    /**
     * Si la OP está Ejecutada, asegura un egreso vinculado y registra el asiento.
     * Si está Anulada, anula los egresos abiertos y revierte asientos.
     */
    public function syncForPaymentOrder(PaymentOrder $paymentOrder): void
    {
        $paymentOrder->refresh();
        $paymentOrder->loadMissing(['imputationAccount', 'fundsAccount', 'supplier', 'purchase_order.supplier']);

        DB::transaction(function () use ($paymentOrder): void {
            if ($paymentOrder->status === 'Anulada') {
                foreach ($paymentOrder->fundMovements()->where('status', '!=', FundMovement::STATUS_ANULADO)->get() as $movement) {
                    $this->annulFundMovement($movement, 'Anulación de la orden de pago '.$paymentOrder->payment_number);
                }
                $postedOnOp = $this->postedOutflowFor($paymentOrder);
                if ($postedOnOp) {
                    $this->reverseEntry($postedOnOp, $paymentOrder, 'Anulación de la orden de pago '.$paymentOrder->payment_number);
                }

                return;
            }

            if ($paymentOrder->status !== 'Ejecutada') {
                return;
            }

            $movement = $this->ensureFundMovementFromPaymentOrder($paymentOrder);
            if ($movement) {
                $this->syncForFundMovement($movement);
            }
        });
    }

    public function syncForFundMovement(FundMovement $movement): void
    {
        $movement->refresh();
        $movement->loadMissing(['imputations.account', 'fundsAccount']);

        DB::transaction(function () use ($movement): void {
            $posted = $this->postedOutflowFor($movement);

            if ($movement->status === FundMovement::STATUS_ANULADO || ! $this->canPostFundMovement($movement)) {
                if ($posted) {
                    $this->reverseEntry($posted, $movement, 'Anulación o datos incompletos del egreso '.$movement->number);
                }

                return;
            }

            $date = $movement->date?->toDateString() ?: now()->toDateString();
            if ($posted && $this->fundEntryMatches($posted, $movement, $date)) {
                return;
            }
            if ($posted) {
                $this->reverseEntry($posted, $movement, 'Reemplazo del asiento del egreso '.$movement->number);
            }
            $this->postFundMovement($movement, $date);
        });
    }

    public function annulFundMovement(FundMovement $movement, string $reason): void
    {
        if ($movement->isAnnulled()) {
            return;
        }
        $user = backpack_user();
        $movement->update([
            'status' => FundMovement::STATUS_ANULADO,
            'annulled_at' => now(),
            'annulment_reason' => $reason,
            'annulled_by_id' => $user instanceof User ? $user->id : $movement->annulled_by_id,
        ]);
        $this->syncForFundMovement($movement->fresh());
    }

    public function ensureFundMovementFromPaymentOrder(PaymentOrder $paymentOrder): ?FundMovement
    {
        $existing = $paymentOrder->fundMovements()
            ->where('status', '!=', FundMovement::STATUS_ANULADO)
            ->orderByDesc('id')
            ->first();

        $amount = round((float) $paymentOrder->total_amount, 2);
        $beneficiary = $paymentOrder->resolvedSupplierName();
        if ($beneficiary === '—') {
            $beneficiary = 'Orden de pago '.$paymentOrder->payment_number;
        }

        $payload = [
            'date' => $paymentOrder->payment_date?->toDateString() ?: $paymentOrder->date?->toDateString() ?: now()->toDateString(),
            'type' => FundMovement::TYPE_EGRESO,
            'beneficiary' => $beneficiary,
            'amount' => $amount,
            'currency_code' => strtoupper(trim((string) ($paymentOrder->currency_code ?? ''))) ?: 'ARS',
            'payment_method' => $paymentOrder->payment_method ?: $paymentOrder->bank,
            'funds_account_id' => $paymentOrder->funds_account_id,
            'payment_order_id' => $paymentOrder->id,
            'observations' => 'Generado desde OP '.$paymentOrder->payment_number,
        ];

        $status = ($paymentOrder->funds_account_id && $paymentOrder->imputation_account_id)
            ? FundMovement::STATUS_CONFIRMADO
            : FundMovement::STATUS_PENDIENTE;
        $payload['status'] = $status;

        if ($existing) {
            $existing->fill($payload);
            $existing->save();
            $movement = $existing;
        } else {
            $user = backpack_user();
            $payload['number'] = FundMovement::getNextNumber();
            $payload['created_by_id'] = $user instanceof User ? $user->id : null;
            $movement = FundMovement::create($payload);
        }

        if ($paymentOrder->imputation_account_id) {
            $line = $movement->imputations()->first();
            if ($line) {
                $line->update([
                    'accounting_account_id' => $paymentOrder->imputation_account_id,
                    'amount' => $amount,
                    'memo' => 'Imputación desde OP',
                ]);
            } else {
                $movement->imputations()->create([
                    'accounting_account_id' => $paymentOrder->imputation_account_id,
                    'amount' => $amount,
                    'memo' => 'Imputación desde OP',
                ]);
            }
        }

        return $movement->fresh(['imputations', 'fundsAccount']);
    }

    public function ensureFundMovementFromInternalVoucher(InternalVoucher $voucher): FundMovement
    {
        $existing = $voucher->fundMovements()
            ->where('status', '!=', FundMovement::STATUS_ANULADO)
            ->orderByDesc('id')
            ->first();
        if ($existing) {
            return $existing;
        }

        $user = backpack_user();
        $type = match ($voucher->type) {
            InternalVoucher::TYPE_INGRESO => FundMovement::TYPE_INGRESO,
            InternalVoucher::TYPE_TRANSFERENCIA => FundMovement::TYPE_TRANSFERENCIA,
            default => FundMovement::TYPE_EGRESO,
        };

        $movement = FundMovement::create([
            'number' => FundMovement::getNextNumber(),
            'date' => $voucher->date?->toDateString() ?: now()->toDateString(),
            'type' => $type,
            'status' => FundMovement::STATUS_PENDIENTE,
            'beneficiary' => $voucher->beneficiary,
            'amount' => $voucher->amount,
            'currency_code' => $voucher->currency_code ?: 'ARS',
            'payment_method' => $voucher->payment_method,
            'funds_account_id' => null,
            'internal_voucher_id' => $voucher->id,
            'payment_order_id' => $voucher->payment_order_id,
            'observations' => $voucher->concept,
            'created_by_id' => $user instanceof User ? $user->id : null,
        ]);

        if ($voucher->accounting_account_id) {
            $movement->imputations()->create([
                'accounting_account_id' => $voucher->accounting_account_id,
                'amount' => $voucher->amount,
                'memo' => $voucher->motive_label,
            ]);
        }

        return $movement->fresh(['imputations']);
    }

    public function previewLines(PaymentOrder $paymentOrder): array
    {
        $amount = round((float) $paymentOrder->total_amount, 2);
        $imputation = $paymentOrder->imputationAccount;
        $funds = $paymentOrder->fundsAccount;

        return [
            [
                'role' => 'imputación',
                'account' => $imputation?->identifying_label ?? '— (cuenta de imputación pendiente)',
                'debit' => $amount,
                'credit' => 0.0,
                'effect' => 'aumenta',
            ],
            [
                'role' => 'fondos',
                'account' => $funds?->identifying_label ?? '— (cuenta de fondos pendiente)',
                'debit' => 0.0,
                'credit' => $amount,
                'effect' => 'disminuye',
            ],
        ];
    }

    public function previewFundMovement(FundMovement $movement): array
    {
        $movement->loadMissing(['imputations.account', 'fundsAccount']);
        $lines = [];
        foreach ($movement->imputations as $imp) {
            $lines[] = [
                'role' => 'imputación',
                'account' => $imp->account?->identifying_label ?? '—',
                'debit' => $movement->type === FundMovement::TYPE_INGRESO ? 0.0 : (float) $imp->amount,
                'credit' => $movement->type === FundMovement::TYPE_INGRESO ? (float) $imp->amount : 0.0,
                'effect' => $movement->type === FundMovement::TYPE_INGRESO ? 'disminuye (haber)' : 'aumenta',
            ];
        }
        $total = (float) $movement->amount;
        $lines[] = [
            'role' => 'fondos',
            'account' => $movement->fundsAccount?->identifying_label ?? '— (Caja/Banco pendiente)',
            'debit' => $movement->type === FundMovement::TYPE_INGRESO ? $total : 0.0,
            'credit' => $movement->type === FundMovement::TYPE_INGRESO ? 0.0 : $total,
            'effect' => $movement->type === FundMovement::TYPE_INGRESO ? 'aumenta' : 'disminuye',
        ];

        return $lines;
    }

    protected function canPostFundMovement(FundMovement $movement): bool
    {
        if (! $this->chartIsLoaded()) {
            return false;
        }
        if ($movement->status !== FundMovement::STATUS_CONFIRMADO) {
            return false;
        }
        if (! $movement->funds_account_id) {
            return false;
        }
        if ($movement->imputations->isEmpty()) {
            return false;
        }

        return round((float) $movement->amount, 2) >= 0.01;
    }

    protected function postedOutflowFor(Model $source): ?AccountingEntry
    {
        return AccountingEntry::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->where('kind', AccountingEntry::KIND_OUTFLOW)
            ->where('status', AccountingEntry::STATUS_POSTED)
            ->with('lines')
            ->first();
    }

    protected function fundEntryMatches(AccountingEntry $entry, FundMovement $movement, string $date): bool
    {
        $entry->loadMissing('lines');
        if ($entry->date?->toDateString() !== $date) {
            return false;
        }
        $creditFunds = $entry->lines->first(fn ($l) => (int) $l->accounting_account_id === (int) $movement->funds_account_id);
        if (! $creditFunds) {
            return false;
        }
        $expectedCredit = $movement->type === FundMovement::TYPE_INGRESO
            ? (float) $creditFunds->debit
            : (float) $creditFunds->credit;

        return abs($expectedCredit - (float) $movement->amount) < 0.01
            && $entry->lines->count() === $movement->imputations->count() + 1;
    }

    protected function postFundMovement(FundMovement $movement, string $date): AccountingEntry
    {
        $user = backpack_user();
        $isIngreso = $movement->type === FundMovement::TYPE_INGRESO;

        $entry = AccountingEntry::create([
            'entry_number' => AccountingEntry::nextEntryNumber(),
            'date' => $date,
            'kind' => AccountingEntry::KIND_OUTFLOW,
            'status' => AccountingEntry::STATUS_POSTED,
            'source_type' => $movement->getMorphClass(),
            'source_id' => $movement->id,
            'description' => ucfirst($movement->type_label).' '.$movement->number.' — '.$movement->beneficiary,
            'created_by_id' => $user instanceof User ? $user->id : null,
        ]);

        foreach ($movement->imputations as $imp) {
            $entry->lines()->create([
                'accounting_account_id' => $imp->accounting_account_id,
                'debit' => $isIngreso ? 0 : (float) $imp->amount,
                'credit' => $isIngreso ? (float) $imp->amount : 0,
                'memo' => $imp->memo ?: 'Imputación',
            ]);
        }
        $entry->lines()->create([
            'accounting_account_id' => $movement->funds_account_id,
            'debit' => $isIngreso ? (float) $movement->amount : 0,
            'credit' => $isIngreso ? 0 : (float) $movement->amount,
            'memo' => $isIngreso ? 'Entrada de fondos' : 'Salida de fondos',
        ]);

        return $entry;
    }

    protected function reverseEntry(AccountingEntry $original, Model $source, string $description): void
    {
        if ($original->status !== AccountingEntry::STATUS_POSTED) {
            return;
        }

        $original->loadMissing('lines');
        $user = backpack_user();

        $reversal = AccountingEntry::create([
            'entry_number' => AccountingEntry::nextEntryNumber(),
            'date' => now()->toDateString(),
            'kind' => AccountingEntry::KIND_REVERSAL,
            'status' => AccountingEntry::STATUS_POSTED,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'description' => $description,
            'reversed_entry_id' => $original->id,
            'created_by_id' => $user instanceof User ? $user->id : null,
        ]);

        foreach ($original->lines as $line) {
            $reversal->lines()->create([
                'accounting_account_id' => $line->accounting_account_id,
                'debit' => $line->credit,
                'credit' => $line->debit,
                'memo' => 'Reverso: '.($line->memo ?? ''),
            ]);
        }

        $original->update(['status' => AccountingEntry::STATUS_REVERSED]);
    }
}
