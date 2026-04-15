<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use App\Http\Requests\PurchaseRequestRequest;
use App\Models\MarketRate;
use Illuminate\Support\Str;

/**
 * Class PurchaseRequestCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class PurchaseRequestCrudController extends CrudController
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
        CRUD::setModel(\App\Models\PurchaseRequest::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/purchase-request');
        CRUD::setEntityNameStrings('solicitud de compra', 'solicitudes de compra');
        
        // Usar FormRequest personalizado
        CRUD::setValidation(PurchaseRequestRequest::class);

        // El responsable de compras no puede editar solicitudes de compra.
        $user = backpack_user();
        if ($user && $user->hasRole('role_responsable_compras', 'backpack')) {
            CRUD::denyAccess('update');
        }
    }

    /**
     * Define what happens when the List operation is loaded.
     * 
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        // Habilitar tabla responsiva
        CRUD::enableResponsiveTable();
        
        CRUD::addClause('with', ['responsibilityArea', 'requestingUser', 'details', 'purchaseOrders', 'marketRates']);
        
        // Filtrar solicitudes según el rol del usuario
        $user = backpack_user();
        if ($user) {
            // Roles que pueden ver todas las solicitudes (administradores, apoderado y representante legal)
            $adminRoles = ['role_admin_sistema', 'role_admin_institucion', 'role_responsable_compras', 'role_apoderado', 'role_representante_legal'];
            $isAdmin = false;
            foreach ($adminRoles as $role) {
                if ($user->hasRole($role, 'backpack')) {
                    $isAdmin = true;
                    break;
                }
            }
            
            if (!$isAdmin) {
                // Todos los usuarios (incluyendo responsables de área) solo ven sus propias solicitudes
                CRUD::addClause('where', 'requesting_user_id', $user->id);
            }
        }
        
        CRUD::column('request_number')->label('Número de Solicitud');
        CRUD::column('request_date')->label('Fecha');
        CRUD::column('responsibilityArea.name')->label('Área');
        CRUD::column('requestingUser.name')->label('Solicitante');
        CRUD::column('status')->label('Estado');
        CRUD::addColumn([
            'name' => 'purchase_type',
            'label' => 'Tipo de Compra',
            'type' => 'closure',
            'function' => function($entry) {
                $type = $entry->purchase_type ?? 'normal';
                $badges = [
                    'normal' => '<span class="badge bg-secondary">Normal</span>',
                    'rapida' => '<span class="badge bg-success">Rápida</span>',
                    'directa' => '<span class="badge bg-info">Directa</span>',
                    'internet' => '<span class="badge bg-primary">Por internet</span>',
                ];
                return $badges[$type] ?? $badges['normal'];
            },
            'escaped' => false,
        ]);
        CRUD::column('priority')->label('Prioridad');
        CRUD::column('total_amount')->label('Monto Total')->type('number')->decimals(2)->prefix('$');
        
        // Agregar columna personalizada para mostrar cantidad de productos
        CRUD::column('details_count')->label('Productos')->type('custom_html')
            ->value(function($entry) {
                $count = $entry->details->count();
                return '<span class="badge bg-info">' . $count . ' productos</span>';
            });

        // Agregar columna personalizada para mostrar cantidad de cotizaciones
        CRUD::column('quotations_count')->label('Cotizaciones')->type('custom_html')
            ->value(function($entry) {
                // Evitar consultas por fila: marketRates ya viene eager-loaded.
                $quotationsCount = $entry->marketRates->count();
                
                if ($quotationsCount > 0) {
                    return '<span class="badge bg-success">' . $quotationsCount . ' cotizaciones</span>';
                } else {
                    return '<span class="badge bg-warning">Sin cotizaciones</span>';
                }
            });

        // Remover botón de edición por defecto y usar el personalizado
        CRUD::removeButton('update');
        
        // Ocultar botones de crear, editar y eliminar para roles sin edición manual de solicitudes.
        if ($user && (
            $user->hasRole('role_admin_institucion', 'backpack')
            || $user->hasRole('role_apoderado', 'backpack')
            || $user->hasRole('role_representante_legal', 'backpack')
            || $user->hasRole('role_responsable_compras', 'backpack')
        )) {
            CRUD::removeButton('create');
            CRUD::removeButton('delete');
            // No agregar el botón personalizado de editar para estos roles
        } else {
            CRUD::addButton('line', 'edit_purchase_request', 'view', 'crud::buttons.edit_purchase_request', 'beginning');
        }
        
        // Botón para ver orden de compra (solo si existe y para usuarios que no sean role_responsable_area)
        if (!$user || !$user->hasRole('role_responsable_area', 'backpack')) {
            CRUD::addButton('line', 'view_purchase_order', 'view', 'crud::buttons.view_purchase_order', 'end');
        }
        
        // Filtro personalizado para solicitudes pendientes
        // Solo aplicar si el parámetro está explícitamente en la URL actual
        // No aplicar si viene de una restauración automática de Backpack desde localStorage
        // Backpack agrega 'persistent-table=true' cuando restaura desde localStorage
        $hasPendientes = request()->query('pendientes') == '1';
        $hasAprobadasPorSuperior = request()->query('aprobadas_por_superior') == '1';
        $isPersistentRestore = request()->query('persistent-table') == 'true';
        
        // Solo aplicar el filtro si:
        // 1. El parámetro pendientes está presente
        // 2. NO es una restauración automática desde localStorage (sin persistent-table)
        // Esto asegura que cuando el usuario accede desde el menú, se muestre todo
        if ($hasPendientes && !$isPersistentRestore) {
            CRUD::addClause('where', 'status', 'Pendiente');
        }

        // Solicitudes aprobadas por nivel superior (desde aviso del dashboard de compras)
        if ($hasAprobadasPorSuperior && !$isPersistentRestore && $user && $user->hasRole('role_responsable_compras', 'backpack')) {
            $supervisorRoleNames = [
                'role_admin_sistema',
                'role_admin_institucion',
                'role_apoderado',
                'role_representante_legal',
            ];
            CRUD::addClause(function ($query) use ($user, $supervisorRoleNames) {
                $query->where('status', 'Aprobada')
                    ->whereNotNull('approved_by')
                    ->where('approved_by', '!=', $user->id)
                    ->whereHas('approvedBy.roles', function ($q) use ($supervisorRoleNames) {
                        $q->where('guard_name', 'backpack')->whereIn('name', $supervisorRoleNames);
                    });
            });
        }
    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        // Verificar si viene de una solicitud general
        $convertedFrom = request()->get('converted_from');
        $generalRequest = null;
        $existingProducts = [];
        
        if ($convertedFrom) {
            // Cargar la solicitud general con detalles, productos (incluyendo stockLevels) y entregas
            $generalRequest = \App\Models\GeneralRequest::with(['details.product.stockLevels', 'deliveries.details'])->find($convertedFrom);
            
            // Cargar productos de la solicitud general para pre-cargarlos en el formulario
            // Solo mostrar productos con cantidades faltantes (no totalmente entregados)
            // Los precios se establecen en 0, ya que el sector de compras los asignará después
            if ($generalRequest && $generalRequest->details) {
                foreach ($generalRequest->details as $detail) {
                    if ($detail->product) {
                        // Calcular cantidad entregada
                        $deliveredQuantity = 0;
                        if ($generalRequest->deliveries) {
                            foreach ($generalRequest->deliveries as $delivery) {
                                $deliveryDetail = $delivery->details->where('product_id', $detail->product_id)->first();
                                if ($deliveryDetail) {
                                    $deliveredQuantity += $deliveryDetail->delivered_quantity ?? 0;
                                }
                            }
                        }
                        
                        // Calcular cantidad faltante
                        $requestedQuantity = $detail->requested_quantity ?? 0;
                        $pendingQuantity = max(0, $requestedQuantity - $deliveredQuantity);
                        
                        // Solo incluir productos con cantidad faltante > 0
                        if ($pendingQuantity > 0) {
                            // Calcular stock total del producto
                            $stockTotal = $detail->product->stockLevels->sum('quantity') ?? 0;
                            
                            $existingProducts[] = [
                                'product_id' => $detail->product_id,
                                'product_name' => $detail->product->name ?? 'Producto no encontrado',
                                'name' => $detail->product->name ?? 'Producto no encontrado',
                                'unit' => $detail->product->unit_measurement ?? 'unidad',
                                'description' => $detail->product->description ?? '',
                                'quantity' => $pendingQuantity, // Usar cantidad faltante en lugar de solicitada
                                'price' => 0, // Precio inicial en 0, el sector de compras lo asignará
                                'specifications' => $detail->specifications ?? '',
                                'product_description' => $detail->product_description ?? '',
                                'minimum_stock' => $detail->product->minimum_stock ?? 0,
                                'stock_total' => $stockTotal
                            ];
                        }
                    }
                }
            }
        }

        // Setup common fields
        $this->setupCreateFields();
        
        // Si hay productos existentes de la solicitud general, reemplazar el campo de productos
        if (!empty($existingProducts)) {
            CRUD::modifyField('products_selection', [
                'value' => $this->getProductsSelectionHtml($existingProducts, $generalRequest->area_id ?? null)
            ]);
        }
        
        // Override defaults if converting from general request
        if ($generalRequest) {
            $user = backpack_user();
            CRUD::modifyField('responsibility_area_id', ['default' => $generalRequest->area_id]);
            // El requesting_user_id debe ser el usuario logueado (responsable de área), no el creador de la solicitud general
            CRUD::modifyField('requesting_user_id', ['default' => $user ? $user->id : $generalRequest->created_by]);
            CRUD::modifyField('priority', ['default' => $generalRequest->priority]);
            CRUD::modifyField('justification', ['default' => $generalRequest->description]);
            
            // Asegurar que los valores por defecto se establezcan correctamente
            if ($generalRequest->area_id) {
                CRUD::modifyField('responsibility_area_id', ['value' => $generalRequest->area_id]);
            }
            // El requesting_user_id debe ser el usuario logueado
            if ($user) {
                CRUD::modifyField('requesting_user_id', ['value' => $user->id]);
            }
        }
        
        // Campo oculto para la conversión
        if ($convertedFrom) {
            // Establecer el valor del campo oculto que ya está definido en setupCreateFields
            CRUD::modifyField('converted_from_general_request_id', ['value' => $convertedFrom]);
            
            // Mostrar información de la solicitud general
            $generalRequestInfo = '';
            if ($convertedFrom) {
                $generalRequest = \App\Models\GeneralRequest::with(['deliveries.details'])->find($convertedFrom);
                if ($generalRequest) {
                    $productsCount = count($existingProducts);
                    $hasDeliveries = $generalRequest->deliveries && $generalRequest->deliveries->isNotEmpty();
                    $deliveryNote = $hasDeliveries ? ' Solo se muestran los productos con cantidades faltantes (no totalmente entregados).' : '';
                    $generalRequestInfo = '<div class="alert alert-info">
                        <h5><i class="la la-info-circle"></i> Conversión desde Solicitud General</h5>
                        <p><strong>Número:</strong> ' . ($generalRequest->number ?? 'N/A') . '</p>
                        <p><strong>Título:</strong> ' . ($generalRequest->title ?? 'N/A') . '</p>
                        <p><strong>Descripción:</strong> ' . ($generalRequest->description ?? 'N/A') . '</p>
                        <p><strong>Productos:</strong> ' . $productsCount . ' producto(s) con cantidades faltantes cargado(s) desde la solicitud general.' . $deliveryNote . ' Puede editarlos o eliminarlos antes de guardar.</p>
                    </div>';
                }
            }
            
            CRUD::field('general_request_info')->label('Información de Solicitud General')->type('custom_html')
                ->value($generalRequestInfo . '
                <script>
                document.addEventListener("DOMContentLoaded", function() {
                    // Asegurar que el campo oculto tenga el valor correcto
                    var hiddenField = document.querySelector("input[name=\'converted_from_general_request_id\']");
                    if (hiddenField) {
                        hiddenField.value = "' . $convertedFrom . '";
                    } else {
                        // Si no existe, crearlo
                        hiddenField = document.createElement("input");
                        hiddenField.type = "hidden";
                        hiddenField.name = "converted_from_general_request_id";
                        hiddenField.value = "' . $convertedFrom . '";
                        document.querySelector("form").appendChild(hiddenField);
                    }
                });
                </script>');
        }
    }

    /**
     * Define what happens when the Update operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        // Verificar permisos y que el usuario solo pueda editar sus propias solicitudes
        $user = backpack_user();
        if (!$user) {
            abort(403, 'No tienes permiso para editar solicitudes de compra.');
        }
        
        $entry = $this->crud->getCurrentEntry();
        if (!$entry) {
            abort(404, 'Solicitud de compra no encontrada.');
        }
        
        // Verificar roles de administrador
        $isAdminSistema = $user->hasRole('role_admin_sistema', 'backpack');
        $isAdminInstitucion = $user->hasRole('role_admin_institucion', 'backpack');
        $isResponsableCompras = $user->hasRole('role_responsable_compras', 'backpack');
        
        $isOwnRequest = $entry->requesting_user_id == $user->id;
        
        // Verificar si el usuario es responsable de área
        $isResponsableArea = $user && $user->hasRole('role_responsable_area', 'backpack');
        
        // Si es administrador del sistema o responsable de compras, puede editar cualquier solicitud
        if ($isAdminSistema || $isResponsableCompras) {
            // Pueden editar cualquier solicitud
        } elseif ($isAdminInstitucion) {
            // El administrador del instituto solo puede editar sus propias solicitudes
            if (!$isOwnRequest) {
                abort(403, 'Solo puedes editar las solicitudes de compra que creaste.');
            }
        } else {
            // Todos los demás usuarios solo pueden editar sus propias solicitudes
            if (!$isOwnRequest) {
                abort(403, 'Solo puedes editar las solicitudes de compra que creaste.');
            }
            
            // Solo puede editar si el estado es "Pendiente"
            if ($entry->status !== 'Pendiente') {
                abort(403, 'Solo puedes editar solicitudes de compra con estado "Pendiente".');
            }
        }
        
        // Si es responsable de área, solo puede modificar prioridad, justificación y productos
        if ($isResponsableArea) {
            // Campos de solo lectura (información) - se muestran como inputs bloqueados con readonly
            CRUD::field('request_number')->label('Número de Solicitud')
                ->type('text')
                ->default($entry->request_number)
                ->attributes(['readonly' => 'readonly']);
            
            CRUD::field('request_date')->label('Fecha de Solicitud')
                ->type('date')
                ->default($entry->request_date ? $entry->request_date->format('Y-m-d') : '')
                ->attributes(['readonly' => 'readonly']);
            
            // Para el área, mostrar el nombre pero mantener el ID
            CRUD::field('responsibility_area_display')->label('Área de Responsabilidad')
                ->type('text')
                ->default($entry->responsibilityArea ? $entry->responsibilityArea->name : '')
                ->attributes(['readonly' => 'readonly']);
            CRUD::field('responsibility_area_id')->type('hidden')->value($entry->responsibility_area_id);
            
            // Para el usuario, mostrar el nombre pero mantener el ID
            CRUD::field('requesting_user_display')->label('Usuario Solicitante')
                ->type('text')
                ->default($entry->requestingUser ? $entry->requestingUser->name : '')
                ->attributes(['readonly' => 'readonly']);
            CRUD::field('requesting_user_id')->type('hidden')->value($entry->requesting_user_id);
            
            // Campos ocultos que deben mantenerse con sus valores actuales
            CRUD::field('status')->type('hidden')->value($entry->status);
            CRUD::field('total_amount')->type('hidden')->value($entry->total_amount);
            CRUD::field('observations')->type('hidden')->value($entry->observations);
            
            // Campos editables para responsable de área
            CRUD::field('priority')->label('Prioridad')
                ->type('select_from_array')
                ->options([
                    'Baja' => 'Baja',
                    'Media' => 'Media',
                    'Alta' => 'Alta',
                    'Urgente' => 'Urgente'
                ])
                ->default($entry->priority ?? 'Media');
            
            CRUD::field('justification')->label('Justificación')->type('textarea')->default($entry->justification);
            
            // Campo para seleccionar productos - solo si no está aprobada
            if ($entry->status !== 'Aprobada') {
                CRUD::addField([
                    'name' => 'selected_products',
                    'type' => 'hidden',
                    'value' => '[]',
                ]);
                $entry->load('details.product');
                if ($entry->details && $entry->details->count() > 0) {
                    $existingProducts = $entry->details->map(function($detail) {
                        return [
                            'product_id' => $detail->product_id,
                            'product_name' => $detail->product ? $detail->product->name : 'N/A',
                            'unit' => $detail->product ? $detail->product->unit_measurement : '',
                            'description' => $detail->product ? $detail->product->description : '',
                            'quantity' => $detail->requested_quantity ?? 0,
                            'price' => $detail->estimated_unit_price ?? 0,
                            'specifications' => $detail->specifications ?? '',
                            'product_description' => $detail->product_description ?? ''
                        ];
                    })->toArray();
                    
                    CRUD::field('products_selection')->label('Productos Solicitados')->type('custom_html')
                        ->value($this->getProductsSelectionHtml($existingProducts, $entry->responsibility_area_id));
                } else {
                    CRUD::field('products_selection')->label('Productos Solicitados')->type('custom_html')
                        ->value($this->getProductsSelectionHtml([], $entry->responsibility_area_id));
                }
            } else {
                // Si está aprobada, mostrar productos como solo lectura
                $entry->load('details.product');
                CRUD::field('products_selection')->label('Productos Solicitados')->type('custom_html')
                    ->value($this->getProductsReadOnlyHtml($entry));
            }
        } else {
            // Para administradores, usar todos los campos
            $this->setupCreateFields();
            
            // Cargar productos existentes para edición - solo si no está aprobada
            if ($entry && $entry->status !== 'Aprobada') {
                // Cargar la relación con productos
                $entry->load('details.product');
                
                if ($entry->details && $entry->details->count() > 0) {
                    $existingProducts = $entry->details->map(function($detail) {
                        return [
                            'product_id' => $detail->product_id,
                            'product_name' => $detail->product ? $detail->product->name : 'N/A',
                            'unit' => $detail->product ? $detail->product->unit_measurement : '',
                            'description' => $detail->product ? $detail->product->description : '',
                            'quantity' => $detail->requested_quantity ?? 0,
                            'price' => $detail->estimated_unit_price ?? 0,
                            'specifications' => $detail->specifications ?? '',
                            'product_description' => $detail->product_description ?? ''
                        ];
                    })->toArray();
                    
                    // Modificar el campo de productos para incluir los existentes
                    CRUD::modifyField('products_selection', [
                        'value' => $this->getProductsSelectionHtml($existingProducts)
                    ]);
                }
            } elseif ($entry && $entry->status === 'Aprobada') {
                // Si está aprobada, mostrar productos como solo lectura
                $entry->load('details.product');
                CRUD::modifyField('products_selection', [
                    'value' => $this->getProductsReadOnlyHtml($entry)
                ]);
            }
            
            // Agregar campos adicionales para actualización
            // Obtener la entrada actual para verificar el monto
            $entry = $this->crud->getCurrentEntry();
            $user = backpack_user();
            
            // Determinar las opciones de estado disponibles según el usuario y el monto
            $statusOptions = [
                'Pendiente' => 'Pendiente',
                'Rechazada' => 'Rechazada',
                'En Proceso' => 'En Proceso',
                'Completada' => 'Completada'
            ];
            
            // Verificar si el usuario puede aprobar esta solicitud
            $canApprove = false;
            if ($entry && $user) {
                $canApprove = $entry->canBeApprovedBy($user);
            }
            
            // Solo agregar la opción "Aprobada" si el usuario puede aprobar
            if ($canApprove) {
                $statusOptions['Aprobada'] = 'Aprobada';
            }
            
            CRUD::field('status')->label('Estado')
                ->type('select_from_array')
                ->options($statusOptions)
                ->hint($canApprove ? '' : ($entry && $entry->requires_admin_approval ? 'Esta solicitud requiere aprobación del administrador del instituto debido a que supera el límite de autorización.' : ''));
            
            // Solo mostrar campos de aprobación si el usuario puede aprobar
            if ($canApprove) {
                CRUD::field('approved_by')->label('Aprobado por')
                    ->type('select')
                    ->model('App\Models\User')
                    ->attribute('name');
                    
                CRUD::field('approved_date')->label('Fecha de Aprobación')->type('date');
            }
        }
    }

    /**
     * Setup common fields for create and update operations
     */
    private function setupCreateFields()
    {
        CRUD::field('request_number')->label('Número de Solicitud')->default(\App\Models\PurchaseRequest::generateNextNumber())->attributes(['readonly' => 'readonly']);
        
        CRUD::field('request_date')->label('Fecha de Solicitud')->type('date')->default(now()->format('Y-m-d'));
        
        CRUD::field('responsibility_area_id')->label('Área de Responsabilidad')
            ->type('select')
            ->model('App\Models\ResponsibilityArea')
            ->attribute('name')
            ->validationRules('required|exists:responsibility_areas,id');
            
        CRUD::field('requesting_user_id')->label('Usuario Solicitante')
            ->type('select')
            ->model('App\Models\User')
            ->attribute('name')
            ->default(auth()->id() ?? 1)
            ->validationRules('required|exists:users,id');
            
        CRUD::field('priority')->label('Prioridad')
            ->type('select_from_array')
            ->options([
                'Baja' => 'Baja',
                'Media' => 'Media',
                'Alta' => 'Alta',
                'Urgente' => 'Urgente'
            ])
            ->default('Media');
            
        CRUD::field('justification')->label('Justificación')->type('textarea');
        CRUD::field('observations')->label('Observaciones')->type('textarea');
        
        // Campos ocultos con valores por defecto
        CRUD::field('status')->type('hidden')->default('Pendiente');
        CRUD::addField([
            'name' => 'purchase_type',
            'label' => 'Tipo de compra',
            'type' => 'select_from_array',
            'options' => [
                'normal' => 'Normal',
                'internet' => 'Por internet (Mercado Libre, etc.) — la OP se genera al aprobar un nivel superior',
            ],
            'default' => 'normal',
            'hint' => 'Marque "Por internet" si la compra es por Mercado Libre u otro canal online; en ese caso la orden de pago se genera automáticamente al aprobar la solicitud (nivel superior por monto).',
        ]);
        CRUD::field('total_amount')->type('hidden')->default(0);
        
        // Campo oculto para conversión desde solicitud general (se establecerá dinámicamente)
        CRUD::field('converted_from_general_request_id')->type('hidden')->attributes(['name' => 'converted_from_general_request_id']);
        
        // Mismo patrón que GeneralRequest: el input debe existir en el formulario para que el POST siempre incluya la clave
        CRUD::addField([
            'name' => 'selected_products',
            'type' => 'hidden',
            'value' => '[]',
        ]);
        
        // Campo para seleccionar productos
        CRUD::field('products_selection')->label('Productos Solicitados')->type('custom_html')
            ->value('
            <div id="products-container">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="product-select" class="form-label">Seleccionar Producto</label>
                        <select id="product-select" class="form-control">
                            <option value="">Seleccionar un producto...</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="product-quantity" class="form-label">Cantidad</label>
                        <input type="number" id="product-quantity" class="form-control" min="1" value="1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="button" id="add-product-btn" class="btn btn-primary btn-block">
                            <i class="la la-plus"></i> Agregar
                        </button>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-12">
                        <button type="button" id="add-new-product-btn" class="btn btn-success">
                            <i class="la la-plus-circle"></i> Agregar Nuevo Producto
                        </button>
                    </div>
                </div>
                <div id="selected-products-list"></div>
            </div>
            
            <script>
            (function purchaseRequestProductsInitCreate() {
                function init() {
                // Cargar productos existentes
                loadProducts();
                
                // Event listeners
                document.getElementById("add-product-btn").addEventListener("click", addProduct);
                document.getElementById("add-new-product-btn").addEventListener("click", showNewProductModal);
                
                const selectedListEl = document.getElementById("selected-products-list");
                if (selectedListEl) {
                    selectedListEl.addEventListener("click", function(e) {
                        const btn = e.target.closest("button.remove-product");
                        if (!btn) return;
                        e.preventDefault();
                        if (!confirm("¿Eliminar esta línea de productos de la solicitud?")) return;
                        const row = btn.closest(".selected-product-item");
                        if (row) {
                            row.remove();
                            updateHiddenFields();
                        }
                    });
                }
                
                // Función para cargar productos
                function loadProducts() {
                    fetch("' . backpack_url('api/productos') . '")
                        .then(response => response.json())
                        .then(data => {
                            const select = document.getElementById("product-select");
                            select.innerHTML = \'<option value="">Seleccionar un producto...</option>\';
                            data.forEach(product => {
                                const option = document.createElement("option");
                                option.value = product.id;
                                option.textContent = product.name + " (" + product.unit_measurement + ")";
                                option.setAttribute("data-unit", product.unit_measurement);
                                option.setAttribute("data-description", product.description || "");
                                select.appendChild(option);
                            });
                            
                            // Después de cargar productos, verificar si hay productos pre-seleccionados en la URL
                            loadPreSelectedProducts(data);
                        })
                        .catch(error => console.error("Error loading products:", error));
                }
                
                // Función para cargar productos pre-seleccionados desde la URL
                function loadPreSelectedProducts(productsData) {
                    const urlParams = new URLSearchParams(window.location.search);
                    const selectedProductsParam = urlParams.get(\'selected_products\');
                    
                    if (selectedProductsParam) {
                        try {
                            const selectedProducts = JSON.parse(decodeURIComponent(selectedProductsParam));
                            
                            if (Array.isArray(selectedProducts) && selectedProducts.length > 0) {
                                // Crear un mapa de productos por ID para búsqueda rápida
                                const productsMap = {};
                                productsData.forEach(product => {
                                    productsMap[product.id] = product;
                                });
                                
                                // Agregar cada producto pre-seleccionado a la lista
                                selectedProducts.forEach(productData => {
                                    const product = productsMap[productData.product_id];
                                    if (product) {
                                        const quantity = productData.quantity || 1;
                                        const price = productData.price || productData.unit_price || 0;
                                        const specs = productData.specifications || productData.product_description || "";
                                        
                                        addProductToList(
                                            product.id,
                                            product.name + " (" + product.unit_measurement + ")",
                                            product.unit_measurement,
                                            product.description || "",
                                            quantity,
                                            price,
                                            specs
                                        );
                                    }
                                });
                            }
                        } catch (error) {
                            console.error("Error parsing pre-selected products:", error);
                        }
                    }
                }
                
                // Función para agregar producto
                function addProduct() {
                    const select = document.getElementById("product-select");
                    const quantity = document.getElementById("product-quantity");
                    
                    if (!select.value) {
                        alert("Por favor seleccione un producto");
                        return;
                    }
                    
                    if (!quantity.value || quantity.value < 1) {
                        alert("Por favor ingrese una cantidad válida");
                        return;
                    }
                    
                    const selectedOption = select.options[select.selectedIndex];
                    const productId = select.value;
                    const productName = selectedOption.textContent;
                    const unit = selectedOption.getAttribute("data-unit");
                    const description = selectedOption.getAttribute("data-description");
                    
                    addProductToList(productId, productName, unit, description, quantity.value, 0, "");
                    
                    // Limpiar campos
                    select.value = "";
                    quantity.value = 1;
                }
                
                // Función para agregar producto a la lista
                function getProductsForm() {
                    const c = document.getElementById("products-container");
                    if (c) {
                        const f = c.closest("form");
                        if (f) return f;
                    }
                    const opUpdate = document.querySelector("[bp-section=crud-operation-update] form");
                    if (opUpdate) return opUpdate;
                    const opCreate = document.querySelector("[bp-section=crud-operation-create] form");
                    if (opCreate) return opCreate;
                    const mainForm = document.querySelector("main form");
                    if (mainForm) return mainForm;
                    return document.querySelector("form[method=post]");
                }
                
                function escapeHtml(s) {
                    if (s == null || s === undefined) return "";
                    return String(s)
                        .replace(/&/g, "&amp;")
                        .replace(/</g, "&lt;")
                        .replace(/>/g, "&gt;")
                        .replace(/"/g, "&quot;");
                }
                
                function addProductToList(productId, productName, unit, description, quantity, price = 0, specifications = "") {
                    const container = document.getElementById("selected-products-list");
                    const productDiv = document.createElement("div");
                    productDiv.className = "selected-product-item border p-3 mb-2";
                    productDiv.setAttribute("data-product-id", productId);
                    if (typeof productId === "string" && productId.indexOf("new_") === 0) {
                        productDiv.setAttribute("data-new-name", productName || "");
                        productDiv.setAttribute("data-new-unit", unit || "unidad");
                        productDiv.setAttribute("data-new-description", description || "");
                    }
                    
                    const safeName = escapeHtml(productName);
                    const safeDesc = escapeHtml(description);
                    const safeSpecs = escapeHtml(specifications);
                    
                    productDiv.innerHTML = `
                        <div class="row">
                            <div class="col-md-4">
                                <strong>${safeName}</strong>
                                ${description ? `<br><small class="text-muted">${safeDesc}</small>` : ""}
                            </div>
                            <div class="col-md-2">
                                <label>Cantidad:</label>
                                <input type="number" class="form-control product-quantity" value="${quantity}" min="1">
                            </div>
                            <div class="col-md-2">
                                <label>Precio Unit. Est.:</label>
                                <input type="number" class="form-control product-price" step="0.01" min="0" value="${price}">
                            </div>
                            <div class="col-md-3">
                                <label>Descripción / Especificaciones:</label>
                                <textarea class="form-control product-specs" rows="2" placeholder="Describa el producto o indique especificaciones...">${safeSpecs}</textarea>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-danger btn-sm remove-product">
                                    <i class="la la-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    
                    container.appendChild(productDiv);
                    
                    // Event listeners para actualizar totales
                    productDiv.querySelector(".product-quantity").addEventListener("input", updateTotals);
                    productDiv.querySelector(".product-price").addEventListener("input", updateTotals);
                    productDiv.querySelector(".product-specs").addEventListener("input", updateTotals);
                    
                    updateHiddenFields();
                }
                
                // Función para actualizar campos ocultos
                function updateHiddenFields() {
                    const form = getProductsForm();
                    if (!form) {
                        console.error("No se encontró el formulario de solicitud de compra (products-container).");
                        return;
                    }
                    const pc = document.getElementById("products-container");
                    const products = [];
                    (pc ? pc.querySelectorAll(".selected-product-item") : []).forEach(item => {
                        const productId = item.getAttribute("data-product-id");
                        const quantity = item.querySelector(".product-quantity").value;
                        const price = item.querySelector(".product-price").value;
                        const specs = item.querySelector(".product-specs").value;
                        
                        const row = {
                            product_id: productId,
                            quantity: quantity,
                            price: price,
                            specifications: specs
                        };
                        if (typeof productId === "string" && productId.indexOf("new_") === 0) {
                            row.name = item.getAttribute("data-new-name") || "";
                            row.unit = item.getAttribute("data-new-unit") || "unidad";
                            row.description = item.getAttribute("data-new-description") || "";
                            row.product_description = specs || item.getAttribute("data-new-description") || "";
                        }
                        products.push(row);
                    });
                    
                    const json = JSON.stringify(products);
                    const hiddens = form.querySelectorAll("input[name=\'selected_products\']");
                    if (hiddens.length === 0) {
                        const h = document.createElement("input");
                        h.type = "hidden";
                        h.name = "selected_products";
                        h.value = json;
                        form.appendChild(h);
                    } else {
                        const arr = Array.from(hiddens);
                        arr.forEach(el => { el.value = json; });
                        arr.slice(1).forEach(el => el.remove());
                    }
                }
                
                // Función para actualizar totales
                function updateTotals() {
                    updateHiddenFields();
                }
                
                // Función para mostrar modal de nuevo producto
                function showNewProductModal() {
                    const productName = prompt("Nombre del nuevo producto:");
                    if (!productName) return;
                    
                    const productUnit = prompt("Unidad del producto (ej: kg, litros, unidades):");
                    if (!productUnit) return;
                    
                    const productDescription = prompt("Descripción / Especificaciones (opcional):") || "";
                    
                    const tempId = "new_" + Date.now();
                    addProductToList(tempId, productName, productUnit, productDescription, 1, 0, productDescription);
                }

                const __prForm = getProductsForm();
                if (__prForm && !__prForm.dataset.prProductsSyncBound) {
                    __prForm.dataset.prProductsSyncBound = "1";
                    __prForm.addEventListener("submit", function() { updateHiddenFields(); }, true);
                }
                }
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", init, { once: true });
                } else {
                    init();
                }
            })();
            </script>
            ');
    }
    
    /**
     * Get products filtered by responsibility area
     * Muestra solo productos de las categorías relacionadas con el área
     */
    public function getProductosByArea()
    {
        $areaId = request()->get('area_id');
        $query = \App\Models\Product::with(['category', 'stockLevels']);
        
        if ($areaId) {
            // Obtener el área
            $area = \App\Models\ResponsibilityArea::find($areaId);
            
            if ($area) {
                // Mapeo de áreas a categorías permitidas
                $areaCategoryMap = [
                    'Informática' => ['Equipos Informáticos', 'Software'],
                    'Salud' => ['Material Médico', 'Reactivos'],
                    'Insumos de Salud' => ['Material Médico', 'Reactivos'],
                    'Mantenimiento' => ['Herramientas', 'Repuestos', 'Limpieza'],
                    'Insumos Generales' => ['Material de Oficina', 'Limpieza', 'Insumos Generales'],
                ];
                
                // Obtener las categorías permitidas para esta área
                $areaName = $area->name;
                if (isset($areaCategoryMap[$areaName])) {
                    $allowedCategoryNames = $areaCategoryMap[$areaName];
                    
                    // Obtener los IDs de las categorías permitidas
                    $categoryIds = \App\Models\Category::whereIn('name', $allowedCategoryNames)
                        ->pluck('id');
                    
                    if ($categoryIds->isNotEmpty()) {
                        // Filtrar productos por las categorías permitidas
                        $query->whereIn('category_id', $categoryIds);
                    } else {
                        // Si no hay categorías relacionadas, no mostrar ningún producto
                        $query->where('id', 0);
                    }
                } else {
                    // Si el área no está en el mapeo, no mostrar ningún producto
                    $query->where('id', 0);
                }
            } else {
                // Si no existe el área, no mostrar ningún producto
                $query->where('id', 0);
            }
        }
        
        // Obtener productos filtrados
        $productos = $query->get()
            ->map(function($producto) {
                $stockTotal = $producto->stockLevels->sum('quantity');
                return [
                    'id' => $producto->id,
                    'name' => $producto->name,
                    'description' => $producto->description,
                    'category_name' => $producto->category->name ?? 'Sin categoría',
                    'unit_measurement' => $producto->unit_measurement,
                    'stock_total' => $stockTotal,
                    'minimum_stock' => $producto->minimum_stock,
                ];
            })
            ->values();

        return response()->json($productos);
    }
    
    /**
     * Generate HTML for products selection with existing products
     */
    private function getProductsSelectionHtml($existingProducts = [], $areaId = null)
    {
        $existingProductsJson = json_encode($existingProducts);
        
        return '
        <div id="products-container">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="product-select" class="form-label">Seleccionar Producto</label>
                    <select id="product-select" class="form-control">
                        <option value="">Seleccionar un producto...</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="product-quantity" class="form-label">Cantidad</label>
                    <input type="number" id="product-quantity" class="form-control" min="1" value="1">
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button type="button" id="add-product-btn" class="btn btn-primary btn-block">
                        <i class="la la-plus"></i> Agregar
                    </button>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-12">
                    <button type="button" id="add-new-product-btn" class="btn btn-success">
                        <i class="la la-plus-circle"></i> Agregar Nuevo Producto
                    </button>
                </div>
            </div>
            <div id="selected-products-list"></div>
        </div>
        
        <script>
        (function purchaseRequestProductsInitEdit() {
            function init() {
            const existingProducts = ' . $existingProductsJson . ';
            
            // Cargar productos existentes
            loadProducts();
            
            // Cargar productos existentes en la lista
            if (existingProducts && existingProducts.length > 0) {
                existingProducts.forEach(product => {
                    const specs = product.specifications || product.product_description || "";
                    addProductToList(
                        product.product_id, 
                        product.product_name + " (" + product.unit + ")", 
                        product.unit, 
                        product.description, 
                        product.quantity,
                        product.price,
                        specs,
                        product.minimum_stock || 0,
                        product.stock_total || 0
                    );
                });
            } else {
                updateHiddenFields();
            }
            
            // Event listeners
            document.getElementById("add-product-btn").addEventListener("click", addProduct);
            document.getElementById("add-new-product-btn").addEventListener("click", showNewProductModal);
            
            const selectedListEl = document.getElementById("selected-products-list");
            if (selectedListEl) {
                selectedListEl.addEventListener("click", function(e) {
                    const btn = e.target.closest("button.remove-product");
                    if (!btn) return;
                    e.preventDefault();
                    if (!confirm("¿Eliminar esta línea de productos de la solicitud?")) return;
                    const row = btn.closest(".selected-product-item");
                    if (row) {
                        row.remove();
                        updateHiddenFields();
                    }
                });
            }
            
            // Función para cargar productos
            function loadProducts() {
                const areaId = ' . ($areaId ? $areaId : 'null') . ';
                const url = areaId ? "' . backpack_url('api/productos-por-area') . '?area_id=" + areaId : "' . backpack_url('api/productos') . '";
                
                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        const select = document.getElementById("product-select");
                        select.innerHTML = \'<option value="">Seleccionar un producto...</option>\';
                        data.forEach(product => {
                            const option = document.createElement("option");
                            option.value = product.id;
                            option.textContent = product.name + " (" + product.unit_measurement + ")";
                            option.setAttribute("data-unit", product.unit_measurement);
                            option.setAttribute("data-description", product.description || "");
                            option.setAttribute("data-minimum-stock", product.minimum_stock || 0);
                            option.setAttribute("data-stock-total", product.stock_total || 0);
                            select.appendChild(option);
                        });
                    })
                    .catch(error => console.error("Error loading products:", error));
            }
            
            // Función para agregar producto
            function addProduct() {
                const select = document.getElementById("product-select");
                const quantity = document.getElementById("product-quantity");
                
                if (!select.value) {
                    alert("Por favor seleccione un producto");
                    return;
                }
                
                if (!quantity.value || quantity.value < 1) {
                    alert("Por favor ingrese una cantidad válida");
                    return;
                }
                
                const selectedOption = select.options[select.selectedIndex];
                const productId = select.value;
                const productName = selectedOption.textContent;
                const unit = selectedOption.getAttribute("data-unit");
                const description = selectedOption.getAttribute("data-description");
                const minimumStock = parseFloat(selectedOption.getAttribute("data-minimum-stock")) || 0;
                const stockTotal = parseFloat(selectedOption.getAttribute("data-stock-total")) || 0;
                
                addProductToList(productId, productName, unit, description, quantity.value, 0, "", minimumStock, stockTotal);
                
                // Limpiar campos
                select.value = "";
                quantity.value = 1;
            }
            
            function getProductsForm() {
                const c = document.getElementById("products-container");
                if (c) {
                    const f = c.closest("form");
                    if (f) return f;
                }
                const opUpdate = document.querySelector("[bp-section=crud-operation-update] form");
                if (opUpdate) return opUpdate;
                const opCreate = document.querySelector("[bp-section=crud-operation-create] form");
                if (opCreate) return opCreate;
                const mainForm = document.querySelector("main form");
                if (mainForm) return mainForm;
                return document.querySelector("form[method=post]");
            }
            
            function escapeHtml(s) {
                if (s == null || s === undefined) return "";
                return String(s)
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;");
            }
            
            // Función para agregar producto a la lista
            function addProductToList(productId, productName, unit, description, quantity, price = 0, specifications = "", minimumStock = 0, stockTotal = 0) {
                const container = document.getElementById("selected-products-list");
                const productDiv = document.createElement("div");
                productDiv.className = "selected-product-item border p-3 mb-2";
                productDiv.setAttribute("data-product-id", productId);
                if (typeof productId === "string" && productId.indexOf("new_") === 0) {
                    productDiv.setAttribute("data-new-name", productName || "");
                    productDiv.setAttribute("data-new-unit", unit || "unidad");
                    productDiv.setAttribute("data-new-description", description || "");
                }
                
                // Calcular cantidad sugerida (cantidad solicitada + stock mínimo)
                const suggestedQuantity = parseFloat(quantity) + (minimumStock > 0 ? minimumStock : 0);
                const showStockMinSuggestion = minimumStock > 0;
                
                const safeName = escapeHtml(productName);
                const safeDesc = escapeHtml(description);
                const safeSpecs = escapeHtml(specifications);
                const safeUnit = escapeHtml(unit);
                
                productDiv.innerHTML = `
                    <div class="row">
                        <div class="col-md-4">
                            <strong>${safeName}</strong>
                            ${description ? `<br><small class="text-muted">${safeDesc}</small>` : ""}
                            ${showStockMinSuggestion ? `<br><small class="text-info"><i class="la la-info-circle"></i> Stock actual: ${stockTotal} | Stock mínimo: ${minimumStock}</small>` : ""}
                        </div>
                        <div class="col-md-2">
                            <label>Cantidad:</label>
                            <input type="number" class="form-control product-quantity" value="${quantity}" min="1">
                            ${showStockMinSuggestion ? `
                                <small class="text-muted d-block mt-1">
                                    <a href="#" class="add-stock-min-link" style="color: #17a2b8; text-decoration: none;">
                                        <i class="la la-plus-circle"></i> Incluir stock mínimo (+${minimumStock})
                                    </a>
                                </small>
                                <small class="text-success d-block mt-1" style="display: none;" id="suggested-${productId}">
                                    Sugerido: ${suggestedQuantity} ${safeUnit}
                                </small>
                            ` : ""}
                        </div>
                        <div class="col-md-2">
                            <label>Precio Unit. Est.:</label>
                            <input type="number" class="form-control product-price" step="0.01" min="0" value="${price}">
                        </div>
                        <div class="col-md-3">
                            <label>Descripción / Especificaciones:</label>
                            <textarea class="form-control product-specs" rows="2" placeholder="Describa el producto o indique especificaciones...">${safeSpecs}</textarea>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-danger btn-sm remove-product">
                                <i class="la la-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
                
                container.appendChild(productDiv);
                
                // Event listener para agregar stock mínimo
                const addStockMinLink = productDiv.querySelector(".add-stock-min-link");
                if (addStockMinLink && showStockMinSuggestion) {
                    addStockMinLink.addEventListener("click", function(e) {
                        e.preventDefault();
                        const quantityInput = productDiv.querySelector(".product-quantity");
                        const currentQuantity = parseFloat(quantityInput.value) || 0;
                        const newQuantity = currentQuantity + minimumStock;
                        quantityInput.value = newQuantity;
                        
                        // Mostrar mensaje de sugerencia
                        const suggestedMsg = productDiv.querySelector(`#suggested-${productId}`);
                        if (suggestedMsg) {
                            suggestedMsg.textContent = `Total: ${newQuantity} ${unit}`;
                            suggestedMsg.style.display = "block";
                        }
                        
                        // Ocultar el enlace después de usarlo
                        addStockMinLink.style.display = "none";
                        
                        updateTotals();
                    });
                }
                
                // Event listeners para actualizar totales
                productDiv.querySelector(".product-quantity").addEventListener("input", updateTotals);
                productDiv.querySelector(".product-price").addEventListener("input", updateTotals);
                productDiv.querySelector(".product-specs").addEventListener("input", updateTotals);
                
                updateHiddenFields();
            }
            
            // Función para actualizar campos ocultos
            function updateHiddenFields() {
                const form = getProductsForm();
                if (!form) {
                    console.error("No se encontró el formulario de solicitud de compra (products-container).");
                    return;
                }
                const pc = document.getElementById("products-container");
                const products = [];
                (pc ? pc.querySelectorAll(".selected-product-item") : []).forEach(item => {
                    const productId = item.getAttribute("data-product-id");
                    const quantity = item.querySelector(".product-quantity").value;
                    const price = item.querySelector(".product-price").value;
                    const specs = item.querySelector(".product-specs").value;
                    
                    const row = {
                        product_id: productId,
                        quantity: quantity,
                        price: price,
                        specifications: specs
                    };
                    if (typeof productId === "string" && productId.indexOf("new_") === 0) {
                        row.name = item.getAttribute("data-new-name") || "";
                        row.unit = item.getAttribute("data-new-unit") || "unidad";
                        row.description = item.getAttribute("data-new-description") || "";
                        row.product_description = specs || item.getAttribute("data-new-description") || "";
                    }
                    products.push(row);
                });
                
                const json = JSON.stringify(products);
                const hiddens = form.querySelectorAll("input[name=\'selected_products\']");
                if (hiddens.length === 0) {
                    const h = document.createElement("input");
                    h.type = "hidden";
                    h.name = "selected_products";
                    h.value = json;
                    form.appendChild(h);
                } else {
                    const arr = Array.from(hiddens);
                    arr.forEach(el => { el.value = json; });
                    arr.slice(1).forEach(el => el.remove());
                }
            }
            
            // Función para actualizar totales
            function updateTotals() {
                updateHiddenFields();
            }
            
            // Función para mostrar modal de nuevo producto
            function showNewProductModal() {
                const productName = prompt("Nombre del nuevo producto:");
                if (!productName) return;
                
                const productUnit = prompt("Unidad del producto (ej: kg, litros, unidades):");
                if (!productUnit) return;
                
                const productDescription = prompt("Descripción / Especificaciones (opcional):") || "";
                
                const tempId = "new_" + Date.now();
                addProductToList(tempId, productName, productUnit, productDescription, 1, 0, productDescription, 0, 0);
            }

            const __prFormEdit = getProductsForm();
            if (__prFormEdit && !__prFormEdit.dataset.prProductsSyncBound) {
                __prFormEdit.dataset.prProductsSyncBound = "1";
                __prFormEdit.addEventListener("submit", function() { updateHiddenFields(); }, true);
            }
            }
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", init, { once: true });
            } else {
                init();
            }
        })();
        </script>
        ';
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store()
    {
        $this->crud->hasAccessOrFail('create');

        // execute the FormRequest authorization and validation, if one is required
        $request = $this->crud->validateRequest();

        // register any Model Events defined on fields
        $this->crud->registerFieldEvents();

        // Obtener datos para guardar
        $dataToSave = $this->crud->getStrippedSaveRequest($request);
        
        // Verificar si viene de una solicitud general desde el parámetro URL o del request
        $convertedFrom = request()->get('converted_from') ?? $request->input('converted_from_general_request_id');
        
        // Validar permisos para convertir solicitudes generales
        $user = backpack_user();
        if ($convertedFrom && $user) {
            // Validar que el usuario personal no pueda convertir
            if ($user->hasRole('role_personal', 'backpack')) {
                \Alert::error('No tienes permisos para convertir solicitudes generales a solicitudes de compra.')->flash();
                return redirect()->back();
            }
            
            // Validar que el responsable de área solo pueda convertir solicitudes de su área
            if ($user->hasRole('role_responsable_area', 'backpack')) {
                $generalRequest = \App\Models\GeneralRequest::find($convertedFrom);
                if ($generalRequest) {
                    $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('id');
                    
                    // Solo puede convertir si la solicitud pertenece a su área
                    // NO puede convertir solicitudes que él creó para otras áreas
                    if (!$generalRequest->area_id || !$userAreas->contains($generalRequest->area_id)) {
                        \Alert::error('Solo puedes convertir a compra las solicitudes que pertenecen a tu depósito/área.')->flash();
                        return redirect()->back();
                    }
                }
            }
        }
        
        \Log::info('Parámetro converted_from desde URL:', ['converted_from' => request()->get('converted_from')]);
        \Log::info('Campo converted_from_general_request_id en request:', ['field' => $request->input('converted_from_general_request_id')]);
        \Log::info('Campo converted_from_general_request_id en datos:', ['field' => $dataToSave['converted_from_general_request_id'] ?? 'no existe']);
        
        // Si viene de una conversión, asegurar que se guarde el ID
        if ($convertedFrom) {
            $dataToSave['converted_from_general_request_id'] = $convertedFrom;
            \Log::info('Agregado converted_from_general_request_id a datos:', ['id' => $convertedFrom]);
        }

        // Asegurar que requesting_user_id sea el usuario logueado (especialmente para role_responsable_area)
        if ($user) {
            $dataToSave['requesting_user_id'] = $user->id;
            \Log::info('Establecido requesting_user_id al usuario logueado:', ['user_id' => $user->id, 'email' => $user->email]);
        }

        // Asegurar que los campos requeridos tengan valores por defecto
        if (!isset($dataToSave['status'])) {
            $dataToSave['status'] = 'Pendiente';
        }
        if (!isset($dataToSave['priority'])) {
            $dataToSave['priority'] = 'Media';
        }
        if (!isset($dataToSave['total_amount'])) {
            $dataToSave['total_amount'] = 0;
        }

        // Debug: Log los datos que se van a guardar
        \Log::info('Datos a guardar en PurchaseRequest:', $dataToSave);

        try {
            // insert item in the db
            $item = $this->crud->create($dataToSave);
            $this->data['entry'] = $this->crud->entry = $item;
            
            // Recargar el item para asegurar que tiene el converted_from_general_request_id
            $item->refresh();
            \Log::info('Item creado:', ['id' => $item->id, 'converted_from_general_request_id' => $item->converted_from_general_request_id]);

            // Verificar si el usuario seleccionó productos manualmente
            $selectedProducts = $request->input('selected_products');
            $hasManualProducts = !empty($selectedProducts) && $selectedProducts !== '[]';

            // Si viene de una solicitud general y NO hay productos seleccionados manualmente,
            // replicar automáticamente los productos de la solicitud general
            if ($item->converted_from_general_request_id && !$hasManualProducts) {
                \Log::info('Replicando productos desde solicitud general');
                $this->replicateProductsFromGeneralRequest($item);
            }

            // Procesar productos seleccionados manualmente (si el usuario los seleccionó)
            if ($hasManualProducts) {
                \Log::info('Procesando productos seleccionados manualmente');
                $this->processSelectedProducts($item, $request);
            }
            
            // Verificar si requiere aprobación de administrador después de calcular el total
            $item->refresh();
            if ($item->total_amount > 0) {
                $comprasLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_responsable_compras');
                $requiresAdminApproval = $item->total_amount > $comprasLimit;
                $item->update(['requires_admin_approval' => $requiresAdminApproval]);
            }

            // show a success message
            \Alert::success(trans('backpack::crud.insert_success'))->flash();

            // save the redirect choice for next time
            $this->crud->setSaveAction();

            // Actualizar estado de la solicitud general si viene de una conversión
            if ($item->converted_from_general_request_id) {
                \Log::info('Intentando actualizar solicitud general:', ['id' => $item->converted_from_general_request_id]);
                $generalRequest = \App\Models\GeneralRequest::with('details.product', 'deliveries.details')
                    ->find($item->converted_from_general_request_id);
                if ($generalRequest) {
                    // Verificar si tiene entregas para determinar el estado correcto
                    $hasAnyDelivery = false;
                    $allDelivered = true;
                    $hasDetails = false;
                    
                    // Verificar el estado de entrega de cada producto
                    foreach ($generalRequest->details as $detail) {
                        $requestedQty = $detail->requested_quantity ?? 0;
                        
                        if ($requestedQty <= 0) {
                            continue;
                        }
                        
                        $hasDetails = true;
                        
                        // Calcular cantidad entregada
                        $deliveredQty = 0;
                        foreach ($generalRequest->deliveries as $delivery) {
                            $deliveryDetail = $delivery->details->where('product_id', $detail->product_id)->first();
                            if ($deliveryDetail) {
                                $deliveredQty += $deliveryDetail->delivered_quantity ?? 0;
                            }
                        }
                        
                        if ($deliveredQty > 0) {
                            $hasAnyDelivery = true;
                        }
                        
                        // Si este producto no está completamente entregado, entonces no todos están entregados
                        if ($deliveredQty < $requestedQty) {
                            $allDelivered = false;
                        }
                    }
                    
                    // Determinar el estado según las entregas
                    $newStatus = 'revisada_area'; // Por defecto, si no hay entregas
                    
                    if ($hasDetails && $hasAnyDelivery) {
                        // Si hay entregas, determinar si es parcial o total
                        if ($allDelivered) {
                            $newStatus = 'entregada_totalmente';
                        } else {
                            $newStatus = 'entregada_parcialmente';
                        }
                    }
                    
                    // No cambiar el estado si está archivada
                    if ($generalRequest->status === 'archivada') {
                        $newStatus = 'archivada';
                    }
                    
                    // Actualizar la solicitud general
                    $generalRequest->update([
                        'is_converted' => true,
                        'status' => $newStatus
                    ]);
                    
                    \Log::info('Solicitud general actualizada exitosamente:', [
                        'id' => $generalRequest->id,
                        'is_converted' => $generalRequest->is_converted,
                        'status' => $newStatus,
                        'has_deliveries' => $hasAnyDelivery
                    ]);
                    
                    \Alert::info('La solicitud general ' . $generalRequest->number . ' ha sido marcada como convertida a compra y su estado ha sido actualizado a: ' . ucfirst(str_replace('_', ' ', $newStatus)) . '.')->flash();
                } else {
                    \Log::error('No se encontró la solicitud general con ID:', ['id' => $item->converted_from_general_request_id]);
                }
            } else {
                \Log::info('No hay converted_from_general_request_id en el item guardado');
            }

            return $this->crud->performSaveAction($item->getKey());
        } catch (\Exception $e) {
            \Log::error('Error al guardar PurchaseRequest: ' . $e->getMessage());
            \Alert::error('Error al guardar la solicitud de compra: ' . $e->getMessage())->flash();
            return redirect()->back()->withInput();
        }
    }

    /**
     * Replicar productos desde la solicitud general a la solicitud de compra
     */
    private function replicateProductsFromGeneralRequest($purchaseRequest)
    {
        try {
            // Cargar la solicitud general con detalles, productos y entregas
            $generalRequest = \App\Models\GeneralRequest::with(['details.product', 'deliveries.details'])
                ->find($purchaseRequest->converted_from_general_request_id);

            if (!$generalRequest) {
                \Log::warning('Solicitud general no encontrada para replicar productos', [
                    'general_request_id' => $purchaseRequest->converted_from_general_request_id
                ]);
                return;
            }

            // Verificar si ya hay detalles en la solicitud de compra
            $existingDetailsCount = \App\Models\PurchaseRequestDetail::where('purchase_request_id', $purchaseRequest->id)->count();
            
            if ($existingDetailsCount > 0) {
                \Log::info('La solicitud de compra ya tiene productos. No se replicarán automáticamente.', [
                    'purchase_request_id' => $purchaseRequest->id,
                    'existing_details_count' => $existingDetailsCount
                ]);
                return;
            }

            $totalAmount = 0;
            $replicatedCount = 0;

            // Replicar cada detalle de la solicitud general
            // Solo incluir productos con cantidades faltantes (no totalmente entregados)
            foreach ($generalRequest->details as $generalDetail) {
                if (!$generalDetail->product) {
                    \Log::warning('Producto no encontrado en detalle de solicitud general', [
                        'general_request_detail_id' => $generalDetail->id
                    ]);
                    continue;
                }

                // Calcular cantidad entregada
                $deliveredQuantity = 0;
                if ($generalRequest->deliveries) {
                    foreach ($generalRequest->deliveries as $delivery) {
                        $deliveryDetail = $delivery->details->where('product_id', $generalDetail->product_id)->first();
                        if ($deliveryDetail) {
                            $deliveredQuantity += $deliveryDetail->delivered_quantity ?? 0;
                        }
                    }
                }
                
                // Calcular cantidad faltante
                $requestedQuantity = (float)($generalDetail->requested_quantity ?? 0);
                $pendingQuantity = max(0, $requestedQuantity - $deliveredQuantity);
                
                // Solo replicar productos con cantidad faltante > 0
                if ($pendingQuantity <= 0) {
                    \Log::info('Producto omitido porque ya está totalmente entregado', [
                        'general_request_detail_id' => $generalDetail->id,
                        'product_id' => $generalDetail->product_id,
                        'requested_quantity' => $requestedQuantity,
                        'delivered_quantity' => $deliveredQuantity
                    ]);
                    continue;
                }

                // Crear el detalle en la solicitud de compra con la cantidad faltante
                // Los precios se establecen en 0, ya que el sector de compras los asignará después
                $purchaseRequestDetail = \App\Models\PurchaseRequestDetail::create([
                    'purchase_request_id' => $purchaseRequest->id,
                    'product_id' => $generalDetail->product_id,
                    'requested_quantity' => $pendingQuantity, // Usar cantidad faltante en lugar de solicitada
                    'specifications' => $generalDetail->specifications,
                    'justification' => $generalDetail->justification,
                    'estimated_unit_price' => 0, // Precio inicial en 0, el sector de compras lo asignará
                    'estimated_total' => 0, // Total inicial en 0
                    'status' => 'Pendiente'
                ]);

                $totalAmount += $purchaseRequestDetail->estimated_total;
                $replicatedCount++;

                \Log::info('Producto replicado desde solicitud general (solo cantidad faltante)', [
                    'general_request_detail_id' => $generalDetail->id,
                    'purchase_request_detail_id' => $purchaseRequestDetail->id,
                    'product_id' => $generalDetail->product_id,
                    'product_name' => $generalDetail->product->name ?? 'N/A',
                    'requested_quantity' => $requestedQuantity,
                    'delivered_quantity' => $deliveredQuantity,
                    'pending_quantity' => $pendingQuantity
                ]);
            }

            // Actualizar el monto total de la solicitud de compra (incluso si es 0)
            $purchaseRequest->update(['total_amount' => $totalAmount]);

            \Log::info('Productos replicados exitosamente desde solicitud general', [
                'general_request_id' => $generalRequest->id,
                'purchase_request_id' => $purchaseRequest->id,
                'products_replicated' => $replicatedCount,
                'total_amount' => $totalAmount
            ]);

            \Alert::info($replicatedCount . ' producto(s) replicado(s) desde la solicitud general ' . $generalRequest->number)->flash();

        } catch (\Exception $e) {
            \Log::error('Error al replicar productos desde solicitud general', [
                'purchase_request_id' => $purchaseRequest->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update()
    {
        $this->crud->hasAccessOrFail('update');

        // execute the FormRequest authorization and validation, if one is required
        $request = $this->crud->validateRequest();

        // register any Model Events defined on fields
        $this->crud->registerFieldEvents();

        $user = backpack_user();
        $entry = $this->crud->getCurrentEntry();
        
        if (!$entry) {
            abort(404, 'Solicitud de compra no encontrada.');
        }
        
        // Validar que el usuario solo pueda editar sus propias solicitudes (para role_admin_institucion)
        $isAdminSistema = $user->hasRole('role_admin_sistema', 'backpack');
        $isAdminInstitucion = $user->hasRole('role_admin_institucion', 'backpack');
        $isResponsableCompras = $user->hasRole('role_responsable_compras', 'backpack');
        $isOwnRequest = $entry->requesting_user_id == $user->id;
        
        // Si no es administrador del sistema ni responsable de compras, verificar restricciones
        if (!$isAdminSistema && !$isResponsableCompras) {
            if ($isAdminInstitucion) {
                // El administrador del instituto solo puede editar sus propias solicitudes
                if (!$isOwnRequest) {
                    abort(403, 'Solo puedes editar las solicitudes de compra que creaste.');
                }
            } else {
                // Todos los demás usuarios solo pueden editar sus propias solicitudes
                if (!$isOwnRequest) {
                    abort(403, 'Solo puedes editar las solicitudes de compra que creaste.');
                }
                
                // Solo puede editar si el estado es "Pendiente"
                if ($entry->status !== 'Pendiente') {
                    abort(403, 'Solo puedes editar solicitudes de compra con estado "Pendiente".');
                }
            }
        }

        // Obtener datos para guardar
        $dataToSave = $this->crud->getStrippedSaveRequest($request);

        try {
            // update item in the db
            $item = $this->crud->update($this->crud->getCurrentEntryId(), $dataToSave);
            $this->data['entry'] = $this->crud->entry = $item;

            // Procesar productos seleccionados (eliminar existentes y crear nuevos) - solo si no está aprobada
            if ($entry->status !== 'Aprobada' && $request->has('selected_products')) {
                \Log::info('Procesando productos en actualización:', ['selected_products' => $request->input('selected_products')]);
                $item->details()->delete();
                $this->processSelectedProducts($item, $request, true);
                $item->refresh();
                $this->pruneOrphanQuoteDetailsForPurchaseRequest($item);
            } elseif ($entry->status === 'Aprobada') {
                // Si está aprobada, no permitir modificar productos
                \Alert::warning('No se pueden modificar los productos de una solicitud aprobada.')->flash();
            }
            
            // Verificar si requiere aprobación de administrador después de actualizar el total
            $item->refresh();
            if ($item->total_amount > 0 && $item->status === 'Pendiente') {
                $comprasLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_responsable_compras');
                $requiresAdminApproval = $item->total_amount > $comprasLimit;
                $item->update(['requires_admin_approval' => $requiresAdminApproval]);
            }

            // show a success message
            \Alert::success(trans('backpack::crud.update_success'))->flash();

            // save the redirect choice for next time
            $this->crud->setSaveAction();

            return $this->crud->performSaveAction($item->getKey());
        } catch (\Exception $e) {
            \Log::error('Error al actualizar PurchaseRequest: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            \Alert::error('Error al actualizar la solicitud de compra: ' . $e->getMessage())->flash();
            return redirect()->back()->withInput();
        }
    }

    /**
     * Define what happens when the Delete operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-delete
     * @return void
     */
    protected function setupDeleteOperation()
    {
        // Bloquear eliminación para role_admin_institucion
        $user = backpack_user();
        if ($user && $user->hasRole('role_admin_institucion', 'backpack')) {
            abort(403, 'No tienes permiso para eliminar solicitudes de compra.');
        }

        $entry = $this->crud->getCurrentEntry();
        if ($entry instanceof \App\Models\PurchaseRequest && $entry->deletionIsForbidden()) {
            abort(403, 'No se puede eliminar una solicitud de compra que ya fue aprobada, está en proceso o está completada.');
        }
    }
    
    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $this->crud->hasAccessOrFail('delete');
        
        // Bloquear eliminación para role_admin_institucion
        $user = backpack_user();
        if ($user && $user->hasRole('role_admin_institucion', 'backpack')) {
            abort(403, 'No tienes permiso para eliminar solicitudes de compra.');
        }

        $entry = \App\Models\PurchaseRequest::find($id);
        if ($entry && $entry->deletionIsForbidden()) {
            $message = 'No se puede eliminar una solicitud de compra que ya fue aprobada, está en proceso o está completada.';
            if (request()->ajax()) {
                return response()->json(['error' => [$message]]);
            }
            \Alert::error($message)->flash();
            return redirect()->back();
        }
        
        return $this->crud->delete($id);
    }

    /**
     * Get HTML for displaying products as read-only
     */
    private function getProductsReadOnlyHtml($entry)
    {
        $entry->load('details.product');
        
        if (!$entry->details || $entry->details->count() === 0) {
            return '<div class="alert alert-info">No hay productos seleccionados.</div>';
        }
        
        $html = '<div class="table-responsive">';
        $html .= '<table class="table table-bordered table-striped">';
        $html .= '<thead class="thead-dark">';
        $html .= '<tr>';
        $html .= '<th>Producto</th>';
        $html .= '<th>Unidad</th>';
        $html .= '<th>Cantidad</th>';
        $html .= '<th>Precio Unitario</th>';
        $html .= '<th>Subtotal</th>';
        $html .= '<th>Descripción / Especificaciones</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        
        $total = 0;
        foreach ($entry->details as $detail) {
            $productName = $detail->product ? $detail->product->name : 'N/A';
            $unit = $detail->product ? $detail->product->unit_measurement : '';
            $quantity = $detail->requested_quantity ?? 0;
            $price = $detail->estimated_unit_price ?? 0;
            $subtotal = $quantity * $price;
            $total += $subtotal;
            $descSpecs = $detail->specifications ?? $detail->product_description ?? '';
            
            $html .= '<tr>';
            $html .= '<td>' . e($productName) . '</td>';
            $html .= '<td>' . e($unit) . '</td>';
            $html .= '<td class="text-right">' . number_format($quantity, 2) . '</td>';
            $html .= '<td class="text-right">$' . number_format($price, 2) . '</td>';
            $html .= '<td class="text-right">$' . number_format($subtotal, 2) . '</td>';
            $html .= '<td><small>' . ($descSpecs ? nl2br(e($descSpecs)) : '-') . '</small></td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '<tfoot>';
        $html .= '<tr class="font-weight-bold">';
        $html .= '<td colspan="4" class="text-right">Total:</td>';
        $html .= '<td class="text-right">$' . number_format($total, 2) . '</td>';
        $html .= '<td></td>';
        $html .= '</tr>';
        $html .= '</tfoot>';
        $html .= '</table>';
        $html .= '</div>';
        $html .= '<div class="alert alert-warning mt-2">';
        $html .= '<i class="la la-lock"></i> <strong>Nota:</strong> Los productos no pueden ser modificados porque la solicitud está aprobada.';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Process selected products and create purchase request details
     */
    private function processSelectedProducts($purchaseRequest, $request, $isUpdate = false)
    {
        $selectedProducts = $request->input('selected_products');
        
        if (!$selectedProducts || $selectedProducts === '[]' || $selectedProducts === '') {
            \Log::info('No hay productos seleccionados');
            $this->resetPurchaseRequestTotalsAfterProductSync($purchaseRequest);

            return;
        }
        
        // Si ya es un array, usarlo directamente, sino decodificar JSON
        if (is_array($selectedProducts)) {
            $products = $selectedProducts;
        } else {
            $products = json_decode($selectedProducts, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                \Log::error('Error al decodificar JSON de productos:', [
                    'json_error' => json_last_error_msg(),
                    'raw_value' => $selectedProducts
                ]);
                $this->resetPurchaseRequestTotalsAfterProductSync($purchaseRequest);

                return;
            }
        }
        
        if (!$products || !is_array($products) || empty($products)) {
            \Log::warning('Productos seleccionados está vacío o no es un array válido');
            $this->resetPurchaseRequestTotalsAfterProductSync($purchaseRequest);

            return;
        }
        
        \Log::info('Productos a procesar:', ['count' => count($products), 'products' => $products]);
        
        $totalAmount = 0;
        
        foreach ($products as $productData) {
            if (!isset($productData['product_id'])) {
                \Log::warning('Producto sin product_id', ['data' => $productData]);
                continue;
            }
            
            $productIdRaw = $productData['product_id'] ?? null;
            // Convertir a números para evitar errores de multiplicación
            $quantity = (float)($productData['quantity'] ?? 0);
            $price = (float)($productData['price'] ?? 0);
            $specifications = $productData['specifications'] ?? '';
            // Unificado: descripción/especificaciones se guarda en ambos campos para compatibilidad
            $productDescription = $productData['product_description'] ?? $specifications;

            $isNewProduct = is_string($productIdRaw) && str_starts_with($productIdRaw, 'new_');

            if ($isNewProduct) {
                $name = trim((string) ($productData['name'] ?? ''));
                $specTrim = trim((string) $specifications);
                if ($name === '' || preg_match('/^producto\s+nuevo$/iu', $name)) {
                    $name = $specTrim !== '' ? Str::limit($specTrim, 255, '') : 'Producto Nuevo';
                }
                $description = trim((string) ($productData['description'] ?? $productData['product_description'] ?? $specTrim));
                $unit = trim((string) ($productData['unit'] ?? 'unidad')) ?: 'unidad';

                $defaultCategoryId = \App\Models\Category::query()->orderBy('id')->value('id') ?? 1;

                $newProduct = \App\Models\Product::create([
                    'name' => $name,
                    'description' => $description,
                    'unit_measurement' => $unit,
                    'category_id' => $defaultCategoryId,
                    'minimum_stock' => 0,
                ]);
                $productId = $newProduct->id;
                \Log::info('Nuevo producto creado:', ['id' => $newProduct->id, 'name' => $newProduct->name]);
            } else {
                // Validar que el producto existe
                $productId = (int) $productIdRaw;
                $product = \App\Models\Product::find($productId);
                if (!$product) {
                    \Log::warning('Producto no encontrado:', ['product_id' => $productId]);
                    continue;
                }
            }
            
            // Crear el detalle de la solicitud de compra
            $detail = \App\Models\PurchaseRequestDetail::create([
                'purchase_request_id' => $purchaseRequest->id,
                'product_id' => $productId,
                'product_description' => $productDescription,
                'requested_quantity' => $quantity,
                'specifications' => $specifications,
                'estimated_unit_price' => $price,
                'estimated_total' => $price * $quantity,
                'status' => 'Pendiente'
            ]);
            
            $totalAmount += $price * $quantity;
            \Log::info('Detalle creado:', ['detail_id' => $detail->id, 'product_id' => $productId]);
        }
        
        // Actualizar el monto total de la solicitud y verificar si requiere aprobación de administrador
        $comprasLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_responsable_compras');
        $requiresAdminApproval = $totalAmount > $comprasLimit;
        
        $purchaseRequest->update([
            'total_amount' => $totalAmount,
            'requires_admin_approval' => $requiresAdminApproval
        ]);
        \Log::info('Monto total actualizado:', ['total' => $totalAmount, 'requires_admin_approval' => $requiresAdminApproval]);
    }

    /**
     * Cuando no quedan líneas de producto, el total de la solicitud debe reflejarlo (detalles ya borrados antes).
     */
    private function resetPurchaseRequestTotalsAfterProductSync(\App\Models\PurchaseRequest $purchaseRequest): void
    {
        $purchaseRequest->update([
            'total_amount' => 0,
            'requires_admin_approval' => false,
        ]);
    }

    /**
     * Elimina líneas de cotización (quote_details) de productos que ya no están en la solicitud.
     */
    private function pruneOrphanQuoteDetailsForPurchaseRequest(\App\Models\PurchaseRequest $purchaseRequest): void
    {
        $purchaseRequest->loadMissing('details');
        $keptProductIds = $purchaseRequest->details->pluck('product_id')->unique()->filter()->values()->all();
        $marketRateIds = \App\Models\MarketRate::query()
            ->where('purchase_request_id', $purchaseRequest->id)
            ->pluck('id');

        if ($marketRateIds->isEmpty()) {
            return;
        }

        $baseQuery = \App\Models\QuoteDetail::query()->whereIn('market_rate_id', $marketRateIds);
        if ($keptProductIds === []) {
            $baseQuery->delete();
        } else {
            (clone $baseQuery)->whereNotIn('product_id', $keptProductIds)->delete();
        }

        foreach ($marketRateIds as $mrId) {
            $mr = \App\Models\MarketRate::query()->find($mrId);
            if (!$mr) {
                continue;
            }
            $total = (float) \App\Models\QuoteDetail::query()
                ->where('market_rate_id', $mrId)
                ->get()
                ->sum(fn (\App\Models\QuoteDetail $d) => (float) $d->quantity * (float) $d->unit_price);
            $mr->update(['total_amount' => $total]);
        }
    }

    /**
     * Generate comparative Excel file for purchase request quotes (sin guardar archivos)
     */
    public function generateComparativeExcel($id)
    {
        $purchaseRequest = \App\Models\PurchaseRequest::with([
            'details.product',
            'responsibilityArea'
        ])->findOrFail($id);

        // Get all market rates for this purchase request (incluye cotizaciones globales sin detalle por producto)
        $productIds = $purchaseRequest->details->pluck('product_id')->toArray();
        $marketRates = \App\Models\MarketRate::with([
            'supplier',
            'quoteDetails.product'
        ])->where('purchase_request_id', $purchaseRequest->id)->get();

        // Group market rates by supplier
        $suppliers = $marketRates->groupBy('supplier_id');
        
        // Generate Excel in memory
        $filename = 'Planilla_Comparativa_' . $purchaseRequest->request_number . '_' . date('Y-m-d') . '.xlsx';
        
        // Create Excel file in memory
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set headers
        $sheet->setCellValue('A1', 'Planilla Comparativa de Cotizaciones');
        $sheet->setCellValue('A2', 'Solicitud: ' . $purchaseRequest->request_number);
        $sheet->setCellValue('A3', 'Fecha: ' . date('d/m/Y'));
        $sheet->setCellValue('A4', 'Área: ' . ($purchaseRequest->responsibilityArea->name ?? 'N/A'));
        
        $row = 6;
        // Resumen de monto total por proveedor para facilitar comparación.
        $sheet->setCellValue('A' . $row, 'Resumen total por proveedor');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;

        $sheet->setCellValue('A' . $row, 'Proveedor');
        $sheet->setCellValue('B' . $row, 'Subtotal');
        $sheet->setCellValue('C' . $row, 'IVA');
        $sheet->setCellValue('D' . $row, 'Total + IVA');
        $sheet->setCellValue('E' . $row, 'Productos incluidos');
        $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true);
        $row++;

        foreach ($suppliers as $supplierId => $supplierRates) {
            $supplier = $supplierRates->first()->supplier;
            $supplierName = $supplier->company_name ?? ('Proveedor ' . $supplierId);
            $effectiveTotal = 0.0;
            $vatAmount = 0.0;
            $totalWithVat = 0.0;
            $totalQty = 0.0;
            $productNames = [];
            $hasGlobalWithoutDetails = false;

            foreach ($supplierRates as $rate) {
                foreach ($rate->quoteDetails as $qd) {
                    $name = $qd->product->name ?? ('Producto #' . ($qd->product_id ?? 'N/A'));
                    if (is_string($name) && trim($name) !== '') {
                        $productNames[] = trim($name);
                    }
                }

                $rateSubtotalFromDetails = (float) $rate->quoteDetails->sum(function ($d) {
                    return ((float) ($d->quantity ?? 0)) * ((float) ($d->unit_price ?? 0));
                });
                $rateTotalQty = (float) $rate->quoteDetails->sum(function ($d) {
                    return (float) ($d->quantity ?? 0);
                });
                $rateSubtotal = $rateSubtotalFromDetails > 0
                    ? $rateSubtotalFromDetails
                    : (float) ($rate->total_amount ?? 0);
                if ($rate->quoteDetails->isEmpty() && $rateSubtotal > 0) {
                    $hasGlobalWithoutDetails = true;
                }

                $rateVat = (float) ($rate->vat_amount ?? 0);
                $rateTotalWithVat = (float) ($rate->total_amount_with_vat ?? 0);

                if ($rateVat <= 0 && $rateTotalWithVat > 0 && $rateSubtotal > 0) {
                    $rateVat = max(0, $rateTotalWithVat - $rateSubtotal);
                }
                if ($rateTotalWithVat <= 0 && $rateSubtotal > 0) {
                    $rateTotalWithVat = $rateSubtotal + max(0, $rateVat);
                }

                $effectiveTotal += max(0, $rateSubtotal);
                $vatAmount += max(0, $rateVat);
                $totalWithVat += max(0, $rateTotalWithVat);
                $totalQty += max(0, $rateTotalQty);
            }
            $sheet->setCellValue('A' . $row, $supplierName);
            $sheet->setCellValue('B' . $row, $effectiveTotal > 0 ? '$' . number_format($effectiveTotal, 2) : 'Sin monto informado');
            $sheet->setCellValue('C' . $row, $vatAmount > 0 ? '$' . number_format($vatAmount, 2) : '$0.00');
            $sheet->setCellValue('D' . $row, $totalWithVat > 0 ? '$' . number_format($totalWithVat, 2) : 'Sin monto informado');
            $productNames = array_values(array_unique($productNames));
            $productsLabel = empty($productNames) ? 'Sin detalle de productos' : implode(', ', $productNames);
            if ($hasGlobalWithoutDetails) {
                $productsLabel .= empty($productNames) ? 'Cotización global (sin detalle)' : ' + Cotización global sin detalle';
            }
            $sheet->setCellValue('E' . $row, $productsLabel);
            $row++;
        }
        
        // Auto-size columns
        foreach (range('A', 'E') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        // Create writer and output to memory
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        // Create temporary file in memory
        $tempFile = tempnam(sys_get_temp_dir(), 'excel_');
        $writer->save($tempFile);
        
        // Return download response without saving to project
        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Generate Excel file with PhpSpreadsheet
     */
    private function generateExcelFile($purchaseRequest, $suppliers, $filePath)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set title
        $sheet->setTitle('Planilla Comparativa');
        
        // Header information
        $sheet->setCellValue('A1', 'PLANILLA COMPARATIVA DE COTIZACIONES');
        $sheet->setCellValue('A2', 'Solicitud de Compra: ' . $purchaseRequest->request_number);
        $sheet->setCellValue('A3', 'Área: ' . $purchaseRequest->responsibilityArea->name);
        $sheet->setCellValue('A4', 'Fecha: ' . date('d/m/Y'));
        
        // Merge title cells
        $sheet->mergeCells('A1:Z1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        // Start data from row 6
        $currentRow = 6;
        
        // Create header row
        $header = ['Producto', 'Cantidad', 'Unidad'];
        $supplierColumns = [];
        $col = 'D'; // Start from column D
        
        foreach ($suppliers as $supplierId => $marketRates) {
            $supplier = $marketRates->first()->supplier;
            $supplierColumns[$supplierId] = [
                'name' => $supplier->company_name,
                'price_col' => $col,
                'subtotal_col' => chr(ord($col) + 1),
                'delivery_col' => chr(ord($col) + 2)
            ];
            
            $header[] = $supplier->company_name . ' - Precio Unit.';
            $header[] = $supplier->company_name . ' - Subtotal';
            $header[] = $supplier->company_name . ' - Plazo';
            
            $col = chr(ord($col) + 3);
        }
        
        $header[] = 'Recomendación';
        $header[] = 'Observaciones';
        
        // Write header
        $col = 'A';
        foreach ($header as $headerText) {
            $sheet->setCellValue($col . $currentRow, $headerText);
            $sheet->getStyle($col . $currentRow)->getFont()->setBold(true);
            $sheet->getStyle($col . $currentRow)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('4472C4');
            $sheet->getStyle($col . $currentRow)->getFont()->getColor()->setRGB('FFFFFF');
            $col++;
        }
        
        $currentRow++;
        
        // Data rows
        foreach ($purchaseRequest->details as $detail) {
            $row = [
                $detail->product->name ?? 'Producto no encontrado',
                $detail->requested_quantity,
                $detail->product->unit ?? 'Unidad'
            ];
            
            $bestOption = null;
            $bestPrice = null;
            $bestScore = 0;
            $observations = [];
            $supplierData = [];
            $recommendations = [];
            
            // Collect data for each supplier
            foreach ($suppliers as $supplierId => $marketRates) {
                $supplier = $marketRates->first()->supplier;
                $quoteDetail = null;
                
                // Find quote detail for this product
                foreach ($marketRates as $marketRate) {
                    $quoteDetail = $marketRate->quoteDetails->where('product_id', $detail->product_id)->first();
                    if ($quoteDetail) break;
                }
                
                if ($quoteDetail) {
                    $unitPrice = $quoteDetail->unit_price;
                    $subtotal = $quoteDetail->quantity * $unitPrice;
                    $deliveryTime = 15; // Default days
                    
                    $supplierData[$supplierId] = [
                        'name' => $supplier->company_name,
                        'unit_price' => $unitPrice,
                        'subtotal' => $subtotal,
                        'delivery_time' => $deliveryTime,
                        'score' => $this->calculateSupplierScore($subtotal, $deliveryTime)
                    ];
                    
                    $observations[] = $supplier->company_name . ': $' . number_format($subtotal, 2) . ' (' . $deliveryTime . ' días)';
                    
                    // Add to recommendations
                    $recommendations[] = [
                        'name' => $supplier->company_name,
                        'price' => $subtotal,
                        'delivery' => $deliveryTime,
                        'score' => $this->calculateSupplierScore($subtotal, $deliveryTime)
                    ];
                } else {
                    $supplierData[$supplierId] = [
                        'name' => $supplier->company_name,
                        'unit_price' => null,
                        'subtotal' => null,
                        'delivery_time' => null,
                        'score' => 0
                    ];
                }
            }
            
            // Determine recommendation based on score (but don't auto-select)
            $recommendation = 'Sin recomendación';
            if (!empty($recommendations)) {
                // Sort by score (highest first)
                usort($recommendations, function($a, $b) {
                    return $b['score'] <=> $a['score'];
                });
                
                $topRecommendation = $recommendations[0];
                $recommendation = $topRecommendation['name'] . ' (Puntuación: ' . number_format($topRecommendation['score'], 1) . ')';
                
                // Add additional recommendations if there are multiple good options
                if (count($recommendations) > 1) {
                    $secondBest = $recommendations[1];
                    if ($secondBest['score'] > $topRecommendation['score'] * 0.8) { // Within 80% of best
                        $recommendation .= ' | ' . $secondBest['name'] . ' (Alt.)';
                    }
                }
            }
            
            // Write product data
            $col = 'A';
            $sheet->setCellValue($col . $currentRow, $row[0]);
            $col++;
            $sheet->setCellValue($col . $currentRow, $row[1]);
            $col++;
            $sheet->setCellValue($col . $currentRow, $row[2]);
            $col++;
            
            // Write supplier data
            foreach ($supplierColumns as $supplierId => $columns) {
                $data = $supplierData[$supplierId] ?? null;
                
                if ($data && $data['unit_price'] !== null) {
                    $sheet->setCellValue($columns['price_col'] . $currentRow, $data['unit_price']);
                    $sheet->setCellValue($columns['subtotal_col'] . $currentRow, $data['subtotal']);
                    $sheet->setCellValue($columns['delivery_col'] . $currentRow, $data['delivery_time'] . ' días');
                    
                    // Format currency
                    $sheet->getStyle($columns['price_col'] . $currentRow)->getNumberFormat()
                        ->setFormatCode('$#,##0.00');
                    $sheet->getStyle($columns['subtotal_col'] . $currentRow)->getNumberFormat()
                        ->setFormatCode('$#,##0.00');
                } else {
                    $sheet->setCellValue($columns['price_col'] . $currentRow, 'Sin cotización');
                    $sheet->setCellValue($columns['subtotal_col'] . $currentRow, 'Sin cotización');
                    $sheet->setCellValue($columns['delivery_col'] . $currentRow, 'Sin cotización');
                }
            }
            
            // Write recommendation and observations
            $sheet->setCellValue($col . $currentRow, $recommendation);
            $col++;
            $sheet->setCellValue($col . $currentRow, implode(' | ', $observations));
            
            // Highlight recommended supplier row (if any)
            if (!empty($recommendations)) {
                $sheet->getStyle('A' . $currentRow . ':' . $col . $currentRow)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFF2CC'); // Light yellow for recommendation
            }
            
            $currentRow++;
        }
        
        // Auto-size columns
        foreach (range('A', $col) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        // Add borders
        $sheet->getStyle('A6:' . $col . ($currentRow - 1))->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        
        // Save file
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save(storage_path('app/public/' . $filePath));
    }

    /**
     * Calculate supplier score based on price and delivery time
     */
    private function calculateSupplierScore($subtotal, $deliveryTime)
    {
        // Score based on price (lower is better) and delivery time (shorter is better)
        // Price weight: 70%, Delivery time weight: 30%
        $priceScore = max(0, 100 - ($subtotal / 100)); // Normalize price
        $deliveryScore = max(0, 100 - ($deliveryTime * 2)); // Normalize delivery time
        
        return ($priceScore * 0.7) + ($deliveryScore * 0.3);
    }

    /**
     * Select winning market rate for purchase request
     */
    public function selectMarketRate($id, $marketRateId)
    {
        $purchaseRequest = \App\Models\PurchaseRequest::findOrFail($id);
        $marketRate = \App\Models\MarketRate::with('quoteDetails')->findOrFail($marketRateId);
        
        // Calcular el monto total de la cotización desde los detalles si no está disponible
        $newTotalAmount = $marketRate->total_amount;
        if (!$newTotalAmount || $newTotalAmount == 0) {
            // Recalcular desde los detalles de la cotización
            $newTotalAmount = $marketRate->quoteDetails->sum(function($detail) {
                return ($detail->quantity ?? 0) * ($detail->unit_price ?? 0);
            });
            
            // Si se calculó un monto, actualizar la cotización
            if ($newTotalAmount > 0) {
                $marketRate->update(['total_amount' => $newTotalAmount]);
            }
        }
        
        // Si aún no hay monto, mantener el de la solicitud de compra
        if (!$newTotalAmount || $newTotalAmount == 0) {
            $newTotalAmount = $purchaseRequest->total_amount ?? 0;
        }
        
        $comprasLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_responsable_compras');
        $requiresAdminApproval = $newTotalAmount > $comprasLimit;
        
        $purchaseRequest->update([
            'selected_market_rate_id' => $marketRateId,
            'total_amount' => $newTotalAmount,
            'requires_admin_approval' => $requiresAdminApproval,
            'status' => 'Aprobada'
        ]);
        
        \Alert::success('Cotización seleccionada exitosamente. El monto total de la solicitud se ha actualizado a $' . number_format($newTotalAmount, 2) . '.')->flash();
        
        return redirect()->back();
    }

    /**
     * Show form to select market rate with justification
     */
    public function showSelectMarketRateForm($id, $marketRateId)
    {
        $purchaseRequest = \App\Models\PurchaseRequest::with([
            'responsibilityArea',
            'details.product'
        ])->findOrFail($id);
        
        $marketRate = \App\Models\MarketRate::with([
            'supplier',
            'quoteDetails.product'
        ])->findOrFail($marketRateId);
        
        return view('admin.purchase-request.select-market-rate', compact('purchaseRequest', 'marketRate'));
    }

    /**
     * Store market rate selection with justification
     */
    public function storeMarketRateSelection($id, $marketRateId)
    {
        // Verificar que solo el responsable de compras pueda seleccionar cotizaciones
        $user = backpack_user();
        $adminRoles = ['role_admin_sistema', 'role_admin_institucion', 'role_responsable_compras'];
        $isAdmin = false;
        foreach ($adminRoles as $role) {
            if ($user && $user->hasRole($role, 'backpack')) {
                $isAdmin = true;
                break;
            }
        }
        
        if (!$isAdmin) {
            abort(403, 'Solo el responsable de compras puede seleccionar cotizaciones.');
        }
        
        $purchaseRequest = \App\Models\PurchaseRequest::findOrFail($id);
        $marketRate = \App\Models\MarketRate::with('quoteDetails')->findOrFail($marketRateId);
        
        $request = request();
        
        // Calcular monto efectivo de la cotización (prioriza total con IVA).
        $newTotalAmount = $this->getMarketRateEffectiveTotal($marketRate);
        if (!$newTotalAmount || $newTotalAmount == 0) {
            $newTotalAmount = (float) ($purchaseRequest->total_amount ?? 0);
        }
        
        $comprasLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_responsable_compras');
        $requiresAdminApproval = $newTotalAmount > $comprasLimit;
        
        // Verificar si el usuario puede aprobar esta solicitud (usando el nuevo monto)
        // Crear una solicitud temporal con el nuevo monto para verificar
        $tempRequest = clone $purchaseRequest;
        $tempRequest->total_amount = $newTotalAmount;
        $canApprove = $tempRequest->canBeApprovedBy($user);
        
        if (!$canApprove) {
            // Si no puede aprobar, solo seleccionar la cotización pero no aprobar
            $purchaseRequest->update([
                'selected_market_rate_id' => $marketRateId,
                'selection_justification' => $request->input('justification'),
                'selected_by' => auth()->id(),
                'selected_at' => now(),
                'total_amount' => $newTotalAmount,
                'requires_admin_approval' => $requiresAdminApproval,
            ]);
            
            \Alert::warning('Cotización seleccionada. El monto total se ha actualizado a $' . number_format($newTotalAmount, 2) . '. La solicitud requiere aprobación del administrador del instituto debido a que supera el límite de autorización.')->flash();
        } else {
            // Si puede aprobar, seleccionar la cotización y aprobar
            $purchaseRequest->update([
                'selected_market_rate_id' => $marketRateId,
                'selection_justification' => $request->input('justification'),
                'selected_by' => auth()->id(),
                'selected_at' => now(),
                'total_amount' => $newTotalAmount,
                'requires_admin_approval' => $requiresAdminApproval,
                'status' => 'Aprobada',
                'approved_by' => auth()->id(),
                'approved_date' => now(),
                'approval_justification' => $request->input('justification'), // Usar la misma justificación de selección
            ]);
            
            \Alert::success('Cotización seleccionada y solicitud aprobada exitosamente.')->flash();
        }

        // Marcar la cotización como seleccionada para permitir selección múltiple en la vista.
        $marketRate->update(['is_selected' => true]);

        // Recalcular total de solicitud según cotizaciones seleccionadas (incluyendo IVA)
        $recalculatedTotal = $this->recalculateSelectedQuotationsTotalForPurchaseRequest($purchaseRequest);
        $purchaseRequest->update([
            'total_amount' => $recalculatedTotal,
            'requires_admin_approval' => $recalculatedTotal > $comprasLimit,
        ]);
        
        return redirect()->route('purchase-request.show', $id);
    }

    /**
     * Toggle selección múltiple de cotizaciones desde la vista de solicitud.
     */
    public function toggleMarketRateSelection($id, $marketRateId)
    {
        // Verificar que solo el responsable de compras/admin pueda seleccionar cotizaciones
        $user = backpack_user();
        $adminRoles = ['role_admin_sistema', 'role_admin_institucion', 'role_responsable_compras'];
        $isAdmin = false;
        foreach ($adminRoles as $role) {
            if ($user && $user->hasRole($role, 'backpack')) {
                $isAdmin = true;
                break;
            }
        }

        if (! $isAdmin) {
            abort(403, 'Solo el responsable de compras puede seleccionar cotizaciones.');
        }

        $purchaseRequest = \App\Models\PurchaseRequest::with('marketRates')->findOrFail($id);
        $marketRate = \App\Models\MarketRate::where('purchase_request_id', $id)->findOrFail($marketRateId);

        $newValue = ! (bool) $marketRate->is_selected;
        $marketRate->update(['is_selected' => $newValue]);

        // Mantener compatibilidad con lógica existente basada en selected_market_rate_id.
        if ($newValue && empty($purchaseRequest->selected_market_rate_id)) {
            $purchaseRequest->update([
                'selected_market_rate_id' => $marketRate->id,
                'selected_by' => auth()->id(),
                'selected_at' => now(),
            ]);
        }

        if (! $newValue && (int) $purchaseRequest->selected_market_rate_id === (int) $marketRate->id) {
            $anotherSelectedId = $purchaseRequest->marketRates()
                ->where('is_selected', true)
                ->where('id', '!=', $marketRate->id)
                ->value('id');

            $purchaseRequest->update([
                'selected_market_rate_id' => $anotherSelectedId,
            ]);
        }

        // Recalcular total y requisito de aprobación usando cotizaciones seleccionadas.
        $comprasLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_responsable_compras');
        $recalculatedTotal = $this->recalculateSelectedQuotationsTotalForPurchaseRequest($purchaseRequest);
        $purchaseRequest->update([
            'total_amount' => $recalculatedTotal,
            'requires_admin_approval' => $recalculatedTotal > $comprasLimit,
        ]);

        \Alert::success($newValue ? 'Cotización seleccionada.' : 'Cotización deseleccionada.')->flash();
        return redirect()->route('purchase-request.show', $id);
    }

    /**
     * Show form to suggest a supplier
     */
    public function showSuggestSupplierForm($id)
    {
        $purchaseRequest = \App\Models\PurchaseRequest::with([
            'responsibilityArea',
            'details.product'
        ])->findOrFail($id);
        
        // Verificar que el usuario sea responsable de área
        $user = backpack_user();
        if (!$user || !$user->hasRole('role_responsable_area', 'backpack')) {
            abort(403, 'Solo los responsables de área pueden sugerir proveedores.');
        }
        
        $suppliers = \App\Models\Supplier::all();
        
        return view('admin.purchase-request.suggest-supplier', compact('purchaseRequest', 'suppliers'));
    }

    /**
     * Store supplier suggestion
     */
    public function storeSupplierSuggestion($id)
    {
        // Verificar que el usuario sea responsable de área
        $user = backpack_user();
        if (!$user || !$user->hasRole('role_responsable_area', 'backpack')) {
            abort(403, 'Solo los responsables de área pueden sugerir proveedores.');
        }
        
        $purchaseRequest = \App\Models\PurchaseRequest::findOrFail($id);
        $request = request();
        
        // Validar que no exista ya una sugerencia del mismo usuario para el mismo proveedor
        $existingSuggestion = \App\Models\SupplierSuggestion::where('purchase_request_id', $id)
            ->where('supplier_id', $request->input('supplier_id'))
            ->where('suggested_by', $user->id)
            ->first();
        
        if ($existingSuggestion) {
            \Alert::error('Ya has sugerido este proveedor para esta solicitud.')->flash();
            return redirect()->back();
        }
        
        \App\Models\SupplierSuggestion::create([
            'purchase_request_id' => $id,
            'supplier_id' => $request->input('supplier_id'),
            'suggested_by' => $user->id,
            'justification' => $request->input('justification'),
        ]);
        
        \Alert::success('Proveedor sugerido exitosamente.')->flash();
        
        return redirect()->route('purchase-request.show', $id);
    }

    /**
     * Approve a purchase request
     */
    public function approvePurchaseRequest($id)
    {
        $purchaseRequest = \App\Models\PurchaseRequest::with('marketRates')->findOrFail($id);
        $user = backpack_user();
        
        if (!$user) {
            abort(403, 'No tienes permiso para aprobar solicitudes de compra.');
        }
        
        // Verificar si el usuario puede aprobar esta solicitud
        if (!$purchaseRequest->canBeApprovedBy($user)) {
            // Verificar si es administrador del instituto y supera su límite
            if ($user->hasRole('role_admin_institucion', 'backpack')) {
                $adminLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_admin_institucion');
                abort(403, 'No puedes aprobar esta solicitud de compra porque supera tu límite de autorización de $' . number_format($adminLimit, 2) . '. El monto de la solicitud es $' . number_format($purchaseRequest->total_amount, 2) . '.');
            }
            // Verificar si es apoderado y supera su límite
            if ($user->hasRole('role_apoderado', 'backpack')) {
                $apoderadoLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_apoderado');
                abort(403, 'No puedes aprobar esta solicitud de compra porque supera tu límite de autorización de $' . number_format($apoderadoLimit, 2) . '. El monto de la solicitud es $' . number_format($purchaseRequest->total_amount, 2) . '.');
            }
            // Verificar si es representante legal y supera su límite
            if ($user->hasRole('role_representante_legal', 'backpack')) {
                $representanteLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_representante_legal');
                abort(403, 'No puedes aprobar esta solicitud de compra porque supera tu límite de autorización de $' . number_format($representanteLimit, 2) . '. El monto de la solicitud es $' . number_format($purchaseRequest->total_amount, 2) . '.');
            }
            abort(403, 'No tienes permiso para aprobar esta solicitud de compra.');
        }
        
        // Validar que la solicitud esté en estado pendiente
        if ($purchaseRequest->status !== 'Pendiente') {
            abort(403, 'Solo se pueden aprobar solicitudes con estado "Pendiente".');
        }

        // No permitir aprobar sin cotización seleccionada (salvo compra directa).
        $hasAnySelectedQuotation = !empty($purchaseRequest->selected_market_rate_id)
            || $purchaseRequest->marketRates->contains(function ($mr) {
                return (bool) ($mr->is_selected ?? false);
            });
        if (! $purchaseRequest->is_direct_purchase && ! $hasAnySelectedQuotation) {
            \Alert::error('Debe seleccionar al menos una cotización antes de aprobar la solicitud.')->flash();
            return redirect()->route('purchase-request.show', $id);
        }

        // Recalcular total efectivo a aprobar (incluye IVA y selección múltiple) antes de validar límites.
        $effectiveTotal = $this->recalculateSelectedQuotationsTotalForPurchaseRequest($purchaseRequest);
        $comprasLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_responsable_compras');
        $purchaseRequest->update([
            'total_amount' => $effectiveTotal,
            'requires_admin_approval' => $effectiveTotal > $comprasLimit,
        ]);
        $purchaseRequest->refresh();
        
        $request = request();
        $request->validate([
            'approval_justification' => 'required|string|max:1000',
        ]);
        
        // Actualizar la solicitud como aprobada
        $purchaseRequest->update([
            'status' => 'Aprobada',
            'approved_by' => $user->id,
            'approved_date' => now(),
            'approval_justification' => $request->input('approval_justification'),
            'requires_admin_approval' => false,
        ]);
        
        \Alert::success('Solicitud de compra aprobada exitosamente.')->flash();
        
        return redirect()->route('purchase-request.show', $id);
    }

    /**
     * Obtiene total efectivo de una cotización (priorizando total con IVA).
     */
    private function getMarketRateEffectiveTotal(\App\Models\MarketRate $marketRate): float
    {
        $totalWithVat = (float) ($marketRate->total_amount_with_vat ?? 0);
        if ($totalWithVat > 0) {
            return $totalWithVat;
        }

        $subtotal = (float) ($marketRate->total_amount ?? 0);
        $vat = (float) ($marketRate->vat_amount ?? 0);
        if ($subtotal > 0 || $vat > 0) {
            return $subtotal + max(0, $vat);
        }

        return (float) $marketRate->quoteDetails->sum(function ($detail) {
            return ((float) ($detail->quantity ?? 0)) * ((float) ($detail->unit_price ?? 0));
        });
    }

    /**
     * Recalcula total de la solicitud según cotizaciones seleccionadas (incluye IVA).
     */
    private function recalculateSelectedQuotationsTotalForPurchaseRequest(\App\Models\PurchaseRequest $purchaseRequest): float
    {
        // Consultar siempre desde DB para evitar relaciones cacheadas al seleccionar/deseleccionar.
        $selectedRates = \App\Models\MarketRate::with('quoteDetails')
            ->where('purchase_request_id', $purchaseRequest->id)
            ->where('is_selected', true)
            ->get();

        if ($selectedRates->isNotEmpty()) {
            return (float) $selectedRates->sum(function ($marketRate) {
                return $this->getMarketRateEffectiveTotal($marketRate);
            });
        }

        if (!empty($purchaseRequest->selected_market_rate_id)) {
            $single = \App\Models\MarketRate::with('quoteDetails')->find($purchaseRequest->selected_market_rate_id);
            if ($single) {
                return $this->getMarketRateEffectiveTotal($single);
            }
        }

        return (float) ($purchaseRequest->total_amount ?? 0);
    }

    /**
     * Reject a purchase request
     */
    public function rejectPurchaseRequest($id)
    {
        $purchaseRequest = \App\Models\PurchaseRequest::findOrFail($id);
        $user = backpack_user();
        
        if (!$user) {
            abort(403, 'No tienes permiso para rechazar solicitudes de compra.');
        }
        
        // Verificar si el usuario puede aprobar/rechazar esta solicitud
        if (!$purchaseRequest->canBeApprovedBy($user)) {
            abort(403, 'No tienes permiso para rechazar esta solicitud de compra.');
        }
        
        // Validar que la solicitud esté en estado pendiente
        if ($purchaseRequest->status !== 'Pendiente') {
            abort(403, 'Solo se pueden rechazar solicitudes con estado "Pendiente".');
        }
        
        // Actualizar la solicitud como rechazada
        $purchaseRequest->update([
            'status' => 'Rechazada',
            'approved_by' => $user->id,
            'approved_date' => now(),
        ]);
        
        \Alert::warning('Solicitud de compra rechazada.')->flash();
        
        return redirect()->route('purchase-request.show', $id);
    }

    /**
     * Mark purchase request as direct purchase (solo sector de compras)
     */
    public function markAsDirectPurchase($id)
    {
        $purchaseRequest = \App\Models\PurchaseRequest::findOrFail($id);
        $user = backpack_user();
        
        if (!$user) {
            abort(403, 'No tienes permiso para marcar compras directas.');
        }
        
        // Solo responsables de compras pueden marcar como compra directa
        if (!$user->hasRole('role_responsable_compras', 'backpack')) {
            abort(403, 'Solo el sector de compras puede marcar compras directas.');
        }
        
        // Validar que la solicitud esté en estado pendiente
        if ($purchaseRequest->status !== 'Pendiente') {
            abort(403, 'Solo se pueden marcar como compra directa las solicitudes con estado "Pendiente".');
        }
        
        $request = request();
        $request->validate([
            'direct_purchase_supplier_id' => 'required|exists:suppliers,id',
            'direct_purchase_justification' => 'required|string|max:1000',
        ]);
        
        // Marcar como compra directa y solicitar autorización automáticamente
        $purchaseRequest->update([
            'is_direct_purchase' => true,
            'direct_purchase_supplier_id' => $request->input('direct_purchase_supplier_id'),
            'direct_purchase_justification' => $request->input('direct_purchase_justification'),
            'direct_purchase_authorization_requested' => true,
            'direct_purchase_authorization_requested_by' => $user->id,
            'direct_purchase_authorization_requested_at' => now(),
        ]);
        
        \Alert::success('Solicitud marcada como compra directa y autorización solicitada exitosamente. La solicitud está pendiente de aprobación por parte del administrador, apoderado o representante legal.')->flash();
        
        return redirect()->route('purchase-request.show', $id);
    }

    /**
     * Approve direct purchase authorization
     */
    public function approveDirectPurchase($id)
    {
        $purchaseRequest = \App\Models\PurchaseRequest::findOrFail($id);
        $user = backpack_user();
        
        if (!$user) {
            abort(403, 'No tienes permiso para aprobar compras directas.');
        }
        
        // Solo administrador, apoderado o representante legal pueden aprobar
        $canApprove = $user->hasRole('role_admin_institucion', 'backpack') 
                   || $user->hasRole('role_apoderado', 'backpack') 
                   || $user->hasRole('role_representante_legal', 'backpack');
        
        if (!$canApprove) {
            abort(403, 'Solo el administrador del instituto, apoderado o representante legal pueden aprobar compras directas.');
        }
        
        // Validar que sea una compra directa y que se haya solicitado autorización
        if (!$purchaseRequest->is_direct_purchase) {
            abort(403, 'Esta solicitud no está marcada como compra directa.');
        }
        
        if (!$purchaseRequest->direct_purchase_authorization_requested) {
            abort(403, 'No se ha solicitado autorización para esta compra directa.');
        }
        
        // Validar que no esté ya aprobada o rechazada
        if ($purchaseRequest->direct_purchase_authorized_by || $purchaseRequest->direct_purchase_authorization_rejected) {
            abort(403, 'Esta compra directa ya ha sido procesada.');
        }
        
        // Validar que el usuario tenga límite suficiente para aprobar
        $userLimit = 0;
        $userRoleName = '';
        
        if ($user->hasRole('role_admin_institucion', 'backpack')) {
            $userLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_admin_institucion');
            $userRoleName = 'administrador del instituto';
        } elseif ($user->hasRole('role_apoderado', 'backpack')) {
            $userLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_apoderado');
            $userRoleName = 'apoderado';
        } elseif ($user->hasRole('role_representante_legal', 'backpack')) {
            $userLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_representante_legal');
            $userRoleName = 'representante legal';
        }
        
        if ($userLimit > 0 && $purchaseRequest->total_amount > $userLimit) {
            abort(403, 'No puedes aprobar esta compra directa porque supera tu límite de autorización de $' . number_format($userLimit, 2) . '. El monto de la compra directa es $' . number_format($purchaseRequest->total_amount, 2) . '.');
        }
        
        // Aprobar la compra directa y la solicitud, y cambiar el tipo de compra
        $purchaseRequest->update([
            'direct_purchase_authorized_by' => $user->id,
            'direct_purchase_authorized_at' => now(),
            'direct_purchase_authorization_rejected' => false,
            'status' => 'Aprobada',
            'approved_by' => $user->id,
            'approved_date' => now(),
            'requires_admin_approval' => false,
            'purchase_type' => 'directa',
        ]);
        
        \Alert::success('Compra directa aprobada exitosamente. La solicitud de compra ha sido aprobada.')->flash();
        
        return redirect()->route('purchase-request.show', $id);
    }

    /**
     * Reject direct purchase authorization
     */
    public function rejectDirectPurchaseAuthorization($id)
    {
        $purchaseRequest = \App\Models\PurchaseRequest::findOrFail($id);
        $user = backpack_user();
        
        if (!$user) {
            abort(403, 'No tienes permiso para rechazar compras directas.');
        }
        
        // Solo administrador, apoderado o representante legal pueden rechazar
        $canReject = $user->hasRole('role_admin_institucion', 'backpack') 
                  || $user->hasRole('role_apoderado', 'backpack') 
                  || $user->hasRole('role_representante_legal', 'backpack');
        
        if (!$canReject) {
            abort(403, 'Solo el administrador del instituto, apoderado o representante legal pueden rechazar compras directas.');
        }
        
        // Validar que sea una compra directa y que se haya solicitado autorización
        if (!$purchaseRequest->is_direct_purchase) {
            abort(403, 'Esta solicitud no está marcada como compra directa.');
        }
        
        if (!$purchaseRequest->direct_purchase_authorization_requested) {
            abort(403, 'No se ha solicitado autorización para esta compra directa.');
        }
        
        // Validar que no esté ya aprobada o rechazada
        if ($purchaseRequest->direct_purchase_authorized_by || $purchaseRequest->direct_purchase_authorization_rejected) {
            abort(403, 'Esta compra directa ya ha sido procesada.');
        }
        
        $request = request();
        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);
        
        // Rechazar la autorización
        $purchaseRequest->update([
            'direct_purchase_authorization_rejected' => true,
            'direct_purchase_authorization_rejection_reason' => $request->input('rejection_reason'),
        ]);
        
        \Alert::warning('Autorización de compra directa rechazada.')->flash();
        
        return redirect()->route('purchase-request.show', $id);
    }

    /**
     * Asignar cotización por producto (para generar una OC con varios proveedores)
     */
    public function assignQuotations($id)
    {
        $user = backpack_user();
        if ($user && $user->hasRole('role_responsable_area', 'backpack')) {
            abort(403, 'Los responsables de área no pueden asignar cotizaciones.');
        }

        $purchaseRequest = \App\Models\PurchaseRequest::with(['details.product', 'marketRates.quoteDetails'])->findOrFail($id);
        if ($purchaseRequest->status === 'Completada') {
            \Alert::error('No se puede modificar la asignación: la solicitud ya está completada.')->flash();
            return redirect()->back();
        }

        try {
            \App\Models\PurchaseRequestDetail::ensureSelectedMarketRateIdColumnExists();
        } catch (\Throwable $e) {
            \Log::error('ensureSelectedMarketRateIdColumnExists failed', ['exception' => $e]);
            \Alert::error('No se pudo preparar la base de datos para asignar cotizaciones. Ejecute: php artisan migrate --path=database/migrations/2026_04_15_130000_ensure_selected_market_rate_id_on_purchase_request_details_table.php')->flash();
            return redirect()->back();
        }

        $detailQuotes = request()->input('detail_quote', []);
        $marketRateIds = $purchaseRequest->marketRates->pluck('id')->toArray();
        $quoteDetailsByRate = $purchaseRequest->marketRates->keyBy('id')->map->quoteDetails->keyBy('product_id');

        foreach ($purchaseRequest->details as $detail) {
            $marketRateId = isset($detailQuotes[$detail->id]) ? (int) $detailQuotes[$detail->id] : null;
            if ($marketRateId === null) {
                \App\Models\PurchaseRequestDetail::where('id', $detail->id)->update(['selected_market_rate_id' => null]);
                continue;
            }
            if (!in_array($marketRateId, $marketRateIds)) {
                \Alert::error('Cotización inválida para el producto ' . ($detail->product->name ?? '') . '.')->flash();
                return redirect()->back();
            }
            $quoteDetail = $purchaseRequest->marketRates->firstWhere('id', $marketRateId)
                ->quoteDetails->firstWhere('product_id', $detail->product_id);
            if (!$quoteDetail) {
                \Alert::error('La cotización seleccionada no incluye el producto: ' . ($detail->product->name ?? '') . '.')->flash();
                return redirect()->back();
            }
            \App\Models\PurchaseRequestDetail::where('id', $detail->id)->update(['selected_market_rate_id' => $marketRateId]);
        }

        \Alert::success('Asignación por producto guardada correctamente.')->flash();
        return redirect()->back();
    }

    /**
     * Generate purchase order from selected market rate or directly if amount <= 60000
     * Si todos los detalles tienen asignada una cotización (selected_market_rate_id), genera una OC con varios proveedores.
     */
    public function generatePurchaseOrder($id)
    {
        // Verificar que el usuario no sea role_responsable_area
        $user = backpack_user();
        if ($user && $user->hasRole('role_responsable_area', 'backpack')) {
            abort(403, 'Los responsables de área no pueden generar órdenes de compra.');
        }
        
        $purchaseRequest = \App\Models\PurchaseRequest::with([
            'selectedMarketRate.supplier',
            'selectedMarketRate.quoteDetails.product',
            'details.product',
            'details.selectedMarketRate.supplier',
            'details.selectedMarketRate.quoteDetails.product',
            'responsibilityArea'
        ])->findOrFail($id);

        try {
            \App\Models\PurchaseRequestDetail::ensureSelectedMarketRateIdColumnExists();
            \App\Models\PurchaseOrderDetail::ensureSupplierIdColumnExists();
            $purchaseRequest->load([
                'details.product',
                'details.selectedMarketRate.supplier',
                'details.selectedMarketRate.quoteDetails.product',
            ]);
        } catch (\Throwable $e) {
            \Log::error('ensure purchase request / OC detail columns failed', ['exception' => $e]);
        }
        
        // Verificar que la solicitud esté aprobada antes de generar la orden
        if ($purchaseRequest->status !== 'Aprobada') {
            if ($purchaseRequest->requires_admin_approval) {
                \Alert::error('No se puede generar la orden de compra. La solicitud requiere aprobación del administrador del instituto debido a que supera el límite de autorización.')->flash();
            } else {
                \Alert::error('No se puede generar la orden de compra. La solicitud debe estar aprobada primero.')->flash();
            }
            return redirect()->back();
        }
        
        // Si es compra directa autorizada, generar orden directamente sin cotizaciones
        if ($purchaseRequest->is_direct_purchase 
            && $purchaseRequest->direct_purchase_authorized_by 
            && $purchaseRequest->direct_purchase_supplier_id
            && !$purchaseRequest->direct_purchase_authorization_rejected) {
            return $this->generatePurchaseOrderWithoutQuote($purchaseRequest, $purchaseRequest->direct_purchase_supplier_id);
        }
        
        $totalAmount = $this->recalculateSelectedQuotationsTotalForPurchaseRequest($purchaseRequest);
        $purchaseRequest->update([
            'total_amount' => $totalAmount,
        ]);
        $threshold = 60000;
        $quotationsCount = $this->countQuotationsForPurchaseRequest($purchaseRequest);
        $allDetailsHaveAssignment = $purchaseRequest->details->isNotEmpty()
            && $purchaseRequest->details->every(fn ($d) => !empty($d->selected_market_rate_id));

        // Flujo: asignación por producto (una OC con varios proveedores)
        if ($allDetailsHaveAssignment) {
            if ($totalAmount > $threshold && $quotationsCount < 3) {
                \Alert::error('Para solicitudes mayores a $' . number_format($threshold, 2) . ' se requieren al menos 3 cotizaciones.')->flash();
                return redirect()->back();
            }
            $productsWithFewer = $purchaseRequest->getProductsWithFewerThanThreeQuotations();
            if ($totalAmount > $threshold && $productsWithFewer->isNotEmpty()) {
                \Alert::error('Cada producto debe estar en al menos 3 cotizaciones. Productos faltantes: ' . $productsWithFewer->pluck('name')->implode(', ') . '.')->flash();
                return redirect()->back();
            }
            return $this->generatePurchaseOrderFromPerProductAssignment($purchaseRequest);
        }

        // Flujo clásico: una cotización para toda la solicitud
        if ($totalAmount > $threshold) {
            if ($quotationsCount < 3) {
                \Alert::error('Para solicitudes de compra mayores a $' . number_format($threshold, 2) . ' se requieren OBLIGATORIAMENTE 3 cotizaciones. Actualmente hay ' . $quotationsCount . ' cotización(es). Debe agregar ' . (3 - $quotationsCount) . ' cotización(es) más antes de generar la orden de compra.')->flash();
                return redirect()->back();
            }
            $productsWithFewerQuotations = $purchaseRequest->getProductsWithFewerThanThreeQuotations();
            if ($productsWithFewerQuotations->isNotEmpty()) {
                $names = $productsWithFewerQuotations->pluck('name')->implode(', ');
                \Alert::error('No se puede generar la orden: los siguientes productos deben estar cotizados en al menos 3 cotizaciones distintas: ' . $names . '. Agregue estos productos a más cotizaciones antes de generar la orden de compra.')->flash();
                return redirect()->back();
            }
            if (!$purchaseRequest->selected_market_rate_id) {
                \Alert::error('Debe seleccionar una cotización antes de generar la orden de compra, o asignar una cotización por producto en la sección inferior.')->flash();
                return redirect()->back();
            }
            return $this->generatePurchaseOrderFromQuote($purchaseRequest);
        }
        if (!$purchaseRequest->selected_market_rate_id) {
            \Alert::error('Debe seleccionar una cotización antes de generar la orden de compra, o asignar una cotización por producto en la sección inferior.')->flash();
            return redirect()->back();
        }
        return $this->generatePurchaseOrderFromQuote($purchaseRequest);
    }

    /**
     * Generar una sola OC con líneas de distintas cotizaciones (varios proveedores)
     */
    private function generatePurchaseOrderFromPerProductAssignment($purchaseRequest)
    {
        $request = request();
        $issueDate = $request->input('issue_date') ? \Carbon\Carbon::parse($request->input('issue_date')) : now();

        $linesBySupplier = [];
        foreach ($purchaseRequest->details as $requestDetail) {
            $marketRate = $requestDetail->selectedMarketRate;
            if (!$marketRate || !$requestDetail->product) {
                continue;
            }
            $quoteDetail = $marketRate->quoteDetails->firstWhere('product_id', $requestDetail->product_id);
            if (!$quoteDetail) {
                continue;
            }
            $input = $this->findOrCreateInputFromProduct($quoteDetail->product);
            if (!$input) {
                continue;
            }
            $unitPrice = $this->parseMonetaryValue($quoteDetail->unit_price);
            $sid = $marketRate->supplier_id;
            if (!isset($linesBySupplier[$sid])) {
                $linesBySupplier[$sid] = [];
            }
            $linesBySupplier[$sid][] = [
                'input_id' => $input->id,
                'quantity' => $quoteDetail->quantity,
                'unit_price' => $unitPrice,
            ];
        }

        if ($linesBySupplier === []) {
            \Alert::error('No se pudo generar la orden: no hay líneas válidas con cotización asignada.')->flash();
            return redirect()->back();
        }

        ksort($linesBySupplier, SORT_NUMERIC);

        $area = $purchaseRequest->responsibilityArea;
        $letter = $area ? $area->purchaseOrderLetter() : 'X';
        $year = (int) now()->year;

        $createdOrders = [];
        \Illuminate\Support\Facades\DB::transaction(function () use (
            $purchaseRequest,
            $issueDate,
            $linesBySupplier,
            $letter,
            $year,
            &$createdOrders
        ) {
            $correlative = \App\Models\PurchaseOrder::nextCorrelativeForAreaAndYear($letter, $year);
            $supplierIndex = 1;
            foreach ($linesBySupplier as $supplierId => $lines) {
                $orderNumber = \App\Models\PurchaseOrder::formatPurchaseOrderNumber($letter, $year, $correlative, $supplierIndex);
                $purchaseOrder = \App\Models\PurchaseOrder::create([
                    'number' => $orderNumber,
                    'date' => now(),
                    'issue_date' => $issueDate,
                    'supplier_id' => $supplierId,
                    'authorizing_user_id' => auth()->id(),
                    'status' => 'Pendiente',
                    'purchase_request_id' => $purchaseRequest->id,
                    'payment_conditions' => '30 días fecha factura',
                ]);
                foreach ($lines as $line) {
                    \App\Models\PurchaseOrderDetail::create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'supplier_id' => $supplierId,
                        'input_id' => $line['input_id'],
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                    ]);
                }
                $createdOrders[] = $purchaseOrder;
                $supplierIndex++;
            }

            $newType = ($purchaseRequest->purchase_type === 'internet') ? 'internet' : 'normal';
            $purchaseRequest->update(['status' => 'Completada', 'purchase_type' => $newType]);
        });

        $numbers = collect($createdOrders)->pluck('number')->implode(', ');
        $msg = count($createdOrders) > 1
            ? 'Órdenes de compra generadas (' . count($createdOrders) . ' proveedores): ' . $numbers
            : 'Orden de compra generada exitosamente: ' . $numbers;
        \Alert::success($msg)->flash();

        return redirect()->route('purchase-order.show', $createdOrders[0]->id);
    }
    
    /**
     * Generate purchase order from selected quote
     */
    private function generatePurchaseOrderFromQuote($purchaseRequest)
    {
        $request = request();
        
        $orderNumber = \App\Models\PurchaseOrder::allocateNextFormattedNumber($purchaseRequest->responsibilityArea, 1);

        // Obtener fecha de emisión del request o usar la fecha actual
        $issueDate = $request->input('issue_date') ? \Carbon\Carbon::parse($request->input('issue_date')) : now();
        
        $quoteDetails = $purchaseRequest->selectedMarketRate->quoteDetails;

        $supplierId = $purchaseRequest->selectedMarketRate->supplier_id;
        // Create purchase order (supplier_id opcional a nivel orden cuando hay varios proveedores por línea)
        $purchaseOrder = \App\Models\PurchaseOrder::create([
            'number' => $orderNumber,
            'date' => now(),
            'issue_date' => $issueDate,
            'supplier_id' => $supplierId,
            'authorizing_user_id' => auth()->id(),
            'status' => 'Pendiente',
            'purchase_request_id' => $purchaseRequest->id,
            'payment_conditions' => '30 días fecha factura', // Valor por defecto
        ]);
        
        // Create purchase order details from quote (cada línea con su proveedor)
        foreach ($quoteDetails as $quoteDetail) {
            // Buscar o crear el Input correspondiente al Product
            $input = $this->findOrCreateInputFromProduct($quoteDetail->product);
            
            if ($input) {
                $unitPrice = $this->parseMonetaryValue($quoteDetail->unit_price);
                \App\Models\PurchaseOrderDetail::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'supplier_id' => $supplierId,
                    'input_id' => $input->id,
                    'quantity' => $quoteDetail->quantity,
                    'unit_price' => $unitPrice,
                ]);
            }
        }
        
        // Update purchase request status and type (preservar 'internet' si aplica)
        $newType = ($purchaseRequest->purchase_type === 'internet') ? 'internet' : 'normal';
        $purchaseRequest->update([
            'status' => 'Completada',
            'purchase_type' => $newType
        ]);
        \Alert::success('Orden de compra generada exitosamente: ' . $purchaseOrder->number)->flash();
        return redirect()->route('purchase-order.show', $purchaseOrder->id);
    }
    
    /**
     * Generate purchase order without quote (for amounts <= 60000)
     */
    private function generatePurchaseOrderWithoutQuote($purchaseRequest, $supplierId)
    {
        $request = request();
        
        // Validar que el proveedor existe
        $supplier = \App\Models\Supplier::findOrFail($supplierId);
        
        $orderNumber = \App\Models\PurchaseOrder::allocateNextFormattedNumber($purchaseRequest->responsibilityArea, 1);

        // Obtener fecha de emisión del request o usar la fecha actual
        $issueDate = $request->input('issue_date') ? \Carbon\Carbon::parse($request->input('issue_date')) : now();
        
        // Obtener precios del request
        $prices = $request->input('prices', []);
        $lines = [];

        foreach ($purchaseRequest->details as $requestDetail) {
            if (!$requestDetail->product) {
                continue;
            }

            $input = $this->findOrCreateInputFromProduct($requestDetail->product);
            if (!$input) {
                continue;
            }

            $rawPrice = $prices[$requestDetail->id] ?? $requestDetail->estimated_unit_price ?? 0;
            $unitPrice = $this->parseMonetaryValue($rawPrice);

            $lines[] = [
                'input_id' => $input->id,
                'quantity' => $requestDetail->requested_quantity,
                'unit_price' => $unitPrice,
            ];
        }

        if (empty($lines)) {
            \Alert::error('No se pudo generar la orden: no hay líneas válidas para crear la OC.')->flash();
            return redirect()->back();
        }
        
        // Create purchase order
        $purchaseOrder = \App\Models\PurchaseOrder::create([
            'number' => $orderNumber,
            'date' => now(),
            'issue_date' => $issueDate,
            'supplier_id' => $supplierId,
            'authorizing_user_id' => auth()->id(),
            'status' => 'Pendiente',
            'purchase_request_id' => $purchaseRequest->id,
            'payment_conditions' => '30 días fecha factura', // Valor por defecto
        ]);
        
        // Create purchase order details from purchase request details (cada línea con el mismo proveedor)
        foreach ($lines as $line) {
            \App\Models\PurchaseOrderDetail::create([
                'purchase_order_id' => $purchaseOrder->id,
                'supplier_id' => $supplierId,
                'input_id' => $line['input_id'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
            ]);
        }
        
        // Determinar el tipo de compra (preservar 'internet' si ya estaba marcada)
        $purchaseType = ($purchaseRequest->purchase_type === 'internet') ? 'internet' : 'normal';
        $totalAmount = $this->recalculateSelectedQuotationsTotalForPurchaseRequest($purchaseRequest);
        $threshold = 60000;
        if ($purchaseType !== 'internet') {
            if ($purchaseRequest->is_direct_purchase && $purchaseRequest->direct_purchase_authorized_by) {
                $purchaseType = 'directa';
            } elseif ($totalAmount <= $threshold) {
                $purchaseType = 'rapida';
            }
        }
        
        // Update purchase request status and type
        $purchaseRequest->update([
            'status' => 'Completada',
            'purchase_type' => $purchaseType
        ]);
        \Alert::success('Orden de compra generada exitosamente: ' . $purchaseOrder->number)->flash();
        return redirect()->route('purchase-order.show', $purchaseOrder->id);
    }

    /**
     * Count quotations for a purchase request
     */
    private function countQuotationsForPurchaseRequest($purchaseRequest)
    {
        // Usar la relación del modelo en lugar de consulta directa
        return $purchaseRequest->marketRates()->count();
    }

    private function parseMonetaryValue($raw): float
    {
        if ($raw === null || $raw === '') {
            return 0.0;
        }
        if (is_numeric($raw)) {
            return (float) $raw;
        }
        $value = trim((string) $raw);
        $value = str_replace(['$', ' '], '', $value);
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }
        $value = preg_replace('/[^0-9.\-]/', '', $value);
        if ($value === '' || !is_numeric($value)) {
            return 0.0;
        }
        return (float) $value;
    }
    
    /**
     * Find or create Input from Product
     */
    protected function findOrCreateInputFromProduct($product)
    {
        // Intentar encontrar un input con el mismo nombre
        $input = \App\Models\Input::where('name', $product->name)->first();
        
        if ($input) {
            return $input;
        }

        // Si no existe, crear uno nuevo
        try {
            $input = \App\Models\Input::create([
                'name' => $product->name,
                'description' => $product->description ?? '',
                'unit' => $product->unit_measurement ?? 'unidad',
                'price' => 0, // El precio se establecerá en el detalle de la orden
            ]);

            \Log::info('Input creado desde Product', [
                'product_id' => $product->id,
                'input_id' => $input->id,
                'name' => $input->name
            ]);

            return $input;
        } catch (\Exception $e) {
            \Log::error('Error al crear input desde Product', [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }


    /**
     * Define what happens when the Show operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-show
     * @return void
     */
    protected function setupShowOperation()
    {
        CRUD::addClause('with', ['responsibilityArea', 'requestingUser', 'approvedBy', 'details.product', 'details.selectedMarketRate.supplier', 'selectedMarketRate.supplier', 'selectedBy', 'convertedFromGeneralRequest', 'deliveries.details', 'purchaseOrders.paymentOrders', 'directPurchaseSupplier', 'directPurchaseAuthorizationRequestedBy', 'directPurchaseAuthorizedBy', 'marketRates']);
        
        // Ocultar botón de eliminar para role_admin_institucion, role_apoderado y role_representante_legal
        $user = backpack_user();
        if ($user && ($user->hasRole('role_admin_institucion', 'backpack') || $user->hasRole('role_apoderado', 'backpack') || $user->hasRole('role_representante_legal', 'backpack'))) {
            CRUD::removeButton('delete');
        }
        
        CRUD::column('request_number')->label('Número de Solicitud');
        CRUD::column('request_date')->label('Fecha');
        CRUD::column('status')->label('Estado');
        CRUD::column('priority')->label('Prioridad');
        CRUD::column('justification')->label('Motivo');
        CRUD::column('observations')->label('Observaciones');
        CRUD::column('responsibilityArea.name')->label('Área');
        CRUD::column('requestingUser.name')->label('Solicitante');
        CRUD::column('approvedBy.name')->label('Aprobada por');
        CRUD::column('approved_date')->label('Fecha Aprobación');
        CRUD::column('total_amount')->label('Monto total');
        
        // Columna para mostrar si requiere aprobación de administrador
        CRUD::column('approval_status')->label('Estado de Aprobación')->type('custom_html')
            ->value(function($entry) {
                if ($entry->status === 'Aprobada') {
                    return '<span class="badge bg-success">Aprobada</span>';
                } elseif ($entry->status === 'Rechazada') {
                    return '<span class="badge bg-danger">Rechazada</span>';
                } elseif ($entry->requires_admin_approval) {
                    $comprasLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_responsable_compras');
                    $total = (float) ($entry->total_amount ?? 0);
                    $adminLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_admin_institucion');
                    $apoderadoLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_apoderado');
                    $representanteLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_representante_legal');

                    $requiredRole = null;
                    if ($adminLimit > 0 && $total <= $adminLimit) {
                        $requiredRole = 'Administrador del Instituto';
                    } elseif ($apoderadoLimit > 0 && $total <= $apoderadoLimit) {
                        $requiredRole = 'Apoderado';
                    } elseif ($representanteLimit > 0 && $total <= $representanteLimit) {
                        $requiredRole = 'Representante Legal';
                    }

                    if ($requiredRole) {
                        return '<span class="badge bg-warning">Requiere aprobación de ' . e($requiredRole) . ' (Monto: $' . number_format($total, 2) . ')</span>';
                    }

                    return '<span class="badge bg-danger">Monto supera todos los límites de aprobación (Monto: $' . number_format($total, 2) . ')</span>';
                } else {
                    return '<span class="badge bg-secondary">Pendiente</span>';
                }
            });

        CRUD::column('total_amount')->label('Monto Total')->type('number')->decimals(2)->prefix('$');
        
        // Eliminar completamente la sección de adjuntos
        CRUD::removeColumn('attachments');

        // Agregar campo personalizado para mostrar información de la solicitud general de origen
        CRUD::column('general_request_info')->label('Solicitud General de Origen')->type('custom_html')
            ->value(function($entry) {
                if (!$entry->convertedFromGeneralRequest) {
                    return '<div class="alert alert-secondary text-dark">
                        <i class="la la-info-circle"></i> Esta solicitud de compra no fue convertida desde una solicitud general.
                    </div>';
                }
                
                $generalRequest = $entry->convertedFromGeneralRequest;
                // Cargar los detalles de productos con entregas
                $generalRequest->load('details.product', 'deliveries.details');
                $generalDetails = $generalRequest->details;
                
                $html = '<div class="card border-info">';
                $html .= '<div class="card-header bg-info text-white">';
                $html .= '<h6 class="mb-0"><i class="la la-file-alt"></i> Solicitud General de Origen</h6>';
                $html .= '</div>';
                $html .= '<div class="card-body">';
                $html .= '<div class="row">';
                $html .= '<div class="col-md-6">';
                $html .= '<p class="mb-1"><strong>Número:</strong> ' . e($generalRequest->number ?? 'N/A') . '</p>';
                $html .= '<p class="mb-1"><strong>Título:</strong> ' . e($generalRequest->title ?? 'N/A') . '</p>';
                $html .= '<p class="mb-1"><strong>Estado:</strong> ';
                $status = $generalRequest->status ?? 'N/A';
                $statusClass = strtolower(str_replace([' ', '_'], '-', $status));
                $statusColors = [
                    'creada' => 'secondary',
                    'revisada-area' => 'info',
                    'archivada' => 'dark',
                    'convertida-a-compra' => 'warning',
                    'entregada-parcialmente' => 'warning',
                    'entregada-totalmente' => 'success',
                ];
                $badgeColor = $statusColors[$statusClass] ?? 'secondary';
                $html .= '<span class="badge bg-' . $badgeColor . '">' . ucfirst(str_replace('_', ' ', $status)) . '</span>';
                $html .= '</p>';
                $html .= '</div>';
                $html .= '<div class="col-md-6">';
                $html .= '<p class="mb-1"><strong>Área:</strong> ' . e($generalRequest->area->name ?? 'N/A') . '</p>';
                $html .= '<p class="mb-1"><strong>Creada por:</strong> ' . e($generalRequest->createdBy->name ?? 'N/A') . '</p>';
                $html .= '<p class="mb-1"><strong>Fecha de creación:</strong> ' . ($generalRequest->created_at ? $generalRequest->created_at->format('d/m/Y H:i') : 'N/A') . '</p>';
                $html .= '</div>';
                $html .= '</div>';
                if ($generalRequest->description) {
                    $html .= '<div class="row mt-2">';
                    $html .= '<div class="col-12">';
                    $html .= '<p class="mb-1"><strong>Descripción:</strong></p>';
                    $html .= '<p class="text-muted small mb-2">' . nl2br(e($generalRequest->description)) . '</p>';
                    $html .= '</div>';
                    $html .= '</div>';
                }
                
                // Mostrar productos de la solicitud general
                if ($generalDetails->isNotEmpty()) {
                    $html .= '<div class="row mt-3">';
                    $html .= '<div class="col-12">';
                    $html .= '<p class="mb-2"><strong>Productos Solicitados (' . $generalDetails->count() . '):</strong></p>';
                    $html .= '<div class="table-responsive" style="max-height: 300px; overflow-y: auto;">';
                    $html .= '<table class="table table-sm table-bordered table-striped mb-0" style="font-size: 0.95rem;">';
                    $html .= '<thead class="table-light" style="position: sticky; top: 0; z-index: 10;">';
                    $html .= '<tr>';
                    $html .= '<th style="width: 35%;">Producto</th>';
                    $html .= '<th style="width: 15%;" class="text-center">Solicitado</th>';
                    $html .= '<th style="width: 15%;" class="text-center">Entregado</th>';
                    $html .= '<th style="width: 15%;" class="text-center">Pendiente</th>';
                    $html .= '<th style="width: 10%;" class="text-center">Estado</th>';
                    $html .= '<th style="width: 10%;">Especificaciones</th>';
                    $html .= '</tr>';
                    $html .= '</thead>';
                    $html .= '<tbody>';
                    
                    foreach ($generalDetails as $detail) {
                        $productName = $detail->product->name ?? 'Producto no encontrado';
                        if (is_array($productName)) {
                            $productName = 'Producto no encontrado';
                        }
                        $unit = $detail->product->unit_measurement ?? '';
                        if (is_array($unit)) {
                            $unit = '';
                        }
                        $requestedQuantity = $detail->requested_quantity ?? 0;
                        
                        // Calcular cantidad entregada
                        $deliveredQuantity = 0;
                        if ($generalRequest->deliveries) {
                            foreach ($generalRequest->deliveries as $delivery) {
                                $deliveryDetail = $delivery->details->where('product_id', $detail->product_id)->first();
                                if ($deliveryDetail) {
                                    $deliveredQuantity += $deliveryDetail->delivered_quantity ?? 0;
                                }
                            }
                        }
                        
                        $pendingQuantity = max(0, $requestedQuantity - $deliveredQuantity);
                        
                        // Determinar estado de entrega
                        $deliveryStatus = 'Pendiente';
                        $deliveryStatusColor = 'secondary';
                        $deliveryStatusIcon = 'clock';
                        if ($deliveredQuantity == 0) {
                            $deliveryStatus = 'Pendiente';
                            $deliveryStatusColor = 'secondary';
                            $deliveryStatusIcon = 'clock';
                        } elseif ($deliveredQuantity >= $requestedQuantity) {
                            $deliveryStatus = 'Completo';
                            $deliveryStatusColor = 'success';
                            $deliveryStatusIcon = 'check-circle';
                        } else {
                            $deliveryStatus = 'Parcial';
                            $deliveryStatusColor = 'warning';
                            $deliveryStatusIcon = 'exclamation-triangle';
                        }
                        
                        $specifications = $detail->specifications ?? '';
                        if (is_array($specifications)) {
                            $specifications = '';
                        }
                        
                        $html .= '<tr>';
                        $html .= '<td><small><strong>' . e($productName) . '</strong>';
                        if ($unit) {
                            $html .= '<br><span class="text-muted">(' . e($unit) . ')</span>';
                        }
                        $html .= '</small></td>';
                        $html .= '<td class="text-center"><small><strong>' . number_format($requestedQuantity) . '</strong></small></td>';
                        $html .= '<td class="text-center"><small>' . number_format($deliveredQuantity) . '</small></td>';
                        $html .= '<td class="text-center"><small>' . number_format($pendingQuantity) . '</small></td>';
                        $html .= '<td class="text-center"><small><span class="badge bg-' . $deliveryStatusColor . '" title="' . $deliveryStatus . '"><i class="la la-' . $deliveryStatusIcon . '"></i> ' . $deliveryStatus . '</span></small></td>';
                        $html .= '<td><small class="text-muted">' . ($specifications ? e(substr($specifications, 0, 40)) . (strlen($specifications) > 40 ? '...' : '') : '-') . '</small></td>';
                        $html .= '</tr>';
                    }
                    
                    $html .= '</tbody>';
                    $html .= '</table>';
                    $html .= '</div>';
                    $html .= '</div>';
                    $html .= '</div>';
                } else {
                    $html .= '<div class="row mt-2">';
                    $html .= '<div class="col-12">';
                    $html .= '<p class="text-muted small mb-0"><em>No hay productos en esta solicitud general.</em></p>';
                    $html .= '</div>';
                    $html .= '</div>';
                }
                
                $html .= '</div>';
                $html .= '</div>';
                
                return $html;
            });

        // Agregar campo personalizado para mostrar detalles de productos
        CRUD::column('details_table')->label('Detalles de Productos')->type('custom_html')
            ->value(function($entry) {
                $entry->loadMissing(['details.product']);
                $details = $entry->details;
                
                if ($details->isEmpty()) {
                    return '<div class="alert alert-info">No hay productos solicitados.</div>';
                }
                
                $html = '<div class="card border-primary">';
                $html .= '<div class="card-header bg-primary text-white">';
                $html .= '<h6 class="mb-0"><i class="la la-shopping-cart"></i> Productos Solicitados <span class="badge bg-light text-primary ms-1">' . $details->count() . '</span></h6>';
                $html .= '</div>';
                $html .= '<div class="card-body p-0">';
                $html .= '<div class="table-responsive">';
                $html .= '<table class="table table-sm table-bordered mb-0">';
                $html .= '<thead class="table-light">';
                $html .= '<tr>';
                $html .= '<th style="width: 28%;">Producto</th>';
                $html .= '<th style="width: 12%;" class="text-center">Cantidad Solicitada</th>';
                $html .= '<th style="width: 12%;" class="text-center">Cantidad Recibida</th>';
                $html .= '<th style="width: 12%;" class="text-center">Estado Recepción</th>';
                $html .= '<th style="width: 24%;">Descripción / Especificaciones</th>';
                $html .= '<th style="width: 12%;" class="text-center">Estado</th>';
                $html .= '</tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                
                foreach ($details as $index => $detail) {
                    $deliveredQuantity = $detail->delivered_quantity ?? 0;
                    $requestedQuantity = $detail->requested_quantity ?? 0;
                    $deliveryStatus = $detail->delivery_status ?? 'Pendiente';
                    $isFullyDelivered = $detail->is_fully_delivered ?? false;
                    
                    // Determinar estado de recepción
                    $deliveryStatusColor = 'secondary';
                    $deliveryStatusIcon = 'clock';
                    if ($deliveryStatus == 'Completo') {
                        $deliveryStatusColor = 'success';
                        $deliveryStatusIcon = 'check-circle';
                    } elseif ($deliveryStatus == 'Parcial') {
                        $deliveryStatusColor = 'warning';
                        $deliveryStatusIcon = 'exclamation-triangle';
                    } else {
                        $deliveryStatusColor = 'secondary';
                        $deliveryStatusIcon = 'clock';
                    }
                    
                    $html .= '<tr>';
                    $lineNo = $index + 1;
                    $rawCatalogName = null;
                    if ($detail->product) {
                        $n = $detail->product->name;
                        $rawCatalogName = is_array($n) ? null : $n;
                    }
                    $specLine = trim((string) ($detail->product_description ?? $detail->specifications ?? ''));
                    $isGenericCatalog = is_string($rawCatalogName) && preg_match('/^producto\s+nuevo$/iu', $rawCatalogName);

                    $html .= '<td>';
                    $html .= '<span class="badge bg-secondary me-1">#' . $lineNo . '</span>';
                    if ($isGenericCatalog && $specLine !== '') {
                        $html .= '<strong>' . e($specLine) . '</strong>';
                        $html .= '<br><small class="text-muted">Catálogo: ' . e((string) $rawCatalogName) . ' · ID ' . (int) $detail->product_id . '</small>';
                    } elseif ($isGenericCatalog) {
                        $html .= '<strong>' . e('Ítem #' . $lineNo . ' (sin descripción en la línea)') . '</strong>';
                        $html .= '<br><small class="text-muted">Nombre en catálogo: ' . e((string) $rawCatalogName) . ' · ID ' . (int) $detail->product_id . '</small>';
                    } else {
                        $label = ($rawCatalogName !== null && $rawCatalogName !== '') ? (string) $rawCatalogName : 'Sin catálogo';
                        $html .= '<strong>' . e($label) . '</strong>';
                        if ($detail->product_id) {
                            $html .= '<br><small class="text-muted">ID producto ' . (int) $detail->product_id . '</small>';
                        }
                    }
                    if ($detail->product && $detail->product->description && !is_array($detail->product->description)) {
                        $html .= '<br><small class="text-muted">' . e($detail->product->description) . '</small>';
                    }
                    $html .= '</td>';
                    $html .= '<td class="text-center"><span class="badge bg-primary">' . number_format($requestedQuantity) . '</span>';
                    if ($detail->product && $detail->product->unit_measurement && !is_array($detail->product->unit_measurement)) {
                        $html .= '<br><small class="text-muted">' . e($detail->product->unit_measurement) . '</small>';
                    }
                    $html .= '</td>';
                    $html .= '<td class="text-center">';
                    $html .= '<span class="badge bg-' . ($deliveredQuantity > 0 ? ($isFullyDelivered ? 'success' : 'warning') : 'secondary') . '" title="Cantidad recibida: ' . number_format($deliveredQuantity) . ' de ' . number_format($requestedQuantity) . '">';
                    $html .= number_format($deliveredQuantity) . ' / ' . number_format($requestedQuantity);
                    $html .= '</span>';
                    $html .= '</td>';
                    $html .= '<td class="text-center">';
                    $html .= '<span class="badge bg-' . $deliveryStatusColor . '" title="Estado de recepción: ' . e($deliveryStatus) . '">';
                    $html .= '<i class="la la-' . $deliveryStatusIcon . '"></i> ' . e($deliveryStatus);
                    $html .= '</span>';
                    $html .= '</td>';
                    $descSpecs = $detail->specifications ?? $detail->product_description ?? '';
                    if (is_array($descSpecs)) {
                        $descSpecs = '';
                    }
                    $html .= '<td><small>' . ($descSpecs ? e($descSpecs) : '-') . '</small></td>';
                    $status = $detail->status ?? 'Pendiente';
                    if (is_array($status)) {
                        $status = 'Pendiente';
                    }
                    $html .= '<td class="text-center"><span class="badge bg-' . ($detail->status == 'Aprobada' ? 'success' : ($detail->status == 'Rechazada' ? 'danger' : 'warning')) . '">' . e((string) $status) . '</span></td>';
                    $html .= '</tr>';
                }
                
                $html .= '</tbody>';
                $html .= '</table>';
                $html .= '</div>';
                $html .= '</div>';
                
                // Agregar botón para editar/agregar productos si el usuario tiene permisos
                $user = backpack_user();
                if ($user) {
                    $adminRoles = ['role_admin_sistema', 'role_admin_institucion', 'role_responsable_compras'];
                    $isAdmin = false;
                    foreach ($adminRoles as $role) {
                        if ($user->hasRole($role, 'backpack')) {
                            $isAdmin = true;
                            break;
                        }
                    }
                    
                    $isOwnRequest = $entry->requesting_user_id == $user->id;
                    $isResponsableArea = $user->hasRole('role_responsable_area', 'backpack');
                    
                    // Verificar si puede editar
                    $canEdit = false;
                    if ($isAdmin) {
                        $canEdit = true;
                    } elseif ($isOwnRequest && $entry->status === 'Pendiente') {
                        $canEdit = true;
                    } elseif ($isResponsableArea && $entry->status === 'Pendiente') {
                        $canEdit = true;
                    }
                    
                    if ($canEdit) {
                        $html .= '<div class="card-footer bg-light text-end">';
                        $html .= '<a href="' . backpack_url('purchase-request/' . $entry->id . '/edit') . '" class="btn btn-primary">';
                        $html .= '<i class="la la-edit"></i> Editar Productos';
                        $html .= '</a>';
                        $html .= '</div>';
                    }
                }
                
                $html .= '</div>';
                
                return $html;
            });

        // Agregar columna para acciones de compra directa (después de Detalles de Productos)
        CRUD::column('direct_purchase_actions')->label('Sugerencia de Compra Directa')->type('custom_html')
            ->value(function($entry) {
                $user = backpack_user();
                if (!$user) {
                    return '';
                }
                
                $html = '';
                
                // Si es una compra directa, mostrar información
                if ($entry->is_direct_purchase) {
                    $html .= '<div class="card border-info mt-3">';
                    $html .= '<div class="card-header bg-info text-white">';
                    $html .= '<h6 class="mb-0"><i class="la la-hand-pointer"></i> Sugerencia de Compra Directa</h6>';
                    $html .= '</div>';
                    $html .= '<div class="card-body">';
                    
                    // Mostrar proveedor
                    if ($entry->directPurchaseSupplier) {
                        $html .= '<p class="mb-2"><strong>Proveedor:</strong> ' . e($entry->directPurchaseSupplier->company_name) . '</p>';
                    }
                    
                    // Mostrar justificación
                    if ($entry->direct_purchase_justification) {
                        $html .= '<p class="mb-2"><strong>Justificación:</strong> ' . nl2br(e($entry->direct_purchase_justification)) . '</p>';
                    }
                    
                    // Si se solicitó autorización
                    if ($entry->direct_purchase_authorization_requested) {
                        if ($entry->directPurchaseAuthorizationRequestedBy) {
                            $html .= '<p class="mb-2"><strong>Solicitud de autorización por:</strong> ' . e($entry->directPurchaseAuthorizationRequestedBy->name) . '</p>';
                        }
                        if ($entry->direct_purchase_authorization_requested_at) {
                            $requestedAt = $entry->direct_purchase_authorization_requested_at instanceof \Carbon\Carbon 
                                ? $entry->direct_purchase_authorization_requested_at->format('d/m/Y H:i') 
                                : \Carbon\Carbon::parse($entry->direct_purchase_authorization_requested_at)->format('d/m/Y H:i');
                            $html .= '<p class="mb-2"><strong>Fecha de solicitud:</strong> ' . $requestedAt . '</p>';
                        }
                        
                        // Si está autorizada
                        if ($entry->direct_purchase_authorized_by) {
                            $html .= '<div class="alert alert-success mt-2">';
                            $html .= '<i class="la la-check-circle"></i> <strong>Autorizada</strong>';
                            if ($entry->directPurchaseAuthorizedBy) {
                                $html .= ' por ' . e($entry->directPurchaseAuthorizedBy->name);
                            }
                            if ($entry->direct_purchase_authorized_at) {
                                $authorizedAt = $entry->direct_purchase_authorized_at instanceof \Carbon\Carbon 
                                    ? $entry->direct_purchase_authorized_at->format('d/m/Y H:i') 
                                    : \Carbon\Carbon::parse($entry->direct_purchase_authorized_at)->format('d/m/Y H:i');
                                $html .= ' el ' . $authorizedAt;
                            }
                            $html .= '</div>';
                        }
                        // Si está rechazada
                        elseif ($entry->direct_purchase_authorization_rejected) {
                            $html .= '<div class="alert alert-danger mt-2">';
                            $html .= '<i class="la la-times-circle"></i> <strong>Autorización Rechazada</strong>';
                            if ($entry->direct_purchase_authorization_rejection_reason) {
                                $html .= '<br><strong>Razón:</strong> ' . nl2br(e($entry->direct_purchase_authorization_rejection_reason));
                            }
                            $html .= '</div>';
                        }
                        // Pendiente de autorización
                        else {
                            $html .= '<div class="alert alert-warning mt-2">';
                            $html .= '<i class="la la-clock"></i> <strong>Pendiente de autorización</strong>';
                            $html .= '</div>';
                            
                            // Verificar si el usuario puede aprobar según su límite
                            $canApproveByLimit = false;
                            $userLimit = 0;
                            $userRoleName = '';
                            
                            if ($user->hasRole('role_admin_institucion', 'backpack')) {
                                $userLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_admin_institucion');
                                $userRoleName = 'administrador del instituto';
                                $canApproveByLimit = $entry->total_amount <= $userLimit;
                            } elseif ($user->hasRole('role_apoderado', 'backpack')) {
                                $userLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_apoderado');
                                $userRoleName = 'apoderado';
                                $canApproveByLimit = $entry->total_amount <= $userLimit;
                            } elseif ($user->hasRole('role_representante_legal', 'backpack')) {
                                $userLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_representante_legal');
                                $userRoleName = 'representante legal';
                                $canApproveByLimit = $entry->total_amount <= $userLimit;
                            }
                            
                            // Si el usuario tiene el rol pero supera su límite, mostrar mensaje
                            if (($user->hasRole('role_admin_institucion', 'backpack') || $user->hasRole('role_apoderado', 'backpack') || $user->hasRole('role_representante_legal', 'backpack')) && !$canApproveByLimit) {
                                $adminLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_admin_institucion');
                                $apoderadoLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_apoderado');
                                $representanteLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_representante_legal');
                                
                                $html .= '<div class="alert alert-danger mt-2">';
                                $html .= '<i class="la la-exclamation-triangle"></i> ';
                                $html .= '<strong>Límite excedido:</strong> Esta compra directa ($' . number_format($entry->total_amount, 2) . ') supera tu límite de autorización de $' . number_format($userLimit, 2) . '. ';
                                $html .= 'No puedes aprobar esta compra directa. ';
                                
                                // Mostrar quién puede aprobar
                                $canApproveList = [];
                                if ($entry->total_amount <= $adminLimit) {
                                    $canApproveList[] = 'administrador del instituto (límite: $' . number_format($adminLimit, 2) . ')';
                                }
                                if ($entry->total_amount <= $apoderadoLimit) {
                                    $canApproveList[] = 'apoderado (límite: $' . number_format($apoderadoLimit, 2) . ')';
                                }
                                if ($entry->total_amount <= $representanteLimit) {
                                    $canApproveList[] = 'representante legal (límite: $' . number_format($representanteLimit, 2) . ')';
                                }
                                
                                if (!empty($canApproveList)) {
                                    $html .= 'Puede ser aprobada por: ' . implode(', ', $canApproveList) . '.';
                                } else {
                                    $html .= 'Ningún usuario tiene límite suficiente para aprobar esta compra directa.';
                                }
                                
                                $html .= '</div>';
                            }
                            // Si el usuario puede aprobar, mostrar botones
                            elseif ($canApproveByLimit) {
                                $html .= '<div class="mt-3">';
                                
                                // Formulario para aprobar
                                $html .= '<form method="POST" action="' . route('purchase-request.approve-direct-purchase', $entry->id) . '" class="d-inline">';
                                $html .= csrf_field();
                                $html .= '<button type="submit" class="btn btn-success btn-sm" onclick="return confirm(\'¿Está seguro de aprobar esta compra directa?\')">';
                                $html .= '<i class="la la-check"></i> Aprobar Compra Directa';
                                $html .= '</button>';
                                $html .= '</form>';
                                
                                // Botón para rechazar (con modal)
                                $html .= '<button type="button" class="btn btn-danger btn-sm ms-2" data-toggle="modal" data-target="#rejectDirectPurchaseModal' . $entry->id . '">';
                                $html .= '<i class="la la-times"></i> Rechazar Autorización';
                                $html .= '</button>';
                                
                                $html .= '</div>';
                                
                                // Modal para rechazar
                                $html .= '<div class="modal fade" id="rejectDirectPurchaseModal' . $entry->id . '" tabindex="-1" role="dialog">';
                                $html .= '<div class="modal-dialog" role="document">';
                                $html .= '<div class="modal-content">';
                                $html .= '<div class="modal-header bg-danger text-white">';
                                $html .= '<h5 class="modal-title">Rechazar Autorización de Compra Directa</h5>';
                                $html .= '<button type="button" class="close text-white" data-dismiss="modal">&times;</button>';
                                $html .= '</div>';
                                $html .= '<form method="POST" action="' . route('purchase-request.reject-direct-purchase-authorization', $entry->id) . '">';
                                $html .= csrf_field();
                                $html .= '<div class="modal-body">';
                                $html .= '<div class="form-group">';
                                $html .= '<label for="rejection_reason">Razón del rechazo:</label>';
                                $html .= '<textarea name="rejection_reason" id="rejection_reason" class="form-control" rows="3" required></textarea>';
                                $html .= '</div>';
                                $html .= '</div>';
                                $html .= '<div class="modal-footer">';
                                $html .= '<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>';
                                $html .= '<button type="submit" class="btn btn-danger">Rechazar</button>';
                                $html .= '</div>';
                                $html .= '</form>';
                                $html .= '</div>';
                                $html .= '</div>';
                                $html .= '</div>';
                            }
                        }
                    }
                    
                    $html .= '</div>';
                    $html .= '</div>';
                }
                // Si no es compra directa y el sector de compras puede marcarla como tal
                elseif ($entry->status === 'Pendiente' && $user->hasRole('role_responsable_compras', 'backpack')) {
                    $html .= '<div class="card border-secondary mt-3">';
                    $html .= '<div class="card-header bg-secondary text-white">';
                    $html .= '<h6 class="mb-0"><i class="la la-hand-pointer"></i> Sugerencia de Compra Directa</h6>';
                    $html .= '</div>';
                    $html .= '<div class="card-body">';
                    $html .= '<p class="mb-3">Si existe un único proveedor para los productos solicitados (por especialidad), puede marcar esta solicitud como compra directa. Al marcarla, se solicitará automáticamente la autorización a nivel superior.</p>';
                    $html .= '<p class="mb-3 text-muted"><small><i class="la la-info-circle"></i> El responsable de área puede sugerir proveedores desde la sección de sugerencias de proveedores.</small></p>';
                    
                    // Botón para abrir modal
                    $modalId = 'markDirectPurchaseModal' . $entry->id;
                    $supplierFieldId = 'direct_purchase_supplier_id_' . $entry->id;
                    $justificationFieldId = 'direct_purchase_justification_' . $entry->id;
                    $suppliers = \App\Models\Supplier::orderBy('company_name')->get();
                    
                    $html .= '<button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#' . $modalId . '">';
                    $html .= '<i class="la la-hand-pointer"></i> Sugerir Compra Directa';
                    $html .= '</button>';
                    
                    // Modal para marcar como compra directa
                    $html .= '<div class="modal fade" id="' . $modalId . '" tabindex="-1" role="dialog" aria-labelledby="' . $modalId . 'Label" aria-hidden="true">';
                    $html .= '<div class="modal-dialog modal-lg" role="document">';
                    $html .= '<div class="modal-content">';
                    $html .= '<div class="modal-header bg-info text-white">';
                    $html .= '<h5 class="modal-title" id="' . $modalId . 'Label">Sugerir Compra Directa</h5>';
                    $html .= '<button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">';
                    $html .= '<span aria-hidden="true">&times;</span>';
                    $html .= '</button>';
                    $html .= '</div>';
                    $html .= '<form method="POST" action="' . route('purchase-request.mark-direct-purchase', $entry->id) . '">';
                    $html .= csrf_field();
                    $html .= '<div class="modal-body">';
                    $html .= '<div class="form-group">';
                    $html .= '<label for="' . $supplierFieldId . '">Proveedor <span class="text-danger">*</span></label>';
                    $html .= '<select name="direct_purchase_supplier_id" id="' . $supplierFieldId . '" class="form-control" required>';
                    $html .= '<option value="">Seleccione un proveedor</option>';
                    foreach ($suppliers as $supplier) {
                        $html .= '<option value="' . $supplier->id . '">' . e($supplier->company_name) . '</option>';
                    }
                    $html .= '</select>';
                    $html .= '</div>';
                    $html .= '<div class="form-group">';
                    $html .= '<label for="' . $justificationFieldId . '">Justificación <span class="text-danger">*</span></label>';
                    $html .= '<textarea name="direct_purchase_justification" id="' . $justificationFieldId . '" class="form-control" rows="4" required placeholder="Explique por qué este proveedor es el único disponible para estos productos (especialidad, exclusividad, etc.)"></textarea>';
                    $html .= '</div>';
                    $html .= '</div>';
                    $html .= '<div class="modal-footer">';
                    $html .= '<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>';
                    $html .= '<button type="submit" class="btn btn-info">Sugerir Compra Directa</button>';
                    $html .= '</div>';
                    $html .= '</form>';
                    $html .= '</div>';
                    $html .= '</div>';
                    $html .= '</div>';
                    
                    // JavaScript para mover el modal al body y asegurar que funcione
                    $html .= '<script>
                    (function() {
                        function initDirectPurchaseModal() {
                            var modal = document.getElementById("' . $modalId . '");
                            if (!modal) return;
                            
                            // Mover el modal al body si no está ahí
                            if (modal.parentElement && modal.parentElement.tagName !== "BODY") {
                                document.body.appendChild(modal);
                            }
                            
                            // Asegurar que el botón funcione con jQuery
                            if (typeof jQuery !== "undefined" && jQuery.fn.modal) {
                                jQuery("button[data-target=\'#' . $modalId . '\']").off("click").on("click", function(e) {
                                    e.preventDefault();
                                    jQuery("#' . $modalId . '").appendTo("body").modal("show");
                                });
                            }
                        }
                        
                        // Ejecutar cuando el DOM esté listo
                        if (document.readyState === "loading") {
                            document.addEventListener("DOMContentLoaded", initDirectPurchaseModal);
                        } else {
                            initDirectPurchaseModal();
                        }
                        
                        // También ejecutar después de un pequeño delay por si acaso
                        setTimeout(initDirectPurchaseModal, 100);
                    })();
                    </script>';
                    
                    $html .= '</div>';
                    $html .= '</div>';
                }
                
                return $html;
            });

        // Agregar campo para mostrar órdenes de compra asociadas
        CRUD::column('purchase_orders_table')->label('Órdenes de Compra Asociadas')->type('custom_html')
            ->value(function($entry) {
                $entry->load('purchaseOrders.supplier', 'purchaseOrders.details');
                $purchaseOrders = $entry->purchaseOrders;
                
                if ($purchaseOrders->isEmpty()) {
                    return '<div class="alert alert-info">No hay órdenes de compra asociadas a esta solicitud.</div>';
                }
                
                $html = '<div class="table-responsive">';
                $html .= '<table class="table table-striped table-bordered">';
                $html .= '<thead class="thead-dark">';
                $html .= '<tr>';
                $html .= '<th>Número</th>';
                $html .= '<th>Fecha</th>';
                $html .= '<th>Proveedor</th>';
                $html .= '<th>Estado</th>';
                $html .= '<th>Total</th>';
                $html .= '<th>Acciones</th>';
                $html .= '</tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                
                foreach ($purchaseOrders as $purchaseOrder) {
                    $statusBadge = match($purchaseOrder->status) {
                        'Pendiente' => 'bg-warning',
                        'Aprobada' => 'bg-success',
                        'Recibida' => 'bg-info',
                        default => 'bg-secondary'
                    };
                    
                    $html .= '<tr>';
                    $html .= '<td><strong>' . e($purchaseOrder->number ?? 'N/A') . '</strong></td>';
                    $html .= '<td>' . ($purchaseOrder->date ? $purchaseOrder->date->format('d/m/Y') : 'N/A') . '</td>';
                    $html .= '<td>' . e($purchaseOrder->supplier_display_name) . '</td>';
                    $html .= '<td><span class="badge ' . $statusBadge . '">' . e($purchaseOrder->status ?? 'N/A') . '</span></td>';
                    $html .= '<td><strong>$' . number_format($purchaseOrder->total ?? 0, 2) . '</strong></td>';
                    $html .= '<td>';
                    $html .= '<a href="' . backpack_url('purchase-order/' . $purchaseOrder->id . '/show') . '" class="btn btn-sm btn-info">';
                    $html .= '<i class="la la-eye"></i> Ver';
                    $html .= '</a>';
                    $html .= '</td>';
                    $html .= '</tr>';
                }
                
                $html .= '</tbody>';
                $html .= '</table>';
                $html .= '</div>';
                
                return $html;
            });

        // Agregar campo para mostrar cotizaciones disponibles.
        CRUD::column('market_rates_table')->label('Cotizaciones Disponibles')->type('custom_html')
            ->value(function($entry) {
                // Usar la relación del modelo en lugar de consulta directa
                $entry->load(['marketRates.supplier', 'marketRates.quoteDetails.product']);
                $marketRates = $entry->marketRates;
                
                $html = '';
                
                if ($marketRates->isEmpty()) {
                    $html .= '<div class="alert alert-warning">No hay cotizaciones disponibles para los productos de esta solicitud.</div>';
                } else {
                    $html .= '<div class="table-responsive">';
                $html .= '<table class="table table-striped table-bordered">';
                $html .= '<thead class="thead-dark">';
                $html .= '<tr>';
                $html .= '<th>Proveedor</th>';
                $html .= '<th>Fecha</th>';
                $html .= '<th>Total</th>';
                $html .= '<th>Productos</th>';
                $html .= '<th>Estado</th>';
                $html .= '<th>Acciones</th>';
                $html .= '</tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                
                foreach ($marketRates as $marketRate) {
                    $isSelected = (bool) ($marketRate->is_selected || $entry->selected_market_rate_id == $marketRate->id);
                    $rowClass = $isSelected ? 'table-success' : '';
                    
                    $html .= '<tr class="' . $rowClass . '">';
                    $supplierName = $marketRate->supplier->company_name ?? 'Proveedor no encontrado';
                    if (is_array($supplierName)) {
                        $supplierName = 'Proveedor no encontrado';
                    }
                    $html .= '<td><strong>' . $supplierName . '</strong></td>';
                    $date = $marketRate->date;
                    if (is_string($date)) {
                        $date = \Carbon\Carbon::parse($date);
                    }
                    $html .= '<td>' . ($date ? $date->format('d/m/Y') : 'N/A') . '</td>';
                    $subtotal = (float) ($marketRate->total_amount ?? 0);
                    $vatAmount = (float) ($marketRate->vat_amount ?? 0);
                    $totalWithVat = (float) ($marketRate->total_amount_with_vat ?? 0);
                    if ($totalWithVat <= 0 && ($subtotal > 0 || $vatAmount > 0)) {
                        $totalWithVat = $subtotal + $vatAmount;
                    }
                    $html .= '<td class="text-end"><strong>$' . number_format($totalWithVat > 0 ? $totalWithVat : $subtotal, 2) . '</strong>';
                    if ($vatAmount > 0) {
                        $html .= '<br><small class="text-muted">Subtotal: $' . number_format($subtotal, 2) . ' + IVA: $' . number_format($vatAmount, 2) . '</small>';
                    }
                    $html .= '</td>';
                    $documentFiles = MarketRate::normalizeDocumentFilesToPathList($marketRate->document_files);

                    if ($marketRate->quoteDetails->isEmpty()) {
                        $productsHtml = '<span class="text-muted">Sin productos</span>';
                    } else {
                        $productsHtml = '<div><span class="badge bg-info mb-1">' . $marketRate->quoteDetails->count() . ' productos</span></div>';
                        $productsHtml .= '<ul class="mb-0 ps-3">';
                        foreach ($marketRate->quoteDetails as $detail) {
                            $productName = $detail->product->name ?? ('Producto #' . $detail->product_id);
                            if (is_array($productName)) {
                                $productName = 'Producto no encontrado';
                            }
                            $productsHtml .= '<li>' . e($productName);
                            $detailDescription = $detail->product_description ?? ($detail->product->description ?? null);
                            if ($detailDescription && !is_array($detailDescription)) {
                                $productsHtml .= '<br><small class="text-muted">' . e($detailDescription) . '</small>';
                            }
                            $productsHtml .= ' - Cant: ' . (float) $detail->quantity . ' - $' . number_format((float) $detail->unit_price, 2) . '/u</li>';
                        }
                        $productsHtml .= '</ul>';
                    }
                    $html .= '<td>' . $productsHtml . '</td>';
                    $html .= '<td>';
                    if ($isSelected) {
                        $html .= '<span class="badge bg-success">Seleccionada</span>';
                    } else {
                        $html .= '<span class="badge bg-secondary">Disponible</span>';
                    }
                    $html .= '</td>';
                    $html .= '<td>';
                    
                    // Solo el responsable de compras puede seleccionar cotizaciones
                    $user = backpack_user();
                    $adminRoles = ['role_admin_sistema', 'role_admin_institucion', 'role_responsable_compras'];
                    $canSelect = false;
                    foreach ($adminRoles as $role) {
                        if ($user && $user->hasRole($role, 'backpack')) {
                            $canSelect = true;
                            break;
                        }
                    }
                    
                    $html .= '<a href="' . route('market-rate.pdf', $marketRate->id) . '" class="btn btn-sm btn-outline-primary me-1" target="_blank">';
                    $html .= '<i class="la la-file-pdf-o"></i> PDF';
                    $html .= '</a>';

                    if ($documentFiles !== []) {
                        foreach ($documentFiles as $idx => $filePath) {
                            $label = $idx === 0 ? 'Archivo subido' : ('Archivo ' . ($idx + 1));
                            $fileUrl = route('market-rate.uploaded-file', ['id' => $marketRate->id, 'index' => $idx]);
                            $html .= '<a href="' . e($fileUrl) . '" class="btn btn-sm btn-outline-secondary me-1" target="_blank" rel="noopener">';
                            $html .= '<i class="la la-paperclip"></i> ' . e($label);
                            $html .= '</a>';
                        }
                    }

                    $referenceUrls = MarketRate::referenceLinkUrlsList($marketRate->reference_links);
                    if ($referenceUrls !== []) {
                        foreach ($referenceUrls as $idx => $linkUrl) {
                            $linkLabel = count($referenceUrls) === 1
                                ? 'Enlace (Mercado Libre u otros)'
                                : ('Enlace ' . ($idx + 1));
                            $html .= '<a href="' . e($linkUrl) . '" class="btn btn-sm btn-outline-info me-1" target="_blank" rel="noopener" title="' . e($linkUrl) . '">';
                            $html .= '<i class="la la-external-link"></i> ' . e($linkLabel);
                            $html .= '</a>';
                        }
                    }

                    if ($entry->status != 'Completada' && $canSelect) {
                        $html .= '<form method="POST" action="' . e(backpack_url('purchase-request/' . $entry->id . '/toggle-market-rate/' . $marketRate->id)) . '" style="display:inline-block;" class="me-1">';
                        $html .= csrf_field();
                        if ($isSelected) {
                            $html .= '<button type="submit" class="btn btn-sm btn-warning"><i class="la la-minus-circle"></i> Deseleccionar</button>';
                        } else {
                            $html .= '<button type="submit" class="btn btn-sm btn-success"><i class="la la-check"></i> Seleccionar</button>';
                        }
                        $html .= '</form>';
                    }
                    
                    $html .= '</td>';
                    $html .= '</tr>';
                }
                
                    $html .= '</tbody>';
                    $html .= '</table>';
                    $html .= '</div>';
                    
                    // Botón para descargar planilla comparativa (solo si hay más de una cotización)
                    $quotationsCount = $marketRates->count();
                    if ($quotationsCount > 1) {
                        $user = backpack_user();
                        // Solo mostrar si el usuario no es role_responsable_area
                        if (!$user || !$user->hasRole('role_responsable_area', 'backpack')) {
                            $html .= '<div class="mt-3">';
                            $html .= '<a href="' . route('purchase-request.comparative-excel', $entry->id) . '" class="btn btn-success">';
                            $html .= '<i class="la la-file-excel"></i> Descargar Planilla Comparativa';
                            $html .= '</a>';
                            $html .= '</div>';
                        }
                    }

                    // Asignar cotización por producto (para OC con varios proveedores)
                    $entry->load(['details.product', 'details.selectedMarketRate.supplier']);
                    if ($quotationsCount >= 2 && $entry->status === 'Aprobada' && $entry->status !== 'Completada') {
                        $html .= '<div class="card mt-3 border-info">';
                        $html .= '<div class="card-header bg-info text-white"><strong><i class="la la-link"></i> Asignar cotización por producto</strong></div>';
                        $html .= '<div class="card-body">';
                        $html .= '<p class="text-muted small">Asigne para cada producto qué cotización usar. Luego puede generar <strong>una sola orden de compra</strong> con ítems de distintos proveedores.</p>';
                        $html .= '<form method="POST" action="' . route('purchase-request.assign-quotations', $entry->id) . '">';
                        $html .= csrf_field();
                        $html .= '<div class="table-responsive"><table class="table table-sm table-bordered">';
                        $html .= '<thead><tr><th>Producto</th><th>Cantidad</th><th>Cotización a usar</th></tr></thead><tbody>';
                        foreach ($entry->details as $detail) {
                            $productName = $detail->product ? $detail->product->name : 'Producto #' . $detail->id;
                            $ratesWithProduct = $marketRates->filter(function ($mr) use ($detail) {
                                return $mr->quoteDetails->contains('product_id', $detail->product_id);
                            });
                            $html .= '<tr><td>' . e($productName) . '</td><td>' . (int)$detail->requested_quantity . '</td><td>';
                            $html .= '<select name="detail_quote[' . $detail->id . ']" class="form-control form-control-sm">';
                            $html .= '<option value="">— Sin asignar —</option>';
                            foreach ($ratesWithProduct as $mr) {
                                $qd = $mr->quoteDetails->firstWhere('product_id', $detail->product_id);
                                $price = $qd ? number_format($qd->unit_price, 2) : '—';
                                $supplierName = $mr->supplier ? $mr->supplier->company_name : 'Proveedor';
                                $selected = $detail->selected_market_rate_id == $mr->id ? ' selected' : '';
                                $html .= '<option value="' . $mr->id . '"' . $selected . '>' . e($supplierName) . ' — $' . $price . '/u</option>';
                            }
                            $html .= '</select></td></tr>';
                        }
                        $html .= '</tbody></table></div>';
                        $html .= '<button type="submit" class="btn btn-info btn-sm"><i class="la la-save"></i> Guardar asignación</button>';
                        $html .= '</form></div></div>';
                    }
                }
                
                // Lógica para mostrar botón de generar orden según el monto
                // El rol role_responsable_area no puede generar órdenes de compra
                $user = backpack_user();
                $canGenerateOrder = !($user && $user->hasRole('role_responsable_area', 'backpack'));
                
                $entry->load('marketRates.quoteDetails');
                $totalAmount = $this->recalculateSelectedQuotationsTotalForPurchaseRequest($entry);
                $threshold = 60000;
                // Usar la relación del modelo en lugar de consulta directa
                $entry->load('marketRates');
                $quotationsCount = $entry->marketRates->count();
                
                // Botón para agregar nueva cotización (compras, admin y responsable de área; solo si no está aprobada/completada)
                $user = backpack_user();
                $adminRoles = ['role_admin_sistema', 'role_admin_institucion', 'role_responsable_compras', 'role_responsable_area'];
                $canCreateQuotation = false;
                foreach ($adminRoles as $role) {
                    if ($user && $user->hasRole($role, 'backpack')) {
                        $canCreateQuotation = true;
                        break;
                    }
                }
                
                // Solo mostrar el botón si tiene permiso Y la solicitud no está aprobada
                if ($canCreateQuotation && $entry->status !== 'Aprobada' && $entry->status !== 'Completada') {
                    $html .= '<div class="mt-3">';
                    $html .= '<a href="' . backpack_url('market-rate/create?purchase_request_id=' . $entry->id) . '" class="btn btn-success">';
                    $html .= '<i class="la la-plus"></i> Agregar Nueva Cotización';
                    $html .= '</a>';
                    $html .= '</div>';
                }
                
                if ($entry->status != 'Completada' && $canGenerateOrder) {
                    // Verificar si es compra directa autorizada
                    $isDirectPurchaseAuthorized = $entry->is_direct_purchase 
                                               && $entry->direct_purchase_authorized_by 
                                               && $entry->direct_purchase_supplier_id
                                               && !$entry->direct_purchase_authorization_rejected;
                    
                    if ($isDirectPurchaseAuthorized) {
                        // Para compras directas autorizadas, mostrar formulario para generar orden sin cotizaciones
                        if ($entry->status !== 'Aprobada') {
                            $html .= '<div class="mt-3 alert alert-warning">';
                            $html .= '<i class="la la-exclamation-triangle"></i> <strong>Atención:</strong> La solicitud debe estar aprobada antes de generar la orden de compra.';
                            $html .= '</div>';
                        } else {
                            $html .= '<div class="mt-3">';
                            $html .= '<div class="alert alert-success">';
                            $html .= '<i class="la la-check-circle"></i> <strong>Compra Directa Autorizada:</strong> Esta compra directa ha sido autorizada. Puede proceder a generar la orden de compra sin necesidad de cotizaciones.';
                            $html .= '</div>';
                            $html .= '<form method="POST" action="' . route('purchase-request.generate-purchase-order', $entry->id) . '">';
                            $html .= csrf_field();
                            $html .= '<div class="row mb-3">';
                            $html .= '<div class="col-md-4">';
                            $html .= '<label for="issue_date" class="form-label">Fecha de Emisión:</label>';
                            $html .= '<input type="date" name="issue_date" id="issue_date" class="form-control" value="' . date('Y-m-d') . '" required>';
                            $html .= '</div>';
                            $html .= '</div>';
                            $html .= '<button type="submit" class="btn btn-primary" onclick="return confirm(\'¿Está seguro de generar la orden de compra?\')">';
                            $html .= '<i class="la la-shopping-cart"></i> Generar Orden de Compra';
                            $html .= '</button>';
                            $html .= '</form>';
                            $html .= '</div>';
                        }
                    } elseif ($totalAmount > $threshold) {
                        // Para montos mayores a 60000, se requieren OBLIGATORIAMENTE 3 cotizaciones y una seleccionada
                        if ($entry->status !== 'Aprobada') {
                            $html .= '<div class="mt-3 alert alert-warning">';
                            $html .= '<i class="la la-exclamation-triangle"></i> <strong>Atención:</strong> La solicitud debe estar aprobada antes de generar la orden de compra.';
                            $html .= '</div>';
                        } elseif ($quotationsCount < 3) {
                            // Mostrar mensaje de error indicando que es obligatorio tener 3 cotizaciones
                            $html .= '<div class="mt-3 alert alert-danger">';
                            $html .= '<i class="la la-exclamation-triangle"></i> <strong>No se puede generar la orden de compra:</strong> Para solicitudes mayores a $' . number_format($threshold, 2) . ' se requieren <strong>OBLIGATORIAMENTE 3 cotizaciones</strong>. Actualmente hay ' . $quotationsCount . ' cotización(es). Debe agregar ' . (3 - $quotationsCount) . ' cotización(es) más antes de poder generar la orden de compra.';
                            $html .= '</div>';
                        } else {
                            // Validar que cada producto esté cotizado en al menos 3 cotizaciones distintas
                            $productsWithFewerQuotations = $entry->getProductsWithFewerThanThreeQuotations();
                            if ($productsWithFewerQuotations->isNotEmpty()) {
                                $productNames = $productsWithFewerQuotations->pluck('name')->implode(', ');
                                $html .= '<div class="mt-3 alert alert-danger">';
                                $html .= '<i class="la la-exclamation-triangle"></i> <strong>No se puede generar la orden de compra:</strong> Los siguientes productos deben estar cotizados en <strong>al menos 3 cotizaciones distintas</strong>: ' . e($productNames) . '. Agregue estos productos a más cotizaciones antes de poder generar la orden.';
                                $html .= '</div>';
                            } else {
                                $allDetailsAssigned = $entry->details->isNotEmpty() && $entry->details->every(fn ($d) => !empty($d->selected_market_rate_id));
                                $canGenerateWithQuote = $entry->selected_market_rate_id || $allDetailsAssigned;
                                if (!$canGenerateWithQuote) {
                                    $html .= '<div class="mt-3 alert alert-warning">';
                                    $html .= '<i class="la la-exclamation-triangle"></i> <strong>Atención:</strong> Debe seleccionar una cotización para toda la solicitud, o asignar una cotización por producto en la sección de arriba.';
                                    $html .= '</div>';
                                } else {
                                // Hay 3 cotizaciones, todos los productos con 3+ cotizaciones y (una seleccionada o asignación por producto), mostrar formulario
                                $html .= '<div class="mt-3">';
                                $html .= '<div class="alert alert-success">';
                                $html .= '<i class="la la-check-circle"></i> <strong>Listo para generar orden:</strong> ' . ($allDetailsAssigned ? 'Tiene asignada una cotización por producto. Se generará una OC con varios proveedores.' : 'Tiene una cotización seleccionada.') . ' Puede proceder a generar la orden de compra.';
                            $html .= '</div>';
                            $html .= '<form method="POST" action="' . route('purchase-request.generate-purchase-order', $entry->id) . '">';
                            $html .= csrf_field();
                            $html .= '<div class="row mb-3">';
                            $html .= '<div class="col-md-4">';
                            $html .= '<label for="issue_date" class="form-label">Fecha de Emisión:</label>';
                            $html .= '<input type="date" name="issue_date" id="issue_date" class="form-control" value="' . date('Y-m-d') . '" required>';
                            $html .= '</div>';
                            $html .= '</div>';
                            $html .= '<button type="submit" class="btn btn-primary" onclick="return confirm(\'¿Está seguro de generar la orden de compra?\')">';
                            $html .= '<i class="la la-shopping-cart"></i> Generar Orden de Compra';
                            $html .= '</button>';
                            $html .= '</form>';
                            $html .= '</div>';
                                }
                            }
                        }
                    } else {
                        // Para montos <= 60000, se puede generar sin cotización (pero necesita proveedor)
                        $html .= '<div class="mt-3">';
                        if ($totalAmount == 0) {
                            $html .= '<div class="alert alert-warning">';
                            $html .= '<i class="la la-exclamation-triangle"></i> <strong>Atención:</strong> Esta solicitud tiene un monto de $0.00. Debe asignar precios a los productos antes de generar la orden de compra. Puede editar la solicitud para asignar los precios.';
                            $html .= '</div>';
                        } else {
                            // Para montos <= 60000, se requiere una sola cotización
                            if ($quotationsCount == 0) {
                                $html .= '<div class="alert alert-info">';
                                $html .= '<i class="la la-info-circle"></i> <strong>Información:</strong> Esta solicitud tiene un monto de $' . number_format($totalAmount, 2) . ', por lo que requiere seleccionar un proveedor y subir su cotización (una sola cotización).';
                                $html .= '</div>';
                            } elseif ($quotationsCount == 1 && !$entry->selected_market_rate_id) {
                                $html .= '<div class="alert alert-warning">';
                                $html .= '<i class="la la-exclamation-triangle"></i> <strong>Atención:</strong> Tiene una cotización cargada. Debe seleccionarla antes de generar la orden de compra.';
                                $html .= '</div>';
                            } elseif ($quotationsCount == 1 && $entry->selected_market_rate_id) {
                                // Hay una cotización y está seleccionada, verificar que esté aprobada
                                if ($entry->status !== 'Aprobada') {
                                    $html .= '<div class="alert alert-warning">';
                                    $html .= '<i class="la la-exclamation-triangle"></i> <strong>Atención:</strong> La solicitud debe estar aprobada antes de generar la orden de compra.';
                                    $html .= '</div>';
                                } else {
                                    // Hay una cotización y está seleccionada, mostrar formulario para generar orden
                                    $html .= '<div class="alert alert-success">';
                                    $html .= '<i class="la la-check-circle"></i> <strong>Listo para generar orden:</strong> Tiene una cotización cargada y seleccionada. Puede proceder a generar la orden de compra.';
                                    $html .= '</div>';
                                    $html .= '<form method="POST" action="' . route('purchase-request.generate-purchase-order', $entry->id) . '">';
                                    $html .= csrf_field();
                                    $html .= '<div class="row mb-3">';
                                    $html .= '<div class="col-md-4">';
                                    $html .= '<label for="issue_date" class="form-label">Fecha de Emisión:</label>';
                                    $html .= '<input type="date" name="issue_date" id="issue_date" class="form-control" value="' . date('Y-m-d') . '" required>';
                                    $html .= '</div>';
                                    $html .= '</div>';
                                    $html .= '<div class="text-end">';
                                    $html .= '<button type="submit" class="btn btn-primary" onclick="return confirm(\'¿Está seguro de generar la orden de compra?\')">';
                                    $html .= '<i class="la la-shopping-cart"></i> Generar Orden de Compra';
                                    $html .= '</button>';
                                    $html .= '</div>';
                                    $html .= '</form>';
                                }
                            } else {
                                // Hay más de una cotización: puede seleccionar una global o asignar por producto
                                $allDetailsAssignedLow = $entry->details->isNotEmpty() && $entry->details->every(fn ($d) => !empty($d->selected_market_rate_id));
                                if ($entry->selected_market_rate_id || $allDetailsAssignedLow) {
                                    $html .= '<div class="alert alert-success">';
                                    $html .= '<i class="la la-check-circle"></i> <strong>Listo para generar orden:</strong> ' . ($allDetailsAssignedLow ? 'Tiene asignada una cotización por producto.' : 'Tiene una cotización seleccionada.') . ' Puede proceder a generar la orden de compra.';
                                    $html .= '</div>';
                                    $html .= '<form method="POST" action="' . route('purchase-request.generate-purchase-order', $entry->id) . '">';
                                    $html .= csrf_field();
                                    $html .= '<div class="row mb-3"><div class="col-md-4">';
                                    $html .= '<label for="issue_date" class="form-label">Fecha de Emisión:</label>';
                                    $html .= '<input type="date" name="issue_date" id="issue_date" class="form-control" value="' . date('Y-m-d') . '" required>';
                                    $html .= '</div></div>';
                                    $html .= '<button type="submit" class="btn btn-primary" onclick="return confirm(\'¿Está seguro de generar la orden de compra?\')">';
                                    $html .= '<i class="la la-shopping-cart"></i> Generar Orden de Compra';
                                    $html .= '</button></form>';
                                } else {
                                    $html .= '<div class="alert alert-warning">';
                                    $html .= '<i class="la la-exclamation-triangle"></i> <strong>Atención:</strong> Tiene ' . $quotationsCount . ' cotización(es). Seleccione una para toda la solicitud o asigne una cotización por producto en la sección de arriba.';
                                    $html .= '</div>';
                                }
                            }
                        }
                        $html .= '</div>';
                    }
                }
                
                return $html;
            });

        // Agregar campo para mostrar sugerencias de proveedores
        CRUD::column('supplier_suggestions_table')->label('Sugerencias de Proveedores')->type('custom_html')
            ->value(function($entry) {
                try {
                    $entry->load(['supplierSuggestions.supplier', 'supplierSuggestions.suggestedBy']);
                    $suggestions = $entry->supplierSuggestions;
                } catch (\Exception $e) {
                    // Si hay un error al cargar las sugerencias (por ejemplo, tabla no existe), usar colección vacía
                    $suggestions = collect([]);
                }
                
                $html = '';
                
                $user = backpack_user();
                $isResponsableArea = $user && $user->hasRole('role_responsable_area', 'backpack');
                
                // Botón para sugerir proveedor (solo responsables de área)
                if ($isResponsableArea && $entry->status != 'Completada') {
                    $html .= '<div class="mb-3">';
                    $html .= '<a href="' . route('purchase-request.suggest-supplier', $entry->id) . '" class="btn btn-info">';
                    $html .= '<i class="la la-lightbulb"></i> Sugerir Proveedor';
                    $html .= '</a>';
                    $html .= '</div>';
                }
                
                if ($suggestions->isEmpty()) {
                    $html .= '-';
                } else {
                    $html .= '<div class="table-responsive">';
                    $html .= '<table class="table table-striped table-bordered">';
                    $html .= '<thead class="thead-dark">';
                    $html .= '<tr>';
                    $html .= '<th>Proveedor</th>';
                    $html .= '<th>Sugerido por</th>';
                    $html .= '<th>Justificación</th>';
                    $html .= '<th>Fecha</th>';
                    $html .= '</tr>';
                    $html .= '</thead>';
                    $html .= '<tbody>';
                    
                    foreach ($suggestions as $suggestion) {
                        $html .= '<tr>';
                        $supplierName = $suggestion->supplier->company_name ?? 'Proveedor no encontrado';
                        if (is_array($supplierName)) {
                            $supplierName = 'Proveedor no encontrado';
                        }
                        $html .= '<td><strong>' . $supplierName . '</strong></td>';
                        $suggestedByName = $suggestion->suggestedBy->name ?? 'Usuario no encontrado';
                        if (is_array($suggestedByName)) {
                            $suggestedByName = 'Usuario no encontrado';
                        }
                        $html .= '<td>' . $suggestedByName . '</td>';
                        $justification = $suggestion->justification ?? 'Sin justificación';
                        if (is_array($justification)) {
                            $justification = 'Sin justificación';
                        }
                        $html .= '<td>' . $justification . '</td>';
                        $createdAt = $suggestion->created_at;
                        if (is_string($createdAt)) {
                            $createdAt = \Carbon\Carbon::parse($createdAt);
                        }
                        $html .= '<td>' . ($createdAt ? $createdAt->format('d/m/Y H:i') : 'N/A') . '</td>';
                        $html .= '</tr>';
                    }
                    
                    $html .= '</tbody>';
                    $html .= '</table>';
                    $html .= '</div>';
                }
                
                return $html;
            });

        // Agregar información de selección si existe
        CRUD::field('selection_info')->label('Información de Selección')->type('custom_html')
            ->value(function($entry) {
                if (!$entry->selected_market_rate_id) {
                    return '';
                }
                
                // Verificar que las relaciones estén cargadas
                if (!$entry->selectedMarketRate || !$entry->selectedMarketRate->supplier) {
                    return '<div class="alert alert-warning">Información de cotización seleccionada no disponible.</div>';
                }
                
                $html = '<div class="alert alert-success">';
                $html .= '<h5><i class="la la-check-circle"></i> Cotización Seleccionada</h5>';
                $supplierName = $entry->selectedMarketRate->supplier->company_name ?? 'Proveedor no encontrado';
                if (is_array($supplierName)) {
                    $supplierName = 'Proveedor no encontrado';
                }
                $html .= '<p><strong>Proveedor:</strong> ' . $supplierName . '</p>';
                $html .= '<p><strong>Total:</strong> $' . number_format($entry->selectedMarketRate->total_amount ?? 0, 2) . '</p>';
                $selectedByName = $entry->selectedBy->name ?? 'Usuario no encontrado';
                if (is_array($selectedByName)) {
                    $selectedByName = 'Usuario no encontrado';
                }
                $html .= '<p><strong>Seleccionado por:</strong> ' . $selectedByName . '</p>';
                $selectedAt = $entry->selected_at;
                if ($selectedAt) {
                    if (is_string($selectedAt)) {
                        $selectedAt = \Carbon\Carbon::parse($selectedAt);
                    }
                    $selectedAtFormatted = $selectedAt ? $selectedAt->format('d/m/Y H:i') : 'No disponible';
                } else {
                    $selectedAtFormatted = 'No disponible';
                }
                $html .= '<p><strong>Fecha de selección:</strong> ' . $selectedAtFormatted . '</p>';
                if ($entry->selection_justification && !is_array($entry->selection_justification)) {
                    $html .= '<p><strong>Justificación:</strong> ' . $entry->selection_justification . '</p>';
                }
                $html .= '</div>';
                return $html;
            });

        // Agregar botones para crear entregas y recepciones (solo para role_responsable_area)
        CRUD::column('delivery_reception_actions')->label('Acciones de Entrega y Recepción')->type('custom_html')
            ->value(function($entry) {
                $user = backpack_user();
                $isResponsableArea = $user && $user->hasRole('role_responsable_area', 'backpack');
                
                if (!$isResponsableArea) {
                    return '';
                }
                
                // Verificar condiciones:
                // 1. La solicitud debe estar aprobada
                $isApproved = $entry->status === 'Aprobada';
                
                // 2. Debe existir al menos una orden de compra relacionada
                $entry->load('purchaseOrders.paymentOrders');
                $hasPurchaseOrder = $entry->purchaseOrders->isNotEmpty();
                
                // 3. Debe existir al menos una orden de pago relacionada con alguna orden de compra
                $hasPaymentOrder = false;
                if ($hasPurchaseOrder) {
                    foreach ($entry->purchaseOrders as $purchaseOrder) {
                        if ($purchaseOrder->paymentOrders->isNotEmpty()) {
                            $hasPaymentOrder = true;
                            break;
                        }
                    }
                }
                
                // Si no se cumplen todas las condiciones, no mostrar nada
                if (!$isApproved || !$hasPurchaseOrder || !$hasPaymentOrder) {
                    return '';
                }
                
                $html = '<div class="card border-success mt-3">';
                $html .= '<div class="card-header bg-success text-white">';
                $html .= '<h6 class="mb-0"><i class="la la-tasks"></i> Acciones Disponibles</h6>';
                $html .= '</div>';
                $html .= '<div class="card-body">';
                $html .= '<div class="row">';
                
                // Botón para crear entrega
                $html .= '<div class="col-md-6 mb-2">';
                $html .= '<a href="' . backpack_url('delivery/create?purchase_request_id=' . $entry->id) . '" class="btn btn-primary btn-block">';
                $html .= '<i class="la la-people-carry"></i> Crear Entrega';
                $html .= '</a>';
                $html .= '</div>';
                
                // Botón para crear recepción
                // Necesitamos obtener la primera orden de compra para pasarla como parámetro
                $firstPurchaseOrder = $entry->purchaseOrders->first();
                if ($firstPurchaseOrder) {
                    $html .= '<div class="col-md-6 mb-2">';
                    $html .= '<a href="' . backpack_url('reception/create?purchase_order_id=' . $firstPurchaseOrder->id) . '" class="btn btn-success btn-block">';
                    $html .= '<i class="la la-truck-loading"></i> Crear Recepción';
                    $html .= '</a>';
                    $html .= '</div>';
                }
                
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                
                return $html;
            });
        
        // Agregar botones de acción en la vista previa
        // Botón para generar planilla comparativa (solo para usuarios que no sean role_responsable_area)
        $user = backpack_user();
        if (!$user || !$user->hasRole('role_responsable_area', 'backpack')) {
            CRUD::addButton('top', 'comparative_excel', 'view', 'crud::buttons.comparative_excel', 'end');
            
            // Botón para generar/ver orden de compra (solo para usuarios que no sean role_responsable_area)
            CRUD::addButton('top', 'purchase_order_action', 'view', 'crud::buttons.purchase_order_action', 'end');
        }
        
        // Agregar columna para botones de aprobación o información de aprobación
        CRUD::column('approval_actions')->label('Aprobación')->type('custom_html')
            ->value(function($entry) {
                $user = backpack_user();
                if (!$user) {
                    return '';
                }
                
                // Si la solicitud está aprobada, mostrar información de aprobación
                if ($entry->status === 'Aprobada') {
                    $html = '<div class="card border-success mt-3">';
                    $html .= '<div class="card-header bg-success text-white">';
                    $html .= '<h6 class="mb-0"><i class="la la-check-circle"></i> Solicitud Aprobada</h6>';
                    $html .= '</div>';
                    $html .= '<div class="card-body">';
                    
                    if ($entry->approvedBy) {
                        $html .= '<p class="mb-2"><strong>Aprobada por:</strong> ' . e($entry->approvedBy->name) . '</p>';
                    }
                    
                    if ($entry->approved_date) {
                        $approvedDate = $entry->approved_date instanceof \Carbon\Carbon 
                            ? $entry->approved_date->format('d/m/Y H:i') 
                            : \Carbon\Carbon::parse($entry->approved_date)->format('d/m/Y H:i');
                        $html .= '<p class="mb-2"><strong>Fecha de aprobación:</strong> ' . $approvedDate . '</p>';
                    }
                    
                    if ($entry->approval_justification) {
                        $html .= '<p class="mb-0"><strong>Justificación:</strong> ' . nl2br(e($entry->approval_justification)) . '</p>';
                    }
                    
                    $html .= '</div>';
                    $html .= '</div>';
                    
                    return $html;
                }
                
                // Si la solicitud está rechazada, mostrar información
                if ($entry->status === 'Rechazada') {
                    $html = '<div class="card border-danger mt-3">';
                    $html .= '<div class="card-header bg-danger text-white">';
                    $html .= '<h6 class="mb-0"><i class="la la-times-circle"></i> Solicitud Rechazada</h6>';
                    $html .= '</div>';
                    $html .= '<div class="card-body">';
                    
                    if ($entry->approvedBy) {
                        $html .= '<p class="mb-2"><strong>Rechazada por:</strong> ' . e($entry->approvedBy->name) . '</p>';
                    }
                    
                    if ($entry->approved_date) {
                        $rejectedDate = $entry->approved_date instanceof \Carbon\Carbon 
                            ? $entry->approved_date->format('d/m/Y H:i') 
                            : \Carbon\Carbon::parse($entry->approved_date)->format('d/m/Y H:i');
                        $html .= '<p class="mb-0"><strong>Fecha de rechazo:</strong> ' . $rejectedDate . '</p>';
                    }
                    
                    $html .= '</div>';
                    $html .= '</div>';
                    
                    return $html;
                }
                
                // Si está completada, no mostrar nada
                if ($entry->status === 'Completada') {
                    return '';
                }
                
                // Si es una compra directa pendiente de autorización, no mostrar nada aquí
                // (se maneja en la columna direct_purchase_actions)
                if ($entry->is_direct_purchase && $entry->direct_purchase_authorization_requested && !$entry->direct_purchase_authorized_by && !$entry->direct_purchase_authorization_rejected) {
                    return '';
                }

                // Recalcular monto efectivo (cotizaciones seleccionadas, incluyendo IVA) para validar límites correctamente en UI.
                $effectiveTotal = $this->recalculateSelectedQuotationsTotalForPurchaseRequest($entry);
                $comprasLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_responsable_compras');
                $entryForApproval = clone $entry;
                $entryForApproval->total_amount = $effectiveTotal;
                $entryForApproval->requires_admin_approval = $effectiveTotal > $comprasLimit;
                
                // Verificar si el usuario puede aprobar esta solicitud
                if (!$entryForApproval->canBeApprovedBy($user)) {
                    // Si es responsable de compras y supera su límite
                    if ($user->hasRole('role_responsable_compras', 'backpack')) {
                        $adminLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_admin_institucion');
                        $apoderadoLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_apoderado');
                        $representanteLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_representante_legal');
                        return '<div class="alert alert-warning mt-3">
                            <i class="la la-exclamation-triangle"></i> 
                            <strong>Límite excedido:</strong> Esta solicitud ($' . number_format($effectiveTotal, 2) . ') supera tu límite de autorización de $' . number_format($comprasLimit, 2) . '. No puedes aprobar esta solicitud. Requiere aprobación del administrador del instituto (límite: $' . number_format($adminLimit, 2) . '), apoderado (límite: $' . number_format($apoderadoLimit, 2) . ') o representante legal (límite: $' . number_format($representanteLimit, 2) . ').
                        </div>';
                    }
                    
                    // Si es administrador del instituto y supera su límite
                    if ($user->hasRole('role_admin_institucion', 'backpack')) {
                        $adminLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_admin_institucion');
                        return '<div class="alert alert-danger mt-3">
                            <i class="la la-exclamation-triangle"></i> 
                            <strong>Límite excedido:</strong> Esta solicitud ($' . number_format($effectiveTotal, 2) . ') supera tu límite de autorización de $' . number_format($adminLimit, 2) . '. No puedes aprobar esta solicitud.
                        </div>';
                    }
                    
                    // Si es apoderado y supera su límite
                    if ($user->hasRole('role_apoderado', 'backpack')) {
                        $apoderadoLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_apoderado');
                        return '<div class="alert alert-danger mt-3">
                            <i class="la la-exclamation-triangle"></i> 
                            <strong>Límite excedido:</strong> Esta solicitud ($' . number_format($effectiveTotal, 2) . ') supera tu límite de autorización de $' . number_format($apoderadoLimit, 2) . '. No puedes aprobar esta solicitud.
                        </div>';
                    }
                    
                    // Si es representante legal y supera su límite
                    if ($user->hasRole('role_representante_legal', 'backpack')) {
                        $representanteLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_representante_legal');
                        return '<div class="alert alert-danger mt-3">
                            <i class="la la-exclamation-triangle"></i> 
                            <strong>Límite excedido:</strong> Esta solicitud ($' . number_format($effectiveTotal, 2) . ') supera tu límite de autorización de $' . number_format($representanteLimit, 2) . '. No puedes aprobar esta solicitud.
                        </div>';
                    }
                    
                    // Si requiere aprobación de administrador y el usuario no es admin, apoderado ni representante legal
                    if ($entryForApproval->requires_admin_approval) {
                        $adminLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_admin_institucion');
                        $apoderadoLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_apoderado');
                        $representanteLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_representante_legal');
                        return '<div class="alert alert-warning mt-3">
                            <i class="la la-exclamation-triangle"></i> 
                            <strong>Requiere aprobación:</strong> Esta solicitud ($' . number_format($effectiveTotal, 2) . ') supera el límite de autorización del responsable de compras ($' . number_format($comprasLimit, 2) . '). Requiere aprobación del administrador del instituto (límite: $' . number_format($adminLimit, 2) . '), apoderado (límite: $' . number_format($apoderadoLimit, 2) . ') o representante legal (límite: $' . number_format($representanteLimit, 2) . ').
                        </div>';
                    }
                    return '';
                }
                
                // Mostrar formulario de aprobación/rechazo
                $html = '<div class="card border-primary mt-3">';
                $html .= '<div class="card-header bg-primary text-white">';
                $html .= '<h6 class="mb-0"><i class="la la-check-circle"></i> Acciones de Aprobación</h6>';
                $html .= '</div>';
                $html .= '<div class="card-body">';

                $hasSelectedQuotation = !empty($entry->selected_market_rate_id)
                    || $entry->marketRates()->where('is_selected', true)->exists();
                if (!$entry->is_direct_purchase && !$hasSelectedQuotation) {
                    $html .= '<div class="alert alert-warning mb-3">';
                    $html .= '<i class="la la-exclamation-triangle"></i> Debe seleccionar al menos una cotización en "Cotizaciones Disponibles" antes de aprobar.';
                    $html .= '</div>';
                }
                
                // Formulario para aprobar
                if ($entry->is_direct_purchase || $hasSelectedQuotation) {
                    $html .= '<form method="POST" action="' . route('purchase-request.approve', $entry->id) . '" class="d-inline">';
                    $html .= csrf_field();
                    $html .= '<div class="mb-3">';
                    $html .= '<label for="approval_justification" class="form-label">Justificación de Aprobación:</label>';
                    $html .= '<textarea name="approval_justification" id="approval_justification" class="form-control" rows="3" required></textarea>';
                    $html .= '</div>';
                    $html .= '<button type="submit" class="btn btn-success" onclick="return confirm(\'¿Está seguro de aprobar esta solicitud de compra?\')">';
                    $html .= '<i class="la la-check"></i> Aprobar Solicitud';
                    $html .= '</button>';
                    $html .= '</form>';
                }
                
                // Botón para rechazar
                $html .= '<form method="POST" action="' . route('purchase-request.reject', $entry->id) . '" class="d-inline ms-2">';
                $html .= csrf_field();
                $html .= '<button type="submit" class="btn btn-danger" onclick="return confirm(\'¿Está seguro de rechazar esta solicitud de compra?\')">';
                $html .= '<i class="la la-times"></i> Rechazar Solicitud';
                $html .= '</button>';
                $html .= '</form>';
                
                $html .= '</div>';
                $html .= '</div>';
                
                return $html;
            });

    }

    /**
     * API endpoint to get purchase request data for quick purchase
     */
    public function getPurchaseRequestData($id)
    {
        $purchaseRequest = \App\Models\PurchaseRequest::with(['details.product'])
            ->findOrFail($id);
        
        return response()->json([
            'id' => $purchaseRequest->id,
            'request_number' => $purchaseRequest->request_number,
            'total_amount' => $purchaseRequest->total_amount,
            'details' => $purchaseRequest->details->map(function($detail) {
                return [
                    'id' => $detail->id,
                    'product_id' => $detail->product_id,
                    'product_description' => $detail->product_description,
                    'product' => $detail->product ? [
                        'id' => $detail->product->id,
                        'name' => $detail->product->name,
                    ] : null,
                    'requested_quantity' => $detail->requested_quantity,
                    'estimated_unit_price' => $detail->estimated_unit_price,
                ];
            })
        ]);
    }

    /**
     * API endpoint to get suppliers list
     */
    public function getSuppliers()
    {
        $suppliers = \App\Models\Supplier::select('id', 'company_name')->get();
        return response()->json($suppliers);
    }

}

