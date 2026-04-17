<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\DeliveryRequest;
use App\Models\Delivery;
use App\Models\DeliveryDetail;
use App\Models\PurchaseRequest;
use App\Models\Reception;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Class DeliveryCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class DeliveryCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     * 
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\Delivery::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/delivery');
        CRUD::setEntityNameStrings('entrega', 'entregas');
    }

    /**
     * Define what happens when the List operation is loaded.
     * 
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::removeButton('show');
        
        // Cargar relaciones para evitar N+1 queries
        CRUD::addClause('with', ['reception', 'generalRequest', 'purchaseRequest', 'receivedBy', 'deliveredBy']);
        
        // Ocultar botones de editar y eliminar para role_admin_institucion
        $user = backpack_user();
        if ($user && $user->hasRole('role_admin_institucion', 'backpack')) {
            CRUD::removeButton('update');
            CRUD::removeButton('delete');
        }
        
        // Si el usuario tiene rol role_personal, solo mostrar entregas donde él es el receptor
        if ($user && $user->hasRole('role_personal')) {
            CRUD::addClause('where', 'received_by', $user->id);
            
            // Ocultar botones de crear, editar y eliminar para role_personal
            CRUD::removeButton('create');
            CRUD::removeButton('update');
            CRUD::removeButton('delete');
        } elseif ($user && $user->hasRole('role_responsable_area')) {
            // role_responsable_area puede ver todas las entregas y crearlas
            // No se aplica filtro adicional
        }
        
        // Si el usuario es role_responsable_compras, usar botón de editar personalizado
        if ($user && $user->hasRole('role_responsable_compras', 'backpack')) {
            CRUD::removeButton('update');
            CRUD::addButton('line', 'edit_delivery', 'view', 'crud::buttons.edit_delivery', 'beginning');
            CRUD::removeButton('delete');
            CRUD::addButton('line', 'delete_delivery', 'view', 'crud::buttons.delete_delivery', 'end');
        }
        
        // Columna para mostrar la solicitud relacionada (General o Purchase)
        CRUD::addColumn([
            'name' => 'request_info',
            'label' => 'Solicitud',
            'type' => 'closure',
            'function' => function($entry) {
                if ($entry->generalRequest) {
                    return '<span class="badge bg-info">SG: ' . e($entry->generalRequest->number) . '</span>';
                } elseif ($entry->purchaseRequest) {
                    return '<span class="badge bg-primary">SC: ' . e($entry->purchaseRequest->request_number) . '</span>';
                }
                return '<span class="badge bg-secondary">Sin solicitud</span>';
            },
            'escaped' => false,
        ]);
        
        // Columna opcional para número de recepción (si existe)
        CRUD::addColumn([
            'name' => 'reception_id',
            'label' => 'Recepción',
            'type' => 'closure',
            'function' => function($entry) {
                if ($entry->reception) {
                    return '<span class="badge bg-success">REC-' . $entry->reception->id . '</span>';
                }
                return '<span class="badge bg-secondary">Sin recepción</span>';
            },
            'escaped' => false,
        ]);
        
        CRUD::column('delivery_date')->label('Fecha');
        
        CRUD::addColumn([
            'name' => 'delivered_by',
            'label' => 'Entregado por',
            'type' => 'select',
            'entity' => 'deliveredBy',
            'attribute' => 'name',
            'model' => 'App\Models\User',
        ]);

        CRUD::addColumn([
            'name' => 'received_by',
            'label' => 'Recibido por',
            'type' => 'select',
            'entity' => 'receivedBy',
            'attribute' => 'name',
            'model' => 'App\Models\User',
        ]);
        
        // Agregar botón PDF en la lista
        CRUD::addColumn([
            'name' => 'pdf_button',
            'label' => 'PDF',
            'type' => 'custom_html',
            'value' => function($entry) {
                return '<a href="' . route('delivery.pdf', $entry->id) . '" class="btn btn-sm" target="_blank" data-toggle="tooltip" title="Descargar Comprobante de Entrega" style="background-color: #800020; border-color: #800020; color: white !important;">
                    <i class="la la-file-pdf" style="color: white !important;"></i> <span style="color: white !important;">PDF</span>
                </a>';
            },
            'escaped' => false,
        ]);
    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        // Si el usuario tiene rol role_personal, no puede crear entregas
        $user = backpack_user();
        if ($user && $user->hasRole('role_personal')) {
            abort(403, 'No tienes permiso para crear entregas. Solo puedes ver las entregas que recibes.');
        }
        // role_responsable_area puede crear entregas, no se bloquea
        
        CRUD::setValidation(DeliveryRequest::class);
        
        // Verificar si viene desde una solicitud general específica
        $generalRequestId = request()->get('general_request_id');
        $generalRequest = null;
        if ($generalRequestId) {
            $generalRequest = \App\Models\GeneralRequest::with(['createdBy', 'requestingUser'])->find($generalRequestId);
        }
        
        // Campo informativo
        $infoMessage = '<div class="alert alert-info">
            <i class="la la-info-circle"></i> <strong>Nota:</strong> Las entregas pueden realizarse desde stock disponible o desde una recepción. 
            Debe seleccionar una solicitud general o una solicitud de compra. La recepción es opcional.
        </div>';
        
        if ($generalRequest) {
            $infoMessage = '<div class="alert alert-success">
                <i class="la la-info-circle"></i> <strong>Registrando entrega para:</strong> ' . e($generalRequest->number) . ' - ' . e($generalRequest->title) . '
            </div>';
        }
        
        CRUD::addField([
            'name' => 'delivery_info',
            'label' => 'Información',
            'type' => 'custom_html',
            'value' => $infoMessage,
        ]);
        
        // Campo para seleccionar tipo de solicitud
        CRUD::addField([
            'name' => 'request_type',
            'label' => 'Tipo de Solicitud',
            'type' => 'select_from_array',
            'options' => [
                'general' => 'Solicitud General',
                'purchase' => 'Solicitud de Compra',
            ],
            'default' => $generalRequestId ? 'general' : 'general',
            'allows_null' => false,
            'attributes' => [
                'id' => 'request_type_select',
            ],
        ]);
        
        // Campo para solicitud general (mostrar/ocultar según tipo)
        // Si viene desde una solicitud general específica, pre-seleccionarla
        $user = backpack_user();
        $generalRequestOptions = function ($query) use ($generalRequestId, $user) {
            // Excluir solicitudes totalmente entregadas
            $query->where('status', '!=', 'entregada_totalmente');
            
            // Si es role_responsable_area, solo mostrar solicitudes de su área
            if ($user && $user->hasRole('role_responsable_area', 'backpack')) {
                $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('id');
                if ($userAreas->isNotEmpty()) {
                    $query->whereIn('area_id', $userAreas);
                } else {
                    // Si no tiene áreas asignadas, no mostrar ninguna solicitud
                    $query->where('id', 0);
                }
            }
            
            if ($generalRequestId) {
                // Si viene desde una solicitud específica, incluirla aunque ya tenga entregas
                // pero solo si no está totalmente entregada y pertenece a su área (si es responsable)
                return $query->where('id', $generalRequestId)->get();
            }
            // Si no, solo mostrar solicitudes que no tienen entregas asociadas
            return $query->whereDoesntHave('deliveries')->get();
        };
        
        CRUD::addField([
            'name' => 'general_request_id',
            'label' => 'Solicitud General',
            'type' => 'select',
            'model' => 'App\Models\GeneralRequest',
            'attribute' => 'number',
            'allows_null' => false,
            'default' => $generalRequestId,
            'options' => $generalRequestOptions,
            'attributes' => [
                'id' => 'general_request_select',
                'style' => 'display: block;',
            ],
        ]);
        
        // Campo para solicitud de compra (mostrar/ocultar según tipo)
        // Solo mostrar solicitudes que no tienen entregas asociadas
        CRUD::addField([
            'name' => 'purchase_request_id',
            'label' => 'Solicitud de Compra',
            'type' => 'select',
            'model' => 'App\Models\PurchaseRequest',
            'attribute' => 'request_number',
            'allows_null' => false,
            'options' => function ($query) {
                return $query->whereDoesntHave('deliveries')->get();
            },
            'attributes' => [
                'id' => 'purchase_request_select',
            ],
        ]);
        
        // Campo opcional para recepción
        // Si es role_responsable_area, solo mostrar sus propias recepciones
        $receptionOptions = function ($query) use ($user) {
            if ($user && $user->hasRole('role_responsable_area', 'backpack')) {
                // Solo mostrar recepciones donde el usuario es el area_manager_id
                return $query->where('area_manager_id', $user->id)->get();
            }
            // Para otros roles, mostrar todas las recepciones
            return $query->get();
        };
        
        CRUD::addField([
            'name' => 'reception_id',
            'label' => 'Recepción (Opcional)',
            'type' => 'select',
            'entity' => 'reception',
            'attribute' => 'number',
            'model' => 'App\Models\Reception',
            'allows_null' => true,
            'hint' => 'Opcional: Solo si la entrega proviene de una recepción específica',
            'options' => $receptionOptions,
        ]);
        
        // Script para mostrar/ocultar campos según el tipo de solicitud
        CRUD::addField([
            'name' => 'request_type_script',
            'type' => 'custom_html',
            'value' => '
            <script>
            document.addEventListener("DOMContentLoaded", function() {
                const requestTypeSelect = document.getElementById("request_type_select");
                const generalRequestSelect = document.getElementById("general_request_select").closest(".form-group");
                const purchaseRequestSelect = document.getElementById("purchase_request_select").closest(".form-group");
                
                function toggleRequestFields() {
                    if (requestTypeSelect.value === "general") {
                        generalRequestSelect.style.display = "block";
                        purchaseRequestSelect.style.display = "none";
                        document.getElementById("purchase_request_select").value = "";
                    } else {
                        generalRequestSelect.style.display = "none";
                        purchaseRequestSelect.style.display = "block";
                        document.getElementById("general_request_select").value = "";
                    }
                }
                
                requestTypeSelect.addEventListener("change", toggleRequestFields);
                toggleRequestFields(); // Ejecutar al cargar
            });
            </script>
            ',
        ]);
        CRUD::field('delivery_date')->label('Fecha');
        
        // Campo oculto para asignar automáticamente el usuario logueado como entregado por
        $user = backpack_user();
        if ($user) {
            CRUD::addField([
                'name' => 'delivered_by',
                'type' => 'hidden',
                'value' => $user->id,
            ]);
        }
        
        // Validar que la solicitud general no esté totalmente entregada
        if ($generalRequest && $generalRequest->status === 'entregada_totalmente') {
            abort(403, 'No se puede crear una entrega para una solicitud que ya está totalmente entregada.');
        }
        
        // Pre-llenar received_by con el solicitante efectivo de la solicitud general si viene desde ahí
        $receivedByDefault = null;
        if ($generalRequest) {
            $receivedByDefault = $generalRequest->solicitingUserId();
        }
        
        CRUD::addField([
            'name' => 'received_by',
            'label' => 'Recibido por',
            'type' => 'select',
            'entity' => 'receivedBy',
            'attribute' => 'name',
            'model' => 'App\Models\User',
            'default' => $receivedByDefault,
        ]);
        // Agregar campo para productos y cantidades si viene desde una solicitud general
        if ($generalRequestId && $generalRequest) {
        CRUD::addField([
                'name' => 'delivery_products',
                'label' => 'Productos a Entregar',
                'type' => 'custom_html',
                'value' => $this->getDeliveryProductsHtml($generalRequest),
            ]);
        }
    }
    
    /**
     * Generate HTML for delivery products selection
     */
    private function getDeliveryProductsHtml($generalRequest)
    {
        $generalRequest->load('details.product');
        $productsHtml = '<div id="delivery-products-container">';
        $productsHtml .= '<div class="alert alert-info">';
        $productsHtml .= '<i class="la la-info-circle"></i> <strong>Nota:</strong> Puedes entregar cantidades parciales. La cantidad máxima disponible se calcula considerando lo ya entregado.';
        $productsHtml .= '</div>';
        $productsHtml .= '<table class="table table-bordered">';
        $productsHtml .= '<thead style="background-color: #871f1f; color: white;">';
        $productsHtml .= '<tr>';
        $productsHtml .= '<th>Producto</th>';
        $productsHtml .= '<th class="text-center">Solicitado</th>';
        $productsHtml .= '<th class="text-center">Ya Entregado</th>';
        $productsHtml .= '<th class="text-center">Pendiente</th>';
        $productsHtml .= '<th class="text-center">Stock Disponible</th>';
        $productsHtml .= '<th class="text-center">Cantidad a Entregar</th>';
        $productsHtml .= '<th>Observaciones</th>';
        $productsHtml .= '</tr>';
        $productsHtml .= '</thead>';
        $productsHtml .= '<tbody>';
        
        foreach ($generalRequest->details as $detail) {
            if (!$detail->product) continue;
            
            $requestedQty = $detail->requested_quantity ?? 0;
            $deliveredQty = $detail->delivered_quantity ?? 0;
            $pendingQty = max(0, $requestedQty - $deliveredQty);
            
            // Calcular stock disponible solo de la ubicación del área de la solicitud
            $stockAvailable = 0;
            if ($detail->product_id) {
                try {
                    // Obtener la ubicación correspondiente al área de la solicitud
                    $location = null;
                    if ($generalRequest->area) {
                        // Mapeo entre nombres de áreas y nombres de ubicaciones
                        $areaLocationMap = [
                            'Informática' => 'Informática',
                            'Mantenimiento' => 'Mantenimiento',
                            'Salud' => 'Insumos de Salud',
                            'Insumos Generales' => 'Insumos Generales',
                        ];
                        
                        $areaName = $generalRequest->area->name;
                        $locationName = $areaLocationMap[$areaName] ?? $areaName;
                        $location = \App\Models\Location::where('name', $locationName)->first();
                    }
                    
                    if ($location) {
                        // Stock solo de la ubicación del área
                        $stockAvailable = (int) \App\Models\StockLevel::where('product_id', $detail->product_id)
                            ->where('location_id', $location->id)
                            ->sum('quantity');
                    } else {
                        // Si no hay ubicación, sumar todas (comportamiento anterior)
                        $stockAvailable = (int) \App\Models\StockLevel::where('product_id', $detail->product_id)->sum('quantity');
                    }
                } catch (\Exception $e) {
                    $stockAvailable = 0;
                }
            }
            
            // La cantidad máxima a entregar es el mínimo entre lo pendiente y el stock disponible
            $maxToDeliver = min($pendingQty, $stockAvailable);
            
            $productsHtml .= '<tr>';
            $productsHtml .= '<td>';
            $productsHtml .= '<strong>' . e($detail->product->name) . '</strong>';
            if ($detail->product->unit_measurement) {
                $productsHtml .= '<br><small class="text-muted">(' . e($detail->product->unit_measurement) . ')</small>';
            }
            $productsHtml .= '</td>';
            $productsHtml .= '<td class="text-center"><span class="badge bg-primary">' . number_format($requestedQty) . '</span></td>';
            $productsHtml .= '<td class="text-center"><span class="badge bg-' . ($deliveredQty > 0 ? 'success' : 'secondary') . '">' . number_format($deliveredQty) . '</span></td>';
            $productsHtml .= '<td class="text-center"><span class="badge bg-warning">' . number_format($pendingQty) . '</span></td>';
            
            // Mostrar stock disponible con color según disponibilidad
            $stockBadgeColor = 'secondary';
            $stockBadgeText = 'Sin stock';
            if ($stockAvailable > 0) {
                if ($stockAvailable >= $pendingQty) {
                    $stockBadgeColor = 'success';
                    $stockBadgeText = number_format($stockAvailable);
                } else {
                    $stockBadgeColor = 'warning';
                    $stockBadgeText = number_format($stockAvailable) . ' (insuficiente)';
                }
            }
            $productsHtml .= '<td class="text-center">';
            $productsHtml .= '<span class="badge bg-' . $stockBadgeColor . '" title="Stock total disponible: ' . number_format($stockAvailable) . '">';
            $productsHtml .= $stockBadgeText;
            $productsHtml .= '</span>';
            $productsHtml .= '</td>';
            
            $productsHtml .= '<td class="text-center">';
            $productsHtml .= '<input type="number" ';
            $productsHtml .= 'name="delivery_products[' . $detail->product_id . '][quantity]" ';
            $productsHtml .= 'class="form-control delivery-quantity" ';
            $productsHtml .= 'min="0" ';
            $productsHtml .= 'max="' . $maxToDeliver . '" ';
            $productsHtml .= 'value="0" ';
            $productsHtml .= 'data-product-id="' . $detail->product_id . '" ';
            $productsHtml .= 'data-pending="' . $pendingQty . '" ';
            $productsHtml .= 'data-stock="' . $stockAvailable . '" ';
            $productsHtml .= 'style="width: 100px; margin: 0 auto;">';
            if ($stockAvailable < $pendingQty) {
                $productsHtml .= '<small class="text-warning d-block mt-1">Máx: ' . number_format($maxToDeliver) . '</small>';
            }
            $productsHtml .= '</td>';
            $productsHtml .= '<td>';
            $productsHtml .= '<input type="text" ';
            $productsHtml .= 'name="delivery_products[' . $detail->product_id . '][observations]" ';
            $productsHtml .= 'class="form-control" ';
            $productsHtml .= 'placeholder="Observaciones (opcional)">';
            $productsHtml .= '</td>';
            $productsHtml .= '</tr>';
        }
        
        $productsHtml .= '</tbody>';
        $productsHtml .= '</table>';
        $productsHtml .= '</div>';
        
        return $productsHtml;
    }
    
    /**
     * Generate HTML for delivery products selection (for edit)
     */
    private function getDeliveryProductsHtmlForEdit($generalRequest, $delivery)
    {
        $generalRequest->load('details.product');
        $delivery->load('details');
        
        // Crear un mapa de cantidades ya entregadas en esta entrega
        $currentDeliveryQuantities = [];
        $currentDeliveryObservations = [];
        foreach ($delivery->details as $detail) {
            $currentDeliveryQuantities[$detail->product_id] = $detail->delivered_quantity;
            $currentDeliveryObservations[$detail->product_id] = $detail->observations;
        }
        
        $productsHtml = '<div id="delivery-products-container">';
        $productsHtml .= '<div class="alert alert-info">';
        $productsHtml .= '<i class="la la-info-circle"></i> <strong>Nota:</strong> Puedes entregar cantidades parciales. La cantidad máxima disponible se calcula considerando lo ya entregado.';
        $productsHtml .= '</div>';
        $productsHtml .= '<table class="table table-bordered">';
        $productsHtml .= '<thead style="background-color: #871f1f; color: white;">';
        $productsHtml .= '<tr>';
        $productsHtml .= '<th>Producto</th>';
        $productsHtml .= '<th class="text-center">Solicitado</th>';
        $productsHtml .= '<th class="text-center">Ya Entregado (otras entregas)</th>';
        $productsHtml .= '<th class="text-center">Pendiente</th>';
        $productsHtml .= '<th class="text-center">Stock Disponible</th>';
        $productsHtml .= '<th class="text-center">Cantidad a Entregar</th>';
        $productsHtml .= '<th>Observaciones</th>';
        $productsHtml .= '</tr>';
        $productsHtml .= '</thead>';
        $productsHtml .= '<tbody>';
        
        foreach ($generalRequest->details as $detail) {
            if (!$detail->product) continue;
            
            $requestedQty = $detail->requested_quantity ?? 0;
            // Calcular lo ya entregado excluyendo esta entrega
            $deliveredQty = $detail->delivered_quantity ?? 0;
            $currentQty = $currentDeliveryQuantities[$detail->product_id] ?? 0;
            $deliveredQtyWithoutCurrent = max(0, $deliveredQty - $currentQty);
            $pendingQty = max(0, $requestedQty - $deliveredQtyWithoutCurrent);
            
            // Calcular stock disponible solo de la ubicación del área de la solicitud
            $stockAvailable = 0;
            if ($detail->product_id) {
                try {
                    // Obtener la ubicación correspondiente al área de la solicitud
                    $location = null;
                    if ($generalRequest->area) {
                        // Mapeo entre nombres de áreas y nombres de ubicaciones
                        $areaLocationMap = [
                            'Informática' => 'Informática',
                            'Mantenimiento' => 'Mantenimiento',
                            'Salud' => 'Insumos de Salud',
                            'Insumos Generales' => 'Insumos Generales',
                        ];
                        
                        $areaName = $generalRequest->area->name;
                        $locationName = $areaLocationMap[$areaName] ?? $areaName;
                        $location = \App\Models\Location::where('name', $locationName)->first();
                    }
                    
                    if ($location) {
                        // Stock solo de la ubicación del área
                        $stockAvailable = (int) \App\Models\StockLevel::where('product_id', $detail->product_id)
                            ->where('location_id', $location->id)
                            ->sum('quantity');
                    } else {
                        // Si no hay ubicación, sumar todas (comportamiento anterior)
                        $stockAvailable = (int) \App\Models\StockLevel::where('product_id', $detail->product_id)->sum('quantity');
                    }
                } catch (\Exception $e) {
                    $stockAvailable = 0;
                }
            }
            
            // La cantidad máxima a entregar es el mínimo entre lo pendiente y el stock disponible
            $maxToDeliver = min($pendingQty, $stockAvailable);
            
            $productsHtml .= '<tr>';
            $productsHtml .= '<td>';
            $productsHtml .= '<strong>' . e($detail->product->name) . '</strong>';
            if ($detail->product->unit_measurement) {
                $productsHtml .= '<br><small class="text-muted">(' . e($detail->product->unit_measurement) . ')</small>';
            }
            $productsHtml .= '</td>';
            $productsHtml .= '<td class="text-center"><span class="badge bg-primary">' . number_format($requestedQty) . '</span></td>';
            $productsHtml .= '<td class="text-center"><span class="badge bg-' . ($deliveredQtyWithoutCurrent > 0 ? 'success' : 'secondary') . '">' . number_format($deliveredQtyWithoutCurrent) . '</span></td>';
            $productsHtml .= '<td class="text-center"><span class="badge bg-warning">' . number_format($pendingQty) . '</span></td>';
            
            // Mostrar stock disponible con color según disponibilidad
            $stockBadgeColor = 'secondary';
            $stockBadgeText = 'Sin stock';
            if ($stockAvailable > 0) {
                if ($stockAvailable >= $pendingQty) {
                    $stockBadgeColor = 'success';
                    $stockBadgeText = number_format($stockAvailable);
                } else {
                    $stockBadgeColor = 'warning';
                    $stockBadgeText = number_format($stockAvailable) . ' (insuficiente)';
                }
            }
            $productsHtml .= '<td class="text-center">';
            $productsHtml .= '<span class="badge bg-' . $stockBadgeColor . '" title="Stock total disponible: ' . number_format($stockAvailable) . '">';
            $productsHtml .= $stockBadgeText;
            $productsHtml .= '</span>';
            $productsHtml .= '</td>';
            
            $productsHtml .= '<td class="text-center">';
            $productsHtml .= '<input type="number" ';
            $productsHtml .= 'name="delivery_products[' . $detail->product_id . '][quantity]" ';
            $productsHtml .= 'class="form-control delivery-quantity" ';
            $productsHtml .= 'min="0" ';
            $productsHtml .= 'max="' . $maxToDeliver . '" ';
            $productsHtml .= 'value="' . $currentQty . '" ';
            $productsHtml .= 'data-product-id="' . $detail->product_id . '" ';
            $productsHtml .= 'data-pending="' . $pendingQty . '" ';
            $productsHtml .= 'data-stock="' . $stockAvailable . '" ';
            $productsHtml .= 'style="width: 100px; margin: 0 auto;">';
            if ($stockAvailable < $pendingQty) {
                $productsHtml .= '<small class="text-warning d-block mt-1">Máx: ' . number_format($maxToDeliver) . '</small>';
            }
            $productsHtml .= '</td>';
            $productsHtml .= '<td>';
            $currentObs = $currentDeliveryObservations[$detail->product_id] ?? '';
            $productsHtml .= '<input type="text" ';
            $productsHtml .= 'name="delivery_products[' . $detail->product_id . '][observations]" ';
            $productsHtml .= 'class="form-control" ';
            $productsHtml .= 'value="' . e($currentObs) . '" ';
            $productsHtml .= 'placeholder="Observaciones (opcional)">';
            $productsHtml .= '</td>';
            $productsHtml .= '</tr>';
        }
        
        $productsHtml .= '</tbody>';
        $productsHtml .= '</table>';
        $productsHtml .= '</div>';
        
        return $productsHtml;
    }

    /**
     * Define what happens when the Update operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        // Si el usuario tiene rol role_personal, no puede editar entregas
        $user = backpack_user();
        if ($user && $user->hasRole('role_personal')) {
            abort(403, 'No tienes permiso para editar entregas. Solo puedes ver las entregas que recibes.');
        }
        
        // Verificar permisos para role_responsable_compras
        $entry = $this->crud->getCurrentEntry();
        if ($user && $user->hasRole('role_responsable_compras', 'backpack')) {
            // El responsable de compras solo puede editar entregas que creó
            if ($entry && $entry->delivered_by != $user->id) {
                abort(403, 'Solo puedes editar las entregas que creaste.');
            }
        }
        
        // role_responsable_area puede editar entregas, no se bloquea
        
        $this->setupCreateOperation();
        
        // Establecer el valor del tipo de solicitud según la entrada actual
        $entryId = $this->crud->getCurrentEntryId();
        if ($entryId) {
            // Cargar el modelo explícitamente
            $entry = \App\Models\Delivery::with('generalRequest.details.product', 'details')->find($entryId);
            if ($entry) {
                if ($entry->general_request_id) {
                    $generalRequestId = $entry->general_request_id;
                    $generalRequest = $entry->generalRequest;
                    CRUD::modifyField('request_type', ['default' => 'general']);
                    
                    // En edición, incluir la solicitud actual aunque tenga entregas
                    CRUD::modifyField('general_request_id', [
                        'options' => function ($query) use ($generalRequestId) {
                            return $query->whereDoesntHave('deliveries')
                                ->orWhere('id', $generalRequestId)
                                ->get();
                        },
                        'default' => $generalRequestId,
                    ]);
                    
                    // Actualizar el campo de productos con valores existentes
                    if ($generalRequest) {
                        CRUD::modifyField('delivery_products', [
                            'value' => $this->getDeliveryProductsHtmlForEdit($generalRequest, $entry),
                        ]);
                    }
                } elseif ($entry->purchase_request_id) {
                    $purchaseRequestId = $entry->purchase_request_id;
                    CRUD::modifyField('request_type', ['default' => 'purchase']);
                    
                    // En edición, incluir la solicitud actual aunque tenga entregas
                    CRUD::modifyField('purchase_request_id', [
                        'options' => function ($query) use ($purchaseRequestId) {
                            return $query->whereDoesntHave('deliveries')
                                ->orWhere('id', $purchaseRequestId)
                                ->get();
                        },
                        'default' => $purchaseRequestId,
                    ]);
                }
            }
        }
    }
    
    /**
     * Setup delete operation - block for role_personal and restrict for role_responsable_compras
     */
    protected function setupDeleteOperation()
    {
        // Si el usuario tiene rol role_personal, no puede eliminar entregas
        $user = backpack_user();
        if ($user && $user->hasRole('role_personal')) {
            abort(403, 'No tienes permiso para eliminar entregas. Solo puedes ver las entregas que recibes.');
        }
        
        // Verificar permisos para role_responsable_compras
        $entry = $this->crud->getCurrentEntry();
        if ($user && $user->hasRole('role_responsable_compras', 'backpack')) {
            // El responsable de compras solo puede eliminar entregas que creó
            if ($entry && $entry->delivered_by != $user->id) {
                abort(403, 'Solo puedes eliminar las entregas que creaste.');
            }
        }
        
        // role_responsable_area puede eliminar entregas, no se bloquea
    }
    
    /**
     * Store a newly created resource in storage.
     */
    public function store()
    {
        // Verificar que no sea role_personal antes de permitir crear
        $user = backpack_user();
        if ($user && $user->hasRole('role_personal')) {
            abort(403, 'No tienes permiso para crear entregas. Solo puedes ver las entregas que recibes.');
        }
        // role_responsable_area puede crear entregas, pero solo para solicitudes de su área
        
        $this->crud->hasAccessOrFail('create');
        
        $request = $this->crud->validateRequest();
        $this->crud->registerFieldEvents();
        
        // Validar que si es role_responsable_area, solo pueda crear entregas para solicitudes de su área
        if ($user && $user->hasRole('role_responsable_area', 'backpack')) {
            $generalRequestId = $request->input('general_request_id');
            if ($generalRequestId) {
                $generalRequest = \App\Models\GeneralRequest::find($generalRequestId);
                if ($generalRequest) {
                    // Validar que la solicitud no esté totalmente entregada
                    if ($generalRequest->status === 'entregada_totalmente') {
                        abort(403, 'No se puede crear una entrega para una solicitud que ya está totalmente entregada.');
                    }
                    
                    // Validar que pertenezca a su área
                    if ($generalRequest->area_id) {
                        $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('id');
                        if (!$userAreas->contains($generalRequest->area_id)) {
                            abort(403, 'No puedes crear entregas para solicitudes de otras áreas.');
                        }
                    }
                }
            }
            
            // Validar que si se selecciona una recepción, sea propia
            $receptionId = $request->input('reception_id');
            if ($receptionId) {
                $reception = \App\Models\Reception::find($receptionId);
                if ($reception && $reception->area_manager_id != $user->id) {
                    abort(403, 'No puedes crear entregas con recepciones que no te pertenecen.');
                }
            }
        }
        
        // Obtener datos y remover request_type (solo es para UI)
        $dataToSave = $this->crud->getStrippedSaveRequest($request);
        unset($dataToSave['request_type']);
        // Al registrar la entrega en este flujo, queda como efectivamente realizada (evita default DB "pendiente" en PDF/listados)
        $dataToSave['status'] = 'entregada';
        
        // Obtener productos a entregar
        $deliveryProducts = $request->input('delivery_products', []);
        
        \Log::info('Delivery store: Iniciando creación de entrega', [
            'deliveryProducts' => $deliveryProducts,
            'general_request_id' => $dataToSave['general_request_id'] ?? null
        ]);
        
        // Insert item in the db
        $item = $this->crud->create($dataToSave);
        $this->data['entry'] = $this->crud->entry = $item;
        
        \Log::info('Delivery store: Entrega creada', [
            'delivery_id' => $item->id,
            'general_request_id' => $item->general_request_id
        ]);
        
        // Procesar detalles de entrega
        $this->processDeliveryDetails($item, $deliveryProducts, $request);
        $item->refresh();
        $this->fillDeliveryDetailsFromPurchaseRequestIfEmpty($item);

        // Restar del stock los productos entregados
        $this->updateStockLevels($item, $deliveryProducts, true);
        
        // Actualizar estado de la solicitud general si corresponde
        if ($item->general_request_id) {
            $this->updateGeneralRequestStatus($item->general_request_id);
        }
        
        \Alert::success(trans('backpack::crud.insert_success'))->flash();
        $this->crud->setSaveAction();
        
        // Asegurar que $item es un modelo antes de llamar getKey()
        $itemId = is_object($item) && method_exists($item, 'getKey') ? $item->getKey() : $item;
        return $this->crud->performSaveAction($itemId);
    }
    
    /**
     * Process delivery details (products and quantities)
     */
    private function processDeliveryDetails($delivery, $deliveryProducts, $request)
    {
        if (empty($deliveryProducts) || !is_array($deliveryProducts)) {
            return;
        }
        
        foreach ($deliveryProducts as $productId => $productData) {
            $quantity = isset($productData['quantity']) ? (int)$productData['quantity'] : 0;
            $observations = $productData['observations'] ?? null;
            
            // Solo crear detalle si hay cantidad a entregar
            if ($quantity > 0) {
                // Validar que no se entregue más de lo solicitado
                if ($delivery->general_request_id) {
                    $generalRequestDetail = \App\Models\GeneralRequestDetail::where('general_request_id', $delivery->general_request_id)
                        ->where('product_id', $productId)
                        ->first();
                    
                    if ($generalRequestDetail) {
                        $requestedQty = $generalRequestDetail->requested_quantity;
                        // Calcular lo ya entregado excluyendo esta entrega si estamos editando
                        $allDeliveries = \App\Models\Delivery::where('general_request_id', $delivery->general_request_id)
                            ->where('id', '!=', $delivery->id) // Excluir esta entrega
                            ->with('details')
                            ->get();
                        
                        $alreadyDelivered = 0;
                        foreach ($allDeliveries as $otherDelivery) {
                            $otherDetail = $otherDelivery->details->where('product_id', $productId)->first();
                            if ($otherDetail) {
                                $alreadyDelivered += $otherDetail->delivered_quantity;
                            }
                        }
                        
                        // Calcular stock disponible solo de la ubicación del área de la solicitud
                        $stockAvailable = 0;
                        if ($delivery->general_request_id) {
                            $generalRequest = \App\Models\GeneralRequest::with('area')->find($delivery->general_request_id);
                            if ($generalRequest && $generalRequest->area) {
                                // Mapeo entre nombres de áreas y nombres de ubicaciones
                                $areaLocationMap = [
                                    'Informática' => 'Informática',
                                    'Mantenimiento' => 'Mantenimiento',
                                    'Salud' => 'Insumos de Salud',
                                    'Insumos Generales' => 'Insumos Generales',
                                ];
                                
                                $areaName = $generalRequest->area->name;
                                $locationName = $areaLocationMap[$areaName] ?? $areaName;
                                $location = \App\Models\Location::where('name', $locationName)->first();
                                
                                if ($location) {
                                    // Stock solo de la ubicación del área
                                    $stockAvailable = (int) \App\Models\StockLevel::where('product_id', $productId)
                                        ->where('location_id', $location->id)
                                        ->sum('quantity');
                                } else {
                                    // Si no hay ubicación, sumar todas (comportamiento anterior)
                                    $stockAvailable = (int) \App\Models\StockLevel::where('product_id', $productId)->sum('quantity');
                                }
                            } else {
                                // Si no hay área, sumar todas (comportamiento anterior)
                                $stockAvailable = (int) \App\Models\StockLevel::where('product_id', $productId)->sum('quantity');
                            }
                        } else {
                            // Si no hay solicitud general, sumar todas (comportamiento anterior)
                            $stockAvailable = (int) \App\Models\StockLevel::where('product_id', $productId)->sum('quantity');
                        }
                        
                        // El máximo a entregar es el mínimo entre lo pendiente y el stock disponible
                        $pendingQty = $requestedQty - $alreadyDelivered;
                        $maxToDeliver = min($pendingQty, $stockAvailable);
                        
                        if ($quantity > $maxToDeliver) {
                            \Alert::warning("La cantidad a entregar excede lo disponible. Máximo: {$maxToDeliver} (Pendiente: {$pendingQty}, Stock: {$stockAvailable})")->flash();
                            $quantity = max(0, $maxToDeliver);
                        }
                    }
                }
                
                \App\Models\DeliveryDetail::create([
                    'delivery_id' => $delivery->id,
                    'product_id' => $productId,
                    'delivered_quantity' => $quantity,
                    'observations' => $observations,
                ]);
            }
        }
    }

    /**
     * Si no hay líneas de entrega (típico: flujo solicitud de compra / recepción sin formulario de productos),
     * genera detalles desde la solicitud de compra con cantidades pendientes por producto.
     */
    private function fillDeliveryDetailsFromPurchaseRequestIfEmpty(Delivery $delivery): void
    {
        $delivery->loadMissing('details');
        if ($delivery->details->isNotEmpty()) {
            return;
        }
        if ($delivery->general_request_id) {
            return;
        }

        $purchaseRequestId = $delivery->purchase_request_id;
        if (! $purchaseRequestId && $delivery->reception_id) {
            $reception = Reception::with('purchase_order')->find($delivery->reception_id);
            $purchaseRequestId = $reception?->purchase_order?->purchase_request_id;
        }
        if (! $purchaseRequestId) {
            return;
        }

        $purchaseRequest = PurchaseRequest::with('details')->find($purchaseRequestId);
        if (! $purchaseRequest) {
            return;
        }

        if (! $delivery->purchase_request_id) {
            $delivery->update(['purchase_request_id' => $purchaseRequestId]);
        }

        foreach ($purchaseRequest->details as $prDetail) {
            if (! $prDetail->product_id) {
                continue;
            }
            $alreadyDelivered = (int) DeliveryDetail::query()
                ->whereHas('delivery', function ($q) use ($purchaseRequestId, $delivery) {
                    $q->where('purchase_request_id', $purchaseRequestId);
                    if ($delivery->id) {
                        $q->where('id', '!=', $delivery->id);
                    }
                })
                ->where('product_id', $prDetail->product_id)
                ->sum('delivered_quantity');

            $pending = max(0, (int) $prDetail->requested_quantity - $alreadyDelivered);
            if ($pending <= 0) {
                continue;
            }

            DeliveryDetail::create([
                'delivery_id' => $delivery->id,
                'product_id' => $prDetail->product_id,
                'delivered_quantity' => $pending,
                'observations' => null,
            ]);
        }
    }
    
    /**
     * Update the status of a general request based on delivery status
     */
    private function updateGeneralRequestStatus($generalRequestId)
    {
        $generalRequest = \App\Models\GeneralRequest::with('details.product', 'deliveries.details')->find($generalRequestId);
        
        if (!$generalRequest) {
            return;
        }
        
        // No actualizar si está archivada
        if ($generalRequest->status === 'archivada') {
            return;
        }
        
        $allDelivered = true;
        $hasAnyDelivery = false;
        $hasDetails = false;
        
        // Verificar el estado de entrega de cada producto
        foreach ($generalRequest->details as $detail) {
            $requestedQty = $detail->requested_quantity ?? 0;
            
            if ($requestedQty <= 0) {
                continue; // Saltar productos sin cantidad solicitada
            }
            
            $hasDetails = true; // Hay al menos un producto con cantidad solicitada
            
            // Calcular cantidad entregada
            $deliveredQty = 0;
            foreach ($generalRequest->deliveries as $delivery) {
                $deliveryDetail = $delivery->details->where('product_id', $detail->product_id)->first();
                if ($deliveryDetail) {
                    $deliveredQty += $deliveryDetail->delivered_quantity ?? 0;
                }
            }
            
            if ($deliveredQty > 0) {
                $hasAnyDelivery = true; // Hay al menos una entrega
            }
            
            // Si este producto no está completamente entregado, entonces no todos están entregados
            if ($deliveredQty < $requestedQty) {
                $allDelivered = false;
            }
        }
        
        // Actualizar status según corresponda
        // No actualizar si está archivada
        if ($generalRequest->status === 'archivada') {
            return;
        }
        
        if ($hasDetails && $hasAnyDelivery) {
            if ($allDelivered) {
                // Todos los productos están completamente entregados
                $generalRequest->status = 'entregada_totalmente';
                $generalRequest->save();
            } else {
                // Algunos productos están entregados pero no todos están completos
                // Incluso si fue convertida a compra, si hay entregas parciales, el status debe ser "entregada_parcialmente"
                $generalRequest->status = 'entregada_parcialmente';
                $generalRequest->save();
            }
        } else {
            // Si no hay entregas, establecer como sin_entrega (solo si no está en otro estado del flujo)
            if (!in_array($generalRequest->status, ['creada', 'revisada_area', 'archivada'])) {
                $generalRequest->status = 'sin_entrega';
                $generalRequest->save();
            }
        }
    }
    
    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $this->crud->hasAccessOrFail('delete');
        
        // Obtener la entrega antes de eliminarla para revertir el stock y actualizar el estado
        $entry = $this->crud->getEntry($id);
        $entry->load('details');
        $generalRequestId = $entry->general_request_id ?? null;
        
        // Revertir el stock antes de eliminar
        $this->revertStockLevels($entry, $entry->details->keyBy('product_id'));
        
        // Eliminar la entrega
        $response = $this->crud->delete($id);
        
        // Actualizar estado de la solicitud general si corresponde
        if ($generalRequestId) {
            $this->updateGeneralRequestStatus($generalRequestId);
        }
        
        return $response;
    }
    
    /**
     * Get location for delivery based on general request area
     */
    private function getLocationForDelivery($delivery)
    {
        if (!$delivery->general_request_id) {
            \Log::warning('getLocationForDelivery: No hay general_request_id', [
                'delivery_id' => $delivery->id ?? null
            ]);
            return null;
        }
        
        $generalRequest = \App\Models\GeneralRequest::with('area')->find($delivery->general_request_id);
        if (!$generalRequest) {
            \Log::warning('getLocationForDelivery: Solicitud general no encontrada', [
                'delivery_id' => $delivery->id ?? null,
                'general_request_id' => $delivery->general_request_id
            ]);
            return null;
        }
        
        if (!$generalRequest->area) {
            \Log::warning('getLocationForDelivery: Solicitud general no tiene área', [
                'delivery_id' => $delivery->id ?? null,
                'general_request_id' => $delivery->general_request_id
            ]);
            return null;
        }
        
        // Mapeo entre nombres de áreas y nombres de ubicaciones
        $areaLocationMap = [
            'Informática' => 'Informática',
            'Mantenimiento' => 'Mantenimiento',
            'Salud' => 'Insumos de Salud',
            'Insumos Generales' => 'Insumos Generales',
        ];
        
        $areaName = $generalRequest->area->name;
        $locationName = $areaLocationMap[$areaName] ?? $areaName;
        
        // Buscar la ubicación usando el mapeo
        $location = \App\Models\Location::where('name', $locationName)->first();
        
        if (!$location) {
            \Log::warning('getLocationForDelivery: Ubicación no encontrada para el área', [
                'delivery_id' => $delivery->id ?? null,
                'area_name' => $areaName,
                'location_name_buscado' => $locationName,
                'general_request_id' => $delivery->general_request_id
            ]);
        } else {
            \Log::info('getLocationForDelivery: Ubicación encontrada', [
                'delivery_id' => $delivery->id ?? null,
                'location_id' => $location->id,
                'location_name' => $location->name,
                'area_name' => $areaName
            ]);
        }
        
        return $location;
    }
    
    /**
     * Update stock levels when delivery is created or updated
     */
    private function updateStockLevels($delivery, $deliveryProducts, $subtract = true)
    {
        if (empty($deliveryProducts) || !is_array($deliveryProducts)) {
            \Log::warning('updateStockLevels: deliveryProducts está vacío o no es un array', [
                'delivery_id' => $delivery->id ?? null,
                'deliveryProducts' => $deliveryProducts
            ]);
            return;
        }
        
        $location = $this->getLocationForDelivery($delivery);
        if (!$location) {
            \Log::warning('No se pudo encontrar la ubicación para la entrega', [
                'delivery_id' => $delivery->id ?? null,
                'general_request_id' => $delivery->general_request_id ?? null
            ]);
            return;
        }
        
        \Log::info('updateStockLevels: Iniciando actualización de stock', [
            'delivery_id' => $delivery->id,
            'location_id' => $location->id,
            'location_name' => $location->name,
            'subtract' => $subtract,
            'deliveryProducts_count' => count($deliveryProducts)
        ]);
        
        // Cargar la relación generalRequest si no está cargada
        if (!$delivery->relationLoaded('generalRequest') && $delivery->general_request_id) {
            $delivery->load('generalRequest');
        }
        
        foreach ($deliveryProducts as $productId => $productData) {
            // Convertir productId a entero si es string
            $productId = (int)$productId;
            $quantity = isset($productData['quantity']) ? (int)$productData['quantity'] : 0;
            
            if ($quantity <= 0) {
                \Log::info('updateStockLevels: Saltando producto con cantidad 0', [
                    'product_id' => $productId,
                    'quantity' => $quantity
                ]);
                continue;
            }
            
            \Log::info('updateStockLevels: Procesando producto', [
                'product_id' => $productId,
                'quantity' => $quantity,
                'location_id' => $location->id,
                'subtract' => $subtract
            ]);
            
            // Buscar o crear el stock level para este producto y ubicación
            $stockLevel = \App\Models\StockLevel::firstOrCreate(
                [
                    'product_id' => $productId,
                    'location_id' => $location->id,
                ],
                [
                    'quantity' => 0,
                    'last_updated_by' => backpack_user()->id ?? null,
                ]
            );
            
            $oldQuantity = $stockLevel->quantity;
            
            if ($subtract) {
                // Restar del stock
                $stockLevel->quantity = max(0, $stockLevel->quantity - $quantity);
            } else {
                // Sumar al stock (revertir)
                $stockLevel->quantity += $quantity;
            }
            
            $stockLevel->last_updated_by = backpack_user()->id ?? null;
            $stockLevel->save();
            
            \Log::info('updateStockLevels: Stock actualizado', [
                'product_id' => $productId,
                'old_quantity' => $oldQuantity,
                'new_quantity' => $stockLevel->quantity,
                'quantity_change' => $subtract ? -$quantity : $quantity
            ]);
            
            // Crear movimiento de inventario
            $requestNumber = $delivery->generalRequest->number ?? 'N/A';
            try {
                \App\Models\InventoryMovement::create([
                    'product_id' => $productId,
                    'location_id' => $location->id,
                    'quantity' => $subtract ? -$quantity : $quantity,
                    'type' => 'uso',
                    'reference' => $delivery->number ?? 'ENT-' . $delivery->id,
                    'user_id' => backpack_user()->id ?? null,
                    'notes' => $subtract 
                        ? "Entrega de productos - Solicitud: " . $requestNumber
                        : "Reversión de entrega - Solicitud: " . $requestNumber,
                ]);
                \Log::info('updateStockLevels: Movimiento de inventario creado', [
                    'product_id' => $productId,
                    'quantity' => $subtract ? -$quantity : $quantity
                ]);
            } catch (\Exception $e) {
                \Log::error('updateStockLevels: Error al crear movimiento de inventario', [
                    'product_id' => $productId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        \Log::info('updateStockLevels: Proceso completado');
    }
    
    /**
     * Revert stock levels when delivery is updated or deleted
     */
    private function revertStockLevels($delivery, $oldDeliveryDetails)
    {
        if ($oldDeliveryDetails->isEmpty()) {
            return;
        }
        
        $location = $this->getLocationForDelivery($delivery);
        if (!$location) {
            \Log::warning('No se pudo encontrar la ubicación para revertir el stock de la entrega', ['delivery_id' => $delivery->id]);
            return;
        }
        
        // Cargar la relación generalRequest si no está cargada
        if (!$delivery->relationLoaded('generalRequest') && $delivery->general_request_id) {
            $delivery->load('generalRequest');
        }
        
        foreach ($oldDeliveryDetails as $productId => $detail) {
            $quantity = $detail->delivered_quantity ?? 0;
            
            if ($quantity <= 0) {
                continue;
            }
            
            // Buscar el stock level para este producto y ubicación
            $stockLevel = \App\Models\StockLevel::where('product_id', $productId)
                ->where('location_id', $location->id)
                ->first();
            
            if ($stockLevel) {
                // Revertir: sumar al stock
                $stockLevel->quantity += $quantity;
                $stockLevel->last_updated_by = backpack_user()->id ?? null;
                $stockLevel->save();
                
                // Crear movimiento de inventario para la reversión
                $requestNumber = $delivery->generalRequest->number ?? 'N/A';
                \App\Models\InventoryMovement::create([
                    'product_id' => $productId,
                    'location_id' => $location->id,
                    'quantity' => $quantity,
                    'type' => 'ajuste',
                    'reference' => $delivery->number ?? 'ENT-' . $delivery->id,
                    'user_id' => backpack_user()->id ?? null,
                    'notes' => "Reversión de entrega - Solicitud: " . $requestNumber,
                ]);
            }
        }
    }
    
    /**
     * Update the specified resource in storage.
     */
    public function update()
    {
        // Verificar que no sea role_personal antes de permitir editar
        $user = backpack_user();
        if ($user && $user->hasRole('role_personal')) {
            abort(403, 'No tienes permiso para editar entregas. Solo puedes ver las entregas que recibes.');
        }
        // role_responsable_area puede editar entregas, pero solo para solicitudes de su área
        
        $this->crud->hasAccessOrFail('update');
        
        $request = $this->crud->validateRequest();
        $this->crud->registerFieldEvents();
        
        // Validar que si es role_responsable_area, solo pueda editar entregas para solicitudes de su área
        if ($user && $user->hasRole('role_responsable_area', 'backpack')) {
            $entry = $this->crud->getCurrentEntry();
            if ($entry && $entry->general_request_id) {
                $generalRequest = \App\Models\GeneralRequest::find($entry->general_request_id);
                if ($generalRequest && $generalRequest->area_id) {
                    $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('id');
                    if (!$userAreas->contains($generalRequest->area_id)) {
                        abort(403, 'No puedes editar entregas para solicitudes de otras áreas.');
                    }
                }
            }
            
            // Validar que si se selecciona una recepción, sea propia
            $receptionId = $request->input('reception_id');
            if ($receptionId) {
                $reception = \App\Models\Reception::find($receptionId);
                if ($reception && $reception->area_manager_id != $user->id) {
                    abort(403, 'No puedes editar entregas con recepciones que no te pertenecen.');
                }
            }
        }
        
        // Obtener datos y remover request_type (solo es para UI)
        $dataToSave = $this->crud->getStrippedSaveRequest($request);
        unset($dataToSave['request_type']);
        $dataToSave['status'] = 'entregada';
        
        // Obtener productos a entregar
        $deliveryProducts = $request->input('delivery_products', []);
        
        // Update item in the db
        $item = $this->crud->update($this->crud->getCurrentEntryId(), $dataToSave);
        $this->data['entry'] = $this->crud->entry = $item;
        
        // Obtener las cantidades anteriores antes de actualizar
        $oldDeliveryDetails = $item->details()->get()->keyBy('product_id');
        
        // Eliminar detalles existentes y recrearlos
        $item->details()->delete();
        
        // Revertir stock anterior
        $this->revertStockLevels($item, $oldDeliveryDetails);
        
        // Procesar detalles de entrega
        $this->processDeliveryDetails($item, $deliveryProducts, $request);
        $item->refresh();
        $this->fillDeliveryDetailsFromPurchaseRequestIfEmpty($item);

        // Restar del stock los productos entregados
        $this->updateStockLevels($item, $deliveryProducts, true);
        
        // Actualizar estado de la solicitud general si corresponde
        if ($item->general_request_id) {
            $this->updateGeneralRequestStatus($item->general_request_id);
        }
        
        \Alert::success(trans('backpack::crud.update_success'))->flash();
        $this->crud->setSaveAction();
        
        // Asegurar que $item es un modelo antes de llamar getKey()
        $itemId = is_object($item) && method_exists($item, 'getKey') ? $item->getKey() : $item;
        return $this->crud->performSaveAction($itemId);
    }

    protected function setupShowOperation()
    {
        // Cargar relaciones
        CRUD::addClause('with', ['reception', 'generalRequest', 'purchaseRequest', 'receivedBy', 'deliveredBy', 'details']);
        
        // Columna para mostrar la solicitud relacionada
        CRUD::addColumn([
            'name' => 'request_info',
            'label' => 'Solicitud',
            'type' => 'closure',
            'function' => function($entry) {
                $html = '';
                if ($entry->generalRequest) {
                    $html .= '<div class="mb-2">';
                    $html .= '<strong>Solicitud General:</strong> ';
                    $html .= '<a href="' . backpack_url('general-request/' . $entry->generalRequest->id . '/show') . '" class="badge bg-info">';
                    $html .= e($entry->generalRequest->number);
                    $html .= '</a>';
                    $html .= '</div>';
                }
                if ($entry->purchaseRequest) {
                    $html .= '<div class="mb-2">';
                    $html .= '<strong>Solicitud de Compra:</strong> ';
                    $html .= '<a href="' . backpack_url('purchase-request/' . $entry->purchaseRequest->id . '/show') . '" class="badge bg-primary">';
                    $html .= e($entry->purchaseRequest->request_number);
                    $html .= '</a>';
                    $html .= '</div>';
                }
                if (!$entry->generalRequest && !$entry->purchaseRequest) {
                    $html .= '<span class="badge bg-secondary">Sin solicitud asociada</span>';
                }
                return $html;
            },
            'escaped' => false,
        ]);
        
        // Columna opcional para recepción
        CRUD::addColumn([
            'name' => 'reception_info',
            'label' => 'Recepción',
            'type' => 'closure',
            'function' => function($entry) {
                if ($entry->reception) {
                    return '<a href="' . backpack_url('reception/' . $entry->reception->id . '/show') . '" class="badge bg-success">REC-' . $entry->reception->id . '</a>';
                }
                return '<span class="badge bg-secondary">Sin recepción (desde stock)</span>';
            },
            'escaped' => false,
        ]);
        
        CRUD::column('delivery_date')->label('Fecha');
        
        CRUD::addColumn([
            'name' => 'delivered_by',
            'label' => 'Entregado por',
            'type' => 'select',
            'entity' => 'deliveredBy',
            'attribute' => 'name',
            'model' => 'App\Models\User',
        ]);
        
        CRUD::addColumn([
            'name' => 'received_by',
            'label' => 'Recibido por',
            'type' => 'select',
            'entity' => 'receivedBy',
            'attribute' => 'name',
            'model' => 'App\Models\User',
        ]);
        
        // Agregar botón PDF en la vista show
        CRUD::addButton('top', 'pdf', 'view', 'crud::buttons.delivery_pdf', 'end');
    }
    
    /**
     * Generate PDF for a delivery
     */
    public function generatePdf($id)
    {
        $delivery = \App\Models\Delivery::with([
            'generalRequest.area',
            'generalRequest.createdBy',
            'generalRequest.details.product',
            'purchaseRequest.responsibilityArea',
            'purchaseRequest.requestingUser',
            'purchaseRequest.details.product',
            'reception.purchase_order.supplier',
            'reception.purchase_order.details.input',
            'reception.purchase_order.details.supplier',
            'deliveredBy',
            'receivedBy',
            'details.product'
        ])->findOrFail($id);

        $prId = $delivery->purchase_request_id;
        if (! $prId && $delivery->reception?->purchase_order) {
            $prId = $delivery->reception->purchase_order->purchase_request_id;
        }
        if ($prId && ! $delivery->purchaseRequest) {
            $pr = PurchaseRequest::with([
                'responsibilityArea',
                'requestingUser',
                'details.product',
            ])->find($prId);
            if ($pr) {
                $delivery->setRelation('purchaseRequest', $pr);
            }
        }

        $deliveryPdfFallbackDetails = collect();
        if ($delivery->details->isEmpty() && $delivery->purchaseRequest) {
            $deliveryPdfFallbackDetails = $delivery->purchaseRequest->details ?? collect();
        }

        $pdf = Pdf::loadView('delivery-pdf', compact('delivery', 'deliveryPdfFallbackDetails'));

        return $pdf->stream('comprobante-entrega-' . $delivery->number . '.pdf');
    }
}
