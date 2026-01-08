<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class GeneralRequestCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup()
    {
        CRUD::setModel(\App\Models\GeneralRequest::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/general-request');
        CRUD::setEntityNameStrings('solicitud general', 'solicitudes generales');
    }

    /**
     * Determinar si un área requiere análisis previo
     */
    private function requiresAnalystApproval($areaId)
    {
        if (!$areaId) {
            return false;
        }
        
        $area = \App\Models\ResponsibilityArea::find($areaId);
        
        if (!$area) {
            return false;
        }
        
        // Áreas que requieren análisis previo
        $areasRequiringAnalysis = [
            'Informática',
            'Insumos de Salud',
            'Salud'
        ];
        
        return in_array($area->name, $areasRequiringAnalysis);
    }

    protected function setupListOperation()
    {
        // Habilitar tabla responsiva
        CRUD::enableResponsiveTable();
        
        // Cargar relaciones necesarias, incluyendo productos para verificar stock y entregas
        CRUD::addClause('with', ['createdBy', 'area', 'details.product', 'deliveries']);

        // Filtrar solicitudes según el rol del usuario
        $user = backpack_user();
        $isAdmin = false;
        if ($user) {
            // Roles que pueden ver todas las solicitudes (administradores)
            $adminRoles = ['role_admin_sistema', 'role_admin_institucion', 'role_responsable_compras'];
            foreach ($adminRoles as $role) {
                if ($user->hasRole($role, 'backpack')) {
                    $isAdmin = true;
                    break;
                }
            }
            
            if (!$isAdmin) {
                // Para role_analista_area, mostrar solo solicitudes pendientes de análisis
                if ($user->hasRole('role_analista_area', 'backpack')) {
                    CRUD::addClause('where', 'status', 'pendiente_analisis');
                    CRUD::addClause('where', 'analysis_status', 'pendiente');
                    
                    // Filtrar solo por áreas que requieren análisis
                    CRUD::addClause('whereHas', 'area', function($query) {
                        $query->whereIn('name', ['Informática', 'Insumos de Salud', 'Salud']);
                    });
                }
                // Para role_responsable_area, mostrar solicitudes de su área Y las que él crea
                elseif ($user->hasRole('role_responsable_area', 'backpack')) {
                    $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('id');
                    
                    CRUD::addClause(function($query) use ($user, $userAreas) {
                        $query->where(function($q) use ($user, $userAreas) {
                            // Siempre incluir las solicitudes que el usuario creó
                            $q->where('created_by', $user->id);
                            
                            // También incluir solicitudes de sus áreas (si tiene áreas asignadas)
                            if ($userAreas->isNotEmpty()) {
                                $q->orWhere(function($areaQ) use ($userAreas) {
                                    $areaQ->whereIn('area_id', $userAreas)
                                          ->where(function($subQ) {
                                              // Incluir solicitudes aprobadas por analista, no requeridas, o sin análisis aún
                                              $subQ->where('analysis_status', 'aprobada')
                                                   ->orWhere('analysis_status', 'no_requerido')
                                                   ->orWhereNull('analysis_status')
                                                   ->orWhere('analysis_status', 'pendiente');
                                          })
                                          ->where('status', '!=', 'rechazada_analista');
                                });
                            }
                        });
                    });
                } else {
                    // Para todos los demás usuarios, solo mostrar sus propias solicitudes
                    CRUD::addClause('where', 'created_by', $user->id);
                }
            }
        }

        CRUD::column('number')->label('Número');
        CRUD::column('title')->label('Título');
        CRUD::column('createdBy.name')->label('Solicitante');
        CRUD::column('area.name')->label('Área');
        CRUD::column('priority')->label('Prioridad');
        CRUD::column('status')->label('Estado');
        
        // Columna para mostrar si está convertida
        CRUD::column('conversion_status')->label('Conversión')->type('custom_html')
            ->value(function($entry) {
                if ($entry->is_converted) {
                    return '<span class="badge bg-success"><i class="la la-check-circle"></i> Convertida</span>';
                }
                return '<span class="badge bg-secondary"><i class="la la-times-circle"></i> No convertida</span>';
            });
        
        // Agregar columna personalizada para mostrar cantidad de productos
        CRUD::column('details_count')->label('Productos')->type('custom_html')
            ->value(function($entry) {
                $count = $entry->details->count();
                return '<span class="badge" style="background-color: #adb5bd; color: #871f1f;">' . $count . ' productos</span>';
            });
            
        CRUD::column('created_at')->label('Fecha de Creación');

        // Botón para convertir a solicitud de compra (solo para solicitudes no convertidas y con productos)
        CRUD::addButton('line', 'convert_to_purchase', 'view', 'crud::buttons.convert_to_purchase', 'end');
        
        // Reemplazar el botón de editar con uno personalizado que verifica condiciones
        CRUD::removeButton('update');
        CRUD::addButton('line', 'edit', 'view', 'crud::buttons.edit_general_request', 'end');
        
        // Reemplazar el botón de eliminar con uno personalizado que verifica condiciones
        CRUD::removeButton('delete');
        CRUD::addButton('line', 'delete', 'view', 'crud::buttons.delete_general_request', 'end');

        // Filtro personalizado por número usando parámetros de URL
        if (request()->has('numero')) {
            $numero = request()->get('numero');
            if ($numero) {
                CRUD::addClause('where', 'number', 'like', '%' . $numero . '%');
            }
        }

        // Filtro personalizado por creado por usando parámetros de URL
        // Solo aplicar si el usuario es admin, o si el valor coincide con el usuario actual
        if (request()->has('creado_por')) {
            $creadoPor = request()->get('creado_por');
            if ($creadoPor) {
                // Si el usuario es admin, aplicar el filtro normalmente
                // Si no es admin, solo aplicar si el valor coincide con el usuario actual
                if ($isAdmin || ($user && $creadoPor == $user->id)) {
                    CRUD::addClause('where', 'created_by', $creadoPor);
                }
            }
        }

        // Filtro personalizado por área usando parámetros de URL
        if (request()->has('area')) {
            $area = request()->get('area');
            if ($area) {
                CRUD::addClause('whereHas', 'area', function($query) use ($area) {
                    $query->where('name', $area);
                });
            }
        }

        // Filtro personalizado por prioridad usando parámetros de URL
        if (request()->has('prioridad')) {
            $prioridad = request()->get('prioridad');
            if ($prioridad) {
                CRUD::addClause('where', 'priority', $prioridad);
            }
        }

        // Filtro personalizado por estado usando parámetros de URL
        if (request()->has('estado')) {
            $estado = request()->get('estado');
            if ($estado) {
                CRUD::addClause('where', 'status', $estado);
            }
        }

        // Filtro personalizado para solicitudes sin entregas
        // Solo aplicar si el parámetro 'explicit=1' está presente (indica acción explícita del usuario desde el dashboard)
        // Backpack puede restaurar parámetros desde localStorage, pero sin 'explicit=1' no se aplicará el filtro
        // Esto asegura que cuando el usuario accede desde el menú, se muestren todas las solicitudes
        $hasSinEntregas = request()->query('sin_entregas') == '1';
        $isExplicit = request()->query('explicit') == '1';
        
        // Solo aplicar el filtro si:
        // 1. El parámetro sin_entregas está presente en la URL
        // 2. Y tiene explicit=1 (acción explícita del usuario desde el dashboard)
        // Si no tiene explicit=1, no se aplica el filtro, incluso si Backpack restauró sin_entregas=1 desde localStorage
        if ($hasSinEntregas && $isExplicit) {
            CRUD::addClause('whereDoesntHave', 'deliveries');
        }
    }

    protected function setupCreateOperation()
    {
        // Verificar permisos
        $user = backpack_user();
        if (!$user || !$user->hasPermissionTo('solicitud.crear', 'backpack')) {
            abort(403, 'No tienes permiso para crear solicitudes generales.');
        }
        
        CRUD::field('number')->label('Número de Solicitud')->default(\App\Models\GeneralRequest::generateNextNumber())->attributes(['readonly' => 'readonly']);

        CRUD::field('title')->label('Título')->validationRules('required|string|max:255');
        
        CRUD::field('description')->label('Descripción')->type('textarea')->validationRules('required');
        
        CRUD::field('area_id')->label('Área')
            ->type('select')
            ->model('App\Models\ResponsibilityArea')
            ->attribute('name')
            ->validationRules('nullable|exists:responsibility_areas,id');

        CRUD::field('created_by')->label('Creado por')
            ->type('hidden')
            ->default(backpack_auth()->id() ?? 1)
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

        CRUD::field('status')->label('Estado')
            ->type('hidden')
            ->default('creada');
            
        // Campo hidden para almacenar los productos seleccionados
        CRUD::addField([
            'name' => 'selected_products',
            'type' => 'hidden',
            'value' => '[]',
        ]);
        
        // Campo para seleccionar productos
        CRUD::field('products_selection')->label('Productos Solicitados')->type('custom_html')
            ->value($this->getProductsSelectionHtml());
    }

    protected function setupUpdateOperation()
    {
        // Verificar permisos y que el usuario solo pueda editar sus propias solicitudes si es role_personal
        $user = backpack_user();
        if (!$user || !$user->hasPermissionTo('solicitud.crear', 'backpack')) {
            abort(403, 'No tienes permiso para editar solicitudes generales.');
        }
        
        $entry = $this->crud->getCurrentEntry();
        if (!$entry) {
            abort(404, 'Solicitud no encontrada.');
        }
        
        // No se puede editar si ya fue convertida a compra
        if ($entry->is_converted) {
            abort(403, 'No se puede editar una solicitud que ya fue convertida a solicitud de compra.');
        }
        
        // Verificar si el usuario es administrador del sistema (puede editar cualquier solicitud)
        $isAdminSistema = $user->hasRole('role_admin_sistema', 'backpack');
        
        // role_admin_institucion solo puede editar sus propias solicitudes
        $isAdminInstitucion = $user->hasRole('role_admin_institucion', 'backpack');
        
        $isOwnRequest = $entry->created_by == $user->id;
        $isResponsableCompras = $user->hasRole('role_responsable_compras', 'backpack');
        $canOnlyChangeStatus = false;
        
        // Si es administrador del sistema, puede editar cualquier solicitud
        if ($isAdminSistema) {
            // Puede editar cualquier solicitud
        } elseif ($isAdminInstitucion) {
            // El administrador del instituto solo puede editar sus propias solicitudes
            if (!$isOwnRequest) {
                abort(403, 'Solo puedes editar las solicitudes generales que creaste.');
            }
            // Solo puede editar si el estado es "creada"
            if ($entry->status !== 'creada') {
                abort(403, 'Solo puedes editar solicitudes con estado "creada".');
            }
        } elseif ($isResponsableCompras) {
            // Si es responsable de compras, solo puede editar sus propias solicitudes
            if (!$isOwnRequest) {
                abort(403, 'Solo puedes editar las solicitudes generales que creaste.');
            }
            // Si es el creador, solo puede editar si el estado es "creada"
            if ($entry->status !== 'creada') {
                abort(403, 'Solo puedes editar solicitudes con estado "creada".');
            }
        } else {
            // Todos los demás usuarios solo pueden editar sus propias solicitudes
            if (!$isOwnRequest) {
                abort(403, 'Solo puedes editar las solicitudes que creaste.');
            }
            
            // Si es el creador, solo puede editar si el estado es "creada"
            if ($entry->status !== 'creada') {
                abort(403, 'Solo puedes editar solicitudes con estado "creada".');
            }
        }
        
        // Usar los mismos campos que en create (edición completa)
        $this->setupCreateOperation();
        
        // Cargar productos existentes para edición
        if ($entry) {
            // Cargar la relación con productos
            $entry->load('details.product');
            
            if ($entry->details) {
                $existingProducts = $entry->details->map(function($detail) {
                    return [
                        'product_id' => $detail->product_id,
                        'product_name' => $detail->product->name,
                        'unit' => $detail->product->unit_measurement,
                        'description' => $detail->product->description,
                        'quantity' => $detail->requested_quantity,
                        'price' => $detail->estimated_unit_price,
                        'specifications' => $detail->specifications
                    ];
                })->toArray();
                
                // Modificar el campo de productos para incluir los existentes
                CRUD::modifyField('products_selection', [
                    'value' => $this->getProductsSelectionHtml($existingProducts)
                ]);
            }
        }
    }

    protected function setupDeleteOperation()
    {
        // Verificar que solo el creador pueda eliminar, solo si el estado es "creada" y no está convertida
        $user = backpack_user();
        $entry = $this->crud->getCurrentEntry();
        
        if ($entry) {
            // Solo el creador puede eliminar
            if ($entry->created_by != $user->id) {
                abort(403, 'Solo puedes eliminar las solicitudes que creaste.');
            }
            
            // Solo se puede eliminar si el estado es "creada"
            if ($entry->status !== 'creada') {
                abort(403, 'Solo se pueden eliminar solicitudes con estado "creada".');
            }
            
            // No se puede eliminar si ya fue convertida a solicitud de compra
            if ($entry->is_converted) {
                abort(403, 'No se puede eliminar una solicitud que ya fue convertida a solicitud de compra.');
            }
        }
    }
    
    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $this->crud->hasAccessOrFail('delete');
        
        $user = backpack_user();
        $entry = $this->crud->getEntry($id);
        
        // Verificar que solo el creador pueda eliminar
        if ($entry->created_by != $user->id) {
            abort(403, 'Solo puedes eliminar las solicitudes que creaste.');
        }
        
        // Solo se puede eliminar si el estado es "creada"
        if ($entry->status !== 'creada') {
            abort(403, 'Solo se pueden eliminar solicitudes con estado "creada".');
        }
        
        // No se puede eliminar si ya fue convertida a solicitud de compra
        if ($entry->is_converted) {
            abort(403, 'No se puede eliminar una solicitud que ya fue convertida a solicitud de compra.');
        }
        
        return $this->crud->delete($id);
    }

    /**
     * Generate HTML for products selection with existing products
     */
    private function getProductsSelectionHtml($existingProducts = [])
    {
        $existingProductsJson = json_encode($existingProducts);
        
        return '
        <div id="products-container">
            <div class="row mb-3">
                <div class="col-md-5">
                    <label for="product-select" class="form-label">Seleccionar Producto</label>
                    <select id="product-select" class="form-control">
                        <option value="">Seleccionar un producto...</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="product-quantity" class="form-label">Cantidad</label>
                    <input type="number" id="product-quantity" class="form-control" min="1" value="1">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Stock Disponible</label>
                    <div id="stock-info" class="form-control-plaintext">
                        <span class="badge bg-secondary">Seleccione un producto</span>
                    </div>
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
            
            // Obtener referencia al campo de área
            const areaField = document.querySelector("select[name=\'area_id\']");
            
            // Función para cargar productos
            function loadProducts() {
                const areaId = areaField ? areaField.value : null;
                let url = "' . backpack_url('api/productos') . '";
                if (areaId) {
                    url += "?area_id=" + areaId;
                }
                
                fetch(url)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error("Error al cargar productos: " + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        const select = document.getElementById("product-select");
                        if (!select) {
                            console.error("No se encontró el select de productos");
                            return;
                        }
                        const currentValue = select.value; // Guardar selección actual
                        select.innerHTML = \'<option value="">Seleccionar un producto...</option>\';
                        if (data && data.length > 0) {
                            data.forEach(product => {
                                const option = document.createElement("option");
                                option.value = product.id;
                                option.textContent = product.name + " (" + product.unit_measurement + ")";
                                option.setAttribute("data-unit", product.unit_measurement);
                                option.setAttribute("data-description", product.description || "");
                                option.setAttribute("data-stock", product.stock_total || 0);
                                option.setAttribute("data-minimum-stock", product.minimum_stock || 0);
                                select.appendChild(option);
                            });
                        } else {
                            console.warn("No se recibieron productos del servidor");
                        }
                        // Restaurar selección si existía
                        if (currentValue) {
                            select.value = currentValue;
                            updateStockInfo();
                        }
                    })
                    .catch(error => {
                        console.error("Error loading products:", error);
                        const select = document.getElementById("product-select");
                        if (select) {
                            select.innerHTML = \'<option value="">Error al cargar productos</option>\';
                        }
                    });
            }
            
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
            document.getElementById("product-select").addEventListener("change", updateStockInfo);
            
            // Escuchar cambios en el campo de área para recargar productos con stock correcto
            if (areaField) {
                areaField.addEventListener("change", function() {
                    loadProducts();
                    // Actualizar stock de productos ya seleccionados
                    updateSelectedProductsStock();
                });
            }
            
            // Función para actualizar el stock de productos ya seleccionados
            function updateSelectedProductsStock() {
                const areaId = areaField ? areaField.value : null;
                if (!areaId) return;
                
                const selectedProductsList = document.getElementById("selected-products-list");
                const productRows = selectedProductsList.querySelectorAll("[data-product-id]");
                
                productRows.forEach(row => {
                    const productId = row.getAttribute("data-product-id");
                    if (productId) {
                        fetch("' . backpack_url('api/productos') . '?area_id=" + areaId)
                            .then(response => response.json())
                            .then(data => {
                                const product = data.find(p => p.id == productId);
                                if (product) {
                                    const stockBadge = row.querySelector(".stock-badge");
                                    if (stockBadge) {
                                        const stock = parseInt(product.stock_total) || 0;
                                        const minimumStock = parseInt(product.minimum_stock) || 0;
                                        
                                        if (stock > minimumStock) {
                                            stockBadge.className = "badge bg-success stock-badge";
                                            stockBadge.textContent = stock + " unidades";
                                        } else if (stock > 0) {
                                            stockBadge.className = "badge bg-warning stock-badge";
                                            stockBadge.textContent = stock + " unidades (Stock bajo)";
                                        } else {
                                            stockBadge.className = "badge bg-danger stock-badge";
                                            stockBadge.textContent = "Sin stock";
                                        }
                                    }
                                }
                            })
                            .catch(error => console.error("Error updating stock:", error));
                    }
                });
            }
            
            // Función para actualizar información de stock
            function updateStockInfo() {
                const select = document.getElementById("product-select");
                const stockInfo = document.getElementById("stock-info");
                const selectedOption = select.options[select.selectedIndex];
                
                if (select.value && selectedOption) {
                    const stock = parseInt(selectedOption.getAttribute("data-stock")) || 0;
                    const minimumStock = parseInt(selectedOption.getAttribute("data-minimum-stock")) || 0;
                    
                    if (stock > minimumStock) {
                        stockInfo.innerHTML = `<span class="badge bg-success">${stock} unidades</span>`;
                    } else if (stock > 0) {
                        stockInfo.innerHTML = `<span class="badge bg-warning">${stock} unidades (Stock bajo)</span>`;
                    } else {
                        stockInfo.innerHTML = `<span class="badge bg-danger">Sin stock</span>`;
                    }
                } else {
                    stockInfo.innerHTML = `<span class="badge bg-secondary">Seleccione un producto</span>`;
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
                
                console.log("Agregando producto:", {productId, productName, unit, description, quantity: quantity.value});
                
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
                
                // Obtener stock del producto seleccionado
                const select = document.getElementById("product-select");
                const selectedOption = select.querySelector(`option[value="${productId}"]`);
                let stock = 0;
                let minimumStock = 0;
                if (selectedOption) {
                    stock = parseInt(selectedOption.getAttribute("data-stock")) || 0;
                    minimumStock = parseInt(selectedOption.getAttribute("data-minimum-stock")) || 0;
                }
                
                // Determinar badge de stock
                let stockBadge = "";
                if (stock > minimumStock) {
                    stockBadge = `<span class="badge bg-success stock-badge">${stock} unidades</span>`;
                } else if (stock > 0) {
                    stockBadge = `<span class="badge bg-warning stock-badge">${stock} unidades (Stock bajo)</span>`;
                } else {
                    stockBadge = `<span class="badge bg-danger stock-badge">Sin stock</span>`;
                }
                
                productDiv.innerHTML = `
                    <div class="row">
                        <div class="col-md-3">
                            <strong>${productName}</strong>
                            ${description ? `<br><small class="text-muted">${description}</small>` : ""}
                        </div>
                        <div class="col-md-2">
                            <label>Cantidad:</label>
                            <input type="number" class="form-control product-quantity" value="${quantity}" min="1">
                        </div>
                        <div class="col-md-2">
                            <label>Stock:</label>
                            <div>${stockBadge}</div>
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
                
                // Event listeners para actualizar campos ocultos
                productDiv.querySelector(".product-quantity").addEventListener("input", function() {
                    updateHiddenFields();
                });
                
                if (productDiv.querySelector(".product-specs")) {
                    productDiv.querySelector(".product-specs").addEventListener("input", function() {
                        updateHiddenFields();
                    });
                }
                
                updateHiddenFields();
            }
            
            // Función para actualizar campos ocultos
            function updateHiddenFields() {
                const products = [];
                document.querySelectorAll(".selected-product-item").forEach(item => {
                    const productId = item.getAttribute("data-product-id");
                    const quantityInput = item.querySelector(".product-quantity");
                    const specsInput = item.querySelector(".product-specs");
                    const priceInput = item.querySelector(".product-price");
                    
                    if (productId && quantityInput) {
                        products.push({
                            product_id: productId,
                            quantity: quantityInput.value || 1,
                            price: priceInput ? (priceInput.value || 0) : 0,
                            specifications: specsInput ? (specsInput.value || "") : ""
                        });
                    }
                });
                
                // Buscar o actualizar campo oculto existente
                let hiddenField = document.querySelector("input[name=\'selected_products\']");
                if (!hiddenField) {
                    console.error("No se encontró el campo hidden selected_products en el formulario");
                    // Intentar crearlo como último recurso
                    const form = document.querySelector("form");
                    if (form) {
                        hiddenField = document.createElement("input");
                        hiddenField.type = "hidden";
                        hiddenField.name = "selected_products";
                        form.appendChild(hiddenField);
                    } else {
                        console.error("No se encontró el formulario");
                        return;
                    }
                }
                hiddenField.value = JSON.stringify(products);
                
                // Debug: Log para verificar que se está enviando
                console.log("Productos seleccionados:", products);
                console.log("Campo oculto value:", hiddenField.value);
            }
            
            // Asegurar que el campo se actualice antes de enviar el formulario
            const form = document.querySelector("form");
            if (form) {
                form.addEventListener("submit", function(e) {
                    updateHiddenFields();
                });
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
            abort(404, 'Solicitud no encontrada.');
        }
        
        // No se puede editar si ya fue convertida a compra
        if ($entry->is_converted) {
            abort(403, 'No se puede editar una solicitud que ya fue convertida a solicitud de compra.');
        }
        
        // Verificar si el usuario es administrador del sistema (puede editar cualquier solicitud)
        $isAdminSistema = $user && $user->hasRole('role_admin_sistema', 'backpack');
        
        // role_admin_institucion solo puede editar sus propias solicitudes
        $isAdminInstitucion = $user && $user->hasRole('role_admin_institucion', 'backpack');
        
        $isOwnRequest = $entry->created_by == $user->id;
        $isResponsableCompras = $user && $user->hasRole('role_responsable_compras', 'backpack');
        
        // Si es administrador del sistema, puede editar cualquier solicitud
        if (!$isAdminSistema) {
            if ($isAdminInstitucion) {
                // El administrador del instituto solo puede editar sus propias solicitudes
                if (!$isOwnRequest) {
                    abort(403, 'Solo puedes editar las solicitudes generales que creaste.');
                }
                // Solo puede editar si el estado es "creada"
                if ($entry->status !== 'creada') {
                    abort(403, 'Solo puedes editar solicitudes con estado "creada".');
                }
            } elseif ($isResponsableCompras) {
                // Si es responsable de compras, solo puede editar sus propias solicitudes
                if (!$isOwnRequest) {
                    abort(403, 'Solo puedes editar las solicitudes generales que creaste.');
                }
                // Si es el creador, solo puede editar si el estado es "creada"
                if ($entry->status !== 'creada') {
                    abort(403, 'Solo puedes editar solicitudes con estado "creada".');
                }
            } else {
                // Todos los demás usuarios solo pueden editar sus propias solicitudes
                if (!$isOwnRequest) {
                    abort(403, 'Solo puedes editar las solicitudes que creaste.');
                }
                
                // Si es el creador, solo puede editar si el estado es "creada"
                if ($entry->status !== 'creada') {
                    abort(403, 'Solo puedes editar solicitudes con estado "creada".');
                }
            }
        }

        // Obtener datos para guardar
        $dataToSave = $this->crud->getStrippedSaveRequest($request);

        // Debug: Log todos los datos del request
        \Log::info('Datos del request completo (UPDATE):', $request->all());
        \Log::info('selected_products (UPDATE):', ['value' => $request->input('selected_products')]);

        try {
            // update item in the db
            $item = $this->crud->update($this->crud->getCurrentEntryId(), $dataToSave);
            $this->data['entry'] = $this->crud->entry = $item;

            // Eliminar productos existentes y procesar los nuevos
            $this->processSelectedProducts($item, $request, true);

            // show a success message
            \Alert::success(trans('backpack::crud.update_success'))->flash();

            // save the redirect choice for next time
            $this->crud->setSaveAction();

            return $this->crud->performSaveAction($item->getKey());
        } catch (\Exception $e) {
            \Log::error('Error al actualizar GeneralRequest: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            \Alert::error('Error al actualizar la solicitud general: ' . $e->getMessage())->flash();
            return redirect()->back()->withInput();
        }
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

        // Asegurar que created_by esté asignado
        $user = backpack_user();
        if ($user && !$request->has('created_by')) {
            $request->merge(['created_by' => $user->id]);
        }

        // Obtener datos para guardar
        $dataToSave = $this->crud->getStrippedSaveRequest($request);

        // Debug: Log todos los datos del request
        \Log::info('Datos del request completo:', $request->all());
        \Log::info('selected_products:', ['value' => $request->input('selected_products')]);

        try {
            // insert item in the db
            $item = $this->crud->create($dataToSave);
            $this->data['entry'] = $this->crud->entry = $item;

            // Si el usuario es responsable de área, mantener el estado como 'creada'
            $isResponsableArea = $user && $user->hasRole('role_responsable_area', 'backpack');
            
            if ($isResponsableArea) {
                // Responsable de área: mantener estado 'creada'
                $item->update([
                    'status' => 'creada',
                    'analysis_status' => 'no_requerido'
                ]);
            } else {
                // Para otros usuarios, determinar si requiere análisis según el área
                if ($this->requiresAnalystApproval($item->area_id)) {
                    // Estado inicial: pendiente de análisis
                    $item->update([
                        'status' => 'pendiente_analisis',
                        'analysis_status' => 'pendiente'
                    ]);
                } else {
                    // Para otras áreas, va directo a revisión del responsable
                    $item->update([
                        'status' => 'revisada_area',
                        'analysis_status' => 'no_requerido'
                    ]);
                }
            }

            // Procesar productos seleccionados
            $this->processSelectedProducts($item, $request);

            // show a success message
            \Alert::success(trans('backpack::crud.insert_success'))->flash();

            // save the redirect choice for next time
            $this->crud->setSaveAction();

            return $this->crud->performSaveAction($item->getKey());
        } catch (\Exception $e) {
            \Log::error('Error al guardar GeneralRequest: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            \Alert::error('Error al guardar la solicitud general: ' . $e->getMessage())->flash();
            return redirect()->back()->withInput();
        }
    }

    /**
     * Process selected products and create general request details
     */
    private function processSelectedProducts($generalRequest, $request, $isUpdate = false)
    {
        // Si es una actualización, eliminar productos existentes
        if ($isUpdate) {
            \Log::info('Eliminando productos existentes de solicitud general:', ['id' => $generalRequest->id]);
            $generalRequest->details()->delete();
        }
        
        $selectedProducts = $request->input('selected_products');
        
        \Log::info('Valor de selected_products recibido:', ['value' => $selectedProducts, 'type' => gettype($selectedProducts)]);
        
        if (!$selectedProducts || $selectedProducts === '[]' || $selectedProducts === '') {
            \Log::warning('No hay productos seleccionados en solicitud general o el campo está vacío');
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
        
        foreach ($products as $index => $productData) {
            try {
                if (!isset($productData['product_id'])) {
                    \Log::warning('Producto sin product_id en índice ' . $index, ['data' => $productData]);
                    continue;
                }
                
                $productId = $productData['product_id'];
                $quantity = isset($productData['quantity']) ? (int)$productData['quantity'] : 1;
                $price = isset($productData['price']) ? (float)$productData['price'] : 0;
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
                    \Log::info('Nuevo producto creado desde solicitud general:', ['id' => $newProduct->id, 'name' => $newProduct->name]);
                } else {
                    // Validar que el producto existe
                    $productId = (int)$productId;
                    $product = \App\Models\Product::find($productId);
                    if (!$product) {
                        \Log::warning('Producto no encontrado:', ['product_id' => $productId]);
                        continue;
                    }
                }
                
                // Crear el detalle de la solicitud general
                $detail = \App\Models\GeneralRequestDetail::create([
                    'general_request_id' => $generalRequest->id,
                    'product_id' => $productId,
                    'requested_quantity' => $quantity,
                    'specifications' => $specifications,
                    'estimated_unit_price' => $price,
                    'estimated_total' => $price * $quantity,
                    'status' => 'Pendiente'
                ]);
                
                \Log::info('Detalle de solicitud general creado exitosamente:', [
                    'detail_id' => $detail->id,
                    'product_id' => $productId,
                    'quantity' => $quantity
                ]);
            } catch (\Exception $e) {
                \Log::error('Error al crear detalle de producto en solicitud general:', [
                    'index' => $index,
                    'product_data' => $productData,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Continuar con el siguiente producto en lugar de fallar todo
                continue;
            }
        }
    }

    protected function setupShowOperation()
    {
        CRUD::addClause('with', ['createdBy', 'area', 'purchaseRequests', 'details.product.stockLevels', 'deliveries.details']);

        // Reemplazar el botón de editar con uno personalizado que verifica condiciones
        CRUD::removeButton('update');
        CRUD::addButton('line', 'edit', 'view', 'crud::buttons.edit_general_request', 'end');
        
        CRUD::removeButton('delete');
        CRUD::addButton('line', 'delete', 'view', 'crud::buttons.delete_general_request', 'end');
        
        // Agregar botón para convertir a solicitud de compra también en la vista de detalle
        CRUD::addButton('line', 'convert_to_purchase', 'view', 'crud::buttons.convert_to_purchase', 'end');
        
        CRUD::column('number')->label('Número');
        CRUD::column('createdBy.name')->label('Solicitante');
        CRUD::column('area.name')->label('Área');
        CRUD::column('title')->label('Título');
        CRUD::column('Description')->label('Descripción');
        CRUD::column('priority')->label('Prioridad');
        CRUD::column('status')->label('Estado de Solicitud');
        
        // Columna para mostrar si está convertida
        CRUD::column('conversion_status_show')->label('Convertida a Compra')->type('custom_html')
            ->value(function($entry) {
                if ($entry->is_converted) {
                    return '<span class="badge bg-success"><i class="la la-check-circle"></i> Sí, convertida</span>';
                }
                return '<span class="badge bg-secondary"><i class="la la-times-circle"></i> No convertida</span>';
            });
        
        // Mostrar productos solicitados
        CRUD::column('products_table')->label('Productos Solicitados')->type('custom_html')
            ->value(function($entry) {
                // Asegurar que se carguen las relaciones necesarias
                $entry->load(['details.product.stockLevels']);
                $details = $entry->details;
                
                if ($details->isEmpty()) {
                    return '<div class="alert alert-info">
                        <i class="la la-info-circle"></i> No hay productos solicitados en esta solicitud general.
                    </div>';
                }
                
                $html = '<div class="card">';
                $html .= '<div class="card-header">';
                $html .= '<h5 class="card-title mb-0">';
                $html .= '<i class="la la-list"></i> Productos Solicitados (' . $details->count() . ' productos)';
                $html .= '</h5>';
                $html .= '</div>';
                $html .= '<div class="card-body p-0">';
                $html .= '<div class="table-responsive">';
                $html .= '<table class="table table-striped table-bordered mb-0">';
                $html .= '<thead style="background-color: #871f1f; color: white;">';
                $html .= '<tr>';
                $html .= '<th width="25%" style="background-color: #871f1f; color: white;">Producto</th>';
                $html .= '<th width="12%" class="text-center" style="background-color: #871f1f; color: white;">Cantidad Solicitada</th>';
                $html .= '<th width="12%" class="text-center" style="background-color: #871f1f; color: white;">Cantidad Entregada</th>';
                $html .= '<th width="12%" class="text-center" style="background-color: #871f1f; color: white;">Estado Entrega</th>';
                $html .= '<th width="12%" class="text-center" style="background-color: #871f1f; color: white;">Stock Disponible</th>';
                $html .= '<th width="12%" class="text-center" style="background-color: #871f1f; color: white;">Disponibilidad</th>';
                $html .= '<th width="15%" style="background-color: #871f1f; color: white;">Especificaciones</th>';
                $html .= '</tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                
                foreach ($details as $detail) {
                    // Asegurar que el producto esté cargado
                    if (!$detail->product) {
                        continue;
                    }
                    
                    $productName = $detail->product->name ?? 'Producto no encontrado';
                    $productDescription = $detail->product->description ?? '';
                    
                    // Calcular stock disponible solo de la ubicación del área de la solicitud
                    $stockAvailable = 0;
                    if ($detail->product_id) {
                        try {
                            // Obtener la ubicación correspondiente al área de la solicitud
                            $location = null;
                            if ($entry->area) {
                                // Mapeo entre nombres de áreas y nombres de ubicaciones
                                $areaLocationMap = [
                                    'Informática' => 'Informática',
                                    'Mantenimiento' => 'Mantenimiento',
                                    'Salud' => 'Insumos de Salud',
                                    'Insumos Generales' => 'Insumos Generales',
                                ];
                                
                                $areaName = $entry->area->name;
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
                            \Log::error('Error al calcular stock para producto ' . $detail->product_id . ': ' . $e->getMessage());
                            $stockAvailable = 0;
                        }
                    }
                    
                    $requestedQuantity = $detail->requested_quantity ?? 0;
                    $deliveredQuantity = $detail->delivered_quantity ?? 0;
                    $pendingQuantity = max(0, $requestedQuantity - $deliveredQuantity);
                    $hasEnoughStock = $stockAvailable >= $requestedQuantity;
                    $stockDifference = $stockAvailable - $requestedQuantity;
                    
                    // Determinar estado de entrega
                    $deliveryStatus = $detail->delivery_status ?? 'Pendiente';
                    $isFullyDelivered = $detail->is_fully_delivered ?? false;
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
                    
                    // Determinar color y mensaje de disponibilidad
                    $availabilityBadge = '';
                    $availabilityColor = '';
                    if ($stockAvailable == 0) {
                        $availabilityBadge = 'Sin stock';
                        $availabilityColor = 'danger';
                    } elseif ($hasEnoughStock) {
                        $availabilityBadge = 'Stock suficiente';
                        $availabilityColor = 'success';
                    } else {
                        $availabilityBadge = 'Stock insuficiente';
                        $availabilityColor = 'warning';
                    }
                    
                    $html .= '<tr>';
                    $html .= '<td>';
                    $html .= '<strong>' . e($productName) . '</strong>';
                    if ($productDescription) {
                        $html .= '<br><small class="text-muted">' . e($productDescription) . '</small>';
                    }
                    if ($detail->product && $detail->product->unit_measurement) {
                        $html .= '<br><small class="text-info"><i class="la la-tag"></i> ' . e($detail->product->unit_measurement) . '</small>';
                    }
                    $html .= '</td>';
                    $html .= '<td class="text-center">';
                    $html .= '<span class="badge bg-primary fs-6">' . number_format($requestedQuantity) . '</span>';
                    $html .= '</td>';
                    $html .= '<td class="text-center">';
                    $html .= '<span class="badge bg-' . ($deliveredQuantity > 0 ? ($isFullyDelivered ? 'success' : 'warning') : 'secondary') . ' fs-6" title="Cantidad entregada: ' . number_format($deliveredQuantity) . ' de ' . number_format($requestedQuantity) . '">';
                    $html .= number_format($deliveredQuantity) . ' / ' . number_format($requestedQuantity);
                    $html .= '</span>';
                    if ($pendingQuantity > 0) {
                        $html .= '<br><small class="text-warning">Faltan: ' . number_format($pendingQuantity) . '</small>';
                    }
                    $html .= '</td>';
                    $html .= '<td class="text-center">';
                    $html .= '<span class="badge bg-' . $deliveryStatusColor . '" title="Estado de entrega: ' . $deliveryStatus . '">';
                    $html .= '<i class="la la-' . $deliveryStatusIcon . '"></i> ' . $deliveryStatus;
                    $html .= '</span>';
                    $html .= '</td>';
                    $html .= '<td class="text-center">';
                    $html .= '<span class="badge bg-' . ($stockAvailable > 0 ? 'info' : 'secondary') . ' fs-6" title="Stock total del producto">' . number_format($stockAvailable, 0, ',', '.') . '</span>';
                    $html .= '</td>';
                    $html .= '<td class="text-center">';
                    $html .= '<span class="badge bg-' . $availabilityColor . '" title="Stock disponible: ' . number_format($stockAvailable) . ', Solicitado: ' . number_format($requestedQuantity) . ($stockDifference < 0 ? ', Faltante: ' . number_format(abs($stockDifference)) : '') . '">';
                    $html .= '<i class="la la-' . ($hasEnoughStock ? 'check-circle' : ($stockAvailable == 0 ? 'times-circle' : 'exclamation-triangle')) . '"></i> ';
                    $html .= $availabilityBadge;
                    $html .= '</span>';
                    if (!$hasEnoughStock && $stockAvailable > 0) {
                        $html .= '<br><small class="text-warning">Faltan ' . number_format(abs($stockDifference)) . ' unidades</small>';
                    }
                    $html .= '</td>';
                    $html .= '<td>';
                    if ($detail->specifications) {
                        $html .= '<small>' . e($detail->specifications) . '</small>';
                    } else {
                        $html .= '<small class="text-muted">Sin especificaciones</small>';
                    }
                    $html .= '</td>';
                    $html .= '</tr>';
                }
                
                $html .= '</tbody>';
                $html .= '</table>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                
                return $html;
            });
        
        // Mostrar información de conversión si existe
        CRUD::column('conversion_info')->label('Información de Conversión')->type('custom_html')
            ->value(function($entry) {
                if ($entry->is_converted && $entry->purchaseRequests->isNotEmpty()) {
                    $html = '<div class="alert alert-success">';
                    $html .= '<h5><i class="la la-check-circle"></i> Solicitud Convertida a Compra</h5>';
                    foreach ($entry->purchaseRequests as $purchaseRequest) {
                        $html .= '<p><strong>Solicitud de Compra:</strong> ' . e($purchaseRequest->request_number) . '</p>';
                        $html .= '<p><strong>Fecha de Conversión:</strong> ' . $purchaseRequest->created_at->format('d/m/Y H:i') . '</p>';
                        $html .= '<p><strong>Estado:</strong> ' . e($purchaseRequest->status) . '</p>';
                    }
                    $html .= '</div>';
                    return $html;
                }
                return '';
            });
        
        // Agregar botones de análisis solo para analistas y solo si requiere análisis
        $user = backpack_user();
        if ($user && $user->hasRole('role_analista_area', 'backpack')) {
            CRUD::column('analyst_actions')->label('Acciones de Análisis')
                ->type('custom_html')
                ->value(function($entry) {
                    // Verificar que requiere análisis y está pendiente
                    $controller = new \App\Http\Controllers\Admin\GeneralRequestCrudController();
                    $reflection = new \ReflectionClass($controller);
                    $method = $reflection->getMethod('requiresAnalystApproval');
                    $method->setAccessible(true);
                    $requiresAnalysis = $method->invoke($controller, $entry->area_id);
                    
                    if (!$requiresAnalysis || $entry->analysis_status !== 'pendiente') {
                        return '';
                    }
                    
                    return '
                        <div class="card mb-3" style="border-left: 4px solid #007bff;">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="la la-clipboard-check"></i> Análisis de Solicitud
                                </h5>
                                <p class="card-text">Esta solicitud requiere tu análisis antes de ser enviada al responsable de área.</p>
                                <form method="POST" action="'.backpack_url('general-request/'.$entry->id.'/approve-by-analyst').'" style="display:inline-block; margin-right:10px;">
                                    '.csrf_field().'
                                    <button type="submit" class="btn btn-success" onclick="return confirm(\'¿Estás seguro de aprobar esta solicitud?\')">
                                        <i class="la la-check"></i> Aprobar Solicitud
                                    </button>
                                </form>
                                <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#rejectModal'.$entry->id.'">
                                    <i class="la la-times"></i> Rechazar Solicitud
                                </button>
                            </div>
                        </div>
                        
                        <!-- Modal para rechazar -->
                        <div class="modal fade" id="rejectModal'.$entry->id.'" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST" action="'.backpack_url('general-request/'.$entry->id.'/reject-by-analyst').'">
                                        '.csrf_field().'
                                        <div class="modal-header">
                                            <h5 class="modal-title">Rechazar Solicitud</h5>
                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="form-group">
                                                <label>Motivo del rechazo <span class="text-danger">*</span></label>
                                                <textarea name="rejection_reason" class="form-control" rows="4" required placeholder="Ingresa el motivo del rechazo..."></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                                            <button type="submit" class="btn btn-danger">Rechazar Solicitud</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    ';
                });
        }

        // Agregar botón para registrar entrega solo para role_responsable_area y solo si la solicitud es de su área
        if ($user && $user->hasRole('role_responsable_area', 'backpack')) {
            CRUD::column('register_delivery_button')->label('Acciones')->type('custom_html')
                ->value(function($entry) use ($user) {
                    // Verificar si la solicitud pertenece a un área donde el usuario es responsable
                    $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('id');
                    $canDeliver = false;
                    
                    // Puede entregar si la solicitud pertenece a una de sus áreas
                    if ($entry->area_id && $userAreas->contains($entry->area_id)) {
                        $canDeliver = true;
                    }
                    
                    if (!$canDeliver) {
                        return '<div class="alert alert-warning">
                            <i class="la la-exclamation-triangle"></i> No puedes registrar entregas para solicitudes de otras áreas.
                        </div>';
                    }
                    
                    // No mostrar el botón si la solicitud ya está totalmente entregada
                    if ($entry->status === 'entregada_totalmente') {
                        return '<div class="alert alert-success">
                            <i class="la la-check-circle"></i> <strong>Solicitud totalmente entregada:</strong> Esta solicitud ya ha sido entregada completamente. No se pueden registrar más entregas.
                        </div>';
                    }
                    
                    return '<div class="card mb-3" style="border-left: 4px solid #28a745;">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="la la-people-carry"></i> Registrar Entrega de Productos
                            </h5>
                            <p class="card-text">
                                Registra la entrega de los productos de esta solicitud general al personal solicitante.
                            </p>
                            <a href="' . backpack_url('delivery/create?general_request_id=' . $entry->id) . '" 
                               class="btn btn-success">
                                <i class="la la-plus"></i> Registrar Entrega
                            </a>
                        </div>
                    </div>';
                });
        }
    }
    
    /**
     * Show converted general requests
     */
    public function showConverted()
    {
        CRUD::setModel(\App\Models\GeneralRequest::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/general-request-converted');
        CRUD::setEntityNameStrings('solicitud general convertida', 'solicitudes generales convertidas');
        
        // Solo mostrar solicitudes convertidas
        CRUD::addClause('where', 'is_converted', '=', true);
        CRUD::addClause('with', ['createdBy', 'area', 'purchaseRequests']);
        
        CRUD::column('number')->label('Número');
        CRUD::column('title')->label('Título');
        CRUD::column('createdBy.name')->label('Creado por');
        CRUD::column('area.name')->label('Área');
        CRUD::column('priority')->label('Prioridad');
        CRUD::column('status')->label('Estado');
        CRUD::column('created_at')->label('Fecha de Creación');
        
        // Mostrar solicitudes de compra relacionadas
        CRUD::column('purchase_requests')->label('Solicitudes de Compra')->type('custom_html')
            ->value(function($entry) {
                if ($entry->purchaseRequests->isNotEmpty()) {
                    $html = '';
                    foreach ($entry->purchaseRequests as $purchaseRequest) {
                        $html .= '<a href="' . backpack_url('purchase-request/' . $purchaseRequest->id . '/show') . '" class="btn btn-sm btn-info">';
                        $html .= $purchaseRequest->request_number;
                        $html .= '</a><br>';
                    }
                    return $html;
                }
                return '<span class="text-muted">Sin solicitudes de compra</span>';
            });
        
        return $this->crud->list();
    }

    /**
     * Aprobar solicitud desde el analista
     */
    public function approveByAnalyst($id)
    {
        $user = backpack_user();
        
        if (!$user->hasRole('role_analista_area', 'backpack')) {
            abort(403, 'Solo los analistas pueden aprobar solicitudes.');
        }
        
        $request = \App\Models\GeneralRequest::findOrFail($id);
        
        if (!$this->requiresAnalystApproval($request->area_id)) {
            abort(403, 'Esta solicitud no requiere análisis.');
        }
        
        if ($request->analysis_status !== 'pendiente') {
            abort(403, 'Esta solicitud ya fue procesada.');
        }
        
        $request->update([
            'analysis_status' => 'aprobada',
            'analyzed_by' => $user->id,
            'analyzed_at' => now(),
            'status' => 'revisada_area'
        ]);
        
        \Alert::success('Solicitud aprobada exitosamente.')->flash();
        return redirect()->back();
    }

    /**
     * Rechazar solicitud desde el analista
     */
    public function rejectByAnalyst($id, \Illuminate\Http\Request $request)
    {
        $user = backpack_user();
        
        if (!$user->hasRole('role_analista_area', 'backpack')) {
            abort(403, 'Solo los analistas pueden rechazar solicitudes.');
        }
        
        $generalRequest = \App\Models\GeneralRequest::findOrFail($id);
        
        if (!$this->requiresAnalystApproval($generalRequest->area_id)) {
            abort(403, 'Esta solicitud no requiere análisis.');
        }
        
        if ($generalRequest->analysis_status !== 'pendiente') {
            abort(403, 'Esta solicitud ya fue procesada.');
        }
        
        $request->validate([
            'rejection_reason' => 'required|string|min:10'
        ]);
        
        $generalRequest->update([
            'analysis_status' => 'rechazada',
            'analyzed_by' => $user->id,
            'analyzed_at' => now(),
            'rejected_reason' => $request->input('rejection_reason'),
            'status' => 'rechazada_analista'
        ]);
        
        \Alert::success('Solicitud rechazada.')->flash();
        return redirect()->back();
    }
}
