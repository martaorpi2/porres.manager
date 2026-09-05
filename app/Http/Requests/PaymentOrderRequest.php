<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // only allow updates if the user is logged in
        return backpack_auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $paymentOrderId = $this->route('id') ?? $this->payment_order_id ?? null;

        return [
            'purchase_order_id' => [
                'nullable',
                'exists:purchase_orders,id',
            ],
            'supplier_id' => [
                'nullable',
                'exists:suppliers,id',
                function ($attribute, $value, $fail) {
                    if (! $this->input('purchase_order_id') && empty($value)) {
                        $fail('Indique el proveedor o una orden de compra.');
                    }
                },
            ],
            'billing_kind' => ['required', 'in:normal,anticipo'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'total_amount' => [
                'required',
                'numeric',
                'min:0.01',
                function ($attribute, $value, $fail) use ($paymentOrderId) {
                    if (($this->input('billing_kind') ?? 'normal') === 'anticipo') {
                        return;
                    }

                    // Obtener el purchase_order_id del input
                    $purchaseOrderId = $this->input('purchase_order_id');

                    if (! $purchaseOrderId) {
                        return;
                    }

                    $purchaseOrder = \App\Models\PurchaseOrder::with('details')->find($purchaseOrderId);

                    if (! $purchaseOrder) {
                        return;
                    }

                    // Calcular el total de la orden de compra
                    $purchaseOrderTotal = $purchaseOrder->total;

                    // Obtener todas las órdenes de pago relacionadas (excluyendo la actual si es una actualización)
                    $existingPaymentOrders = \App\Models\PaymentOrder::where('purchase_order_id', $purchaseOrderId);

                    if ($paymentOrderId) {
                        $existingPaymentOrders->where('id', '!=', $paymentOrderId);
                    }

                    // Calcular el total pagado sumando los detalles (op_details) de las órdenes de pago existentes
                    $existingPaymentOrderIds = $existingPaymentOrders->pluck('id')->toArray();
                    $totalPaidFromDetails = 0;
                    if (! empty($existingPaymentOrderIds)) {
                        $totalPaidFromDetails = \Illuminate\Support\Facades\DB::table('op_details')
                            ->whereIn('payment_order_id', $existingPaymentOrderIds)
                            ->sum('amount');
                    }

                    // Suma de líneas de pago (ya normalizadas en prepareForValidation)
                    $paymentDetails = $this->input('payment_details', []);
                    $detailsTotal = 0;
                    if (! empty($paymentDetails) && is_array($paymentDetails)) {
                        foreach ($paymentDetails as $detail) {
                            if (isset($detail['amount'])) {
                                $detailsTotal += (float) $detail['amount'];
                            }
                        }
                    }

                    // Monto que aporta esta orden de pago: líneas si hay; si no, el monto total del formulario
                    $amountToUse = $detailsTotal > 0 ? $detailsTotal : (float) $value;

                    // totalPaidFromDetails solo incluye otras OP de la misma OC (la actual ya está excluida del query)
                    $newTotalPaid = $totalPaidFromDetails + $amountToUse;

                    if ($newTotalPaid > $purchaseOrderTotal + 0.01) {
                        $remaining = max(0, $purchaseOrderTotal - $totalPaidFromDetails);
                        $fail("El monto total de las órdenes de pago ($" . number_format($newTotalPaid, 2) . ") no puede superar el total de la orden de compra ($" . number_format($purchaseOrderTotal, 2) . "). Monto máximo permitido para esta orden: $" . number_format($remaining, 2));
                    }

                    if ($detailsTotal > 0 && abs($detailsTotal - (float) $value) > 0.01) {
                        $fail("La suma de los montos de los detalles ($" . number_format($detailsTotal, 2) . ") debe coincidir con el monto total de la orden de pago ($" . number_format((float) $value, 2) . ").");
                    }
                },
            ],
            'date' => 'required|date',
            'payment_date' => 'nullable|date',
            'payment_method' => 'nullable|string|max:255',
            'bank' => 'nullable|string|max:255',
            'observations' => 'nullable|string',
            'payment_number' => 'required|string|max:255',
            'status' => 'required|in:Pendiente,Aprobada,Ejecutada,Anulada',
            'authorizing_user_id' => 'required|exists:users,id',
            'payment_details' => 'nullable|array',
            'imputation_account_id' => [
                'nullable',
                'exists:accounting_accounts,id',
                function ($attribute, $value, $fail) {
                    if (app(\App\Services\AccountingOutflowService::class)->chartIsLoaded() && empty($value)) {
                        $fail('Con el plan de cuentas cargado debe indicar la cuenta de imputación del egreso (gasto o bien).');
                    }
                },
            ],
            'funds_account_id' => [
                'nullable',
                'exists:accounting_accounts,id',
                function ($attribute, $value, $fail) {
                    $chartLoaded = app(\App\Services\AccountingOutflowService::class)->chartIsLoaded();
                    if ($chartLoaded && ($this->input('status') === 'Ejecutada') && empty($value)) {
                        $fail('Para ejecutar el egreso debe indicar la cuenta de fondos (Caja o Banco).');
                    }
                },
            ],
            'payment_details.*.concept' => 'required|in:advance,residue,partiality',
            'payment_details.*.amount' => 'required|numeric|min:0.01',
            'payment_details.*.method_payment' => 'nullable|string|max:255',
            'payment_details.*.expiration_date' => 'nullable|date',
            'payment_details.*.actual_payment_date' => 'nullable|date',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('payment_date') && $this->input('payment_date') === '') {
            $merge['payment_date'] = null;
        }

        $bk = $this->input('billing_kind');
        if (! in_array($bk, ['normal', 'anticipo'], true)) {
            $merge['billing_kind'] = 'normal';
        }

        $cc = strtoupper(trim((string) $this->input('currency_code', '')));
        if ($cc === '') {
            $merge['currency_code'] = null;
        } else {
            $merge['currency_code'] = $cc;
        }

        foreach (['imputation_account_id', 'funds_account_id', 'purchase_order_id', 'supplier_id'] as $accountField) {
            $rawAccount = $this->input($accountField);
            if ($rawAccount === '' || $rawAccount === '0') {
                $merge[$accountField] = null;
            }
        }

        $raw = $this->input('payment_details');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        } elseif (! is_array($raw)) {
            $raw = [];
        }

        $normalized = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $amount = isset($row['amount']) ? (float) str_replace(',', '.', (string) $row['amount']) : 0.0;
            if ($amount <= 0) {
                continue;
            }
            $concept = $row['concept'] ?? 'partiality';
            if (! in_array($concept, ['advance', 'residue', 'partiality'], true)) {
                $concept = 'partiality';
            }
            $method = trim((string) ($row['method_payment'] ?? ''));
            $exp = $row['expiration_date'] ?? null;
            $exp = ($exp === '' || $exp === null) ? null : $exp;
            $paid = $row['actual_payment_date'] ?? null;
            $paid = ($paid === '' || $paid === null) ? null : $paid;

            $normalized[] = [
                'concept' => $concept,
                'amount' => $amount,
                'method_payment' => $method,
                'expiration_date' => $exp,
                'actual_payment_date' => $paid,
            ];
        }

        $merge['payment_details'] = $normalized;
        if (count($normalized) > 0) {
            $sum = 0.0;
            foreach ($normalized as $n) {
                $sum += (float) $n['amount'];
            }
            $merge['total_amount'] = round($sum, 2);
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * Get the validation attributes that apply to the request.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'purchase_order_id' => 'orden de compra',
            'supplier_id' => 'proveedor',
            'billing_kind' => 'tipo de orden de pago',
            'currency_code' => 'moneda',
            'total_amount' => 'monto total',
            'date' => 'fecha',
            'payment_date' => 'fecha de pago',
            'payment_method' => 'forma de pago',
            'bank' => 'banco',
            'observations' => 'observaciones',
            'payment_number' => 'número de orden de pago',
            'status' => 'estado',
            'authorizing_user_id' => 'usuario autorizador',
            'imputation_account_id' => 'cuenta de imputación',
            'funds_account_id' => 'cuenta de fondos',
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'purchase_order_id.exists' => 'La orden de compra seleccionada no existe.',
            'total_amount.required' => 'El campo monto total es obligatorio.',
            'total_amount.numeric' => 'El campo monto total debe ser un número.',
            'total_amount.min' => 'El campo monto total debe ser mayor a 0.',
            'date.required' => 'El campo fecha es obligatorio.',
            'date.date' => 'El campo fecha debe ser una fecha válida.',
            'status.required' => 'El campo estado es obligatorio.',
            'status.in' => 'El campo estado debe ser: Pendiente, Aprobada, Ejecutada o Anulada.',
            'authorizing_user_id.required' => 'El campo usuario autorizador es obligatorio.',
            'authorizing_user_id.exists' => 'El usuario autorizador seleccionado no existe.',
            'payment_date.date' => 'La fecha de pago debe ser una fecha válida.',
            'payment_number.required' => 'El número de orden de pago es obligatorio.',
        ];
    }
}
