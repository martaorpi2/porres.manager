<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccountingAccountRequest extends FormRequest
{
    public function authorize()
    {
        return backpack_auth()->check();
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('account_type') === '') {
            $this->merge(['account_type' => null]);
        }
    }

    public function rules()
    {
        return [
            'code' => 'required|string|max:30|unique:accounting_accounts,code,' . $this->route('id'),
            'name' => ['required', 'string', 'max:255'],
            'account_type' => ['nullable', 'in:activo,pasivo,patrimonio,ingreso,gasto'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes()
    {
        return [
            'code' => 'código',
            'name' => 'nombre',
            'account_type' => 'tipo de cuenta',
            'is_active' => 'activa',
        ];
    }
}
