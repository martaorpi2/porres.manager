<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockLevelRequest extends FormRequest
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
            'product_id' => 'required|exists:products,id',
            'location_id' => 'required|exists:locations,id',
            'quantity' => 'required|integer|min:0',
            'entry_date' => 'required|date',
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
            'product_id' => 'producto',
            'location_id' => 'depósito',
            'quantity' => 'cantidad',
            'entry_date' => 'fecha de ingreso',
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
            'entry_date.required' => 'La fecha de ingreso es obligatoria.',
            'entry_date.date' => 'La fecha de ingreso debe ser una fecha válida.',
        ];
    }
}
