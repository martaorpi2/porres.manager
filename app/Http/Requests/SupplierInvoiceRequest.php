<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
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
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'invoice_number' => ['required', 'string', 'max:64'],
            'invoice_date' => ['required', 'date'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'observations' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('purchase_order_id') && ($this->input('purchase_order_id') === '' || $this->input('purchase_order_id') === '0')) {
            $this->merge(['purchase_order_id' => null]);
        }

        $c = strtoupper(trim((string) $this->input('currency_code', '')));
        if ($c === '') {
            $this->merge(['currency_code' => 'ARS']);
        } else {
            $this->merge(['currency_code' => $c]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $sid = (int) $this->input('supplier_id');
            $poid = $this->input('purchase_order_id');
            $num = trim((string) $this->input('invoice_number'));
            $id = $this->route('id');

            if ($poid) {
                $po = \App\Models\PurchaseOrder::query()->find((int) $poid);
                if ($po) {
                    $ok = ((int) $po->supplier_id === $sid) || $po->details()->where('supplier_id', $sid)->exists();
                    if (! $ok) {
                        $validator->errors()->add('supplier_id', 'El proveedor seleccionado no coincide con la orden de compra elegida.');
                    }
                }
            }

            $dup = \App\Models\SupplierInvoice::query()
                ->where('supplier_id', $sid)
                ->where('invoice_number', $num)
                ->when($poid, fn ($q) => $q->where('purchase_order_id', (int) $poid), fn ($q) => $q->whereNull('purchase_order_id'));
            if ($id) {
                $dup->where('id', '!=', $id);
            }
            if ($dup->exists()) {
                $validator->errors()->add('invoice_number', 'Ya existe una factura con este número para el mismo proveedor y orden de compra (o sin OC).');
            }
        });
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
