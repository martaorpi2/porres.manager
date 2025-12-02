<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DevolutionRequest extends FormRequest
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
            'reception_id' => 'required|integer|exists:receptions,id',
            'reason' => 'required|string|max:1000',
            'date' => 'required|date',
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
            'reception_id' => 'recepción',
            'reason' => 'motivo',
            'date' => 'fecha',
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
            'reception_id.required' => 'El campo recepción es obligatorio.',
            'reception_id.integer' => 'El campo recepción debe ser un número entero.',
            'reception_id.exists' => 'La recepción seleccionada no existe.',
            'reason.required' => 'El campo motivo es obligatorio.',
            'reason.string' => 'El campo motivo debe ser texto.',
            'reason.max' => 'El campo motivo no puede tener más de 1000 caracteres.',
            'date.required' => 'El campo fecha es obligatorio.',
            'date.date' => 'El campo fecha debe ser una fecha válida.',
        ];
    }
}
