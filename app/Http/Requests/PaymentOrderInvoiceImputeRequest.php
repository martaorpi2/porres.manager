<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class PaymentOrderInvoiceImputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = backpack_user();

        return $u instanceof User && $u->canManageSupplierInvoices();
    }

    public function rules(): array
    {
        return [
            'supplier_invoice_id' => ['required', 'exists:supplier_invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'imputed_at' => ['required', 'date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_invoice_id' => 'factura',
            'amount' => 'monto a imputar',
            'imputed_at' => 'fecha de imputación',
        ];
    }
}
