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
use App\Models\StockLevel;
use App\Models\Product;
use App\Models\Location;
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
        $isResponsableCompras = $user->hasRole('role_responsable_compras', 'backpack');
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
                'purchase_requests_normal' => 0,
                'purchase_requests_direct' => 0,
                'purchase_requests_quick' => 0,
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
                'general_requests_pending_delivery' => (clone $userRequestsQuery)->whereDoesntHave('deliveries')->count(),
                'purchase_requests' => PurchaseRequest::where('requesting_user_id', $user->id)->count(),
                'purchase_requests_pending' => PurchaseRequest::where('requesting_user_id', $user->id)->where('status', 'Pendiente')->count(),
                'purchase_requests_normal' => PurchaseRequest::where('requesting_user_id', $user->id)
                    ->whereIn('status', ['Aprobada', 'Completada'])
                    ->where(function($q) {
                        $q->where('purchase_type', 'normal')->orWhereNull('purchase_type');
                    })->count(),
                'purchase_requests_direct' => PurchaseRequest::where('requesting_user_id', $user->id)
                    ->whereIn('status', ['Aprobada', 'Completada'])
                    ->where('purchase_type', 'directa')->count(),
                'purchase_requests_quick' => PurchaseRequest::where('requesting_user_id', $user->id)
                    ->whereIn('status', ['Aprobada', 'Completada'])
                    ->where('purchase_type', 'rapida')->count(),
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
                'purchase_requests_normal' => PurchaseRequest::whereIn('status', ['Aprobada', 'Completada'])
                    ->where(function($q) {
                        $q->where('purchase_type', 'normal')->orWhereNull('purchase_type');
                    })->count(),
                'purchase_requests_direct' => PurchaseRequest::whereIn('status', ['Aprobada', 'Completada'])
                    ->where('purchase_type', 'directa')->count(),
                'purchase_requests_quick' => PurchaseRequest::whereIn('status', ['Aprobada', 'Completada'])
                    ->where('purchase_type', 'rapida')->count(),
                'purchase_orders' => PurchaseOrder::count(),
                'purchase_orders_pending' => PurchaseOrder::where('status', 'Pendiente')->count(),
                'payment_orders' => PaymentOrder::count(),
                'payment_orders_pending' => PaymentOrder::dashboardPendingPayment()->count(),
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
            // Responsable de área: ver solicitudes de su área Y las que él solicita
            $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('id');
            $generalRequestsQuery->where(function($query) use ($user, $userAreas) {
                $query->where('created_by', $user->id);
                if ($userAreas->isNotEmpty()) {
                    $query->orWhereIn('area_id', $userAreas);
                }
            });
        } elseif ($isResponsableCompras) {
            // Responsable de compras: solo sus propias solicitudes generales
            $generalRequestsQuery->where('created_by', $user->id);
        }
        
        $generalRequests = $generalRequestsQuery
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit(12)
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
                ->limit(12)
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
                ->orderBy('created_at', 'asc') // Ordenar por más antiguas primero
                ->limit(12)
                ->get();
        }

        // Responsable de compras: solicitudes ya aprobadas por un usuario de nivel superior (no por compras)
        $superiorApprovedPurchaseRequestsCount = 0;
        if ($isResponsableCompras) {
            $supervisorRoleNames = [
                'role_admin_sistema',
                'role_admin_institucion',
                'role_apoderado',
                'role_representante_legal',
            ];
            $superiorApprovedQuery = PurchaseRequest::query()
                ->where('status', 'Aprobada')
                ->whereNotNull('approved_by')
                ->where('approved_by', '!=', $user->id)
                ->whereHas('approvedBy.roles', function ($q) use ($supervisorRoleNames) {
                    $q->where('guard_name', 'backpack')->whereIn('name', $supervisorRoleNames);
                });
            $superiorApprovedPurchaseRequestsCount = (clone $superiorApprovedQuery)->count();
        }

        // Responsable de compras: OC con al menos una recepción conforme y sin orden de pago asociada
        $purchaseOrdersPendingPaymentAfterConformeCount = 0;
        if ($isResponsableCompras) {
            $purchaseOrdersPendingPaymentAfterConformeCount = PurchaseOrder::query()
                ->whereHas('receptions', function ($q) {
                    $q->where('according', 'Si');
                })
                ->whereDoesntHave('paymentOrders')
                ->count();
        }

        // Obtener órdenes de compra recientes (solo si no es role_personal ni role_responsable_area)
        $purchaseOrders = collect();
        if (!$isPersonal && !$isResponsableArea) {
            $purchaseOrders = PurchaseOrder::with(['supplier', 'user', 'details', 'receptions'])
                ->orderBy('created_at', 'desc')
                ->limit(12)
                ->get();
        }

        // Obtener órdenes de pago recientes (solo si no es role_personal ni role_responsable_area)
        $paymentOrders = collect();
        if (!$isPersonal && !$isResponsableArea) {
            $paymentOrders = PaymentOrder::with(['purchase_order.supplier', 'user'])
                ->orderBy('created_at', 'desc')
                ->limit(12)
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
                ->limit(12)
                ->get();
        }

        // Obtener devoluciones recientes (solo si no es role_personal ni role_responsable_area)
        $devolutions = collect();
        if (!$isPersonal && !$isResponsableArea) {
            $devolutions = Devolution::with(['reception.purchase_order.supplier', 'user'])
                ->orderBy('created_at', 'desc')
                ->limit(12)
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
            ->limit(12)
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

        // Obtener alertas de stock mínimo para responsables de área
        $stockAlerts = collect();
        $stockAlertsHtml = '';
        if ($isResponsableArea) {
            $stockAlerts = $this->getStockMinimumAlerts($user);
            
            // Generar HTML para las alertas
            if ($stockAlerts->isNotEmpty()) {
                \Log::info('Generando HTML para alertas de stock', ['count' => $stockAlerts->count()]);
                
                $stockAlertsHtml = '<div style="text-align: left; max-height: 400px; overflow-y: auto;">';
                $stockAlertsHtml .= '<div class="alert alert-warning" style="margin-bottom: 15px;">';
                $stockAlertsHtml .= '<i class="la la-info-circle"></i> <strong>Atención:</strong> Tienes <strong>' . $stockAlerts->count() . '</strong> producto(s) con stock por debajo del mínimo requerido.';
                $stockAlertsHtml .= '</div>';
                $stockAlertsHtml .= '<table class="table table-sm table-hover" style="font-size: 0.9em;">';
                $stockAlertsHtml .= '<thead style="background-color: #f8d7da;"><tr>';
                $stockAlertsHtml .= '<th>Producto</th><th>Stock Actual</th><th>Stock Mín.</th><th>Déficit</th><th>Ubicaciones</th>';
                $stockAlertsHtml .= '</tr></thead><tbody>';
                
                foreach ($stockAlerts as $alert) {
                    $stockAlertsHtml .= '<tr>';
                    $stockAlertsHtml .= '<td><strong>' . e($alert['product']->name) . '</strong>';
                    if ($alert['product']->description) {
                        $stockAlertsHtml .= '<br><small class="text-muted">' . e(\Str::limit($alert['product']->description, 40)) . '</small>';
                    }
                    $stockAlertsHtml .= '</td>';
                    $stockAlertsHtml .= '<td><span class="badge bg-danger">' . number_format($alert['current_stock'], 0) . '</span></td>';
                    $stockAlertsHtml .= '<td><span class="badge bg-warning text-dark">' . number_format($alert['minimum_stock'], 0) . '</span></td>';
                    $stockAlertsHtml .= '<td><span class="badge bg-danger">' . number_format($alert['deficit'], 0) . ' ' . e($alert['product']->unit_measurement ?? 'unidades') . '</span></td>';
                    $stockAlertsHtml .= '<td>';
                    if ($alert['locations']->isNotEmpty()) {
                        foreach ($alert['locations'] as $location) {
                            $stockAlertsHtml .= '<span class="badge bg-secondary" style="margin: 2px;">' . e($location['name']) . ': ' . number_format($location['quantity'], 0) . '</span>';
                        }
                    } else {
                        $stockAlertsHtml .= '<span class="text-muted">Sin stock</span>';
                    }
                    $stockAlertsHtml .= '</td>';
                    $stockAlertsHtml .= '</tr>';
                }
                
                $stockAlertsHtml .= '</tbody></table>';
                $stockAlertsHtml .= '</div>';
                
                \Log::info('HTML de alertas generado', ['length' => strlen($stockAlertsHtml)]);
            }
        }

        // Calcular estadísticas de antigüedad para solicitudes generales pendientes
        $generalRequestsAgeStats = [
            'average_days' => 0,
            'max_days' => 0,
        ];
        
        // Solo calcular si NO es responsable de compras (o si es personal/responsable área)
        if (!$isResponsableCompras || $isPersonal || $isResponsableArea) {
            // Incluir solicitudes con estado 'creada' o 'pendiente_analisis' como pendientes
            $generalRequestsPendingQuery = GeneralRequest::whereIn('status', ['creada', 'pendiente_analisis']);
            if ($isPersonal) {
                $generalRequestsPendingQuery->where('created_by', $user->id);
            } elseif ($isResponsableArea) {
                // Responsable de área: ver solicitudes de su área Y las que él solicita
                $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('id');
                $generalRequestsPendingQuery->where(function($query) use ($user, $userAreas) {
                    $query->where('created_by', $user->id);
                    if ($userAreas->isNotEmpty()) {
                        $query->orWhereIn('area_id', $userAreas);
                    }
                });
            } elseif ($isResponsableCompras) {
                // Responsable de compras: solo sus propias solicitudes generales
                $generalRequestsPendingQuery->where('created_by', $user->id);
            }
            
            $generalRequestsPending = $generalRequestsPendingQuery->get();
            $hasPendingRequests = $generalRequestsPending->count() > 0;
            $generalRequestsAgeStats = [
                'average_days' => $hasPendingRequests
                    ? round($generalRequestsPending->avg(function($req) { return floor($req->created_at->diffInDays(now())); }))
                    : 0,
                'max_days' => $hasPendingRequests
                    ? (int) floor($generalRequestsPending->max(function($req) { return $req->created_at->diffInDays(now()); }))
                    : 0,
                'has_pending' => $hasPendingRequests,
            ];
        }

        // Calcular estadísticas de antigüedad para solicitudes de compra pendientes
        $purchaseRequestsPendingQuery = PurchaseRequest::where('status', 'Pendiente');
        if ($isResponsableArea) {
            // Responsable de área: solo sus propias solicitudes de compra
            $purchaseRequestsPendingQuery->where('requesting_user_id', $user->id);
        } elseif ($isPersonal) {
            // Personal: solo sus propias solicitudes de compra
            $purchaseRequestsPendingQuery->where('requesting_user_id', $user->id);
        } elseif ($isResponsableCompras) {
            // Responsable de compras: ver TODAS las solicitudes de compra pendientes
            // No filtrar por usuario
        }
        // Para otros roles (admin, apoderado, etc.): ver todas
        
        $purchaseRequestsPending = $purchaseRequestsPendingQuery->get();
        $hasPendingPurchaseRequests = $purchaseRequestsPending->count() > 0;
        $purchaseRequestsAgeStats = [
            'average_days' => $hasPendingPurchaseRequests
                ? round($purchaseRequestsPending->avg(function($req) { 
                    $date = $req->created_at ?? $req->request_date;
                    return floor($date->diffInDays(now())); 
                }))
                : 0,
            'max_days' => $hasPendingPurchaseRequests
                ? (int) floor($purchaseRequestsPending->max(function($req) { 
                    $date = $req->created_at ?? $req->request_date;
                    return $date->diffInDays(now()); 
                }))
                : 0,
            'has_pending' => $hasPendingPurchaseRequests,
        ];

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
            'isResponsableCompras',
            'isAdminInstitucion',
            'isApoderado',
            'isRepresentanteLegal',
            'superiorApprovedPurchaseRequestsCount',
            'purchaseOrdersPendingPaymentAfterConformeCount',
            'pendingApprovalRequests',
            'stockAlerts',
            'stockAlertsHtml',
            'user',
            'generalRequestsAgeStats',
            'purchaseRequestsAgeStats'
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

                foreach ($this->purchaseOrdersForProcessFlow($purchaseRequest->id) as $purchaseOrder) {
                    if (! in_array($purchaseOrder->id, array_column($flow['purchase_orders'], 'id'))) {
                        $flow['purchase_orders'][] = $purchaseOrder;
                    }
                }
            }

            // Agregar TODOS los flujos, incluso los incompletos
            // Esto incluye solicitudes generales sin solicitudes de compra
            $flows[] = $flow;
        }

        return $this->mergeStandalonePurchaseRequestFlows($flows, null);
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

                foreach ($this->purchaseOrdersForProcessFlow($purchaseRequest->id, $user->id) as $purchaseOrder) {
                    if (! in_array($purchaseOrder->id, array_column($flow['purchase_orders'], 'id'))) {
                        $flow['purchase_orders'][] = $purchaseOrder;
                    }
                }
            }

            $flows[] = $flow;
        }

        return $this->mergeStandalonePurchaseRequestFlows($flows, function ($query) use ($user) {
            $query->where('requesting_user_id', $user->id);
        }, $user->id);
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

        return $this->mergeStandalonePurchaseRequestFlows($flows, function ($query) use ($user, $userAreas) {
            $query->where(function ($q) use ($user, $userAreas) {
                $q->where('requesting_user_id', $user->id);
                if ($userAreas->isNotEmpty()) {
                    $q->orWhereIn('responsibility_area_id', $userAreas);
                }
            });
        }, null);
    }

    /**
     * Relaciones eager-load para órdenes de compra en la línea de tiempo del dashboard.
     *
     * @return array<string, mixed>
     */
    private function purchaseOrdersTimelineWith(): array
    {
        return [
            'supplier',
            'details',
            'paymentOrders' => function ($query) {
                $query->with('user')->orderBy('created_at', 'desc');
            },
            'receptions' => function ($query) {
                $query->with([
                    'user',
                    'devolutions' => function ($q) {
                        $q->with('user')->orderBy('created_at', 'desc');
                    },
                    'deliveries' => function ($q) {
                        $q->with(['generalRequest', 'deliveredBy', 'receivedBy', 'details.product'])
                            ->orderBy('created_at', 'desc');
                    },
                ])->orderBy('created_at', 'desc');
            },
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\PurchaseOrder>
     */
    private function purchaseOrdersForProcessFlow(int $purchaseRequestId, ?int $personalDeliveriesReceivedByUserId = null)
    {
        if ($personalDeliveriesReceivedByUserId !== null) {
            return PurchaseOrder::where('purchase_request_id', $purchaseRequestId)
                ->with([
                    'supplier',
                    'details',
                    'paymentOrders' => function ($query) {
                        $query->with('user')->orderBy('created_at', 'desc');
                    },
                    'receptions' => function ($query) use ($personalDeliveriesReceivedByUserId) {
                        $query->with([
                            'user',
                            'devolutions' => function ($q) {
                                $q->with('user')->orderBy('created_at', 'desc');
                            },
                            'deliveries' => function ($q) use ($personalDeliveriesReceivedByUserId) {
                                $q->where('received_by', $personalDeliveriesReceivedByUserId)
                                    ->with(['generalRequest', 'deliveredBy', 'receivedBy', 'details.product'])
                                    ->orderBy('created_at', 'desc');
                            },
                        ])->orderBy('created_at', 'desc');
                    },
                ])
                ->get();
        }

        return PurchaseOrder::where('purchase_request_id', $purchaseRequestId)
            ->with($this->purchaseOrdersTimelineWith())
            ->get();
    }

    /**
     * Añade flujos de solicitudes de compra creadas sin solicitud general (converted_from_general_request_id nulo).
     *
     * @param  array<int, array<string, mixed>>  $flows
     * @return array<int, array<string, mixed>>
     */
    private function mergeStandalonePurchaseRequestFlows(array $flows, ?callable $purchaseRequestScope = null, ?int $personalDeliveriesReceivedByUserId = null): array
    {
        $idsAlreadyShown = collect($flows)
            ->flatMap(function ($f) {
                return collect($f['purchase_requests'] ?? [])->pluck('id');
            })
            ->filter()
            ->unique()
            ->values();

        $query = PurchaseRequest::query()
            ->whereNull('converted_from_general_request_id')
            ->with([
                'selectedMarketRate.supplier',
                'details.product',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(20);

        if ($purchaseRequestScope) {
            $purchaseRequestScope($query);
        }

        if ($idsAlreadyShown->isNotEmpty()) {
            $query->whereNotIn('id', $idsAlreadyShown->all());
        }

        foreach ($query->get() as $purchaseRequest) {
            $purchaseOrders = [];
            foreach ($this->purchaseOrdersForProcessFlow($purchaseRequest->id, $personalDeliveriesReceivedByUserId) as $purchaseOrder) {
                $purchaseOrders[] = $purchaseOrder;
            }

            $flows[] = [
                'general_request' => null,
                'purchase_requests' => [$purchaseRequest],
                'purchase_orders' => $purchaseOrders,
                'purchase_request_only' => true,
            ];
        }

        return $flows;
    }

    /**
     * Obtener el mapeo de áreas de responsabilidad a ubicaciones
     */
    private function getAreaLocationMap()
    {
        return [
            'Informática' => 'Informática',
            'Mantenimiento' => 'Mantenimiento',
            'Salud' => 'Insumos de Salud',
            'Insumos Generales' => 'Insumos Generales',
        ];
    }

    /**
     * Obtener alertas de stock mínimo para un responsable de área
     */
    private function getStockMinimumAlerts($user)
    {
        // Obtener las áreas de responsabilidad del usuario
        $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)
            ->where('is_active', true)
            ->pluck('name');
        
        if ($userAreas->isEmpty()) {
            return collect();
        }

        // Mapear nombres de áreas a nombres de ubicaciones
        $areaLocationMap = $this->getAreaLocationMap();
        $locationNames = [];
        
        foreach ($userAreas as $areaName) {
            if (isset($areaLocationMap[$areaName])) {
                $locationNames[] = $areaLocationMap[$areaName];
            } else {
                // Si no hay mapeo, usar el nombre del área directamente
                $locationNames[] = $areaName;
            }
        }

        // Obtener IDs de ubicaciones
        $locationIds = Location::whereIn('name', $locationNames)->pluck('id');
        
        if ($locationIds->isEmpty()) {
            return collect();
        }

        // Mapeo de áreas a categorías permitidas (igual que en ProductCrudController)
        $areaCategoryMap = [
            'Informática' => ['Equipos Informáticos', 'Software'],
            'Salud' => ['Material Médico', 'Reactivos'],
            'Insumos de Salud' => ['Material Médico', 'Reactivos'],
            'Mantenimiento' => ['Herramientas', 'Repuestos', 'Limpieza'],
            'Insumos Generales' => ['Material de Oficina', 'Limpieza', 'Insumos Generales'],
        ];
        
        // Obtener todas las categorías permitidas para las áreas del usuario
        $allowedCategoryNames = collect();
        foreach ($userAreas as $areaName) {
            if (isset($areaCategoryMap[$areaName])) {
                $allowedCategoryNames = $allowedCategoryNames->merge($areaCategoryMap[$areaName]);
            }
        }
        
        // Obtener los IDs de las categorías permitidas
        $allowedCategoryIds = \App\Models\Category::whereIn('name', $allowedCategoryNames->unique())
            ->pluck('id');
        
        // Construir la consulta de productos
        $productsQuery = Product::where('minimum_stock', '>', 0);
        
        // Filtrar por categorías si hay categorías permitidas
        if ($allowedCategoryIds->isNotEmpty()) {
            $productsQuery->whereIn('category_id', $allowedCategoryIds);
        } else {
            // Si no hay categorías relacionadas, no mostrar ningún producto
            return collect();
        }
        
        // Obtener productos con sus niveles de stock filtrados por ubicaciones
        $products = $productsQuery->with(['stockLevels' => function($query) use ($locationIds) {
                $query->whereIn('location_id', $locationIds)
                    ->with('location');
            }])
            ->get();

        \Log::info('Productos encontrados con stock mínimo', [
            'total_products' => $products->count(),
            'location_ids' => $locationIds->toArray()
        ]);

        $alerts = collect();

        foreach ($products as $product) {
            // Calcular el stock total en las ubicaciones del responsable
            $totalStock = $product->stockLevels->sum('quantity');
            
            \Log::info('Verificando producto', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'total_stock' => $totalStock,
                'minimum_stock' => $product->minimum_stock,
                'stock_levels_count' => $product->stockLevels->count()
            ]);
            
            // Verificar si el stock está por debajo del mínimo (usando comparación numérica)
            if ((float)$totalStock < (float)$product->minimum_stock) {
                // Obtener las ubicaciones donde está el stock
                $locations = $product->stockLevels->map(function($stockLevel) {
                    return [
                        'name' => $stockLevel->location->name ?? 'N/A',
                        'quantity' => $stockLevel->quantity
                    ];
                });

                $alerts->push([
                    'product' => $product,
                    'current_stock' => $totalStock,
                    'minimum_stock' => $product->minimum_stock,
                    'deficit' => $product->minimum_stock - $totalStock,
                    'locations' => $locations,
                ]);
                
                \Log::info('Alerta agregada para producto', [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'current_stock' => $totalStock,
                    'minimum_stock' => $product->minimum_stock
                ]);
            }
        }

        \Log::info('Total de alertas generadas', ['count' => $alerts->count()]);

        // Ordenar por déficit (mayor déficit primero)
        return $alerts->sortByDesc('deficit')->values();
    }
}

