<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeneralRequest;
use App\Models\PurchaseRequest;
use App\Models\PurchaseOrder;
use App\Models\PaymentOrder;
use App\Models\Reception;
use App\Models\Devolution;
use App\Models\Delivery;
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
        $user = backpack_user();
        
        // Validar que el usuario esté autenticado
        if (!$user) {
            abort(403, 'Usuario no autenticado');
        }
        
        $isPersonal = $user->hasRole('role_personal');
        $isResponsableArea = $user->hasRole('role_responsable_area');
        $isAdminInstitucion = $user->hasRole('role_admin_institucion', 'backpack');
        $isApoderado = $user->hasRole('role_apoderado', 'backpack');
        $isRepresentanteLegal = $user->hasRole('role_representante_legal', 'backpack');
        
        // Estadísticas generales
        if ($isPersonal) {
            // Para role_personal, solo mostrar sus propias solicitudes y entregas
            $userRequests = GeneralRequest::where('created_by', $user->id);
            
            $stats = [
                'general_requests' => $userRequests->count(),
                'general_requests_pending' => GeneralRequest::where('created_by', $user->id)->where('status', 'creada')->count(),
                'general_requests_delivered' => GeneralRequest::where('created_by', $user->id)
                    ->whereIn('status', ['entregada_parcialmente', 'entregada_totalmente'])
                    ->count(),
                // Aprobadas: cualquier estado excepto 'creada' (a menos que esté convertida), 'archivada' y 'entregada_parcialmente'/'entregada_totalmente'
                // Las convertidas a compra con estado entregada NO cuentan como aprobadas
                // IMPORTANTE: Solo del usuario logueado (created_by = $user->id)
                'general_requests_approved' => GeneralRequest::where('created_by', $user->id)
                    ->whereNotIn('status', ['entregada_parcialmente', 'entregada_totalmente'])
                    ->where(function($query) {
                        $query->where(function($q) {
                            // Estados que no son 'creada' ni 'archivada'
                            $q->where('status', '!=', 'creada')
                              ->where('status', '!=', 'archivada');
                        })->orWhere(function($q) {
                            // Estado 'creada' pero convertida a compra
                            $q->where('status', 'creada')
                              ->where('is_converted', true);
                        });
                    })
                    ->count(),
                // Entregadas: las que tienen status = 'entregada_totalmente' o 'entregada_parcialmente'
                'general_requests_entregada' => GeneralRequest::where('created_by', $user->id)
                    ->whereIn('status', ['entregada_parcialmente', 'entregada_totalmente'])
                    ->count(),
                // Rechazadas: archivadas (status = 'archivada')
                'general_requests_rejected' => GeneralRequest::where('created_by', $user->id)
                    ->where('status', 'archivada')
                    ->count(),
                'purchase_requests' => 0,
                'purchase_requests_pending' => 0,
                'purchase_orders' => 0,
                'purchase_orders_pending' => 0,
                'payment_orders' => 0,
                'payment_orders_pending' => 0,
                'receptions' => 0,
                'devolutions' => 0,
                'deliveries' => Delivery::where('received_by', $user->id)->count(),
            ];
        } elseif ($isResponsableArea) {
            // Para role_responsable_area, mostrar solicitudes de su área y sus recepciones/entregas
            $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('id');
            $userRequestsQuery = GeneralRequest::where(function($query) use ($user, $userAreas) {
                $query->where('created_by', $user->id);
                if ($userAreas->isNotEmpty()) {
                    $query->orWhereIn('area_id', $userAreas);
                }
            });
            
            // Filtrar entregas relacionadas con solicitudes generales de su área
            $deliveriesQuery = Delivery::whereHas('generalRequest', function($query) use ($user, $userAreas) {
                $query->where(function($q) use ($user, $userAreas) {
                    $q->where('created_by', $user->id);
                    if ($userAreas->isNotEmpty()) {
                        $q->orWhereIn('area_id', $userAreas);
                    }
                });
            });
            
            $stats = [
                'general_requests' => $userRequestsQuery->count(),
                'general_requests_pending' => (clone $userRequestsQuery)->where('status', 'creada')->count(),
                'general_requests_delivered' => (clone $userRequestsQuery)->whereHas('deliveries')->count(),
                'purchase_requests' => PurchaseRequest::where('requesting_user_id', $user->id)->count(),
                'purchase_requests_pending' => PurchaseRequest::where('requesting_user_id', $user->id)->where('status', 'Pendiente')->count(),
                'purchase_orders' => 0,
                'purchase_orders_pending' => 0,
                'payment_orders' => 0,
                'payment_orders_pending' => 0,
                'receptions' => Reception::where('area_manager_id', $user->id)->count(),
                'devolutions' => 0,
                'deliveries' => $deliveriesQuery->count(),
            ];
        } else {
            // Para otros roles, mostrar todas las estadísticas
            $stats = [
                'general_requests' => GeneralRequest::count(),
                'general_requests_pending' => GeneralRequest::where('status', 'Pendiente')->count(),
                'general_requests_delivered' => GeneralRequest::whereHas('deliveries')->count(),
                'purchase_requests' => PurchaseRequest::count(),
                'purchase_requests_pending' => PurchaseRequest::where('status', 'Pendiente')->count(),
                'purchase_orders' => PurchaseOrder::count(),
                'purchase_orders_pending' => PurchaseOrder::where('status', 'Pendiente')->count(),
                'payment_orders' => PaymentOrder::count(),
                'payment_orders_pending' => PaymentOrder::where('status', 'Pendiente')->count(),
                'receptions' => Reception::count(),
                'devolutions' => Devolution::count(),
                'deliveries' => Delivery::count(),
            ];
        }

        // Obtener solicitudes generales recientes con sus detalles
        $generalRequestsQuery = GeneralRequest::with(['createdBy', 'area', 'details.product', 'purchaseRequests']);
        
        if ($isPersonal) {
            $generalRequestsQuery->where('created_by', $user->id);
        } elseif ($isResponsableArea) {
            $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('id');
            $generalRequestsQuery->where(function($query) use ($user, $userAreas) {
                $query->where('created_by', $user->id);
                if ($userAreas->isNotEmpty()) {
                    $query->orWhereIn('area_id', $userAreas);
                }
            });
        }
        
        $generalRequests = $generalRequestsQuery
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Obtener solicitudes de compra recientes
        $purchaseRequestsQuery = PurchaseRequest::with(['requestingUser', 'responsibilityArea', 'convertedFromGeneralRequest', 'details.product', 'selectedMarketRate']);
        
        if ($isResponsableArea) {
            // Para role_responsable_area, solo mostrar sus solicitudes de compra
            $purchaseRequestsQuery->where('requesting_user_id', $user->id);
        } elseif ($isPersonal) {
            // Para role_personal, no mostrar solicitudes de compra
            $purchaseRequests = collect();
        }
        
        if (!$isPersonal) {
            $purchaseRequests = $purchaseRequestsQuery
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        }
        
        // Obtener solicitudes de compra pendientes de aprobación del administrador del instituto, apoderado o representante legal
        $pendingApprovalRequests = collect();
        if ($isAdminInstitucion || $isApoderado || $isRepresentanteLegal) {
            $comprasLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_responsable_compras');
            
            if ($isAdminInstitucion) {
                $userLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_admin_institucion');
            } elseif ($isApoderado) {
                $userLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_apoderado');
            } else {
                $userLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_representante_legal');
            }
            
            $pendingApprovalRequests = PurchaseRequest::with(['requestingUser', 'responsibilityArea', 'details.product', 'directPurchaseSupplier'])
                ->where('status', 'Pendiente')
                ->where(function($query) use ($userLimit, $comprasLimit) {
                    // Solicitudes normales que requieren aprobación de administrador
                    $query->where(function($q) use ($userLimit, $comprasLimit) {
                        $q->where('requires_admin_approval', true)
                          ->where('total_amount', '<=', $userLimit)
                          ->where('total_amount', '>', $comprasLimit);
                    })
                    // O compras directas pendientes de autorización
                    ->orWhere(function($q) use ($userLimit) {
                        $q->where('is_direct_purchase', true)
                          ->where('direct_purchase_authorization_requested', true)
                          ->whereNull('direct_purchase_authorized_by')
                          ->where(function($subQ) {
                              $subQ->where('direct_purchase_authorization_rejected', false)
                                   ->orWhereNull('direct_purchase_authorization_rejected');
                          })
                          ->where('total_amount', '<=', $userLimit);
                    });
                })
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        }

        // Obtener órdenes de compra recientes (solo si no es role_personal ni role_responsable_area)
        $purchaseOrders = collect();
        if (!$isPersonal && !$isResponsableArea) {
            $purchaseOrders = PurchaseOrder::with(['supplier', 'user', 'details', 'receptions'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        }

        // Obtener órdenes de pago recientes (solo si no es role_personal ni role_responsable_area)
        $paymentOrders = collect();
        if (!$isPersonal && !$isResponsableArea) {
            $paymentOrders = PaymentOrder::with(['purchase_order.supplier', 'user'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        }

        // Obtener recepciones recientes
        $receptionsQuery = Reception::with(['purchase_order.supplier', 'user', 'devolutions']);
        if ($isResponsableArea) {
            $receptionsQuery->where('area_manager_id', $user->id);
        } elseif ($isPersonal) {
            $receptions = collect();
        }
        
        if (!$isPersonal) {
            $receptions = $receptionsQuery
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        }

        // Obtener devoluciones recientes (solo si no es role_personal ni role_responsable_area)
        $devolutions = collect();
        if (!$isPersonal && !$isResponsableArea) {
            $devolutions = Devolution::with(['reception.purchase_order.supplier', 'user'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        }

        // Obtener entregas recientes
        $deliveriesQuery = Delivery::with(['reception.purchase_order.supplier', 'generalRequest', 'deliveredBy', 'receivedBy', 'details.product']);
        
        if ($isPersonal) {
            // Para role_personal, solo mostrar entregas donde él es el receptor
            $deliveriesQuery->where('received_by', $user->id);
        } elseif ($isResponsableArea) {
            // Para role_responsable_area, solo mostrar entregas relacionadas con solicitudes generales de su área
            $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('id');
            $deliveriesQuery->whereHas('generalRequest', function($query) use ($user, $userAreas) {
                $query->where(function($q) use ($user, $userAreas) {
                    $q->where('created_by', $user->id);
                    if ($userAreas->isNotEmpty()) {
                        $q->orWhereIn('area_id', $userAreas);
                    }
                });
            });
        }
        
        $deliveries = $deliveriesQuery
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Obtener el flujo completo de procesos (trazabilidad completa)
        $processFlows = [];
        if ($isPersonal) {
            // Para role_personal, mostrar solo el flujo de sus propias solicitudes
            $processFlows = $this->getProcessFlowsForPersonal($user);
        } elseif ($isResponsableArea) {
            // Para role_responsable_area, mostrar flujo de solicitudes de su área
            $processFlows = $this->getProcessFlowsForResponsableArea($user);
        } else {
            // Para administradores y apoderados, mostrar todos los flujos
            $processFlows = $this->getProcessFlows();
        }

        // Obtener 8 proveedores con sus calificaciones promedio (solo si no es role_personal)
        $suppliersWithRatings = collect();
        if (!$isPersonal) {
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
        }

        return view('vendor.backpack.ui.dashboard', compact(
            'stats',
            'generalRequests',
            'purchaseRequests',
            'purchaseOrders',
            'paymentOrders',
            'receptions',
            'devolutions',
            'deliveries',
            'processFlows',
            'suppliersWithRatings',
            'isPersonal',
            'isResponsableArea',
            'isAdminInstitucion',
            'isApoderado',
            'isRepresentanteLegal',
            'pendingApprovalRequests',
            'user'
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
                                },
                                'deliveries' => function($q) {
                                    $q->with(['generalRequest', 'deliveredBy', 'receivedBy', 'details.product'])->orderBy('created_at', 'desc');
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
    
    /**
     * Obtener el flujo completo de procesos para usuarios con rol role_personal
     */
    private function getProcessFlowsForPersonal($user)
    {
        $flows = [];

        // Obtener solo las solicitudes generales del usuario
        $generalRequests = GeneralRequest::where('created_by', $user->id)
            ->with([
                'purchaseRequests' => function($query) {
                    $query->with([
                        'selectedMarketRate.supplier',
                        'details.product'
                    ]);
                },
                'createdBy',
                'area',
                'details.product',
                'deliveries' => function($query) use ($user) {
                    $query->where('received_by', $user->id)
                        ->with(['deliveredBy', 'receivedBy', 'details.product']);
                }
            ])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get();

        foreach ($generalRequests as $generalRequest) {
            $flow = [
                'general_request' => $generalRequest,
                'purchase_requests' => [],
                'purchase_orders' => [],
            ];

            // Obtener solicitudes de compra relacionadas
            foreach ($generalRequest->purchaseRequests as $purchaseRequest) {
                $flow['purchase_requests'][] = $purchaseRequest;

                // Obtener órdenes de compra relacionadas
                $purchaseOrders = PurchaseOrder::where('purchase_request_id', $purchaseRequest->id)
                    ->with([
                        'supplier', 
                        'details', 
                        'paymentOrders' => function($query) {
                            $query->with('user')->orderBy('created_at', 'desc');
                        },
                        'receptions' => function($query) use ($user) {
                            $query->with([
                                'user',
                                'devolutions' => function($q) {
                                    $q->with('user')->orderBy('created_at', 'desc');
                                },
                                'deliveries' => function($q) use ($user) {
                                    $q->where('received_by', $user->id)
                                        ->with(['generalRequest', 'deliveredBy', 'receivedBy', 'details.product'])
                                        ->orderBy('created_at', 'desc');
                                }
                            ])->orderBy('created_at', 'desc');
                        }
                    ])
                    ->get();
                
                foreach ($purchaseOrders as $purchaseOrder) {
                    if (!in_array($purchaseOrder->id, array_column($flow['purchase_orders'], 'id'))) {
                        $flow['purchase_orders'][] = $purchaseOrder;
                    }
                }
            }

            $flows[] = $flow;
        }

        return $flows;
    }
    
    /**
     * Obtener el flujo completo de procesos para usuarios con rol role_responsable_area
     */
    private function getProcessFlowsForResponsableArea($user)
    {
        $flows = [];
        $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('id');

        // Obtener solicitudes generales de su área o que él creó
        $generalRequests = GeneralRequest::where(function($query) use ($user, $userAreas) {
            $query->where('created_by', $user->id);
            if ($userAreas->isNotEmpty()) {
                $query->orWhereIn('area_id', $userAreas);
            }
        })
            ->with([
                'purchaseRequests' => function($query) use ($user) {
                    $query->where('requesting_user_id', $user->id)
                        ->with([
                            'selectedMarketRate.supplier',
                            'details.product'
                        ]);
                },
                'createdBy',
                'area',
                'details.product',
                'deliveries' => function($query) {
                    $query->with(['deliveredBy', 'receivedBy', 'details.product']);
                }
            ])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        foreach ($generalRequests as $generalRequest) {
            $flow = [
                'general_request' => $generalRequest,
                'purchase_requests' => [],
                'purchase_orders' => [],
            ];

            // Obtener solicitudes de compra relacionadas (solo las del usuario)
            // NO incluir órdenes de compra ni órdenes de pago para role_responsable_area
            foreach ($generalRequest->purchaseRequests as $purchaseRequest) {
                // Solo incluir si el purchaseRequest fue creado por este usuario
                if ($purchaseRequest->requesting_user_id == $user->id) {
                    $flow['purchase_requests'][] = $purchaseRequest;
                    // No incluir órdenes de compra ni órdenes de pago para este rol
                }
            }

            $flows[] = $flow;
        }

        return $flows;
    }
}

