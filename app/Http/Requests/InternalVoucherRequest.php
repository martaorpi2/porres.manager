<?php

namespace App\Http\Requests;

use App\Models\AccountingAccount;
use App\Models\InternalVoucher;
use App\Models\PaymentOrder;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class InternalVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = backpack_user();

        return $u instanceof User && $u->canManageInternalVouchers();
    }

    public function rules(): array
    {
        $rules = [
            'number' => ['nullable', 'string', 'max:32'],
            'date' => ['required', 'date'],
            'type' => ['required', 'in:'.implode(',', array_keys(InternalVoucher::typeLabels()))],
            'motive' => ['required', 'in:'.implode(',', array_keys(InternalVoucher::motiveLabels()))],
            'concept' => ['required', 'string', 'max:2000'],
            'beneficiary' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'accounting_account_id' => AccountingAccount::chartIsLoaded()
                ? ['required', 'exists:accounting_accounts,id']
                : ['nullable', 'exists:accounting_accounts,id'],
            'payment_method' => ['required', 'string', 'max:255'],
            'authorizing_user_id' => ['required', 'exists:users,id'],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'payment_order_id' => ['nullable', 'exists:payment_orders,id'],
            'status' => ['required', 'in:Pendiente,Emitido'],
            'observations' => ['nullable', 'string'],
        ];

        if ($this->hasFile('attachment')) {
            $rules['attachment'] = ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['purchase_order_id', 'payment_order_id', 'accounting_account_id'] as $key) {
            if ($this->input($key) === '' || $this->input($key) === '0') {
                $merge[$key] = null;
            }
        }

        $c = strtoupper(trim((string) $this->input('currency_code', '')));
        $merge['currency_code'] = $c === '' ? 'ARS' : $c;

        $paymentOrderId = $merge['payment_order_id'] ?? $this->input('payment_order_id');
        $purchaseOrderId = $merge['purchase_order_id'] ?? $this->input('purchase_order_id');
        if ($paymentOrderId && ! $purchaseOrderId) {
            $fromOp = PaymentOrder::query()->whereKey((int) $paymentOrderId)->value('purchase_order_id');
            if ($fromOp) {
                $merge['purchase_order_id'] = (int) $fromOp;
            }
        }

        if ($this->input('status') === InternalVoucher::STATUS_ANULADO) {
            $merge['status'] = InternalVoucher::STATUS_EMITIDO;
        }

        if (! $this->hasFile('attachment')) {
            $this->request->remove('attachment');
        }

        $this->merge($merge);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $paymentOrderId = $this->input('payment_order_id');
            $purchaseOrderId = $this->input('purchase_order_id');
            if (! $paymentOrderId) {
                return;
            }

            $paymentOrder = PaymentOrder::query()->find((int) $paymentOrderId);
            if (! $paymentOrder) {
                return;
            }

            if ($paymentOrder->status === 'Anulada') {
                $validator->errors()->add('payment_order_id', 'No se puede asociar un comprobante interno a una orden de pago anulada.');
            }

            if ($purchaseOrderId && (int) $paymentOrder->purchase_order_id !== (int) $purchaseOrderId) {
                $validator->errors()->add(
                    'payment_order_id',
                    'La orden de pago seleccionada no pertenece a la orden de compra elegida.'
                );
            }
        });
    }

    public function attributes(): array
    {
        return [
            'number' => 'número',
            'date' => 'fecha',
            'type' => 'tipo',
            'motive' => 'motivo',
            'concept' => 'concepto',
            'beneficiary' => 'beneficiario',
            'amount' => 'importe',
            'currency_code' => 'moneda',
            'accounting_account_id' => 'cuenta contable',
            'payment_method' => 'medio de pago',
            'authorizing_user_id' => 'autorizado por',
            'purchase_order_id' => 'orden de compra',
            'payment_order_id' => 'orden de pago',
            'status' => 'estado',
            'observations' => 'observaciones',
            'attachment' => 'adjunto',
        ];
    }
}
