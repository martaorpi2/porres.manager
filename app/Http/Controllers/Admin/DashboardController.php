<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Devolution;
use App\Models\GeneralRequest;
use App\Models\Location;
use App\Models\PaymentOrder;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseAuthorizationLimit;
use App\Models\PurchaseRequest;
use App\Models\Reception;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseRequestNotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with process flow visualization.
     */
    public function index()
    {
        $user = backpack_user();

        // Validar que el usuario esté autenticado
        if (! $user) {
            abort(403, 'Usuario no autenticado');
        }

        $isPersonal = $user->hasRole('role_personal');
        $isResponsableArea = $user->hasResponsableAreaOrInstituteAuthorityRole();
        $isAutoridadInstituto = $user->hasInstituteAuthorityRole();
        $isResponsableCompras = $user->effectivelyHasResponsableComprasRole();
        $isAdminInstitucion = $user->hasRole('role_admin_institucion', 'backpack');
        $isApoderado = $user->hasRole('role_apoderado', 'backpack');
        $isRepresentanteLegal = $user->hasRole('role_representante_legal', 'backpack');

        // Estadísticas generales
        if ($isPersonal) {
            // Para role_personal: solicitudes que creó o en las que figura como solicitante nominado
            $userRequests = GeneralRequest::where(function ($q) use ($user) {
                $q->where('created_by', $user->id)->orWhere('requesting_user_id', $user->id);
            });

            $stats = [
                'general_requests' => $userRequests->count(),
                'general_requests_pending' => (clone $userRequests)->where('status', 'creada')->count(),
                'general_requests_delivered' => (clone $userRequests)
                    ->whereIn('status', ['entregada_parcialmente', 'entregada_totalmente'])
                    ->count(),
                // Aprobadas: cualquier estado excepto 'creada' (a menos que esté convertida), 'archivada' y 'entregada_parcialmente'/'entregada_totalmente'
                // Las convertidas a compra con estado entregada NO cuentan como aprobadas
                // IMPORTANTE: Solo del usuario logueado (created_by = $user->id)
                'general_requests_approved' => (clone $userRequests)
                    ->whereNotIn('status', ['entregada_parcialmente', 'entregada_totalmente'])
                    ->where(function ($query) {
                        $query->where(function ($q) {
                            // Estados que no son 'creada' ni 'archivada'
                            $q->where('status', '!=', 'creada')
                                ->where('status', '!=', 'archivada');
                        })->orWhere(function ($q) {
                            // Estado 'creada' pero convertida a compra
                            $q->where('status', 'creada')
                                ->where('is_converted', true);
                        });
                    })
                    ->count(),
                // Entregadas: las que tienen status = 'entregada_totalmente' o 'entregada_parcialmente'
                'general_requests_entregada' => (clone $userRequests)
                    ->whereIn('status', ['entregada_parcialmente', 'entregada_totalmente'])
                    ->count(),
                // Rechazadas: archivadas (status = 'archivada')
                'general_requests_rejected' => (clone $userRequests)
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
            $userRequestsQuery = GeneralRequest::where(function ($query) use ($user, $userAreas) {
                $query->where('created_by', $user->id);
                if ($userAreas->isNotEmpty()) {
                    $query->orWhereIn('area_id', $userAreas);
                }
            });

            // Filtrar entregas relacionadas con solicitudes generales de su área
            $deliveriesQuery = Delivery::whereHas('generalRequest', function ($query) use ($user, $userAreas) {
                $query->where(function ($q) use ($user, $userAreas) {
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
                    ->where(function ($q) {
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
                    ->where(function ($q) {
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
        $generalRequestsQuery = GeneralRequest::with(['createdBy', 'requestingUser', 'area', 'details.product', 'purchaseRequests']);

        if ($isPersonal) {
            $generalRequestsQuery->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)->orWhere('requesting_user_id', $user->id);
            });
        } elseif ($isResponsableArea) {
            // Responsable de área: ver solicitudes de su área Y las que él solicita
            $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('id');
            $generalRequestsQuery->where(function ($query) use ($user, $userAreas) {
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
            $purchaseRequestsQuery->where(function ($q) use ($user) {
                $q->where('requesting_user_id', $user->id);
                if (Schema::hasColumn('purchase_requests', 'created_by')) {
                    $q->orWhere('created_by', $user->id);
                }
            });
        } elseif ($isPersonal) {
            // Para role_personal, no mostrar solicitudes de compra
            $purchaseRequests = collect();
        }

        if (! $isPersonal) {
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

            $pendingApprovalRequests = PurchaseRequest::with(['requestingUser', 'responsibilityArea', 'details.product', 'directPurchaseSupplier', 'marketRates'])
                ->whereIn('status', ['Pendiente', 'En Proceso'])
                ->where(function ($query) use ($userLimit, $comprasLimit) {
                    // Solicitudes normales que requieren aprobación de administrador
                    $query->where(function ($q) use ($userLimit, $comprasLimit) {
                        $q->where('requires_admin_approval', true)
                            ->where('total_amount', '<=', $userLimit)
                            ->where('total_amount', '>', $comprasLimit);
                    })
                    // O compras directas pendientes de autorización
                        ->orWhere(function ($q) use ($userLimit) {
                            $q->where('is_direct_purchase', true)
                                ->where('direct_purchase_authorization_requested', true)
                                ->whereNull('direct_purchase_authorized_by')
                                ->where(function ($subQ) {
                                    $subQ->where('direct_purchase_authorization_rejected', false)
                                        ->orWhereNull('direct_purchase_authorization_rejected');
                                })
                                ->where('total_amount', '<=', $userLimit);
                        });
                })
                ->orderBy('created_at', 'asc') // Ordenar por más antiguas primero
                ->limit(12)
                ->get()
                // Sin cotización elegida aún no corresponde aviso/listado de aprobación (salvo compra directa)
                ->filter(function (PurchaseRequest $pr) {
                    if ($pr->is_direct_purchase) {
                        return true;
                    }

                    return $pr->hasQuotationSelectionResolved();
                })
                ->values();
        }

        // Cotizaciones cargadas sin asignación completa por producto (compras o administradora sin sector de compras)
        $purchaseRequestsAwaitingQuoteSelectionCount = 0;
        if ($isResponsableCompras || $isAdminInstitucion) {
            $purchaseRequestsAwaitingQuoteSelectionCount = PurchaseRequest::purchaseRequestsNeedingQuotationAssignmentCount();
        }

        // Compras o administradora (sin usuario de compras): aprobadas por nivel superior, pendientes de OC
        $superiorApprovedPurchaseRequests = collect();
        $superiorApprovedPurchaseRequestsCount = 0;
        $actsAsComprasMailbox = $user->effectivelyHasResponsableComprasRole();
        if ($actsAsComprasMailbox) {
            $superiorApprovedPurchaseRequests = $this->purchaseRequestsForInboxList(
                PurchaseRequest::queryApprovedBySuperiorWithoutPurchaseOrder($user->id)
            );
            $superiorApprovedPurchaseRequestsCount = $superiorApprovedPurchaseRequests->count();
        }

        // Administradora del instituto: OC sin orden de pago asociada (la OP no depende de la recepción)
        $purchaseOrdersPendingPaymentAfterConformeCount = 0;
        if ($isAdminInstitucion) {
            $purchaseOrdersPendingPaymentAfterConformeCount = PurchaseOrder::query()
                ->whereDoesntHave('paymentOrders')
                ->count();
        }

        // Obtener órdenes de compra recientes (solo si no es role_personal ni role_responsable_area)
        $purchaseOrders = collect();
        if (! $isPersonal && ! $isResponsableArea) {
            $purchaseOrders = PurchaseOrder::with(['supplier', 'user', 'details', 'receptions'])
                ->orderBy('created_at', 'desc')
                ->limit(12)
                ->get();
        }

        // Obtener órdenes de pago recientes (solo si no es role_personal ni role_responsable_area)
        $paymentOrders = collect();
        if (! $isPersonal && ! $isResponsableArea) {
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

        if (! $isPersonal) {
            $receptions = $receptionsQuery
                ->orderBy('created_at', 'desc')
                ->limit(12)
                ->get();
        }

        // Obtener devoluciones recientes (solo si no es role_personal ni role_responsable_area)
        $devolutions = collect();
        if (! $isPersonal && ! $isResponsableArea) {
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
            $deliveriesQuery->whereHas('generalRequest', function ($query) use ($user, $userAreas) {
                $query->where(function ($q) use ($user, $userAreas) {
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
        if (! $isPersonal) {
            $suppliersWithRatings = Supplier::with('ratings')
                ->get()
                ->filter(function ($supplier) {
                    return $supplier->ratings->count() > 0;
                })
                ->map(function ($supplier) {
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
                $stockAlertsHtml = '<div style="text-align: left; max-height: 400px; overflow-y: auto;">';
                $stockAlertsHtml .= '<div class="alert alert-warning" style="margin-bottom: 15px;">';
                $stockAlertsHtml .= '<i class="la la-info-circle"></i> <strong>Atención:</strong> Tienes <strong>'.$stockAlerts->count().'</strong> producto(s) con stock por debajo del mínimo requerido.';
                $stockAlertsHtml .= '</div>';
                $stockAlertsHtml .= '<table class="table table-sm table-hover" style="font-size: 0.9em;">';
                $stockAlertsHtml .= '<thead style="background-color: #f8d7da;"><tr>';
                $stockAlertsHtml .= '<th>Producto</th><th>Stock Actual</th><th>Stock Mín.</th><th>Déficit</th><th>Ubicaciones</th>';
                $stockAlertsHtml .= '</tr></thead><tbody>';

                foreach ($stockAlerts as $alert) {
                    $stockAlertsHtml .= '<tr>';
                    $stockAlertsHtml .= '<td><strong>'.e($alert['product']->name).'</strong>';
                    if ($alert['product']->description) {
                        $stockAlertsHtml .= '<br><small class="text-muted">'.e(\Str::limit($alert['product']->description, 40)).'</small>';
                    }
                    $stockAlertsHtml .= '</td>';
                    $stockAlertsHtml .= '<td><span class="badge bg-danger">'.number_format($alert['current_stock'], 0).'</span></td>';
                    $stockAlertsHtml .= '<td><span class="badge bg-warning text-dark">'.number_format($alert['minimum_stock'], 0).'</span></td>';
                    $stockAlertsHtml .= '<td><span class="badge bg-danger">'.number_format($alert['deficit'], 0).' '.e($alert['product']->unit_measurement ?? 'unidades').'</span></td>';
                    $stockAlertsHtml .= '<td>';
                    if ($alert['locations']->isNotEmpty()) {
                        foreach ($alert['locations'] as $location) {
                            $stockAlertsHtml .= '<span class="badge bg-secondary" style="margin: 2px;">'.e($location['name']).': '.number_format($location['quantity'], 0).'</span>';
                        }
                    } else {
                        $stockAlertsHtml .= '<span class="text-muted">Sin stock</span>';
                    }
                    $stockAlertsHtml .= '</td>';
                    $stockAlertsHtml .= '</tr>';
                }

                $stockAlertsHtml .= '</tbody></table>';
                $stockAlertsHtml .= '</div>';
            }
        }

        // Calcular estadísticas de antigüedad para solicitudes generales pendientes
        $generalRequestsAgeStats = [
            'average_days' => 0,
            'max_days' => 0,
        ];

        // Solo calcular si NO es responsable de compras (o si es personal/responsable área)
        if (! $isResponsableCompras || $isPersonal || $isResponsableArea) {
            // Incluir solicitudes con estado 'creada' o 'pendiente_analisis' como pendientes
            $generalRequestsPendingQuery = GeneralRequest::whereIn('status', ['creada', 'pendiente_analisis']);
            if ($isPersonal) {
                $generalRequestsPendingQuery->where(function ($q) use ($user) {
                    $q->where('created_by', $user->id)->orWhere('requesting_user_id', $user->id);
                });
            } elseif ($isResponsableArea) {
                // Responsable de área: ver solicitudes de su área Y las que él solicita
                $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('id');
                $generalRequestsPendingQuery->where(function ($query) use ($user, $userAreas) {
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
                    ? round($generalRequestsPending->avg(function ($req) {
                        return floor($req->created_at->diffInDays(now()));
                    }))
                    : 0,
                'max_days' => $hasPendingRequests
                    ? (int) floor($generalRequestsPending->max(function ($req) {
                        return $req->created_at->diffInDays(now());
                    }))
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
                ? round($purchaseRequestsPending->avg(function ($req) {
                    $date = $req->created_at ?? $req->request_date;

                    return floor($date->diffInDays(now()));
                }))
                : 0,
            'max_days' => $hasPendingPurchaseRequests
                ? (int) floor($purchaseRequestsPending->max(function ($req) {
                    $date = $req->created_at ?? $req->request_date;

                    return $date->diffInDays(now());
                }))
                : 0,
            'has_pending' => $hasPendingPurchaseRequests,
        ];

        $adminInstitucionInbox = $isAdminInstitucion
            ? $this->buildAdminInstitucionInbox(
                $user,
                $pendingApprovalRequests,
                $purchaseOrdersPendingPaymentAfterConformeCount,
                $purchaseRequestsAwaitingQuoteSelectionCount,
                $superiorApprovedPurchaseRequests
            )
            : null;

        $superiorAuthorityInbox = null;
        if (($isRepresentanteLegal || $isApoderado) && ! $isAdminInstitucion) {
            $superiorAuthorityInbox = $this->buildSuperiorAuthorityInbox(
                $user,
                $pendingApprovalRequests,
                $isRepresentanteLegal
            );
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
            'isAutoridadInstituto',
            'isResponsableCompras',
            'isAdminInstitucion',
            'isApoderado',
            'isRepresentanteLegal',
            'superiorApprovedPurchaseRequestsCount',
            'purchaseRequestsAwaitingQuoteSelectionCount',
            'purchaseOrdersPendingPaymentAfterConformeCount',
            'pendingApprovalRequests',
            'stockAlerts',
            'stockAlertsHtml',
            'user',
            'generalRequestsAgeStats',
            'purchaseRequestsAgeStats',
            'adminInstitucionInbox',
            'superiorAuthorityInbox',
        ));
    }

    /**
     * Solicitudes de compra con datos para el listado de tarjetas de la bandeja.
     *
     * @return \Illuminate\Support\Collection<int, PurchaseRequest>
     */
    private function purchaseRequestsForInboxList(\Illuminate\Database\Eloquent\Builder $query): Collection
    {
        return $query
            ->with(['requestingUser', 'responsibilityArea'])
            ->withCount('details')
            ->orderByDesc('updated_at')
            ->get();
    }

    /**
     * Accesos directos y contadores para la administradora del instituto.
     *
     * @return array{acts_as_compras: bool, total_actionable: int, items: array<int, array<string, mixed>>}
     */
    private function buildAdminInstitucionInbox(
        User $user,
        Collection $pendingApprovalRequests,
        int $purchaseOrdersPendingPaymentCount,
        int $purchaseRequestsAwaitingQuoteSelectionCount,
        Collection $superiorApprovedPurchaseRequests
    ): array {
        $actsAsCompras = ! User::backpackHasAnyUserWithRole('role_responsable_compras');
        $items = [];

        $approvalCount = $pendingApprovalRequests->count();
        $items[] = [
            'key' => 'approvals',
            'title' => 'Aprobar solicitudes de compra',
            'description' => 'Montos dentro de su límite de autorización y compras directas pendientes.',
            'count' => $approvalCount,
            'url' => backpack_url('purchase-request'),
            'icon' => 'la la-gavel',
            'variant' => $approvalCount > 0 ? 'danger' : 'secondary',
            'purchase_requests' => $pendingApprovalRequests,
        ];

        $quoteAssignmentRequests = PurchaseRequest::purchaseRequestsNeedingQuotationAssignment();
        $quoteAssignmentRequests->load(['requestingUser', 'responsibilityArea']);
        $quoteAssignmentRequests->loadCount('details');
        $quoteAssignmentCount = $quoteAssignmentRequests->count();
        $superiorApprovedCount = $superiorApprovedPurchaseRequests->count();
        if ($actsAsCompras && $superiorApprovedCount > 0) {
            $items[] = [
                'key' => 'superior_approved_oc',
                'title' => 'Generar orden de compra',
                'description' => 'Solicitudes ya aprobadas por representante legal o apoderado; sin OC generada.',
                'count' => $superiorApprovedCount,
                'url' => backpack_url('purchase-request').'?aprobadas_por_superior=1',
                'icon' => 'la la-shopping-cart',
                'variant' => 'success',
                'purchase_requests' => $superiorApprovedPurchaseRequests,
            ];
        }

        $items[] = [
            'key' => 'quote_selection',
            'title' => 'Cotizaciones y asignación por producto',
            'description' => $actsAsCompras
                ? 'Sin usuario de compras operativo: elegir cotización y asignar cada producto.'
                : 'Cotizaciones cargadas que aún no tienen asignación completa por producto.',
            'count' => $quoteAssignmentCount,
            'url' => backpack_url('purchase-request').'?pendiente_seleccion_cotizacion=1',
            'icon' => 'la la-balance-scale',
            'variant' => $quoteAssignmentCount > 0 ? 'warning' : 'secondary',
            'purchase_requests' => $quoteAssignmentRequests,
        ];

        $enProcesoRequests = $this->purchaseRequestsForInboxList(
            PurchaseRequest::query()->where('status', 'En Proceso')
        );
        $enProcesoCount = $enProcesoRequests->count();
        $items[] = [
            'key' => 'en_proceso',
            'title' => 'Circuito de compras en curso',
            'description' => $actsAsCompras
                ? 'Cotizaciones, seguimiento y preparación de órdenes de compra.'
                : 'Solicitudes notificadas al sector de compras.',
            'count' => $enProcesoCount,
            'url' => backpack_url('purchase-request').'?en_proceso=1',
            'icon' => 'la la-cogs',
            'variant' => $enProcesoCount > 0 ? 'primary' : 'secondary',
            'purchase_requests' => $enProcesoRequests,
        ];

        $pendientesRequests = $this->purchaseRequestsForInboxList(
            PurchaseRequest::query()->where('status', 'Pendiente')
        );
        $pendientesCount = $pendientesRequests->count();
        $items[] = [
            'key' => 'pendientes',
            'title' => 'Solicitudes pendientes',
            'description' => 'Revisión inicial, cotizaciones o envío a aprobación superior.',
            'count' => $pendientesCount,
            'url' => backpack_url('purchase-request').'?pendientes=1',
            'icon' => 'la la-clock',
            'variant' => $pendientesCount > 0 ? 'info' : 'secondary',
            'purchase_requests' => $pendientesRequests,
        ];

        $items[] = [
            'key' => 'payment_orders',
            'title' => 'Generar orden de pago',
            'description' => 'Órdenes de compra sin orden de pago asociada.',
            'count' => $purchaseOrdersPendingPaymentCount,
            'url' => $purchaseOrdersPendingPaymentCount > 0
                ? backpack_url('dashboard').'#purchase-orders-process-section'
                : backpack_url('purchase-order'),
            'icon' => 'la la-money-bill-wave',
            'variant' => $purchaseOrdersPendingPaymentCount > 0 ? 'success' : 'secondary',
        ];

        $items = array_values(array_filter(
            $items,
            fn (array $item) => (int) ($item['count'] ?? 0) > 0
        ));

        usort($items, fn (array $a, array $b) => ($b['count'] ?? 0) <=> ($a['count'] ?? 0));

        $totalActionable = collect($items)->sum(fn (array $item) => (int) ($item['count'] ?? 0));

        return [
            'acts_as_compras' => $actsAsCompras,
            'total_actionable' => $totalActionable,
            'items' => $items,
        ];
    }

    /**
     * Bandeja para apoderado / representante legal: aprobaciones y escalamientos desde administración.
     *
     * @return array{profile_label: string, total_actionable: int, items: array<int, array<string, mixed>>}
     */
    private function buildSuperiorAuthorityInbox(
        User $user,
        Collection $pendingApprovalRequests,
        bool $isRepresentanteLegal
    ): array {
        $profileLabel = $isRepresentanteLegal ? 'representante legal' : 'apoderado';
        $escalationRequests = $this->purchaseRequestsAwaitingSuperiorEscalationForUser($user);
        $pendingIds = $pendingApprovalRequests->pluck('id');
        $escalationOnly = $escalationRequests
            ->filter(fn (PurchaseRequest $pr) => ! $pendingIds->contains($pr->id))
            ->values();

        $items = [];

        $approvalCount = $pendingApprovalRequests->count();
        if ($approvalCount > 0) {
            $items[] = [
                'key' => 'approvals',
                'title' => 'Aprobar solicitudes de compra',
                'description' => 'Montos dentro de su límite de autorización y compras directas pendientes de su decisión.',
                'count' => $approvalCount,
                'url' => backpack_url('purchase-request'),
                'icon' => 'la la-gavel',
                'variant' => 'danger',
                'purchase_requests' => $pendingApprovalRequests,
            ];
        }

        $escalationCount = $escalationOnly->count();
        if ($escalationCount > 0) {
            $items[] = [
                'key' => 'superior_escalation',
                'title' => 'Escalamiento desde administración',
                'description' => 'La administradora solicitó su intervención por monto; revise cotización y autorice por ítem en cada solicitud.',
                'count' => $escalationCount,
                'url' => backpack_url('purchase-request'),
                'icon' => 'la la-level-up',
                'variant' => 'warning',
                'purchase_requests' => $escalationOnly,
            ];
        }

        $items = array_values(array_filter(
            $items,
            fn (array $item) => (int) ($item['count'] ?? 0) > 0
        ));

        usort($items, fn (array $a, array $b) => ($b['count'] ?? 0) <=> ($a['count'] ?? 0));

        $totalActionable = collect($items)->sum(fn (array $item) => (int) ($item['count'] ?? 0));

        return [
            'profile_label' => $profileLabel,
            'total_actionable' => $totalActionable,
            'items' => $items,
        ];
    }

    /**
     * Solicitudes con escalamiento superior pendiente dirigidas al rol del usuario (apoderado / representante legal).
     *
     * @return Collection<int, PurchaseRequest>
     */
    private function purchaseRequestsAwaitingSuperiorEscalationForUser(User $user): Collection
    {
        $targetRole = null;
        if ($user->hasRole('role_representante_legal', 'backpack')) {
            $targetRole = 'role_representante_legal';
        } elseif ($user->hasRole('role_apoderado', 'backpack')) {
            $targetRole = 'role_apoderado';
        }

        if ($targetRole === null) {
            return collect();
        }

        $userLimit = (float) PurchaseAuthorizationLimit::getLimitForRole($targetRole);
        if ($userLimit <= 0) {
            return collect();
        }

        if (! Schema::hasColumn((new PurchaseRequest)->getTable(), 'superior_quotation_escalation_pending_at')) {
            return collect();
        }

        $candidates = $this->purchaseRequestsForInboxList(
            PurchaseRequest::query()->whereNotNull('superior_quotation_escalation_pending_at')
        );

        return $candidates->filter(function (PurchaseRequest $pr) use ($targetRole, $userLimit) {
            if ($pr->wasApprovedBySuperiorAuthority()) {
                return false;
            }

            if ($pr->is_direct_purchase) {
                if (! $pr->direct_purchase_authorization_requested || $pr->direct_purchase_authorized_by) {
                    return false;
                }
            } elseif (! $pr->hasQuotationSelectionResolved()) {
                return false;
            }

            $effectiveTotal = $pr->effectiveTotalForAuthorizationLimits();
            if ($effectiveTotal > $userLimit) {
                return false;
            }

            $targetRoles = PurchaseRequestNotificationService::superiorApproverRoleNamesForAmountFromAdministrator($effectiveTotal);

            return in_array($targetRole, $targetRoles, true);
        })->values();
    }

    /**
     * Obtener el flujo completo de procesos relacionando todas las entidades
     */
    private function getProcessFlows()
    {
        $flows = [];

        // Obtener solicitudes generales con sus procesos relacionados
        $generalRequests = GeneralRequest::with([
            'purchaseRequests' => function ($query) {
                $query->with([
                    'selectedMarketRate.supplier',
                    'details.product',
                ]);
            },
            'createdBy',
            'area',
            'details.product',
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

        // Solicitudes generales del usuario (creadas por él o nominado como solicitante)
        $generalRequests = GeneralRequest::where(function ($q) use ($user) {
            $q->where('created_by', $user->id)->orWhere('requesting_user_id', $user->id);
        })
            ->with([
                'purchaseRequests' => function ($query) {
                    $query->with([
                        'selectedMarketRate.supplier',
                        'details.product',
                    ]);
                },
                'createdBy',
                'requestingUser',
                'area',
                'details.product',
                'deliveries' => function ($query) use ($user) {
                    $query->where('received_by', $user->id)
                        ->with(['deliveredBy', 'receivedBy', 'details.product']);
                },
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
            $query->where(function ($q) use ($user) {
                $q->where('requesting_user_id', $user->id);
                if (Schema::hasColumn('purchase_requests', 'created_by')) {
                    $q->orWhere('created_by', $user->id);
                }
            });
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
        $generalRequests = GeneralRequest::where(function ($query) use ($user, $userAreas) {
            $query->where('created_by', $user->id);
            if ($userAreas->isNotEmpty()) {
                $query->orWhereIn('area_id', $userAreas);
            }
        })
            ->with([
                'purchaseRequests' => function ($query) use ($user) {
                    $query->where(function ($q) use ($user) {
                        $q->where('requesting_user_id', $user->id);
                        if (Schema::hasColumn('purchase_requests', 'created_by')) {
                            $q->orWhere('created_by', $user->id);
                        }
                    })
                        ->with([
                            'selectedMarketRate.supplier',
                            'details.product',
                        ]);
                },
                'createdBy',
                'area',
                'details.product',
                'deliveries' => function ($query) {
                    $query->with(['deliveredBy', 'receivedBy', 'details.product']);
                },
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
                if ($purchaseRequest->isActingAsCreatingUser((int) $user->id)) {
                    $flow['purchase_requests'][] = $purchaseRequest;
                    // No incluir órdenes de compra ni órdenes de pago para este rol
                }
            }

            $flows[] = $flow;
        }

        return $this->mergeStandalonePurchaseRequestFlows($flows, function ($query) use ($user, $userAreas) {
            $query->where(function ($q) use ($user, $userAreas) {
                $q->where('requesting_user_id', $user->id);
                if (Schema::hasColumn('purchase_requests', 'created_by')) {
                    $q->orWhere('created_by', $user->id);
                }
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
        $products = $productsQuery->with(['stockLevels' => function ($query) use ($locationIds) {
            $query->whereIn('location_id', $locationIds)
                ->with('location');
        }])
            ->get();

        $alerts = collect();

        foreach ($products as $product) {
            // Calcular el stock total en las ubicaciones del responsable
            $totalStock = $product->stockLevels->sum('quantity');

            // Verificar si el stock está por debajo del mínimo (usando comparación numérica)
            if ((float) $totalStock < (float) $product->minimum_stock) {
                // Obtener las ubicaciones donde está el stock
                $locations = $product->stockLevels->map(function ($stockLevel) {
                    return [
                        'name' => $stockLevel->location->name ?? 'N/A',
                        'quantity' => $stockLevel->quantity,
                    ];
                });

                $alerts->push([
                    'product' => $product,
                    'current_stock' => $totalStock,
                    'minimum_stock' => $product->minimum_stock,
                    'deficit' => $product->minimum_stock - $totalStock,
                    'locations' => $locations,
                ]);
            }
        }

        // Ordenar por déficit (mayor déficit primero)
        return $alerts->sortByDesc('deficit')->values();
    }
}
