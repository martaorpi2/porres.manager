<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceptionRequest extends FormRequest
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
        $receptionId = $this->route('id') ?? $this->reception_id ?? null;
        
        return [
            'purchase_order_id' => [
                'required',
                'exists:purchase_orders,id',
                function ($attribute, $value, $fail) use ($receptionId) {
                    // Verificar si ya existe una recepción para esta orden de compra
                    $existingReception = \App\Models\Reception::where('purchase_order_id', $value)
                        ->when($receptionId, function ($query) use ($receptionId) {
                            // Si es una actualización, excluir la recepción actual
                            return $query->where('id', '!=', $receptionId);
                        })
                        ->first();
                    
                    if ($existingReception) {
                        $fail('Esta orden de compra ya tiene una recepción registrada.');
                    }
                },
            ],
            'date' => 'required|date',
            'according' => 'required|in:Si,No',
            'area_manager_id' => 'required|exists:users,id',
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
            'purchase_order_id' => 'orden de compra',
            'date' => 'fecha',
            'according' => 'conforme',
            'area_manager_id' => 'responsable de área',
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
            'purchase_order_id.required' => 'La orden de compra es obligatoria.',
            'purchase_order_id.exists' => 'La orden de compra seleccionada no existe.',
            'date.required' => 'La fecha es obligatoria.',
            'date.date' => 'La fecha debe ser una fecha válida.',
            'according.required' => 'El campo conforme es obligatorio.',
            'according.in' => 'El campo conforme debe ser Si o No.',
            'area_manager_id.required' => 'El responsable es obligatorio.',
            'area_manager_id.exists' => 'El responsable seleccionado no existe.',
        ];
    }
}
