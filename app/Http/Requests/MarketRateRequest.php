<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MarketRateRequest extends FormRequest
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
     * Normalizar total_amount: aceptar coma o punto como decimal (ej. 191885,88 o 191885.88).
     */
    protected function prepareForValidation()
    {
        $amount = $this->input('total_amount');
        if ($amount !== null && $amount !== '') {
            if (is_string($amount)) {
                $amount = trim($amount);
                // Si hay coma, formato europeo (ej. 191.885,88 o 191885,88): quitar puntos de miles, coma = decimal
                if (str_contains($amount, ',')) {
                    $amount = str_replace('.', '', $amount);
                    $amount = str_replace(',', '.', $amount);
                }
                // Si solo hay punto, puede ser US (191885.88) o miles (191.885) - asumir decimal
            }
            $this->merge(['total_amount' => $amount]);
        }
        $vatAmount = $this->input('vat_amount');
        if ($vatAmount !== null && $vatAmount !== '') {
            if (is_string($vatAmount)) {
                $vatAmount = trim($vatAmount);
                if (str_contains($vatAmount, ',')) {
                    $vatAmount = str_replace('.', '', $vatAmount);
                    $vatAmount = str_replace(',', '.', $vatAmount);
                }
            }
            $this->merge(['vat_amount' => $vatAmount]);
        }
        $totalWithVat = $this->input('total_amount_with_vat');
        if ($totalWithVat !== null && $totalWithVat !== '') {
            if (is_string($totalWithVat)) {
                $totalWithVat = trim($totalWithVat);
                if (str_contains($totalWithVat, ',')) {
                    $totalWithVat = str_replace('.', '', $totalWithVat);
                    $totalWithVat = str_replace(',', '.', $totalWithVat);
                }
            }
            $this->merge(['total_amount_with_vat' => $totalWithVat]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_request_id' => 'required|exists:purchase_requests,id',
            'date' => 'required|date',
            'delivery_date' => 'nullable|date',
            'delivery_term' => 'nullable|string|max:255',
            'payment_method' => 'nullable|string|max:255',
            'validity_term' => 'nullable|string|max:255',
            'total_amount' => 'nullable|numeric|min:0',
            'vat_amount' => 'nullable|numeric|min:0',
            'total_amount_with_vat' => 'nullable|numeric|min:0',
            'reference_links' => 'nullable|string|max:20000',
            'clear_document_files' => 'nullable|array',
            'clear_document_files.*' => 'nullable|string|max:500',
            'is_selected' => 'boolean',
        ];
    }

    /**
     * El monto total de la cotización es obligatorio: campo explícito y/o suma de ítems mayor que cero.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $manual = $this->input('total_amount');
            $manualNum = is_numeric($manual) ? (float) $manual : null;
            $fromLines = $this->sumSelectedQuoteItemsTotal((string) $this->input('selected_quote_items', ''));
            $effective = ($manualNum !== null && $manualNum > 0) ? $manualNum : $fromLines;
            if ($effective <= 0) {
                $v->errors()->add(
                    'total_amount',
                    'El monto total de la cotización es obligatorio: ingréselo en el campo correspondiente o cargue ítems con cantidad e importe que sumen un total mayor a cero.'
                );
            }
        });
    }

    private function sumSelectedQuoteItemsTotal(?string $selectedItems): float
    {
        if ($selectedItems === null || $selectedItems === '') {
            return 0.0;
        }
        $items = json_decode($selectedItems, true);
        if (! is_array($items)) {
            return 0.0;
        }
        $sum = 0.0;
        foreach ($items as $itemData) {
            if (! is_array($itemData)) {
                continue;
            }
            $sum += (float) ($itemData['quantity'] ?? 0) * (float) ($itemData['unit_price'] ?? 0);
        }

        return $sum;
    }

    /**
     * Get the validation attributes that apply to the request.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'supplier_id' => 'proveedor',
            'purchase_request_id' => 'solicitud de compra',
            'date' => 'fecha',
            'delivery_date' => 'fecha de entrega',
            'delivery_term' => 'plazo de entrega',
            'payment_method' => 'forma de pago',
            'validity_term' => 'validez de la cotización',
            'total_amount' => 'monto total',
            'vat_amount' => 'IVA',
            'total_amount_with_vat' => 'monto total con IVA',
            'reference_links' => 'enlaces de referencia',
            'is_selected' => 'estado de selección',
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
            'supplier_id.required' => 'El campo proveedor es obligatorio.',
            'supplier_id.exists' => 'El proveedor seleccionado no existe.',
            'purchase_request_id.required' => 'El campo solicitud de compra es obligatorio.',
            'purchase_request_id.exists' => 'La solicitud de compra seleccionada no existe.',
            'date.required' => 'El campo fecha es obligatorio.',
            'date.date' => 'El campo fecha debe ser una fecha válida.',
            'delivery_date.date' => 'El campo fecha de entrega debe ser una fecha válida.',
            'delivery_term.max' => 'El campo plazo de entrega no puede superar los 255 caracteres.',
            'payment_method.max' => 'El campo forma de pago no puede superar los 255 caracteres.',
            'validity_term.max' => 'El campo validez de la cotización no puede superar los 255 caracteres.',
            'total_amount.numeric' => 'El campo monto total debe ser un número.',
            'total_amount.min' => 'El campo monto total debe ser mayor a cero cuando se informa manualmente.',
            'vat_amount.numeric' => 'El campo IVA debe ser un número.',
            'vat_amount.min' => 'El campo IVA debe ser mayor o igual a 0.',
            'total_amount_with_vat.numeric' => 'El campo monto total con IVA debe ser un número.',
            'total_amount_with_vat.min' => 'El campo monto total con IVA debe ser mayor o igual a 0.',
            'is_selected.boolean' => 'El campo estado de selección debe ser verdadero o falso.',
        ];
    }
}
