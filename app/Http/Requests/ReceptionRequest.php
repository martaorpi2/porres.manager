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
        // Excluir la recepción actual al validar OC duplicada. En PUT/PATCH, si la ruta no expone id, usar el hidden del formulario (no en POST crear: evita eludir la regla con un id falso).
        $receptionId = $this->route('id') ?? $this->route()?->parameter('id');
        if (($receptionId === null || $receptionId === '') && in_array($this->method(), ['PUT', 'PATCH'], true)) {
            $receptionId = $this->input('id');
        }
        $receptionId = $receptionId !== null && $receptionId !== '' ? (int) $receptionId : null;
        
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
            'conformidad_estado' => 'required|in:Si,No',
            'conformidad_cantidad' => 'required|in:Si,No',
            'conformidad_factura' => 'required|in:Si,No',
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
            'conformidad_estado' => 'conformidad de estado',
            'conformidad_cantidad' => 'conformidad de cantidad',
            'conformidad_factura' => 'conformidad de factura',
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
            'conformidad_estado.required' => 'La conformidad de estado es obligatoria.',
            'conformidad_cantidad.required' => 'La conformidad de cantidad es obligatoria.',
            'conformidad_factura.required' => 'La conformidad de factura es obligatoria.',
            'conformidad_estado.in' => 'La conformidad de estado debe ser Si o No.',
            'conformidad_cantidad.in' => 'La conformidad de cantidad debe ser Si o No.',
            'conformidad_factura.in' => 'La conformidad de factura debe ser Si o No.',
            'area_manager_id.required' => 'El responsable es obligatorio.',
            'area_manager_id.exists' => 'El responsable seleccionado no existe.',
        ];
    }
}
