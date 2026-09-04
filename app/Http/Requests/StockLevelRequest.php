<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockLevelRequest extends FormRequest
{
    public function authorize()
    {
        return backpack_auth()->check();
    }

    public function rules()
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'quantity' => ['required', 'integer', 'min:0'],
            'date' => ['required', 'date'],
            'document_kind' => ['required', 'in:factura,remito'],
            'supplier_invoice_id' => ['nullable', 'required_if:document_kind,factura', 'exists:supplier_invoices,id'],
            'remito_id' => ['nullable', 'required_if:document_kind,remito', 'exists:remitos,id'],
        ];
    }

    protected function prepareForValidation()
    {
        $kind = $this->input('document_kind');

        if ($kind === 'factura') {
            $this->merge(['remito_id' => null]);
        } elseif ($kind === 'remito') {
            $this->merge(['supplier_invoice_id' => null]);
        }

        if ($this->input('supplier_invoice_id') === '' || $this->input('supplier_invoice_id') === '0') {
            $this->merge(['supplier_invoice_id' => null]);
        }
        if ($this->input('remito_id') === '' || $this->input('remito_id') === '0') {
            $this->merge(['remito_id' => null]);
        }
    }

    public function attributes()
    {
        return [
            'product_id' => 'producto',
            'location_id' => 'depósito',
            'quantity' => 'cantidad',
            'date' => 'fecha',
            'document_kind' => 'tipo de comprobante',
            'supplier_invoice_id' => 'factura',
            'remito_id' => 'remito',
        ];
    }

    public function messages()
    {
        return [
            'document_kind.required' => 'Debe asociar el stock a una factura o a un remito.',
            'supplier_invoice_id.required_if' => 'Debe seleccionar la factura de proveedor.',
            'remito_id.required_if' => 'Debe seleccionar el remito.',
        ];
    }
}
