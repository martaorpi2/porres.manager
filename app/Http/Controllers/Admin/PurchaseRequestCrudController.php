<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use App\Http\Requests\PurchaseRequestRequest;

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
        
        CRUD::addClause('with', ['responsibilityArea', 'requestingUser', 'details', 'purchaseOrders']);
        
        // Filtrar solicitudes según el rol del usuario
        $user = backpack_user();
        if ($user) {
            // Roles que pueden ver todas las solicitudes (administradores)
            $adminRoles = ['role_admin_sistema', 'role_admin_institucion', 'role_responsable_compras'];
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
                // Usar la relación del modelo en lugar de consulta directa
                $entry->load('marketRates');
                $quotationsCount = $entry->marketRates->count();
                
                if ($quotationsCount > 0) {
                    return '<span class="badge bg-success">' . $quotationsCount . ' cotizaciones</span>';
                } else {
                    return '<span class="badge bg-warning">Sin cotizaciones</span>';
                }
            });

        // Remover botón de edición por defecto y usar el personalizado
        CRUD::removeButton('update');
        CRUD::addButton('line', 'edit_purchase_request', 'view', 'crud::buttons.edit_purchase_request', 'beginning');
        
        // Botón para ver orden de compra (solo si existe y para usuarios que no sean role_responsable_area)
        if (!$user || !$user->hasRole('role_responsable_area', 'backpack')) {
            CRUD::addButton('line', 'view_purchase_order', 'view', 'crud::buttons.view_purchase_order', 'end');
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
        
        if ($convertedFrom) {
            $generalRequest = \App\Models\GeneralRequest::find($convertedFrom);
        }

        // Setup common fields
        $this->setupCreateFields();
        
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
                $generalRequest = \App\Models\GeneralRequest::find($convertedFrom);
                if ($generalRequest) {
                    $generalRequestInfo = '<div class="alert alert-info">
                        <h5><i class="la la-info-circle"></i> Conversión desde Solicitud General</h5>
                        <p><strong>Número:</strong> ' . ($generalRequest->number ?? 'N/A') . '</p>
                        <p><strong>Título:</strong> ' . ($generalRequest->title ?? 'N/A') . '</p>
                        <p><strong>Descripción:</strong> ' . ($generalRequest->description ?? 'N/A') . '</p>
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
        
        // Verificar si el usuario es administrador (puede editar cualquier solicitud)
        $adminRoles = ['role_admin_sistema', 'role_admin_institucion', 'role_responsable_compras'];
        $isAdmin = false;
        foreach ($adminRoles as $role) {
            if ($user->hasRole($role, 'backpack')) {
                $isAdmin = true;
                break;
            }
        }
        
        $isOwnRequest = $entry->requesting_user_id == $user->id;
        
        // Verificar si el usuario es responsable de área
        $isResponsableArea = $user && $user->hasRole('role_responsable_area', 'backpack');
        
        // Si no es administrador, verificar restricciones
        if (!$isAdmin) {
            // Todos los usuarios solo pueden editar sus propias solicitudes
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
            
            // Campo para seleccionar productos
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
                        'specifications' => $detail->specifications ?? ''
                    ];
                })->toArray();
                
                CRUD::field('products_selection')->label('Productos Solicitados')->type('custom_html')
                    ->value($this->getProductsSelectionHtml($existingProducts, $entry->responsibility_area_id));
            } else {
                CRUD::field('products_selection')->label('Productos Solicitados')->type('custom_html')
                    ->value($this->getProductsSelectionHtml([], $entry->responsibility_area_id));
            }
        } else {
            // Para administradores, usar todos los campos
            $this->setupCreateFields();
            
            // Cargar productos existentes para edición
            if ($entry) {
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
                            'specifications' => $detail->specifications ?? ''
                        ];
                    })->toArray();
                    
                    // Modificar el campo de productos para incluir los existentes
                    CRUD::modifyField('products_selection', [
                        'value' => $this->getProductsSelectionHtml($existingProducts)
                    ]);
                }
            }
            
            // Agregar campos adicionales para actualización
            CRUD::field('status')->label('Estado')
                ->type('select_from_array')
                ->options([
                    'Pendiente' => 'Pendiente',
                    'Aprobada' => 'Aprobada',
                    'Rechazada' => 'Rechazada',
                    'En Proceso' => 'En Proceso',
                    'Completada' => 'Completada'
                ]);
            
            CRUD::field('approved_by')->label('Aprobado por')
                ->type('select')
                ->model('App\Models\User')
                ->attribute('name');
                
            CRUD::field('approved_date')->label('Fecha de Aprobación')->type('date');
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
        CRUD::field('total_amount')->type('hidden')->default(0);
        
        // Campo oculto para conversión desde solicitud general (se establecerá dinámicamente)
        CRUD::field('converted_from_general_request_id')->type('hidden')->attributes(['name' => 'converted_from_general_request_id']);
        
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
            document.addEventListener("DOMContentLoaded", function() {
                // Cargar productos existentes
                loadProducts();
                
                // Event listeners
                document.getElementById("add-product-btn").addEventListener("click", addProduct);
                document.getElementById("add-new-product-btn").addEventListener("click", showNewProductModal);
                
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
                    
                    addProductToList(productId, productName, unit, description, quantity.value);
                    
                    // Limpiar campos
                    select.value = "";
                    quantity.value = 1;
                }
                
                // Función para agregar producto a la lista
                function addProductToList(productId, productName, unit, description, quantity, price = 0, specifications = "") {
                    const container = document.getElementById("selected-products-list");
                    const productDiv = document.createElement("div");
                    productDiv.className = "selected-product-item border p-3 mb-2";
                    productDiv.setAttribute("data-product-id", productId);
                    
                    productDiv.innerHTML = `
                        <div class="row">
                            <div class="col-md-4">
                                <strong>${productName}</strong>
                                ${description ? `<br><small class="text-muted">${description}</small>` : ""}
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
                                <label>Especificaciones:</label>
                                <textarea class="form-control product-specs" rows="2">${specifications}</textarea>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-danger btn-sm remove-product">
                                    <i class="la la-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    
                    container.appendChild(productDiv);
                    
                    // Event listener para remover producto
                    productDiv.querySelector(".remove-product").addEventListener("click", function() {
                        productDiv.remove();
                        updateHiddenFields();
                    });
                    
                    // Event listeners para actualizar totales
                    productDiv.querySelector(".product-quantity").addEventListener("input", updateTotals);
                    productDiv.querySelector(".product-price").addEventListener("input", updateTotals);
                    
                    updateHiddenFields();
                }
                
                // Función para actualizar campos ocultos
                function updateHiddenFields() {
                    const products = [];
                    document.querySelectorAll(".selected-product-item").forEach(item => {
                        const productId = item.getAttribute("data-product-id");
                        const quantity = item.querySelector(".product-quantity").value;
                        const price = item.querySelector(".product-price").value;
                        const specs = item.querySelector(".product-specs").value;
                        
                        products.push({
                            product_id: productId,
                            quantity: quantity,
                            price: price,
                            specifications: specs
                        });
                    });
                    
                    // Crear o actualizar campo oculto
                    let hiddenField = document.querySelector("input[name=\'selected_products\']");
                    if (!hiddenField) {
                        hiddenField = document.createElement("input");
                        hiddenField.type = "hidden";
                        hiddenField.name = "selected_products";
                        document.querySelector("form").appendChild(hiddenField);
                    }
                    hiddenField.value = JSON.stringify(products);
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
                    
                    const productDescription = prompt("Descripción del producto (opcional):") || "";
                    
                    // Agregar como producto temporal con ID negativo
                    const tempId = "new_" + Date.now();
                    const productData = {
                        product_id: tempId,
                        name: productName,
                        unit: productUnit,
                        description: productDescription,
                        quantity: 1,
                        price: 0,
                        specifications: ""
                    };
                    
                    // Agregar a la lista de productos seleccionados
                    addProductToList(tempId, productName, productUnit, productDescription, 1);
                }
            });
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
                    'Mantenimiento' => ['Herramientas', 'Repuestos'],
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
        document.addEventListener("DOMContentLoaded", function() {
            const existingProducts = ' . $existingProductsJson . ';
            
            // Cargar productos existentes
            loadProducts();
            
            // Cargar productos existentes en la lista
            if (existingProducts && existingProducts.length > 0) {
                existingProducts.forEach(product => {
                    addProductToList(
                        product.product_id, 
                        product.product_name + " (" + product.unit + ")", 
                        product.unit, 
                        product.description, 
                        product.quantity,
                        product.price,
                        product.specifications
                    );
                });
            }
            
            // Event listeners
            document.getElementById("add-product-btn").addEventListener("click", addProduct);
            document.getElementById("add-new-product-btn").addEventListener("click", showNewProductModal);
            
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
                
                addProductToList(productId, productName, unit, description, quantity.value);
                
                // Limpiar campos
                select.value = "";
                quantity.value = 1;
            }
            
            // Función para agregar producto a la lista
            function addProductToList(productId, productName, unit, description, quantity, price = 0, specifications = "") {
                const container = document.getElementById("selected-products-list");
                const productDiv = document.createElement("div");
                productDiv.className = "selected-product-item border p-3 mb-2";
                productDiv.setAttribute("data-product-id", productId);
                
                productDiv.innerHTML = `
                    <div class="row">
                        <div class="col-md-4">
                            <strong>${productName}</strong>
                            ${description ? `<br><small class="text-muted">${description}</small>` : ""}
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
                            <label>Especificaciones:</label>
                            <textarea class="form-control product-specs" rows="2">${specifications}</textarea>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-danger btn-sm remove-product">
                                <i class="la la-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
                
                container.appendChild(productDiv);
                
                // Event listener para remover producto
                productDiv.querySelector(".remove-product").addEventListener("click", function() {
                    productDiv.remove();
                    updateHiddenFields();
                });
                
                // Event listeners para actualizar totales
                productDiv.querySelector(".product-quantity").addEventListener("input", updateTotals);
                productDiv.querySelector(".product-price").addEventListener("input", updateTotals);
                
                updateHiddenFields();
            }
            
            // Función para actualizar campos ocultos
            function updateHiddenFields() {
                const products = [];
                document.querySelectorAll(".selected-product-item").forEach(item => {
                    const productId = item.getAttribute("data-product-id");
                    const quantity = item.querySelector(".product-quantity").value;
                    const price = item.querySelector(".product-price").value;
                    const specs = item.querySelector(".product-specs").value;
                    
                    products.push({
                        product_id: productId,
                        quantity: quantity,
                        price: price,
                        specifications: specs
                    });
                });
                
                // Crear o actualizar campo oculto
                let hiddenField = document.querySelector("input[name=\'selected_products\']");
                if (!hiddenField) {
                    hiddenField = document.createElement("input");
                    hiddenField.type = "hidden";
                    hiddenField.name = "selected_products";
                    document.querySelector("form").appendChild(hiddenField);
                }
                hiddenField.value = JSON.stringify(products);
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
                
                const productDescription = prompt("Descripción del producto (opcional):") || "";
                
                // Agregar como producto temporal con ID negativo
                const tempId = "new_" + Date.now();
                const productData = {
                    product_id: tempId,
                    name: productName,
                    unit: productUnit,
                    description: productDescription,
                    quantity: 1,
                    price: 0,
                    specifications: ""
                };
                
                // Agregar a la lista de productos seleccionados
                addProductToList(tempId, productName, productUnit, productDescription, 1);
            }
        });
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
        
        // Validar que el usuario personal no pueda convertir solicitudes generales
        $user = backpack_user();
        if ($convertedFrom && $user && $user->hasRole('role_personal', 'backpack')) {
            \Alert::error('No tienes permisos para convertir solicitudes generales a solicitudes de compra.')->flash();
            return redirect()->back();
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

            // show a success message
            \Alert::success(trans('backpack::crud.insert_success'))->flash();

            // save the redirect choice for next time
            $this->crud->setSaveAction();

            // Actualizar estado de la solicitud general si viene de una conversión
            if ($item->converted_from_general_request_id) {
                \Log::info('Intentando actualizar solicitud general:', ['id' => $item->converted_from_general_request_id]);
                $generalRequest = \App\Models\GeneralRequest::find($item->converted_from_general_request_id);
                if ($generalRequest) {
                    $generalRequest->update(['is_converted' => true]);
                    \Log::info('Solicitud general actualizada exitosamente:', ['id' => $generalRequest->id, 'is_converted' => $generalRequest->is_converted]);
                    \Alert::info('La solicitud general ' . $generalRequest->number . ' ha sido marcada como convertida a compra.')->flash();
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
            $generalRequest = \App\Models\GeneralRequest::with('details.product')
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
            foreach ($generalRequest->details as $generalDetail) {
                if (!$generalDetail->product) {
                    \Log::warning('Producto no encontrado en detalle de solicitud general', [
                        'general_request_detail_id' => $generalDetail->id
                    ]);
                    continue;
                }

                // Crear el detalle en la solicitud de compra
                // Convertir a números para evitar errores de multiplicación
                $estimatedUnitPrice = (float)($generalDetail->estimated_unit_price ?? 0);
                $requestedQuantity = (float)($generalDetail->requested_quantity ?? 0);
                $estimatedTotal = (float)($generalDetail->estimated_total ?? 0);
                
                // Si no hay estimated_total, calcularlo
                if ($estimatedTotal == 0 && $estimatedUnitPrice > 0 && $requestedQuantity > 0) {
                    $estimatedTotal = $estimatedUnitPrice * $requestedQuantity;
                }
                
                $purchaseRequestDetail = \App\Models\PurchaseRequestDetail::create([
                    'purchase_request_id' => $purchaseRequest->id,
                    'product_id' => $generalDetail->product_id,
                    'requested_quantity' => $requestedQuantity,
                    'specifications' => $generalDetail->specifications,
                    'justification' => $generalDetail->justification,
                    'estimated_unit_price' => $estimatedUnitPrice,
                    'estimated_total' => $estimatedTotal,
                    'status' => 'Pendiente'
                ]);

                $totalAmount += $purchaseRequestDetail->estimated_total;
                $replicatedCount++;

                \Log::info('Producto replicado desde solicitud general', [
                    'general_request_detail_id' => $generalDetail->id,
                    'purchase_request_detail_id' => $purchaseRequestDetail->id,
                    'product_id' => $generalDetail->product_id,
                    'product_name' => $generalDetail->product->name ?? 'N/A'
                ]);
            }

            // Actualizar el monto total de la solicitud de compra
            if ($totalAmount > 0) {
                $purchaseRequest->update(['total_amount' => $totalAmount]);
            }

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

        // Obtener datos para guardar
        $dataToSave = $this->crud->getStrippedSaveRequest($request);

        try {
            // update item in the db
            $item = $this->crud->update($this->crud->getCurrentEntryId(), $dataToSave);
            $this->data['entry'] = $this->crud->entry = $item;

            // Procesar productos seleccionados (eliminar existentes y crear nuevos)
            $selectedProducts = $request->input('selected_products');
            if ($selectedProducts && $selectedProducts !== '[]') {
                \Log::info('Procesando productos en actualización:', ['selected_products' => $selectedProducts]);
                // Eliminar productos existentes
                $item->details()->delete();
                // Procesar nuevos productos
                $this->processSelectedProducts($item, $request, true);
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
     * Process selected products and create purchase request details
     */
    private function processSelectedProducts($purchaseRequest, $request, $isUpdate = false)
    {
        $selectedProducts = $request->input('selected_products');
        
        if (!$selectedProducts || $selectedProducts === '[]' || $selectedProducts === '') {
            \Log::info('No hay productos seleccionados');
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
                return;
            }
        }
        
        if (!$products || !is_array($products) || empty($products)) {
            \Log::warning('Productos seleccionados está vacío o no es un array válido');
            return;
        }
        
        \Log::info('Productos a procesar:', ['count' => count($products), 'products' => $products]);
        
        $totalAmount = 0;
        
        foreach ($products as $productData) {
            if (!isset($productData['product_id'])) {
                \Log::warning('Producto sin product_id', ['data' => $productData]);
                continue;
            }
            
            $productId = $productData['product_id'];
            // Convertir a números para evitar errores de multiplicación
            $quantity = (float)($productData['quantity'] ?? 0);
            $price = (float)($productData['price'] ?? 0);
            $specifications = $productData['specifications'] ?? '';
            
            // Si es un producto nuevo (ID que empieza con "new_")
            if (strpos($productId, 'new_') === 0) {
                // Crear el nuevo producto
                $newProduct = \App\Models\Product::create([
                    'name' => $productData['name'] ?? 'Producto Nuevo',
                    'description' => $productData['description'] ?? '',
                    'unit_measurement' => $productData['unit'] ?? 'unidad',
                    'category_id' => 1, // Categoría por defecto
                    'minimum_stock' => 0
                ]);
                $productId = $newProduct->id;
                \Log::info('Nuevo producto creado:', ['id' => $newProduct->id, 'name' => $newProduct->name]);
            } else {
                // Validar que el producto existe
                $productId = (int)$productId;
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
                'requested_quantity' => $quantity,
                'specifications' => $specifications,
                'estimated_unit_price' => $price,
                'estimated_total' => $price * $quantity,
                'status' => 'Pendiente'
            ]);
            
            $totalAmount += $price * $quantity;
            \Log::info('Detalle creado:', ['detail_id' => $detail->id, 'product_id' => $productId]);
        }
        
        // Actualizar el monto total de la solicitud
        $purchaseRequest->update(['total_amount' => $totalAmount]);
        \Log::info('Monto total actualizado:', ['total' => $totalAmount]);
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

        // Get all market rates for products in this purchase request
        $productIds = $purchaseRequest->details->pluck('product_id')->toArray();
        $marketRates = \App\Models\MarketRate::with([
            'supplier',
            'quoteDetails.product'
        ])->whereHas('quoteDetails', function($query) use ($productIds) {
            $query->whereIn('product_id', $productIds);
        })->get();

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
        
        // Headers for products
        $sheet->setCellValue('A' . $row, 'Producto');
        $sheet->setCellValue('B' . $row, 'Cantidad Solicitada');
        $sheet->setCellValue('C' . $row, 'Especificaciones');
        
        $col = 'D';
        foreach ($suppliers as $supplierId => $supplierRates) {
            $supplier = $supplierRates->first()->supplier;
            $sheet->setCellValue($col . $row, $supplier->company_name ?? 'Proveedor ' . $supplierId);
            $col++;
        }
        
        $row++;
        
        // Add product rows
        foreach ($purchaseRequest->details as $detail) {
            $sheet->setCellValue('A' . $row, $detail->product->name ?? 'Producto no encontrado');
            $sheet->setCellValue('B' . $row, $detail->requested_quantity);
            $sheet->setCellValue('C' . $row, $detail->specifications ?? 'Sin especificaciones');
            
            $col = 'D';
            foreach ($suppliers as $supplierId => $supplierRates) {
                $quoteDetail = $supplierRates->flatMap(function($rate) {
                    return $rate->quoteDetails;
                })->where('product_id', $detail->product_id)->first();
                
                if ($quoteDetail) {
                    $sheet->setCellValue($col . $row, '$' . number_format($quoteDetail->unit_price, 2));
                } else {
                    $sheet->setCellValue($col . $row, 'Sin cotización');
                }
                $col++;
            }
            $row++;
        }
        
        // Auto-size columns
        foreach (range('A', $col) as $column) {
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
        $marketRate = \App\Models\MarketRate::findOrFail($marketRateId);
        
        $purchaseRequest->update([
            'selected_market_rate_id' => $marketRateId,
            'status' => 'Aprobada'
        ]);
        
        \Alert::success('Cotización seleccionada exitosamente.')->flash();
        
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
        $marketRate = \App\Models\MarketRate::findOrFail($marketRateId);
        
        $request = request();
        
        $purchaseRequest->update([
            'selected_market_rate_id' => $marketRateId,
            'selection_justification' => $request->input('justification'),
            'selected_by' => auth()->id(),
            'selected_at' => now(),
            'status' => 'Aprobada'
        ]);
        
        \Alert::success('Cotización seleccionada y justificada exitosamente.')->flash();
        
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
     * Generate purchase order from selected market rate or directly if amount <= 60000
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
            'responsibilityArea'
        ])->findOrFail($id);
        
        $totalAmount = $purchaseRequest->total_amount ?? 0;
        $threshold = 60000;
        
        // Si el monto es mayor a 60000, se requiere cotización
        if ($totalAmount > $threshold) {
            // Validar que haya al menos 3 cotizaciones
            $quotationsCount = $this->countQuotationsForPurchaseRequest($purchaseRequest);
            
            if ($quotationsCount < 3) {
                \Alert::error('Para solicitudes de compra mayores a $' . number_format($threshold, 2) . ' se requieren al menos 3 cotizaciones. Actualmente hay ' . $quotationsCount . ' cotización(es).')->flash();
                return redirect()->back();
            }
            
            // Validar que haya una cotización seleccionada
            if (!$purchaseRequest->selected_market_rate_id) {
                \Alert::error('Debe seleccionar una cotización antes de generar la orden de compra.')->flash();
                return redirect()->back();
            }
            
            // Generar orden desde cotización seleccionada
            return $this->generatePurchaseOrderFromQuote($purchaseRequest);
        } else {
            // Si el monto es <= 60000, se puede generar sin cotización
            // Verificar si se proporcionó un proveedor
            $supplierId = request()->input('supplier_id');
            
            if (!$supplierId) {
                \Alert::error('Debe seleccionar un proveedor para generar la orden de compra.')->flash();
                return redirect()->back();
            }
            
            // Generar orden directamente desde la solicitud
            return $this->generatePurchaseOrderWithoutQuote($purchaseRequest, $supplierId);
        }
    }
    
    /**
     * Generate purchase order from selected quote
     */
    private function generatePurchaseOrderFromQuote($purchaseRequest)
    {
        $request = request();
        
        // Generar número de orden
        $ultimo = \App\Models\PurchaseOrder::max('id');
        $orderNumber = 'OC-' . date('Y') . '-' . str_pad(($ultimo + 1), 3, '0', STR_PAD_LEFT);
        
        // Obtener fecha de emisión del request o usar la fecha actual
        $issueDate = $request->input('issue_date') ? \Carbon\Carbon::parse($request->input('issue_date')) : now();
        
        // Create purchase order
        $purchaseOrder = \App\Models\PurchaseOrder::create([
            'number' => $orderNumber,
            'date' => now(),
            'issue_date' => $issueDate,
            'supplier_id' => $purchaseRequest->selectedMarketRate->supplier_id,
            'authorizing_user_id' => auth()->id(),
            'status' => 'Pendiente',
            'purchase_request_id' => $purchaseRequest->id,
        ]);
        
        // Create purchase order details from quote
        foreach ($purchaseRequest->selectedMarketRate->quoteDetails as $quoteDetail) {
            // Buscar o crear el Input correspondiente al Product
            $input = $this->findOrCreateInputFromProduct($quoteDetail->product);
            
            if ($input) {
                \App\Models\PurchaseOrderDetail::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'input_id' => $input->id,
                    'quantity' => $quoteDetail->quantity,
                    'unit_price' => $quoteDetail->unit_price,
                ]);
            }
        }
        
        // Update purchase request status
        $purchaseRequest->update(['status' => 'Completada']);
        
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
        
        // Generar número de orden
        $ultimo = \App\Models\PurchaseOrder::max('id');
        $orderNumber = 'OC-' . date('Y') . '-' . str_pad(($ultimo + 1), 3, '0', STR_PAD_LEFT);
        
        // Obtener fecha de emisión del request o usar la fecha actual
        $issueDate = $request->input('issue_date') ? \Carbon\Carbon::parse($request->input('issue_date')) : now();
        
        // Obtener precios del request
        $prices = $request->input('prices', []);
        
        // Create purchase order
        $purchaseOrder = \App\Models\PurchaseOrder::create([
            'number' => $orderNumber,
            'date' => now(),
            'issue_date' => $issueDate,
            'supplier_id' => $supplierId,
            'authorizing_user_id' => auth()->id(),
            'status' => 'Pendiente',
            'purchase_request_id' => $purchaseRequest->id,
        ]);
        
        // Create purchase order details from purchase request details
        foreach ($purchaseRequest->details as $requestDetail) {
            if (!$requestDetail->product) {
                continue;
            }
            
            // Buscar o crear el Input correspondiente al Product
            $input = $this->findOrCreateInputFromProduct($requestDetail->product);
            
            if ($input) {
                // Usar el precio del formulario si está disponible, sino usar el precio estimado
                $unitPrice = isset($prices[$requestDetail->id]) ? (float)$prices[$requestDetail->id] : ($requestDetail->estimated_unit_price ?? 0);
                
                \App\Models\PurchaseOrderDetail::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'input_id' => $input->id,
                    'quantity' => $requestDetail->requested_quantity,
                    'unit_price' => $unitPrice,
                ]);
            }
        }
        
        // Update purchase request status
        $purchaseRequest->update(['status' => 'Completada']);
        
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
        CRUD::addClause('with', ['responsibilityArea', 'requestingUser', 'approvedBy', 'details.product', 'selectedMarketRate.supplier', 'selectedBy', 'convertedFromGeneralRequest', 'deliveries.details', 'purchaseOrders.paymentOrders']);
        
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

        CRUD::column('total_amount')->label('Monto Total')->type('number')->decimals(2)->prefix('$');
        
        // Eliminar completamente la sección de adjuntos
        CRUD::removeColumn('attachments');

        // Agregar campo personalizado para mostrar información de la solicitud general de origen
        CRUD::column('general_request_info')->label('Solicitud General de Origen')->type('custom_html')
            ->value(function($entry) {
                if (!$entry->convertedFromGeneralRequest) {
                    return '<div class="alert alert-secondary">
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
                $details = $entry->details;
                
                if ($details->isEmpty()) {
                    return '<div class="alert alert-info">No hay productos solicitados.</div>';
                }
                
                $html = '<div class="card border-primary">';
                $html .= '<div class="card-header bg-primary text-white">';
                $html .= '<h6 class="mb-0"><i class="la la-shopping-cart"></i> Productos Solicitados</h6>';
                $html .= '</div>';
                $html .= '<div class="card-body p-0">';
                $html .= '<div class="table-responsive">';
                $html .= '<table class="table table-sm table-bordered mb-0">';
                $html .= '<thead class="table-light">';
                $html .= '<tr>';
                $html .= '<th style="width: 30%;">Producto</th>';
                $html .= '<th style="width: 12%;" class="text-center">Cantidad Solicitada</th>';
                $html .= '<th style="width: 12%;" class="text-center">Cantidad Recibida</th>';
                $html .= '<th style="width: 12%;" class="text-center">Estado Recepción</th>';
                $html .= '<th style="width: 24%;">Especificaciones</th>';
                $html .= '<th style="width: 10%;" class="text-center">Estado</th>';
                $html .= '</tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                
                foreach ($details as $detail) {
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
                    $productName = $detail->product->name ?? 'Producto no encontrado';
                    if (is_array($productName)) {
                        $productName = 'Producto no encontrado';
                    }
                    $html .= '<td><strong>' . $productName . '</strong>';
                    if ($detail->product && $detail->product->description && !is_array($detail->product->description)) {
                        $html .= '<br><small class="text-muted">' . $detail->product->description . '</small>';
                    }
                    $html .= '</td>';
                    $html .= '<td class="text-center"><span class="badge bg-primary">' . number_format($requestedQuantity) . '</span>';
                    if ($detail->product && $detail->product->unit_measurement && !is_array($detail->product->unit_measurement)) {
                        $html .= '<br><small class="text-muted">' . $detail->product->unit_measurement . '</small>';
                    }
                    $html .= '</td>';
                    $html .= '<td class="text-center">';
                    $html .= '<span class="badge bg-' . ($deliveredQuantity > 0 ? ($isFullyDelivered ? 'success' : 'warning') : 'secondary') . '" title="Cantidad recibida: ' . number_format($deliveredQuantity) . ' de ' . number_format($requestedQuantity) . '">';
                    $html .= number_format($deliveredQuantity) . ' / ' . number_format($requestedQuantity);
                    $html .= '</span>';
                    $html .= '</td>';
                    $html .= '<td class="text-center">';
                    $html .= '<span class="badge bg-' . $deliveryStatusColor . '" title="Estado de recepción: ' . $deliveryStatus . '">';
                    $html .= '<i class="la la-' . $deliveryStatusIcon . '"></i> ' . $deliveryStatus;
                    $html .= '</span>';
                    $html .= '</td>';
                    $specifications = $detail->specifications ?? 'Sin especificaciones';
                    if (is_array($specifications)) {
                        $specifications = 'Sin especificaciones';
                    }
                    $html .= '<td><small>' . $specifications . '</small></td>';
                    $status = $detail->status ?? 'Pendiente';
                    if (is_array($status)) {
                        $status = 'Pendiente';
                    }
                    $html .= '<td class="text-center"><span class="badge bg-' . ($detail->status == 'Aprobada' ? 'success' : ($detail->status == 'Rechazada' ? 'danger' : 'warning')) . '">' . $status . '</span></td>';
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

        // Agregar campo para mostrar cotizaciones disponibles (oculto para responsables de área)
        CRUD::column('market_rates_table')->label('Cotizaciones Disponibles')->type('custom_html')
            ->value(function($entry) {
                // Ocultar esta sección para responsables de área
                $user = backpack_user();
                $isResponsableArea = $user && $user->hasRole('role_responsable_area', 'backpack');
                
                if ($isResponsableArea) {
                    return '';
                }
                
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
                    $isSelected = $entry->selected_market_rate_id == $marketRate->id;
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
                    $html .= '<td class="text-end"><strong>$' . number_format($marketRate->total_amount, 2) . '</strong></td>';
                    $html .= '<td><span class="badge bg-info">' . $marketRate->quoteDetails->count() . ' productos</span></td>';
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
                    
                    if (!$isSelected && $entry->status != 'Completada' && $canSelect) {
                        $html .= '<a href="' . route('purchase-request.show-select-market-rate', [$entry->id, $marketRate->id]) . '" class="btn btn-sm btn-success">';
                        $html .= '<i class="la la-check"></i> Seleccionar';
                        $html .= '</a>';
                    }
                    
                    $html .= '</td>';
                    $html .= '</tr>';
                }
                
                    $html .= '</tbody>';
                    $html .= '</table>';
                    $html .= '</div>';
                }
                
                // Lógica para mostrar botón de generar orden según el monto
                // El rol role_responsable_area no puede generar órdenes de compra
                $user = backpack_user();
                $canGenerateOrder = !($user && $user->hasRole('role_responsable_area', 'backpack'));
                
                $totalAmount = $entry->total_amount ?? 0;
                $threshold = 60000;
                // Usar la relación del modelo en lugar de consulta directa
                $entry->load('marketRates');
                $quotationsCount = $entry->marketRates->count();
                
                // Botón para agregar nueva cotización (solo responsable de compras)
                $user = backpack_user();
                $adminRoles = ['role_admin_sistema', 'role_admin_institucion', 'role_responsable_compras'];
                $canCreateQuotation = false;
                foreach ($adminRoles as $role) {
                    if ($user && $user->hasRole($role, 'backpack')) {
                        $canCreateQuotation = true;
                        break;
                    }
                }
                
                if ($canCreateQuotation) {
                    $html .= '<div class="mt-3">';
                    $html .= '<a href="' . backpack_url('market-rate/create?purchase_request_id=' . $entry->id) . '" class="btn btn-success">';
                    $html .= '<i class="la la-plus"></i> Agregar Nueva Cotización';
                    $html .= '</a>';
                    $html .= '</div>';
                }
                
                // Mostrar advertencia si hay menos de 3 cotizaciones (incluso después de generar orden)
                if ($quotationsCount < 3) {
                    $html .= '<div class="mt-3 alert alert-warning">';
                    $html .= '<i class="la la-exclamation-triangle"></i> <strong>Atención:</strong> Se recomienda tener al menos 3 cotizaciones. Actualmente hay ' . $quotationsCount . ' cotización(es).';
                    $html .= '</div>';
                }
                
                if ($entry->status != 'Completada' && $canGenerateOrder) {
                    if ($totalAmount > $threshold) {
                        // Para montos mayores a 60000, se requieren 3 cotizaciones y una seleccionada
                        if ($quotationsCount < 3) {
                            // El mensaje de advertencia ya se mostró arriba, solo mostrar mensaje específico para generar orden
                            $html .= '<div class="mt-3 alert alert-danger">';
                            $html .= '<i class="la la-exclamation-triangle"></i> <strong>No se puede generar la orden:</strong> Para solicitudes mayores a $' . number_format($threshold, 2) . ' se requieren al menos 3 cotizaciones. Actualmente hay ' . $quotationsCount . ' cotización(es).';
                            $html .= '</div>';
                        } elseif (!$entry->selected_market_rate_id) {
                            $html .= '<div class="mt-3 alert alert-warning">';
                            $html .= '<i class="la la-exclamation-triangle"></i> <strong>Atención:</strong> Debe seleccionar una cotización antes de generar la orden de compra.';
                            $html .= '</div>';
                        } else {
                            // Hay 3+ cotizaciones y una seleccionada, mostrar formulario con fecha de emisión
                            $html .= '<div class="mt-3">';
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
                    } else {
                        // Para montos <= 60000, se puede generar sin cotización (pero necesita proveedor)
                        $html .= '<div class="mt-3">';
                        $html .= '<div class="alert alert-info">';
                        $html .= '<i class="la la-info-circle"></i> <strong>Información:</strong> Esta solicitud tiene un monto de $' . number_format($totalAmount, 2) . ', por lo que no se requiere cotización. Puede generar la orden de compra directamente seleccionando un proveedor.';
                        $html .= '</div>';
                        $html .= '<form method="POST" action="' . route('purchase-request.generate-purchase-order', $entry->id) . '">';
                        $html .= csrf_field();
                        $html .= '<div class="row mb-3">';
                        $html .= '<div class="col-md-4">';
                        $html .= '<label for="supplier_id" class="form-label">Seleccionar Proveedor:</label>';
                        $html .= '<select name="supplier_id" id="supplier_id" class="form-control" required>';
                        $html .= '<option value="">Seleccione un proveedor...</option>';
                        $suppliers = \App\Models\Supplier::all();
                        foreach ($suppliers as $supplier) {
                            $html .= '<option value="' . $supplier->id . '">' . ($supplier->company_name ?? 'Proveedor #' . $supplier->id) . '</option>';
                        }
                        $html .= '</select>';
                        $html .= '</div>';
                        $html .= '<div class="col-md-4">';
                        $html .= '<label for="issue_date" class="form-label">Fecha de Emisión:</label>';
                        $html .= '<input type="date" name="issue_date" id="issue_date" class="form-control" value="' . date('Y-m-d') . '" required>';
                        $html .= '</div>';
                        $html .= '</div>';
                        
                        // Tabla para ingresar precios
                        $html .= '<div class="table-responsive mb-3">';
                        $html .= '<table class="table table-sm table-bordered">';
                        $html .= '<thead class="table-light">';
                        $html .= '<tr>';
                        $html .= '<th>Producto</th>';
                        $html .= '<th style="width: 15%;">Cantidad</th>';
                        $html .= '<th style="width: 20%;">Precio Unitario</th>';
                        $html .= '</tr>';
                        $html .= '</thead>';
                        $html .= '<tbody>';
                        foreach ($entry->details as $detail) {
                            $productName = $detail->product->name ?? 'Producto no encontrado';
                            if (is_array($productName)) {
                                $productName = 'Producto no encontrado';
                            }
                            $html .= '<tr>';
                            $html .= '<td>' . e($productName) . '</td>';
                            $html .= '<td>' . number_format($detail->requested_quantity) . '</td>';
                            $html .= '<td>';
                            $html .= '<input type="number" name="prices[' . $detail->id . ']" class="form-control form-control-sm" step="0.01" min="0" value="' . ($detail->estimated_unit_price ?? 0) . '" required>';
                            $html .= '</td>';
                            $html .= '</tr>';
                        }
                        $html .= '</tbody>';
                        $html .= '</table>';
                        $html .= '</div>';
                        
                        $html .= '<div class="text-end">';
                        $html .= '<button type="submit" class="btn btn-primary" onclick="return confirm(\'¿Está seguro de generar la orden de compra?\')">';
                        $html .= '<i class="la la-shopping-cart"></i> Generar Orden de Compra';
                        $html .= '</button>';
                        $html .= '</div>';
                        $html .= '</form>';
                        $html .= '</div>';
                    }
                } elseif ($entry->status != 'Completada' && !$canGenerateOrder) {
                    // Usuario con rol role_responsable_area no puede generar órdenes de compra
                    $html .= '<div class="mt-3 alert alert-info">';
                    $html .= '<i class="la la-info-circle"></i> <strong>Información:</strong> Los responsables de área no pueden generar órdenes de compra. Esta función está reservada para otros roles del sistema.';
                    $html .= '</div>';
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
                    $html .= '<div class="alert alert-info">No hay sugerencias de proveedores para esta solicitud.</div>';
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
        CRUD::column('delivery_reception_actions')->label('Acciones')->type('custom_html')
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
    }
}

