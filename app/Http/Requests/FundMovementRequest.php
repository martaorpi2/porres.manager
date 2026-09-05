<?php

namespace App\Http\Requests;

use App\Models\AccountingAccount;
use App\Models\FundMovement;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class FundMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = backpack_user();

        return $u instanceof User && $u->canManageFundMovements();
    }

    public function rules(): array
    {
        $chartLoaded = AccountingAccount::chartIsLoaded();
        $status = $this->input('status');

        $rules = [
            'number' => ['nullable', 'string', 'max:32'],
            'date' => ['required', 'date'],
            'type' => ['required', 'in:'.implode(',', array_keys(FundMovement::typeLabels()))],
            'status' => ['required', 'in:Pendiente,Confirmado'],
            'beneficiary' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'funds_account_id' => $chartLoaded && $status === FundMovement::STATUS_CONFIRMADO
                ? ['required', 'exists:accounting_accounts,id']
                : ['nullable', 'exists:accounting_accounts,id'],
            'payment_order_id' => ['nullable', 'exists:payment_orders,id'],
            'internal_voucher_id' => ['nullable', 'exists:internal_vouchers,id'],
            'supplier_invoice_id' => ['nullable', 'exists:supplier_invoices,id'],
            'observations' => ['nullable', 'string'],
            'imputations' => ['nullable', 'array'],
            'imputations.*.accounting_account_id' => ['required', 'exists:accounting_accounts,id'],
            'imputations.*.amount' => ['required', 'numeric', 'min:0.01'],
            'imputations.*.memo' => ['nullable', 'string', 'max:255'],
        ];

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['funds_account_id', 'payment_order_id', 'internal_voucher_id', 'supplier_invoice_id'] as $key) {
            if ($this->input($key) === '' || $this->input($key) === '0') {
                $merge[$key] = null;
            }
        }
        $c = strtoupper(trim((string) $this->input('currency_code', '')));
        $merge['currency_code'] = $c === '' ? 'ARS' : $c;

        $raw = $this->input('imputations');
        if (! is_array($raw)) {
            $raw = [];
        }
        $normalized = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $account = $row['accounting_account_id'] ?? null;
            $amount = isset($row['amount']) ? (float) str_replace(',', '.', (string) $row['amount']) : 0;
            if (! $account || $amount <= 0) {
                continue;
            }
            $normalized[] = [
                'accounting_account_id' => (int) $account,
                'amount' => round($amount, 2),
                'memo' => trim((string) ($row['memo'] ?? '')) ?: null,
            ];
        }
        $merge['imputations'] = $normalized;

        $this->merge($merge);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $imputations = $this->input('imputations', []);
            $amount = (float) $this->input('amount');
            $sum = 0.0;
            foreach ($imputations as $row) {
                $sum += (float) ($row['amount'] ?? 0);
            }
            $chartLoaded = AccountingAccount::chartIsLoaded();
            $status = $this->input('status');

            if ($status === FundMovement::STATUS_CONFIRMADO) {
                if ($chartLoaded && $sum < 0.01) {
                    $validator->errors()->add('imputations', 'Al confirmar el egreso debe indicar al menos una imputación contable.');
                }
                if ($sum > 0 && abs($sum - $amount) > 0.01) {
                    $validator->errors()->add(
                        'amount',
                        'La suma de las imputaciones ($'.number_format($sum, 2).') debe coincidir con el importe ($'.number_format($amount, 2).').'
                    );
                }
            } elseif ($sum > 0 && abs($sum - $amount) > 0.01) {
                $validator->errors()->add(
                    'amount',
                    'Si carga imputaciones, su suma debe coincidir con el importe.'
                );
            }
        });
    }

    public function attributes(): array
    {
        return [
            'date' => 'fecha',
            'type' => 'tipo',
            'status' => 'estado',
            'beneficiary' => 'beneficiario',
            'amount' => 'importe',
            'funds_account_id' => 'cuenta de fondos',
            'payment_order_id' => 'orden de pago',
            'internal_voucher_id' => 'comprobante interno',
            'supplier_invoice_id' => 'factura',
            'imputations' => 'imputaciones',
            'imputations.*.accounting_account_id' => 'cuenta de imputación',
            'imputations.*.amount' => 'monto imputado',
        ];
    }
}
