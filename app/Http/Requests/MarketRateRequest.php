<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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

        // Evitar que el input oculto de upload_multiple envíe valores que fallen la validación si no hay archivos reales
        if (! $this->hasFile('document_files')) {
            $this->request->remove('document_files');
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
            'payment_method' => 'nullable|string|max:255',
            'total_amount' => 'nullable|numeric|min:0',
            'reference_links' => 'nullable|string|max:20000',
            'clear_document_files' => 'nullable|array',
            'clear_document_files.*' => 'nullable|string|max:500',
            'is_selected' => 'boolean',
        ];
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
            'payment_method' => 'forma de pago',
            'total_amount' => 'monto total',
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
            'payment_method.max' => 'El campo forma de pago no puede superar los 255 caracteres.',
            'total_amount.numeric' => 'El campo monto total debe ser un número.',
            'total_amount.min' => 'El campo monto total debe ser mayor o igual a 0.',
            'is_selected.boolean' => 'El campo estado de selección debe ser verdadero o falso.',
        ];
    }
}
