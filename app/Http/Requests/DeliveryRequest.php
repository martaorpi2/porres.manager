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
            'status' => 'nullable|in:pendiente,entregada,cancelada',
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
            //
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
            //
        ];
    }
}
