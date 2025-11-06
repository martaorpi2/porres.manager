<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentOrderRequest extends FormRequest
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
        $paymentOrderId = $this->route('id') ?? $this->payment_order_id ?? null;
        
        return [
            'purchase_order_id' => [
                'required',
                'exists:purchase_orders,id',
            ],
            'total_amount' => [
                'required',
                'numeric',
                'min:0.01',
                function ($attribute, $value, $fail) use ($paymentOrderId) {
                    // Obtener el purchase_order_id del input
                    $purchaseOrderId = $this->input('purchase_order_id');
                    
                    if (!$purchaseOrderId) {
                        return;
                    }
                    
                    $purchaseOrder = \App\Models\PurchaseOrder::with('details')->find($purchaseOrderId);
                    
                    if (!$purchaseOrder) {
                        return;
                    }
                    
                    // Calcular el total de la orden de compra
                    $purchaseOrderTotal = $purchaseOrder->total;
                    
                    // Obtener todas las órdenes de pago relacionadas (excluyendo la actual si es una actualización)
                    $existingPaymentOrders = \App\Models\PaymentOrder::where('purchase_order_id', $purchaseOrderId);
                    
                    if ($paymentOrderId) {
                        $existingPaymentOrders->where('id', '!=', $paymentOrderId);
                    }
                    
                    // Calcular el total pagado sumando los detalles (op_details) de las órdenes de pago existentes
                    $existingPaymentOrderIds = $existingPaymentOrders->pluck('id')->toArray();
                    $totalPaidFromDetails = 0;
                    if (!empty($existingPaymentOrderIds)) {
                        $totalPaidFromDetails = \Illuminate\Support\Facades\DB::table('op_details')
                            ->whereIn('payment_order_id', $existingPaymentOrderIds)
                            ->sum('amount');
                    }
                    
                    // Obtener la suma de los detalles de la orden de pago actual (si existe)
                    $currentOrderDetailsTotal = 0;
                    if ($paymentOrderId) {
                        $currentOrderDetailsTotal = \Illuminate\Support\Facades\DB::table('op_details')
                            ->where('payment_order_id', $paymentOrderId)
                            ->sum('amount');
                    }
                    
                    // Si hay detalles en el request, calcular su suma
                    $paymentDetails = $this->input('payment_details', []);
                    $detailsTotal = 0;
                    if (!empty($paymentDetails) && is_array($paymentDetails)) {
                        foreach ($paymentDetails as $detail) {
                            if (isset($detail['amount'])) {
                                $detailsTotal += (float) $detail['amount'];
                            }
                        }
                    }
                    
                    // Si hay detalles en el request, usar su suma; si no, usar el total_amount
                    // Pero si hay detalles existentes en BD y no hay detalles en el request, usar los de BD
                    $amountToUse = $value;
                    if ($detailsTotal > 0) {
                        $amountToUse = $detailsTotal;
                    } elseif ($currentOrderDetailsTotal > 0 && !$paymentOrderId) {
                        // Si es creación y hay detalles existentes (no debería pasar), usar los de BD
                        $amountToUse = $currentOrderDetailsTotal;
                    }
                    
                    // Restar el total actual de detalles de la orden de pago (si se está actualizando)
                    if ($paymentOrderId && $currentOrderDetailsTotal > 0) {
                        $totalPaidFromDetails -= $currentOrderDetailsTotal;
                    }
                    
                    // Calcular el nuevo total pagado
                    $newTotalPaid = $totalPaidFromDetails + $amountToUse;
                    
                    // Validar que no supere el total de la orden de compra
                    if ($newTotalPaid > $purchaseOrderTotal) {
                        $remaining = $purchaseOrderTotal - $totalPaidFromDetails;
                        $fail("El monto total de las órdenes de pago ($" . number_format($newTotalPaid, 2) . ") no puede superar el total de la orden de compra ($" . number_format($purchaseOrderTotal, 2) . "). Monto máximo permitido: $" . number_format($remaining, 2));
                    }
                    
                    // Validar que la suma de los detalles coincida con el total_amount (si hay detalles)
                    if ($detailsTotal > 0 && abs($detailsTotal - $value) > 0.01) {
                        $fail("La suma de los montos de los detalles ($" . number_format($detailsTotal, 2) . ") debe coincidir con el monto total de la orden de pago ($" . number_format($value, 2) . ").");
                    }
                    
                    // Si no hay detalles en el request pero hay detalles en BD, validar que coincidan
                    if ($paymentOrderId && $currentOrderDetailsTotal > 0 && $detailsTotal == 0) {
                        if (abs($currentOrderDetailsTotal - $value) > 0.01) {
                            $fail("La suma de los montos de los detalles existentes ($" . number_format($currentOrderDetailsTotal, 2) . ") debe coincidir con el monto total de la orden de pago ($" . number_format($value, 2) . ").");
                        }
                    }
                },
            ],
            'date' => 'required|date',
            'status' => 'required|in:Pendiente,Aprobada,Ejecutada',
            'authorizing_user_id' => 'required|exists:users,id',
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
            //
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
