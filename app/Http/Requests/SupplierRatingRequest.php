<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierRatingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
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
            'supplier_id' => 'required|exists:suppliers,id',
            'quality_rating' => 'required|integer|min:1|max:5',
            'price_rating' => 'required|integer|min:1|max:5',
            'delivery_time_rating' => 'required|integer|min:1|max:5',
            'service_rating' => 'required|integer|min:1|max:5',
            'overall_rating' => 'required|integer|min:1|max:5',
            'comments' => 'nullable|string|max:1000',
            'evaluation_date' => 'required|date',
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
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
            'supplier_id' => 'proveedor',
            'quality_rating' => 'calificación de calidad',
            'price_rating' => 'calificación de precio',
            'delivery_time_rating' => 'calificación de tiempo de entrega',
            'service_rating' => 'calificación de servicio',
            'overall_rating' => 'calificación general',
            'comments' => 'comentarios',
            'evaluation_date' => 'fecha de evaluación',
            'purchase_order_id' => 'orden de compra',
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
            'supplier_id.required' => 'Debe seleccionar un proveedor.',
            'quality_rating.required' => 'Debe proporcionar una calificación de calidad.',
            'quality_rating.min' => 'La calificación de calidad debe ser al menos 1.',
            'quality_rating.max' => 'La calificación de calidad no puede ser mayor a 5.',
            'price_rating.required' => 'Debe proporcionar una calificación de precio.',
            'price_rating.min' => 'La calificación de precio debe ser al menos 1.',
            'price_rating.max' => 'La calificación de precio no puede ser mayor a 5.',
            'delivery_time_rating.required' => 'Debe proporcionar una calificación de tiempo de entrega.',
            'delivery_time_rating.min' => 'La calificación de tiempo de entrega debe ser al menos 1.',
            'delivery_time_rating.max' => 'La calificación de tiempo de entrega no puede ser mayor a 5.',
            'service_rating.required' => 'Debe proporcionar una calificación de servicio.',
            'service_rating.min' => 'La calificación de servicio debe ser al menos 1.',
            'service_rating.max' => 'La calificación de servicio no puede ser mayor a 5.',
            'overall_rating.required' => 'Debe proporcionar una calificación general.',
            'overall_rating.min' => 'La calificación general debe ser al menos 1.',
            'overall_rating.max' => 'La calificación general no puede ser mayor a 5.',
            'evaluation_date.required' => 'Debe proporcionar una fecha de evaluación.',
            'evaluation_date.date' => 'La fecha de evaluación debe ser una fecha válida.',
        ];
    }
}

