<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccountingAccountRequest extends FormRequest
{
    public function authorize()
    {
        return backpack_auth()->check();
    }

    public function rules()
    {
        return [
            'code' => 'required|string|max:30|unique:accounting_accounts,code,' . $this->route('id'),
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes()
    {
        return [
            'code' => 'código',
            'name' => 'nombre',
            'is_active' => 'activa',
        ];
    }
}
