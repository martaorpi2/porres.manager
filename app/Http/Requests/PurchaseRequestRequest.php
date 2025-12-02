<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseRequestRequest extends FormRequest
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
            'request_number' => 'required|string|max:255|unique:purchase_requests,request_number,' . $this->route('id'),
            'request_date' => 'required|date',
            'responsibility_area_id' => 'required|exists:responsibility_areas,id',
            'requesting_user_id' => 'required|exists:users,id',
            'priority' => 'required|in:Baja,Media,Alta,Urgente',
            'status' => 'nullable|in:Pendiente,Aprobada,Rechazada,En Proceso,Completada',
            'justification' => 'nullable|string',
            'observations' => 'nullable|string',
            'total_amount' => 'nullable|numeric|min:0',
            'converted_from_general_request_id' => 'nullable|exists:general_requests,id',
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
            'request_number' => 'número de solicitud',
            'request_date' => 'fecha de solicitud',
            'responsibility_area_id' => 'área de responsabilidad',
            'requesting_user_id' => 'usuario solicitante',
            'priority' => 'prioridad',
            'status' => 'estado',
            'justification' => 'justificación',
            'observations' => 'observaciones',
            'total_amount' => 'monto total',
            'converted_from_general_request_id' => 'solicitud general origen',
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
            'request_number.required' => 'El número de solicitud es obligatorio.',
            'request_number.unique' => 'El número de solicitud ya existe.',
            'request_date.required' => 'La fecha de solicitud es obligatoria.',
            'responsibility_area_id.required' => 'El área de responsabilidad es obligatoria.',
            'responsibility_area_id.exists' => 'El área de responsabilidad seleccionada no es válida.',
            'requesting_user_id.required' => 'El usuario solicitante es obligatorio.',
            'requesting_user_id.exists' => 'El usuario solicitante seleccionado no es válido.',
            'priority.required' => 'La prioridad es obligatoria.',
            'priority.in' => 'La prioridad debe ser: Baja, Media, Alta o Urgente.',
            'status.in' => 'El estado debe ser: Pendiente, Aprobada, Rechazada, En Proceso o Completada.',
            'total_amount.numeric' => 'El campo monto total debe ser un número.',
            'total_amount.min' => 'El campo monto total debe ser mayor o igual a 0.',
            'converted_from_general_request_id.exists' => 'La solicitud general origen seleccionada no existe.',
        ];
    }
}
