<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RemitoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return backpack_auth()->check();
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'number' => ['required', 'string', 'max:64', 'unique:remitos,number,'.$id.',id,supplier_id,'.((int) $this->input('supplier_id'))],
            'date' => ['required', 'date'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'observations' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('purchase_order_id') === '' || $this->input('purchase_order_id') === '0') {
            $this->merge(['purchase_order_id' => null]);
        }

        if ($this->has('number')) {
            $this->merge(['number' => trim((string) $this->input('number'))]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $sid = (int) $this->input('supplier_id');
            $poid = $this->input('purchase_order_id');
            if (! $poid || ! $sid) {
                return;
            }

            $po = \App\Models\PurchaseOrder::query()->find((int) $poid);
            if (! $po) {
                return;
            }

            $ok = ((int) $po->supplier_id === $sid) || $po->details()->where('supplier_id', $sid)->exists();
            if (! $ok) {
                $validator->errors()->add('supplier_id', 'El proveedor seleccionado no coincide con la orden de compra elegida.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'number' => 'número de remito',
            'date' => 'fecha',
            'supplier_id' => 'proveedor',
            'purchase_order_id' => 'orden de compra',
            'observations' => 'observaciones',
            'attachment' => 'archivo',
        ];
    }

    public function messages(): array
    {
        return [
            'number.unique' => 'Ya existe un remito con este número para el mismo proveedor.',
        ];
    }
}
