<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeneralRequest;
use App\Models\PurchaseRequest;
use App\Models\PurchaseOrder;
use App\Models\PaymentOrder;
use App\Models\Reception;
use App\Models\Devolution;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with process flow visualization.
     */
    public function index()
    {
        // Estadísticas generales
        $stats = [
            'general_requests' => GeneralRequest::count(),
            'general_requests_pending' => GeneralRequest::where('status', 'Pendiente')->count(),
            'purchase_requests' => PurchaseRequest::count(),
            'purchase_requests_pending' => PurchaseRequest::where('status', 'Pendiente')->count(),
            'purchase_orders' => PurchaseOrder::count(),
            'purchase_orders_pending' => PurchaseOrder::where('status', 'Pendiente')->count(),
            'payment_orders' => PaymentOrder::count(),
            'receptions' => Reception::count(),
            'devolutions' => Devolution::count(),
        ];

        // Obtener solicitudes generales recientes con sus detalles
        $generalRequests = GeneralRequest::with(['createdBy', 'area', 'details.product', 'purchaseRequests'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Obtener solicitudes de compra recientes
        $purchaseRequests = PurchaseRequest::with(['requestingUser', 'responsibilityArea', 'convertedFromGeneralRequest', 'details.product', 'selectedMarketRate'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Obtener órdenes de compra recientes
        $purchaseOrders = PurchaseOrder::with(['supplier', 'user', 'details', 'receptions'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Obtener órdenes de pago recientes
        $paymentOrders = PaymentOrder::with(['purchase_order.supplier', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Obtener recepciones recientes
        $receptions = Reception::with(['purchase_order.supplier', 'user', 'devolutions'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Obtener devoluciones recientes
        $devolutions = Devolution::with(['reception.purchase_order.supplier', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Obtener el flujo completo de procesos (trazabilidad completa)
        $processFlows = $this->getProcessFlows();

        // Obtener 8 proveedores con sus calificaciones promedio
        $suppliersWithRatings = Supplier::with('ratings')
            ->get()
            ->filter(function($supplier) {
                return $supplier->ratings->count() > 0;
            })
            ->map(function($supplier) {
                $supplier->average_rating = $supplier->average_rating;
                $supplier->total_ratings = $supplier->total_ratings;
                return $supplier;
            })
            ->sortByDesc('average_rating')
            ->take(8);

        return view('vendor.backpack.ui.dashboard', compact(
            'stats',
            'generalRequests',
            'purchaseRequests',
            'purchaseOrders',
            'paymentOrders',
            'receptions',
            'devolutions',
            'processFlows',
            'suppliersWithRatings'
        ));
    }

    /**
     * Obtener el flujo completo de procesos relacionando todas las entidades
     */
    private function getProcessFlows()
    {
        $flows = [];

        // Obtener solicitudes generales con sus procesos relacionados
        $generalRequests = GeneralRequest::with([
            'purchaseRequests' => function($query) {
                $query->with([
                    'selectedMarketRate.supplier',
                    'details.product'
                ]);
            },
            'createdBy',
            'area',
            'details.product'
        ])->orderBy('created_at', 'desc')->limit(20)->get();

        foreach ($generalRequests as $generalRequest) {
            // Solo mostrar flujos que tienen al menos una solicitud de compra
            if ($generalRequest->purchaseRequests->isEmpty()) {
                continue;
            }

            $flow = [
                'general_request' => $generalRequest,
                'purchase_requests' => [],
                'purchase_orders' => [],
                'payment_orders' => [],
                'receptions' => [],
                'devolutions' => [],
            ];

            // Obtener solicitudes de compra relacionadas
            foreach ($generalRequest->purchaseRequests as $purchaseRequest) {
                $flow['purchase_requests'][] = $purchaseRequest;

                // Obtener órdenes de compra relacionadas directamente por la relación
                $purchaseOrders = PurchaseOrder::where('purchase_request_id', $purchaseRequest->id)
                    ->with(['supplier', 'details', 'paymentOrders', 'receptions.devolutions'])
                    ->get();
                
                foreach ($purchaseOrders as $purchaseOrder) {
                    // Evitar duplicados
                    if (!in_array($purchaseOrder->id, array_column($flow['purchase_orders'], 'id'))) {
                        $flow['purchase_orders'][] = $purchaseOrder;
                        
                        // Obtener órdenes de pago directamente de la relación
                        foreach ($purchaseOrder->paymentOrders as $paymentOrder) {
                            if (!in_array($paymentOrder->id, array_column($flow['payment_orders'], 'id'))) {
                                $flow['payment_orders'][] = $paymentOrder;
                            }
                        }
                        
                        // Obtener recepciones directamente de la relación
                        foreach ($purchaseOrder->receptions as $reception) {
                            if (!in_array($reception->id, array_column($flow['receptions'], 'id'))) {
                                $flow['receptions'][] = $reception;
                                
                                // Obtener devoluciones directamente de la relación
                                foreach ($reception->devolutions as $devolution) {
                                    if (!in_array($devolution->id, array_column($flow['devolutions'], 'id'))) {
                                        $flow['devolutions'][] = $devolution;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Agregar flujos que tengan al menos solicitudes de compra
            // Mostrar todos los flujos que tengan solicitudes de compra, incluso si no tienen órdenes aún
            if (!empty($flow['purchase_requests'])) {
                $flows[] = $flow;
            }
        }

        return $flows;
    }
}

