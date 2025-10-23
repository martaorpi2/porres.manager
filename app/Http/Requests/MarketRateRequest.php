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
            'total_amount' => 'required|numeric|min:0',
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
            'total_amount' => 'monto total',
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
            'total_amount.required' => 'El campo monto total es obligatorio.',
            'total_amount.numeric' => 'El campo monto total debe ser un número.',
            'total_amount.min' => 'El campo monto total debe ser mayor o igual a 0.',
            'is_selected.boolean' => 'El campo estado de selección debe ser verdadero o falso.',
        ];
    }
}
