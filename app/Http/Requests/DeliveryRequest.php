<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeliveryRequest extends FormRequest
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
            'reception_id' => 'nullable|exists:receptions,id',
            'general_request_id' => 'nullable|exists:general_requests,id',
            'purchase_request_id' => 'nullable|exists:purchase_requests,id',
            'delivery_date' => 'required|date',
            'delivered_by' => 'required|exists:users,id',
            'received_by' => 'required|exists:users,id',
            'observations' => 'nullable|string',
        ];
    }
    
    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validar que al menos uno de general_request_id o purchase_request_id esté presente
            if (empty($this->general_request_id) && empty($this->purchase_request_id)) {
                $validator->errors()->add('general_request_id', 'Debe seleccionar una solicitud general o una solicitud de compra.');
                $validator->errors()->add('purchase_request_id', 'Debe seleccionar una solicitud general o una solicitud de compra.');
            }
        });
    }

    /**
     * Get the validation attributes that apply to the request.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'delivery_date' => 'fecha de entrega',
            'delivered_by' => 'entregado por',
            'received_by' => 'recibido por',
            'general_request_id' => 'solicitud general',
            'purchase_request_id' => 'solicitud de compra',
            'reception_id' => 'recepción',
            'observations' => 'observaciones',
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
            'delivery_date.required' => 'El campo fecha de entrega es obligatorio.',
            'delivery_date.date' => 'El campo fecha de entrega debe ser una fecha válida.',
            'delivered_by.required' => 'El campo entregado por es obligatorio.',
            'delivered_by.exists' => 'El usuario seleccionado en entregado por no existe.',
            'received_by.required' => 'El campo recibido por es obligatorio.',
            'received_by.exists' => 'El usuario seleccionado en recibido por no existe.',
            'general_request_id.exists' => 'La solicitud general seleccionada no existe.',
            'purchase_request_id.exists' => 'La solicitud de compra seleccionada no existe.',
            'reception_id.exists' => 'La recepción seleccionada no existe.',
        ];
    }
}
