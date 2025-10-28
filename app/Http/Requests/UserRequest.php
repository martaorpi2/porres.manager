<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return backpack_auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($userId),
            ],
            'password' => $userId ? 'nullable|string|min:8' : 'required|string|min:8',
            'roles' => 'nullable|array|present',
            'permissions' => 'nullable|array|present',
        ];
    }
    
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        // Convertir a array si viene como string
        if ($this->has('roles')) {
            $roles = $this->input('roles');
            if (!is_array($roles)) {
                $roles = $roles ? [$roles] : [];
            }
            $this->merge(['roles' => $roles]);
        } else {
            $this->merge(['roles' => []]);
        }
        
        if ($this->has('permissions')) {
            $permissions = $this->input('permissions');
            if (!is_array($permissions)) {
                $permissions = $permissions ? [$permissions] : [];
            }
            $this->merge(['permissions' => $permissions]);
        } else {
            $this->merge(['permissions' => []]);
        }
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El campo nombre es obligatorio.',
            'email.required' => 'El campo email es obligatorio.',
            'email.email' => 'El email debe ser válido.',
            'email.unique' => 'Este email ya está registrado.',
            'password.required' => 'El campo contraseña es obligatorio al crear un usuario.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ];
    }
}
