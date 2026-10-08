<?php

namespace App\Http\Requests;

use App\Models\AccountingPeriod;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AccountingPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = backpack_user();

        return $user instanceof User && $user->canViewAccounting();
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));
        $this->merge(['name' => $name === '' ? null : $name]);
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $ignoreId = $this->route('id');
            $overlaps = AccountingPeriod::query()
                ->overlapping(
                    (string) $this->input('start_date'),
                    (string) $this->input('end_date'),
                    $ignoreId ? (int) $ignoreId : null,
                )
                ->exists();

            if ($overlaps) {
                $validator->errors()->add('start_date', 'Las fechas se superponen con otro período contable.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'start_date' => 'fecha de inicio',
            'end_date' => 'fecha de fin',
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
        ];
    }
}
