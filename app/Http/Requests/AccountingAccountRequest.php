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
        if ($this->input('balance_nature') === '' || $this->boolean('is_grouping')) {
            $this->merge(['balance_nature' => null]);
        }
    }

    public function rules()
    {
        return [
            'code' => 'required|string|max:30|unique:accounting_accounts,code,' . $this->route('id'),
            'name' => ['required', 'string', 'max:255'],
            'account_type' => ['nullable', 'in:activo,pasivo,patrimonio,ingreso,gasto,egreso'],
            'balance_nature' => ['nullable', 'required_unless:is_grouping,1', 'in:deudor,acreedor'],
            'is_grouping' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes()
    {
        return [
            'code' => 'código',
            'name' => 'nombre',
            'account_type' => 'tipo de cuenta',
            'balance_nature' => 'tipo de saldo',
            'is_grouping' => 'rubro',
            'is_active' => 'activa',
        ];
    }
}
