<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SupplierInvoiceImputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = backpack_user();

        return $u instanceof User && $u->canManageSupplierInvoices();
    }

    public function rules(): array
    {
        return [
            'payment_order_id' => ['required', 'exists:payment_orders,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'imputed_at' => ['required', 'date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'payment_order_id' => 'orden de pago',
            'amount' => 'monto a imputar',
            'imputed_at' => 'fecha de imputación',
        ];
    }
}
