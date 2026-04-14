<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierRequest extends FormRequest
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
            'company_name' => ['required', 'string', 'max:255'],
            'cuit' => ['required', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact' => ['nullable', 'string', 'max:150'],
            'cvu' => ['nullable', 'string', 'max:22'],
            'alias' => ['nullable', 'string', 'max:50'],
            'supplier_heading_id' => ['required', 'exists:suppliers_headings,id'],
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
            'company_name' => 'nombre',
            'cuit' => 'CUIT',
            'address' => 'dirección',
            'email' => 'email',
            'contact' => 'teléfono',
            'cvu' => 'CBU/CVU',
            'alias' => 'alias',
            'supplier_heading_id' => 'rubro',
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
