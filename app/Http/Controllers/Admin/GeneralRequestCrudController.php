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

    protected function setupListOperation()
    {
        // Habilitar tabla responsiva
        CRUD::enableResponsiveTable();
        
        // Cargar relaciones necesarias, incluyendo productos para verificar stock y entregas
        CRUD::addClause('with', ['createdBy', 'area', 'details.product', 'deliveries']);

        // Si el usuario tiene rol role_personal, solo mostrar sus solicitudes
        $user = backpack_user();
        if ($user && $user->hasRole('role_personal')) {
            CRUD::addClause('where', 'created_by', $user->id);
        } elseif ($user && $user->hasRole('role_responsable_area')) {
            // Para role_responsable_area, mostrar solicitudes de su área o que él creó
            $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('id');
            if ($userAreas->isNotEmpty()) {
                CRUD::addClause(function($query) use ($user, $userAreas) {
                    $query->where('created_by', $user->id)
                        ->orWhereIn('area_id', $userAreas);
                });
            } else {
                CRUD::addClause('where', 'created_by', $user->id);
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
        if (request()->has('creado_por')) {
            $creadoPor = request()->get('creado_por');
            if ($creadoPor) {
                CRUD::addClause('where', 'created_by', $creadoPor);
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
        
        // Si es role_personal, solo puede editar sus propias solicitudes
        if ($user->hasRole('role_personal', 'backpack')) {
            $entry = $this->crud->getCurrentEntry();
            if ($entry && $entry->created_by != $user->id) {
                abort(403, 'Solo puedes editar tus propias solicitudes.');
            }
        }
        
        $entry = $this->crud->getCurrentEntry();
        $isResponsableArea = $user->hasRole('role_responsable_area', 'backpack');
        $isOwnRequest = $entry && $entry->created_by == $user->id;
        $canOnlyChangeStatus = false;
        
        // Si es role_responsable_area y la solicitud NO es propia, verificar si puede cambiar solo el estado
        if ($isResponsableArea && !$isOwnRequest && $entry) {
            // Verificar si la solicitud pertenece a un área donde el usuario es responsable
            $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('id');
            if ($entry->area_id && $userAreas->contains($entry->area_id)) {
                // Puede cambiar solo el estado
                $canOnlyChangeStatus = true;
            } else {
                // No puede editar solicitudes de otras áreas
                abort(403, 'Solo puedes editar solicitudes de tu área o las que creaste.');
            }
        } elseif ($isResponsableArea && !$isOwnRequest) {
            // Si es role_responsable_area intentando editar una solicitud que no es suya y no es de su área
            abort(403, 'Solo puedes editar solicitudes de tu área o las que creaste.');
        }
        
        // Si solo puede cambiar el estado, mostrar solo ese campo
        if ($canOnlyChangeStatus) {
            // Mostrar información de la solicitud (solo lectura)
            CRUD::field('number')->label('Número de Solicitud')->type('text')->attributes(['readonly' => 'readonly', 'disabled' => 'disabled']);
            CRUD::field('title')->label('Título')->type('text')->attributes(['readonly' => 'readonly', 'disabled' => 'disabled']);
            CRUD::field('description')->label('Descripción')->type('textarea')->attributes(['readonly' => 'readonly', 'disabled' => 'disabled']);
            
            CRUD::field('area_id')->label('Área')
                ->type('select')
                ->model('App\Models\ResponsibilityArea')
                ->attribute('name')
                ->attributes(['readonly' => 'readonly', 'disabled' => 'disabled']);
            
            CRUD::field('created_by')->label('Creado por')
                ->type('select')
                ->model('App\Models\User')
                ->attribute('name')
                ->attributes(['readonly' => 'readonly', 'disabled' => 'disabled']);
            
            CRUD::field('priority')->label('Prioridad')
                ->type('select_from_array')
                ->options([
                    'Baja' => 'Baja',
                    'Media' => 'Media',
                    'Alta' => 'Alta',
                    'Urgente' => 'Urgente'
                ])
                ->attributes(['readonly' => 'readonly', 'disabled' => 'disabled']);
            
            // Campo editable: Estado (solo estados de solicitud)
            CRUD::field('status')->label('Estado de Solicitud')
                ->type('select_from_array')
                ->options([
                    'creada' => 'Creada',
                    'revisada_area' => 'Revisada por Área',
                    'archivada' => 'Archivada',
                ])
                ->allows_null(false);
            
            // Campo de solo lectura para mostrar si está convertida
            CRUD::field('is_converted_display')->label('Convertida a Compra')
                ->type('custom_html')
                ->value(function($entry) {
                    if ($entry->is_converted) {
                        return '<div class="alert alert-success">
                            <i class="la la-check-circle"></i> <strong>Sí, esta solicitud ha sido convertida a orden de compra.</strong>
                        </div>';
                    }
                    return '<div class="alert alert-secondary">
                        <i class="la la-times-circle"></i> <strong>No, esta solicitud aún no ha sido convertida.</strong>
                    </div>';
                });
            
            // Agregar mensaje informativo
            CRUD::addField([
                'name' => 'status_change_info',
                'type' => 'custom_html',
                'value' => '<div class="alert alert-info">
                    <i class="la la-info-circle"></i> <strong>Nota:</strong> Solo puedes modificar el estado de esta solicitud. Los demás campos no son editables.
                </div>',
            ]);
        } else {
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
    }

    protected function setupDeleteOperation()
    {
        // Verificar que solo el creador pueda eliminar
        $user = backpack_user();
        $entry = $this->crud->getCurrentEntry();
        
        if ($entry) {
            // Solo el creador puede eliminar
            if ($entry->created_by != $user->id) {
                abort(403, 'Solo puedes eliminar las solicitudes que creaste.');
            }
            
            // Verificar si tiene entregas (total o parcialmente)
            $entry->load('deliveries.details');
            $hasDeliveries = $entry->deliveries->isNotEmpty();
            
            if ($hasDeliveries) {
                abort(403, 'No se puede eliminar una solicitud que tiene entregas registradas (total o parcialmente).');
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
        
        // Verificar si tiene entregas (total o parcialmente)
        $entry->load('deliveries.details');
        $hasDeliveries = $entry->deliveries->isNotEmpty();
        
        if ($hasDeliveries) {
            abort(403, 'No se puede eliminar una solicitud que tiene entregas registradas (total o parcialmente).');
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
                            option.setAttribute("data-stock", product.stock_total || 0);
                            option.setAttribute("data-minimum-stock", product.minimum_stock || 0);
                            select.appendChild(option);
                        });
                    })
                    .catch(error => console.error("Error loading products:", error));
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
        $isResponsableArea = $user && $user->hasRole('role_responsable_area', 'backpack');
        $isOwnRequest = $entry && $entry->created_by == $user->id;
        $canOnlyChangeStatus = false;
        
        // Verificar si solo puede cambiar el estado
        if ($isResponsableArea && !$isOwnRequest && $entry) {
            $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('id');
            if ($entry->area_id && $userAreas->contains($entry->area_id)) {
                $canOnlyChangeStatus = true;
            }
        }

        // Obtener datos para guardar
        $dataToSave = $this->crud->getStrippedSaveRequest($request);

        // Si solo puede cambiar el estado, solo guardar el estado
        if ($canOnlyChangeStatus) {
            // Solo guardar el estado, ignorar otros campos
            $dataToSave = [];
            if ($request->has('status')) {
                $dataToSave['status'] = $request->input('status');
            }
        }

        // Debug: Log todos los datos del request
        \Log::info('Datos del request completo (UPDATE):', $request->all());
        \Log::info('selected_products (UPDATE):', ['value' => $request->input('selected_products')]);

        try {
            // update item in the db
            $item = $this->crud->update($this->crud->getCurrentEntryId(), $dataToSave);
            $this->data['entry'] = $this->crud->entry = $item;

            // Solo procesar productos si no es solo cambio de estado
            if (!$canOnlyChangeStatus) {
                // Eliminar productos existentes y procesar los nuevos
                $this->processSelectedProducts($item, $request, true);
            }

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

        CRUD::removeButton('delete');
        CRUD::addButton('line', 'delete', 'view', 'crud::buttons.delete_general_request', 'end');
        
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
                    
                    // Calcular stock disponible usando consulta directa
                    $stockAvailable = 0;
                    if ($detail->product_id) {
                        try {
                            $stockAvailable = (int) \App\Models\StockLevel::where('product_id', $detail->product_id)->sum('quantity');
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
        
        // Agregar botón para registrar entrega solo para role_responsable_area y solo si la solicitud es de su área
        $user = backpack_user();
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
}
