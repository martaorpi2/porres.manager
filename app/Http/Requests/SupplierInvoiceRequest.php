<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = backpack_user();

        return $u instanceof User && $u->hasAdministradoraInstitucionRole();
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'purchase_order_id' => ['required', 'exists:purchase_orders,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'invoice_number' => [
                'required',
                'string',
                'max:64',
                Rule::unique('supplier_invoices', 'invoice_number')
                    ->where('purchase_order_id', (int) $this->input('purchase_order_id'))
                    ->where('supplier_id', (int) $this->input('supplier_id'))
                    ->ignore($id),
            ],
            'invoice_date' => ['required', 'date'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'observations' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $c = strtoupper(trim((string) $this->input('currency_code', '')));
        if ($c === '') {
            $this->merge(['currency_code' => 'ARS']);
        } else {
            $this->merge(['currency_code' => $c]);
        }
    }

    public function attributes(): array
    {
        return [
            'purchase_order_id' => 'orden de compra',
            'supplier_id' => 'proveedor',
            'invoice_number' => 'número de factura',
            'invoice_date' => 'fecha de factura',
            'total_amount' => 'importe total',
            'currency_code' => 'moneda',
        ];
    }
}
