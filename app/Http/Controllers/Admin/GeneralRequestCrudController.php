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
        CRUD::addClause('with', ['createdBy', 'area', 'details']);
        
        // Filtrar solicitudes que no estén convertidas a compra
        CRUD::addClause('where', 'status', '!=', 'convertida_a_compra');

        CRUD::column('number')->label('Número');
        CRUD::column('title')->label('Título');
        CRUD::column('createdBy.name')->label('Creado por');
        CRUD::column('area.name')->label('Área');
        CRUD::column('priority')->label('Prioridad');
        CRUD::column('status')->label('Estado');
        
        // Agregar columna personalizada para mostrar cantidad de productos
        CRUD::column('details_count')->label('Productos')->type('custom_html')
            ->value(function($entry) {
                $count = $entry->details->count();
                return '<span class="badge bg-info">' . $count . ' productos</span>';
            });
            
        CRUD::column('created_at')->label('Fecha de Creación');

        // Botón para convertir a solicitud de compra (solo para solicitudes no convertidas)
        CRUD::addButton('line', 'convert_to_purchase', 'view', 'crud::buttons.convert_to_purchase', 'end');
    }

    protected function setupCreateOperation()
    {
        CRUD::field('number')->label('Número de Solicitud')->default(\App\Models\GeneralRequest::generateNextNumber())->attributes(['readonly' => 'readonly']);

        CRUD::field('title')->label('Título')->validationRules('required|string|max:255');
        
        CRUD::field('description')->label('Descripción')->type('textarea')->validationRules('required');
        
        CRUD::field('area_id')->label('Área')
            ->type('select')
            ->model('App\Models\ResponsibilityArea')
            ->attribute('name')
            ->validationRules('nullable|exists:responsibility_areas,id');

        CRUD::field('created_by')->label('Creado por')
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

        CRUD::field('status')->label('Estado')
            ->type('select_from_array')
            ->options([
                'creada' => 'Creada',
                'revisada_area' => 'Revisada por Área',
                'archivada' => 'Archivada',
                'convertida_a_compra' => 'Convertida a Compra'
            ])
            ->default('creada');
            
        // Campo para seleccionar productos
        CRUD::field('products_selection')->label('Productos Solicitados')->type('custom_html')
            ->value($this->getProductsSelectionHtml());
    }

    protected function setupUpdateOperation()
    {
        // Usar los mismos campos que en create
        $this->setupCreateOperation();
        
        // Cargar productos existentes para edición
        $entry = $this->crud->getCurrentEntry();
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

    /**
     * Generate HTML for products selection with existing products
     */
    private function getProductsSelectionHtml($existingProducts = [])
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
                
                // Debug: Log para verificar que se está enviando
                console.log("Productos seleccionados:", products);
                console.log("Campo oculto value:", hiddenField.value);
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

        // Obtener datos para guardar
        $dataToSave = $this->crud->getStrippedSaveRequest($request);

        // Debug: Log todos los datos del request
        \Log::info('Datos del request completo:', $request->all());
        \Log::info('selected_products:', ['value' => $request->input('selected_products')]);

        try {
            // update item in the db
            $item = $this->crud->update($this->crud->getCurrentEntryId(), $dataToSave);
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
        
        if (!$selectedProducts) {
            \Log::info('No hay productos seleccionados en solicitud general');
            return;
        }
        
        $products = json_decode($selectedProducts, true);
        \Log::info('Productos seleccionados en solicitud general:', $products);
        
        foreach ($products as $productData) {
            $productId = $productData['product_id'];
            $quantity = $productData['quantity'];
            $price = $productData['price'] ?? 0;
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
            
            \Log::info('Detalle de solicitud general creado:', ['detail_id' => $detail->id, 'product_id' => $productId]);
        }
    }

    protected function setupShowOperation()
    {
        CRUD::addClause('with', ['createdBy', 'area', 'purchaseRequests', 'details.product']);
        
        CRUD::setFromDb();
        
        // Mostrar productos solicitados
        CRUD::field('products_table')->label('Productos Solicitados')->type('custom_html')
            ->value(function($entry) {
                $details = $entry->details;
                
                if ($details->isEmpty()) {
                    return '<div class="alert alert-info">
                        <i class="la la-info-circle"></i> No hay productos solicitados en esta solicitud general.
                    </div>';
                }
                
                $totalAmount = 0;
                $html = '<div class="card">';
                $html .= '<div class="card-header">';
                $html .= '<h5 class="card-title mb-0">';
                $html .= '<i class="la la-list"></i> Productos Solicitados (' . $details->count() . ' productos)';
                $html .= '</h5>';
                $html .= '</div>';
                $html .= '<div class="card-body p-0">';
                $html .= '<div class="table-responsive">';
                $html .= '<table class="table table-striped table-bordered mb-0">';
                $html .= '<thead class="thead-dark">';
                $html .= '<tr>';
                $html .= '<th width="25%">Producto</th>';
                $html .= '<th width="10%" class="text-center">Cantidad</th>';
                $html .= '<th width="25%">Especificaciones</th>';
                $html .= '<th width="12%" class="text-end">Precio Unit. Est.</th>';
                $html .= '<th width="12%" class="text-end">Total Estimado</th>';
                $html .= '<th width="16%" class="text-center">Estado</th>';
                $html .= '</tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                
                foreach ($details as $detail) {
                    $productName = $detail->product->name ?? 'Producto no encontrado';
                    $productDescription = $detail->product->description ?? '';
                    $unitPrice = $detail->estimated_unit_price ?? 0;
                    $totalPrice = $detail->estimated_total ?? 0;
                    $totalAmount += $totalPrice;
                    
                    // Determinar color del badge según el estado
                    $statusColor = 'secondary';
                    switch ($detail->status) {
                        case 'Aprobada':
                            $statusColor = 'success';
                            break;
                        case 'Rechazada':
                            $statusColor = 'danger';
                            break;
                        case 'En Cotización':
                            $statusColor = 'info';
                            break;
                        case 'Comprada':
                            $statusColor = 'primary';
                            break;
                        default:
                            $statusColor = 'warning';
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
                    $html .= '<span class="badge bg-primary fs-6">' . number_format($detail->requested_quantity) . '</span>';
                    $html .= '</td>';
                    $html .= '<td>';
                    if ($detail->specifications) {
                        $html .= '<small>' . e($detail->specifications) . '</small>';
                    } else {
                        $html .= '<small class="text-muted">Sin especificaciones</small>';
                    }
                    $html .= '</td>';
                    $html .= '<td class="text-end">';
                    if ($unitPrice > 0) {
                        $html .= '<strong>$' . number_format($unitPrice, 2) . '</strong>';
                    } else {
                        $html .= '<small class="text-muted">No definido</small>';
                    }
                    $html .= '</td>';
                    $html .= '<td class="text-end">';
                    if ($totalPrice > 0) {
                        $html .= '<strong class="text-success">$' . number_format($totalPrice, 2) . '</strong>';
                    } else {
                        $html .= '<small class="text-muted">No definido</small>';
                    }
                    $html .= '</td>';
                    $html .= '<td class="text-center">';
                    $html .= '<span class="badge bg-' . $statusColor . '">' . e($detail->status) . '</span>';
                    $html .= '</td>';
                    $html .= '</tr>';
                }
                
                $html .= '</tbody>';
                $html .= '<tfoot class="table-dark">';
                $html .= '<tr>';
                $html .= '<th colspan="4" class="text-end">Total Estimado:</th>';
                $html .= '<th class="text-end">';
                $html .= '<strong class="text-white">$' . number_format($totalAmount, 2) . '</strong>';
                $html .= '</th>';
                $html .= '<th></th>';
                $html .= '</tr>';
                $html .= '</tfoot>';
                $html .= '</table>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                
                return $html;
            });
        
        // Mostrar resumen estadístico de productos
        CRUD::field('products_summary')->label('Resumen de Productos')->type('custom_html')
            ->value(function($entry) {
                $details = $entry->details;
                
                if ($details->isEmpty()) {
                    return '';
                }
                
                $totalProducts = $details->count();
                $totalQuantity = $details->sum('requested_quantity');
                $totalAmount = $details->sum('estimated_total');
                $statusCounts = $details->groupBy('status')->map->count();
                
                $html = '<div class="row">';
                
                // Tarjeta de productos totales
                $html .= '<div class="col-md-3">';
                $html .= '<div class="card bg-primary text-white">';
                $html .= '<div class="card-body text-center">';
                $html .= '<h4 class="card-title">' . $totalProducts . '</h4>';
                $html .= '<p class="card-text">Productos Solicitados</p>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                
                // Tarjeta de cantidad total
                $html .= '<div class="col-md-3">';
                $html .= '<div class="card bg-info text-white">';
                $html .= '<div class="card-body text-center">';
                $html .= '<h4 class="card-title">' . number_format($totalQuantity) . '</h4>';
                $html .= '<p class="card-text">Cantidad Total</p>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                
                // Tarjeta de monto total
                $html .= '<div class="col-md-3">';
                $html .= '<div class="card bg-success text-white">';
                $html .= '<div class="card-body text-center">';
                $html .= '<h4 class="card-title">$' . number_format($totalAmount, 2) . '</h4>';
                $html .= '<p class="card-text">Monto Total Estimado</p>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                
                // Tarjeta de estados
                $html .= '<div class="col-md-3">';
                $html .= '<div class="card bg-secondary text-white">';
                $html .= '<div class="card-body text-center">';
                $html .= '<h4 class="card-title">' . $statusCounts->count() . '</h4>';
                $html .= '<p class="card-text">Estados Diferentes</p>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                
                $html .= '</div>';
                
                // Desglose por estados
                if ($statusCounts->count() > 1) {
                    $html .= '<div class="row mt-3">';
                    $html .= '<div class="col-12">';
                    $html .= '<div class="card">';
                    $html .= '<div class="card-header">';
                    $html .= '<h6 class="card-title mb-0"><i class="la la-chart-pie"></i> Desglose por Estados</h6>';
                    $html .= '</div>';
                    $html .= '<div class="card-body">';
                    
                    foreach ($statusCounts as $status => $count) {
                        $percentage = round(($count / $totalProducts) * 100, 1);
                        $statusColor = 'secondary';
                        switch ($status) {
                            case 'Aprobada':
                                $statusColor = 'success';
                                break;
                            case 'Rechazada':
                                $statusColor = 'danger';
                                break;
                            case 'En Cotización':
                                $statusColor = 'info';
                                break;
                            case 'Comprada':
                                $statusColor = 'primary';
                                break;
                            default:
                                $statusColor = 'warning';
                        }
                        
                        $html .= '<div class="row align-items-center mb-2">';
                        $html .= '<div class="col-md-2">';
                        $html .= '<span class="badge bg-' . $statusColor . '">' . e($status) . '</span>';
                        $html .= '</div>';
                        $html .= '<div class="col-md-8">';
                        $html .= '<div class="progress" style="height: 20px;">';
                        $html .= '<div class="progress-bar bg-' . $statusColor . '" role="progressbar" style="width: ' . $percentage . '%" aria-valuenow="' . $percentage . '" aria-valuemin="0" aria-valuemax="100">';
                        $html .= $percentage . '%';
                        $html .= '</div>';
                        $html .= '</div>';
                        $html .= '</div>';
                        $html .= '<div class="col-md-2 text-end">';
                        $html .= '<strong>' . $count . ' productos</strong>';
                        $html .= '</div>';
                        $html .= '</div>';
                    }
                    
                    $html .= '</div>';
                    $html .= '</div>';
                    $html .= '</div>';
                    $html .= '</div>';
                }
                
                return $html;
            });
        
        // Mostrar información de conversión si existe
        CRUD::field('conversion_info')->label('Información de Conversión')->type('custom_html')
            ->value(function($entry) {
                if ($entry->status == 'convertida_a_compra' && $entry->purchaseRequests->isNotEmpty()) {
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
        CRUD::addClause('where', 'status', '=', 'convertida_a_compra');
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
