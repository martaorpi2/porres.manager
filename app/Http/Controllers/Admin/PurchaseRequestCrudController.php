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
        
        CRUD::addClause('with', ['responsibilityArea', 'requestingUser', 'details']);
        
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
                $productIds = $entry->details->pluck('product_id')->toArray();
                $quotationsCount = \App\Models\MarketRate::whereHas('quoteDetails', function($query) use ($productIds) {
                    $query->whereIn('product_id', $productIds);
                })->count();
                
                if ($quotationsCount > 0) {
                    return '<span class="badge bg-success">' . $quotationsCount . ' cotizaciones</span>';
                } else {
                    return '<span class="badge bg-warning">Sin cotizaciones</span>';
                }
            });

        // Botón para generar planilla comparativa
        CRUD::addButton('line', 'comparative_excel', 'view', 'crud::buttons.comparative_excel', 'end');
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
            CRUD::modifyField('responsibility_area_id', ['default' => $generalRequest->area_id]);
            CRUD::modifyField('requesting_user_id', ['default' => $generalRequest->created_by]);
            CRUD::modifyField('priority', ['default' => $generalRequest->priority]);
            CRUD::modifyField('justification', ['default' => $generalRequest->description]);
            
            // Asegurar que los valores por defecto se establezcan correctamente
            if ($generalRequest->area_id) {
                CRUD::modifyField('responsibility_area_id', ['value' => $generalRequest->area_id]);
            }
            if ($generalRequest->created_by) {
                CRUD::modifyField('requesting_user_id', ['value' => $generalRequest->created_by]);
            }
        }
        
        // Campo oculto para la conversión
        if ($convertedFrom) {
            // Agregar el campo oculto con el ID de la solicitud general
            CRUD::field('converted_from_general_request_id')
                ->type('hidden')
                ->value($convertedFrom)
                ->attributes(['name' => 'converted_from_general_request_id']);
            
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
                    // Asegurar que el campo oculto se envíe con el formulario
                    var hiddenField = document.createElement("input");
                    hiddenField.type = "hidden";
                    hiddenField.name = "converted_from_general_request_id";
                    hiddenField.value = "' . $convertedFrom . '";
                    document.querySelector("form").appendChild(hiddenField);
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
        $this->setupCreateFields();
        
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
                function addProductToList(productId, productName, unit, description, quantity) {
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
                                <input type="number" class="form-control product-price" step="0.01" min="0">
                            </div>
                            <div class="col-md-3">
                                <label>Especificaciones:</label>
                                <textarea class="form-control product-specs" rows="2"></textarea>
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
        
        // Verificar si viene de una solicitud general desde el parámetro URL
        $convertedFrom = request()->get('converted_from');
        \Log::info('Parámetro converted_from desde URL:', ['converted_from' => $convertedFrom]);
        \Log::info('Campo converted_from_general_request_id en datos:', ['field' => $dataToSave['converted_from_general_request_id'] ?? 'no existe']);
        
        if ($convertedFrom && !isset($dataToSave['converted_from_general_request_id'])) {
            $dataToSave['converted_from_general_request_id'] = $convertedFrom;
            \Log::info('Agregado converted_from_general_request_id a datos:', ['id' => $convertedFrom]);
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

            // Verificar si el usuario seleccionó productos manualmente
            $selectedProducts = $request->input('selected_products');
            $hasManualProducts = !empty($selectedProducts);

            // Si viene de una solicitud general y NO hay productos seleccionados manualmente,
            // replicar automáticamente los productos de la solicitud general
            if ($item->converted_from_general_request_id && !$hasManualProducts) {
                $this->replicateProductsFromGeneralRequest($item);
            }

            // Procesar productos seleccionados manualmente (si el usuario los seleccionó)
            if ($hasManualProducts) {
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
                    $generalRequest->update(['status' => 'convertida_a_compra']);
                    \Log::info('Solicitud general actualizada exitosamente:', ['id' => $generalRequest->id, 'status' => $generalRequest->status]);
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
                $purchaseRequestDetail = \App\Models\PurchaseRequestDetail::create([
                    'purchase_request_id' => $purchaseRequest->id,
                    'product_id' => $generalDetail->product_id,
                    'requested_quantity' => $generalDetail->requested_quantity,
                    'specifications' => $generalDetail->specifications,
                    'justification' => $generalDetail->justification,
                    'estimated_unit_price' => $generalDetail->estimated_unit_price ?? 0,
                    'estimated_total' => $generalDetail->estimated_total ?? ($generalDetail->estimated_unit_price * $generalDetail->requested_quantity ?? 0),
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
     * Process selected products and create purchase request details
     */
    private function processSelectedProducts($purchaseRequest, $request)
    {
        $selectedProducts = $request->input('selected_products');
        
        if (!$selectedProducts) {
            \Log::info('No hay productos seleccionados');
            return;
        }
        
        $products = json_decode($selectedProducts, true);
        \Log::info('Productos seleccionados:', $products);
        
        $totalAmount = 0;
        
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
                \Log::info('Nuevo producto creado:', ['id' => $newProduct->id, 'name' => $newProduct->name]);
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
     * Generate purchase order from selected market rate
     */
    public function generatePurchaseOrder($id)
    {
        $purchaseRequest = \App\Models\PurchaseRequest::with([
            'selectedMarketRate.supplier',
            'selectedMarketRate.quoteDetails.product',
            'responsibilityArea'
        ])->findOrFail($id);
        
        if (!$purchaseRequest->selected_market_rate_id) {
            \Alert::error('Debe seleccionar una cotización antes de generar la orden de compra.')->flash();
            return redirect()->back();
        }
        
        // Create purchase order
        $purchaseOrder = \App\Models\PurchaseOrder::create([
            'order_number' => \App\Models\PurchaseOrder::generateNextNumber(),
            'order_date' => now(),
            'supplier_id' => $purchaseRequest->selectedMarketRate->supplier_id,
            'status' => 'Pendiente',
            'total_amount' => $purchaseRequest->selectedMarketRate->total_amount,
            'delivery_date' => now()->addDays(15),
            'notes' => 'Generada desde solicitud: ' . $purchaseRequest->request_number
        ]);
        
        // Create purchase order details
        foreach ($purchaseRequest->selectedMarketRate->quoteDetails as $quoteDetail) {
            \App\Models\PurchaseOrderDetail::create([
                'purchase_order_id' => $purchaseOrder->id,
                'input_id' => $quoteDetail->product_id, // Assuming product maps to input
                'quantity' => $quoteDetail->quantity,
                'unit_cost' => $quoteDetail->unit_price,
                'total_cost' => $quoteDetail->quantity * $quoteDetail->unit_price
            ]);
        }
        
        // Update purchase request status
        $purchaseRequest->update(['status' => 'Completada']);
        
        \Alert::success('Orden de compra generada exitosamente: ' . $purchaseOrder->order_number)->flash();
        
        return redirect()->route('purchase-order.show', $purchaseOrder->id);
    }


    /**
     * Define what happens when the Show operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-show
     * @return void
     */
    protected function setupShowOperation()
    {
        CRUD::addClause('with', ['responsibilityArea', 'requestingUser', 'approvedBy', 'details.product', 'selectedMarketRate.supplier', 'selectedBy', 'convertedFromGeneralRequest']);
        
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
                $html .= '<th style="width: 40%;">Producto</th>';
                $html .= '<th style="width: 20%;">Cantidad</th>';
                $html .= '<th style="width: 30%;">Especificaciones</th>';
                $html .= '<th style="width: 10%;">Estado</th>';
                $html .= '</tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                
                foreach ($details as $detail) {
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
                    $html .= '<td><span class="badge bg-info">' . $detail->requested_quantity . '</span>';
                    if ($detail->product && $detail->product->unit_measurement && !is_array($detail->product->unit_measurement)) {
                        $html .= '<br><small class="text-muted">' . $detail->product->unit_measurement . '</small>';
                    }
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
                    $html .= '<td><span class="badge bg-' . ($detail->status == 'Aprobada' ? 'success' : ($detail->status == 'Rechazada' ? 'danger' : 'warning')) . '">' . $status . '</span></td>';
                    $html .= '</tr>';
                }
                
                $html .= '</tbody>';
                $html .= '</table>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                
                return $html;
            });

        // Agregar campo para mostrar cotizaciones disponibles
        CRUD::column('market_rates_table')->label('Cotizaciones Disponibles')->type('custom_html')
            ->value(function($entry) {
                $productIds = $entry->details->pluck('product_id')->toArray();
                $marketRates = \App\Models\MarketRate::with([
                    'supplier',
                    'quoteDetails.product'
                ])->whereHas('quoteDetails', function($query) use ($productIds) {
                    $query->whereIn('product_id', $productIds);
                })->get();
                
                if ($marketRates->isEmpty()) {
                    return '<div class="alert alert-warning">No hay cotizaciones disponibles para los productos de esta solicitud.</div>';
                }
                
                $html = '<div class="table-responsive">';
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
                    
                    if (!$isSelected && $entry->status != 'Completada') {
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
                
                // Botón para generar orden de compra si hay cotización seleccionada
                if ($entry->selected_market_rate_id && $entry->status != 'Completada') {
                    $html .= '<div class="mt-3">';
                    $html .= '<form method="POST" action="' . route('purchase-request.generate-purchase-order', $entry->id) . '">';
                    $html .= csrf_field();
                    $html .= '<button type="submit" class="btn btn-primary" onclick="return confirm(\'¿Está seguro de generar la orden de compra?\')">';
                    $html .= '<i class="la la-shopping-cart"></i> Generar Orden de Compra';
                    $html .= '</button>';
                    $html .= '</form>';
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
    }
}

