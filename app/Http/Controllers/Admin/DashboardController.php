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
            $flow = [
                'general_request' => $generalRequest,
                'purchase_requests' => [],
                'purchase_orders' => [],
            ];

            // Obtener solicitudes de compra relacionadas (puede estar vacío)
            foreach ($generalRequest->purchaseRequests as $purchaseRequest) {
                $flow['purchase_requests'][] = $purchaseRequest;

                // Obtener órdenes de compra relacionadas directamente por la relación
                // Cargar todas las relaciones necesarias: paymentOrders, receptions con devolutions
                $purchaseOrders = PurchaseOrder::where('purchase_request_id', $purchaseRequest->id)
                    ->with([
                        'supplier', 
                        'details', 
                        'paymentOrders' => function($query) {
                            $query->with('user')->orderBy('created_at', 'desc');
                        },
                        'receptions' => function($query) {
                            $query->with([
                                'user',
                                'devolutions' => function($q) {
                                    $q->with('user')->orderBy('created_at', 'desc');
                                }
                            ])->orderBy('created_at', 'desc');
                        }
                    ])
                    ->get();
                
                foreach ($purchaseOrders as $purchaseOrder) {
                    // Evitar duplicados
                    if (!in_array($purchaseOrder->id, array_column($flow['purchase_orders'], 'id'))) {
                        // Guardar la orden de compra con todas sus relaciones cargadas
                        $flow['purchase_orders'][] = $purchaseOrder;
                    }
                }
            }

            // Agregar TODOS los flujos, incluso los incompletos
            // Esto incluye solicitudes generales sin solicitudes de compra
            $flows[] = $flow;
        }

        return $flows;
    }
}

