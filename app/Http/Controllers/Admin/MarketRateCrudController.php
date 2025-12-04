<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\MarketRateRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Support\Facades\Log;

/**
 * Class MarketRateCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class MarketRateCrudController extends CrudController
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
        CRUD::setModel(\App\Models\MarketRate::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/market-rate');
        CRUD::setEntityNameStrings('cotización', 'cotizaciones');
        
        // Restringir creación de cotizaciones solo al responsable de compras
        $user = backpack_user();
        if ($user && !$user->hasRole('role_responsable_compras', 'backpack') && !$user->hasRole('role_admin_sistema', 'backpack') && !$user->hasRole('role_admin_institucion', 'backpack')) {
            CRUD::denyAccess('create');
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
        
        // Ocultar botones de editar y eliminar para role_admin_institucion, role_apoderado y role_representante_legal
        $user = backpack_user();
        if ($user && ($user->hasRole('role_admin_institucion', 'backpack') || $user->hasRole('role_apoderado', 'backpack') || $user->hasRole('role_representante_legal', 'backpack'))) {
            CRUD::removeButton('update');
            CRUD::removeButton('delete');
        }
        
        // Cargar relaciones necesarias
        CRUD::addClause('with', ['supplier', 'quoteDetails', 'purchaseRequest']);
        
        CRUD::column('supplier.company_name')->label('Proveedor');
        CRUD::column('purchaseRequest.status')->label('Estado de la Solicitud')->type('custom_html')
            ->value(function($entry) {
                $status = $entry->purchaseRequest->status ?? 'Sin estado';
                $badgeClass = match($status) {
                    'Pendiente' => 'bg-warning',
                    'Aprobada' => 'bg-success',
                    'Rechazada' => 'bg-danger',
                    'En Proceso' => 'bg-info',
                    'Completada' => 'bg-primary',
                    default => 'bg-secondary'
                };
                return '<span class="badge ' . $badgeClass . '">' . $status . '</span>';
            });
        CRUD::column('is_selected')->label('Estado de Selección')->type('custom_html')
            ->value(function($entry) {
                $status = $entry->is_selected ? 'Seleccionada' : 'No seleccionada';
                $badgeClass = $entry->is_selected ? 'bg-success' : 'bg-secondary';
                $icon = $entry->is_selected ? '✓' : '✗';
                return '<span class="badge ' . $badgeClass . '">' . $icon . ' ' . $status . '</span>';
            });
        CRUD::column('date')->label('Fecha');
        CRUD::column('total_amount')->label('Monto Total')->type('number')->decimals(2)->prefix('$');
        
        // Agregar columna personalizada para mostrar detalles de cotización
        CRUD::column('quote_details_count')->label('Detalles')->type('custom_html')
            ->value(function($entry) {
                $count = $entry->quoteDetails->count();
                return '<span class="badge bg-info">' . $count . ' productos</span>';
            });
            
        // Agregar botón PDF en cada fila
        CRUD::addButton('line', 'pdf', 'view', 'crud::buttons.pdf', 'end');
    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(MarketRateRequest::class);
        
        // Obtener purchase_request_id de la URL si existe
        $purchaseRequestId = request()->get('purchase_request_id');
        
        // Validar que la solicitud de compra no esté aprobada si se proporciona el ID
        if ($purchaseRequestId) {
            $purchaseRequest = \App\Models\PurchaseRequest::find($purchaseRequestId);
            if ($purchaseRequest && $purchaseRequest->status === 'Aprobada') {
                \Alert::error('No se pueden agregar cotizaciones a una solicitud de compra que ya está aprobada.')->flash();
                return redirect()->back();
            }
        }
        
        // Cargar productos de la solicitud de compra si se proporciona el ID
        $purchaseRequestProducts = [];
        if ($purchaseRequestId) {
            $purchaseRequest = \App\Models\PurchaseRequest::with('details.product')->find($purchaseRequestId);
            if ($purchaseRequest && $purchaseRequest->details) {
                $purchaseRequestProducts = $purchaseRequest->details->map(function($detail) {
                    return [
                        'product_id' => $detail->product_id,
                        'product_name' => $detail->product ? ($detail->product->name . ' (' . ($detail->product->unit_measurement ?? 'unidad') . ')') : 'Producto no encontrado',
                        'quantity' => $detail->requested_quantity ?? 0,
                        'unit_price' => 0, // Precio inicial en 0, el usuario debe ingresarlo
                        'unit' => $detail->product ? ($detail->product->unit_measurement ?? 'unidad') : 'unidad',
                        'description' => $detail->product ? ($detail->product->description ?? '') : ''
                    ];
                })->toArray();
            }
        }
        
        // Campo para seleccionar proveedor
        CRUD::field([
            'name' => 'supplier_id',
            'label' => 'Proveedor',
            'type' => 'select',
            'entity' => 'supplier',
            'attribute' => 'company_name',
            'model' => 'App\Models\Supplier',
        ]);
        
        // Campo para seleccionar solicitud de compra (solo solicitudes no aprobadas)
        CRUD::field([
            'name' => 'purchase_request_id',
            'label' => 'Solicitud de Compra',
            'type' => 'select',
            'entity' => 'purchaseRequest',
            'attribute' => 'request_number',
            'model' => 'App\Models\PurchaseRequest',
            'default' => $purchaseRequestId,
            'options' => function ($query) {
                // Filtrar solo solicitudes que no estén aprobadas o completadas
                return $query->where('status', '!=', 'Aprobada')
                             ->where('status', '!=', 'Completada')
                             ->get();
            },
        ]);
        
        // Campo informativo para mostrar información sobre las cotizaciones
        $infoMessage = '<div class="alert alert-info">
            <i class="la la-info-circle"></i> 
            <strong>Información:</strong> Las cotizaciones se asocian con solicitudes de compra específicas.';
        if ($purchaseRequestId && !empty($purchaseRequestProducts)) {
            $infoMessage .= '<br><strong>Los productos de la solicitud de compra se han cargado automáticamente. Por favor, ingrese los precios unitarios para cada producto.</strong>';
        } else {
            $infoMessage .= ' Selecciona la solicitud de compra para la cual deseas crear la cotización.';
        }
        $infoMessage .= '</div>';
        
        CRUD::field([
            'name' => 'purchase_request_info',
            'label' => 'Información',
            'type' => 'custom_html',
            'value' => $infoMessage,
        ]);
        
        CRUD::field('date')->label('Fecha')->type('date')->default(now()->format('Y-m-d'));
        CRUD::field('total_amount')->label('Monto Total')->type('number')->decimals(2)->prefix('$')->default(0);
        CRUD::field([
            'name' => 'is_selected',
            'label' => 'Estado de Selección',
            'type' => 'boolean',
            'default' => false,
            'hint' => 'Indica si esta cotización ha sido seleccionada para la compra',
        ]);

        // Campo dinámico para agregar items de cotización
        CRUD::field([
            'name' => 'quote_items_selection',
            'label' => 'Items de la Cotización',
            'type' => 'custom_html',
            'value' => $this->getQuoteItemsSelectionHtml($purchaseRequestProducts),
        ]);
        
        // Campo oculto para almacenar los items seleccionados
        CRUD::field([
            'name' => 'selected_quote_items',
            'type' => 'hidden',
        ]);
        /**
         * Fields can be defined using the fluent syntax:
         * - CRUD::field('price')->type('number');
         */
    }

    /**
     * Define what happens when the Update operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
        
        // Cargar los items existentes para edición
        $entry = $this->crud->getCurrentEntry();
        if ($entry) {
            $existingItems = $entry->quoteDetails->map(function($detail) {
                return [
                    'product_id' => $detail->product_id,
                    'product_name' => $detail->product->name ?? 'Producto no encontrado',
                    'quantity' => $detail->quantity,
                    'unit_price' => $detail->unit_price,
                ];
            })->toArray();
            
            CRUD::modifyField('quote_items_selection', [
                'value' => $this->getQuoteItemsSelectionHtml($existingItems),
            ]);
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
        // Cargar relaciones necesarias para la vista
        CRUD::addClause('with', ['supplier', 'quoteDetails.product', 'purchaseRequest']);
        
        CRUD::setFromDb(); // set fields from db columns.
        
        // Mostrar el estado de la solicitud de compra
        CRUD::modifyColumn('purchase_request_id', [
            'label' => 'Estado de la Solicitud de Compra',
            'type' => 'custom_html',
            'value' => function($entry) {
                $status = $entry->purchaseRequest->status ?? 'Sin estado';
                $requestNumber = $entry->purchaseRequest->request_number ?? 'N/A';
                $badgeClass = match($status) {
                    'Pendiente' => 'bg-warning',
                    'Aprobada' => 'bg-success',
                    'Rechazada' => 'bg-danger',
                    'En Proceso' => 'bg-info',
                    'Completada' => 'bg-primary',
                    default => 'bg-secondary'
                };
                return '<div>
                    <strong>Solicitud:</strong> ' . $requestNumber . '<br>
                    <span class="badge ' . $badgeClass . ' fs-6">' . $status . '</span>
                </div>';
            }
        ]);

        // Mostrar el estado de selección
        CRUD::modifyColumn('is_selected', [
            'label' => 'Estado de Selección',
            'type' => 'custom_html',
            'value' => function($entry) {
                $status = $entry->is_selected ? 'Seleccionada' : 'No seleccionada';
                $badgeClass = $entry->is_selected ? 'bg-success' : 'bg-secondary';
                $icon = $entry->is_selected ? '✓' : '✗';
                return '<span class="badge ' . $badgeClass . ' fs-6">' . $icon . ' ' . $status . '</span>';
            }
        ]);

        // Agregar campo personalizado para mostrar detalles de cotización (con estilo de PurchaseRequest)
        CRUD::column('quote_details_table')->label('Detalles de Cotización')->type('custom_html')
            ->value(function($entry) {
                $quoteDetails = $entry->quoteDetails;
                
                if ($quoteDetails->isEmpty()) {
                    return '<div class="alert alert-info">No hay detalles de cotización disponibles.</div>';
                }
                
                $html = '<div class="card border-primary">';
                $html .= '<div class="card-header bg-primary text-white">';
                $html .= '<h6 class="mb-0"><i class="la la-shopping-cart"></i> Productos Cotizados</h6>';
                $html .= '</div>';
                $html .= '<div class="card-body p-0">';
                $html .= '<div class="table-responsive">';
                $html .= '<table class="table table-sm table-bordered mb-0">';
                $html .= '<thead class="table-light">';
                $html .= '<tr>';
                $html .= '<th style="width: 35%;">Producto</th>';
                $html .= '<th style="width: 15%;">Cantidad</th>';
                $html .= '<th style="width: 20%;">Precio Unitario</th>';
                $html .= '<th style="width: 20%;">Subtotal</th>';
                $html .= '<th style="width: 10%;">Estado</th>';
                $html .= '</tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                
                $total = 0;
                foreach ($quoteDetails as $detail) {
                    $subtotal = $detail->quantity * $detail->unit_price;
                    $total += $subtotal;
                    
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
                    $html .= '<td><span class="badge bg-info">' . $detail->quantity . '</span>';
                    if ($detail->product && $detail->product->unit_measurement && !is_array($detail->product->unit_measurement)) {
                        $html .= '<br><small class="text-muted">' . $detail->product->unit_measurement . '</small>';
                    }
                    $html .= '</td>';
                    $html .= '<td class="text-end"><strong>$' . number_format($detail->unit_price, 2) . '</strong></td>';
                    $html .= '<td class="text-end"><span class="badge bg-success">$' . number_format($subtotal, 2) . '</span></td>';
                    $html .= '<td><span class="badge bg-success">Cotizado</span></td>';
                    $html .= '</tr>';
                }
                
                $html .= '</tbody>';
                $html .= '<tfoot class="table-light">';
                $html .= '<tr>';
                $html .= '<th colspan="3" class="text-end">Total:</th>';
                $html .= '<th class="text-end"><span class="badge bg-primary fs-6">$' . number_format($total, 2) . '</span></th>';
                $html .= '<th></th>';
                $html .= '</tr>';
                $html .= '</tfoot>';
                $html .= '</table>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                
                return $html;
            });
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store()
    {
        // Verificar que solo el responsable de compras pueda crear cotizaciones
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
            abort(403, 'Solo el responsable de compras puede crear cotizaciones.');
        }
        
        $this->crud->hasAccessOrFail('create');

        // execute the FormRequest authorization and validation, if one is required
        $request = $this->crud->validateRequest();

        // register any Model Events defined on fields
        $this->crud->registerFieldEvents();

        // Obtener datos para guardar
        $dataToSave = $this->crud->getStrippedSaveRequest($request);
        
        // Si no se seleccionó una solicitud de compra, buscar una pendiente por defecto
        if (!isset($dataToSave['purchase_request_id']) || empty($dataToSave['purchase_request_id'])) {
            $pendingRequest = \App\Models\PurchaseRequest::where('status', 'Pendiente')->first();
            if ($pendingRequest) {
                $dataToSave['purchase_request_id'] = $pendingRequest->id;
            }
        }

        // Validar que la solicitud de compra no esté aprobada
        if (isset($dataToSave['purchase_request_id']) && !empty($dataToSave['purchase_request_id'])) {
            $purchaseRequest = \App\Models\PurchaseRequest::find($dataToSave['purchase_request_id']);
            if ($purchaseRequest && $purchaseRequest->status === 'Aprobada') {
                \Alert::error('No se pueden agregar cotizaciones a una solicitud de compra que ya está aprobada.')->flash();
                return redirect()->back()->withInput();
            }
        }

        // insert item in the db
        $item = $this->crud->create($dataToSave);
        $this->data['entry'] = $this->crud->entry = $item;

        // Procesar los items de cotización seleccionados
        $this->processSelectedQuoteItems($item, $request);

        // show a success message
        \Alert::success(trans('backpack::crud.insert_success'))->flash();

        // save the redirect choice for next time
        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($item->getKey());
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

        // Obtener la cotización actual
        $currentEntry = $this->crud->getCurrentEntry();
        
        // Validar que la solicitud de compra asociada no esté aprobada
        if ($currentEntry && $currentEntry->purchaseRequest) {
            if ($currentEntry->purchaseRequest->status === 'Aprobada') {
                \Alert::error('No se pueden editar cotizaciones de una solicitud de compra que ya está aprobada.')->flash();
                return redirect()->back();
            }
        }

        // Obtener datos para guardar
        $dataToSave = $this->crud->getStrippedSaveRequest($request);

        // Validar también si se está cambiando la solicitud de compra a una aprobada
        if (isset($dataToSave['purchase_request_id']) && !empty($dataToSave['purchase_request_id'])) {
            $purchaseRequest = \App\Models\PurchaseRequest::find($dataToSave['purchase_request_id']);
            if ($purchaseRequest && $purchaseRequest->status === 'Aprobada') {
                \Alert::error('No se puede asociar una cotización a una solicitud de compra que ya está aprobada.')->flash();
                return redirect()->back()->withInput();
            }
        }

        // update item in the db
        $item = $this->crud->update($this->crud->getCurrentEntry()->getKey(), $dataToSave);
        $this->data['entry'] = $this->crud->entry = $item;

        // Procesar los items de cotización (eliminar existentes y crear nuevos)
        $this->processSelectedQuoteItems($item, $request, true);

        // show a success message
        \Alert::success(trans('backpack::crud.update_success'))->flash();

        // save the redirect choice for next time
        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($item->getKey());
    }

    /**
     * Generate PDF for a market rate (cotización)
     */
    public function generatePdf($id)
    {
        $marketRate = \App\Models\MarketRate::with(['supplier', 'quoteDetails.product', 'purchaseRequest'])->findOrFail($id);
        
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('market-rate-pdf', compact('marketRate'));
        
        return $pdf->stream('cotizacion-' . str_pad($marketRate->id, 4, '0', STR_PAD_LEFT) . '.pdf');
    }

    /**
     * Generate HTML for quote items selection (similar to products in general requests)
     */
    private function getQuoteItemsSelectionHtml($existingItems = [])
    {
        $existingItemsJson = json_encode($existingItems);
        
        return '
        <div id="quote-items-container">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="product-select" class="form-label">Seleccionar Producto</label>
                    <select id="product-select" class="form-control">
                        <option value="">Seleccionar un producto...</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="item-quantity" class="form-label">Cantidad</label>
                    <input type="number" id="item-quantity" class="form-control" min="1" value="1">
                </div>
                <div class="col-md-2">
                    <label for="item-price" class="form-label">Precio Unitario</label>
                    <input type="number" id="item-price" class="form-control" min="0" step="0.01" value="0">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Subtotal</label>
                    <div id="subtotal-info" class="form-control-plaintext">
                        <span class="badge bg-secondary">$0.00</span>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="button" id="add-item-btn" class="btn btn-primary btn-block">
                        <i class="la la-plus"></i> Agregar
                    </button>
                </div>
            </div>
            <div id="selected-items-list"></div>
            <div class="row mt-3">
                <div class="col-12">
                    <div class="alert alert-info">
                        <strong>Total de la Cotización:</strong> <span id="total-amount-display">$0.00</span>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Cargar productos existentes
            fetchProducts();
            
            // Event listeners
            document.getElementById("add-item-btn").addEventListener("click", addQuoteItem);
            document.getElementById("product-select").addEventListener("change", updateProductInfo);
            document.getElementById("item-quantity").addEventListener("input", calculateSubtotal);
            document.getElementById("item-price").addEventListener("input", calculateSubtotal);
            
            // Cargar items existentes si hay
            const existingItems = ' . $existingItemsJson . ';
            if (existingItems && existingItems.length > 0) {
                existingItems.forEach(item => {
                    addItemToList(item.product_id, item.product_name, item.quantity, item.unit_price);
                });
            }
            
            function fetchProducts() {
                fetch("/admin/api/products")
                    .then(response => response.json())
                    .then(products => {
                        const select = document.getElementById("product-select");
                        select.innerHTML = "<option value=\"\">Seleccionar un producto...</option>";
                        
                        products.forEach(product => {
                            const option = document.createElement("option");
                            option.value = product.id;
                            option.textContent = product.name;
                            option.setAttribute("data-unit", product.unit_measurement || "unidad");
                            option.setAttribute("data-description", product.description || "");
                            select.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error("Error cargando productos:", error);
                    });
            }
            
            function updateProductInfo() {
                const select = document.getElementById("product-select");
                const selectedOption = select.options[select.selectedIndex];
                
                if (selectedOption.value) {
                    const unit = selectedOption.getAttribute("data-unit");
                    const description = selectedOption.getAttribute("data-description");
                    
                    // Aquí podrías mostrar información adicional del producto si es necesario
                    calculateSubtotal();
                }
            }
            
            function calculateSubtotal() {
                const quantity = parseFloat(document.getElementById("item-quantity").value) || 0;
                const price = parseFloat(document.getElementById("item-price").value) || 0;
                const subtotal = quantity * price;
                
                document.getElementById("subtotal-info").innerHTML = 
                    `<span class="badge bg-info">$${subtotal.toFixed(2)}</span>`;
            }
            
            function addQuoteItem() {
                const select = document.getElementById("product-select");
                const quantity = document.getElementById("item-quantity");
                const price = document.getElementById("item-price");
                
                if (!select.value) {
                    alert("Por favor seleccione un producto");
                    return;
                }
                
                if (!quantity.value || quantity.value < 1) {
                    alert("Por favor ingrese una cantidad válida");
                    return;
                }
                
                if (!price.value || price.value < 0) {
                    alert("Por favor ingrese un precio válido");
                    return;
                }
                
                const selectedOption = select.options[select.selectedIndex];
                const productId = select.value;
                const productName = selectedOption.textContent;
                const unit = selectedOption.getAttribute("data-unit");
                const description = selectedOption.getAttribute("data-description");
                
                addItemToList(productId, productName, quantity.value, price.value, unit, description);
                
                // Limpiar campos
                select.value = "";
                quantity.value = 1;
                price.value = 0;
                calculateSubtotal();
            }
            
            function addItemToList(productId, productName, quantity, unitPrice, unit = "unidad", description = "") {
                const container = document.getElementById("selected-items-list");
                const itemDiv = document.createElement("div");
                itemDiv.className = "selected-item-item border p-3 mb-2";
                itemDiv.setAttribute("data-product-id", productId);
                
                const subtotal = quantity * unitPrice;
                
                itemDiv.innerHTML = `
                    <div class="row">
                        <div class="col-md-3">
                            <strong>${productName}</strong>
                            ${description ? `<br><small class="text-muted">${description}</small>` : ""}
                            <br><small class="text-muted">Unidad: ${unit}</small>
                        </div>
                        <div class="col-md-2">
                            <label>Cantidad:</label>
                            <input type="number" class="form-control item-quantity" value="${quantity}" min="1">
                        </div>
                        <div class="col-md-2">
                            <label>Precio Unitario:</label>
                            <input type="number" class="form-control item-price" value="${unitPrice}" min="0" step="0.01">
                        </div>
                        <div class="col-md-2">
                            <label>Subtotal:</label>
                            <div class="form-control-plaintext">
                                <span class="badge bg-success item-subtotal">$${subtotal.toFixed(2)}</span>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-danger btn-sm remove-item">
                                <i class="la la-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
                
                container.appendChild(itemDiv);
                
                // Event listener para remover item
                itemDiv.querySelector(".remove-item").addEventListener("click", function() {
                    itemDiv.remove();
                    updateHiddenFields();
                    updateTotalAmount();
                });
                
                // Event listeners para actualizar cantidades y precios
                itemDiv.querySelector(".item-quantity").addEventListener("input", function() {
                    updateItemSubtotal(itemDiv);
                    updateHiddenFields();
                    updateTotalAmount();
                });
                
                itemDiv.querySelector(".item-price").addEventListener("input", function() {
                    updateItemSubtotal(itemDiv);
                    updateHiddenFields();
                    updateTotalAmount();
                });
                
                updateHiddenFields();
                updateTotalAmount();
            }
            
            function updateItemSubtotal(itemDiv) {
                const quantity = parseFloat(itemDiv.querySelector(".item-quantity").value) || 0;
                const price = parseFloat(itemDiv.querySelector(".item-price").value) || 0;
                const subtotal = quantity * price;
                
                itemDiv.querySelector(".item-subtotal").textContent = `$${subtotal.toFixed(2)}`;
            }
            
            function updateHiddenFields() {
                const items = [];
                const itemDivs = document.querySelectorAll(".selected-item-item");
                
                itemDivs.forEach(itemDiv => {
                    const productId = itemDiv.getAttribute("data-product-id");
                    const quantity = itemDiv.querySelector(".item-quantity").value;
                    const unitPrice = itemDiv.querySelector(".item-price").value;
                    const productName = itemDiv.querySelector("strong").textContent;
                    
                    items.push({
                        product_id: productId,
                        product_name: productName,
                        quantity: quantity,
                        unit_price: unitPrice
                    });
                });
                
                document.querySelector("input[name=\'selected_quote_items\']").value = JSON.stringify(items);
            }
            
            function updateTotalAmount() {
                let total = 0;
                const itemDivs = document.querySelectorAll(".selected-item-item");
                
                itemDivs.forEach(itemDiv => {
                    const quantity = parseFloat(itemDiv.querySelector(".item-quantity").value) || 0;
                    const price = parseFloat(itemDiv.querySelector(".item-price").value) || 0;
                    total += quantity * price;
                });
                
                document.getElementById("total-amount-display").textContent = `$${total.toFixed(2)}`;
                
                // Actualizar también el campo de monto total del formulario
                const totalAmountField = document.querySelector("input[name=\'total_amount\']");
                if (totalAmountField) {
                    totalAmountField.value = total.toFixed(2);
                }
            }
        });
        </script>';
    }

    /**
     * Process selected quote items and create quote details
     */
    private function processSelectedQuoteItems($marketRate, $request, $isUpdate = false)
    {
        // Si es una actualización, eliminar items existentes
        if ($isUpdate) {
            Log::info('Eliminando items existentes de cotización:', ['id' => $marketRate->id]);
            $marketRate->quoteDetails()->delete();
        }
        
        $selectedItems = $request->input('selected_quote_items');
        
        if (!$selectedItems) {
            Log::info('No hay items de cotización seleccionados');
            return;
        }
        
        $items = json_decode($selectedItems, true);
        Log::info('Items de cotización seleccionados:', $items);
        
        $totalAmount = 0;
        
        foreach ($items as $itemData) {
            $productId = $itemData['product_id'];
            $quantity = $itemData['quantity'];
            $unitPrice = $itemData['unit_price'];
            
            // Crear el detalle de la cotización
            $detail = \App\Models\QuoteDetail::create([
                'market_rate_id' => $marketRate->id,
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
            ]);
            
            $totalAmount += $quantity * $unitPrice;
            Log::info('Detalle de cotización creado:', ['id' => $detail->id, 'product_id' => $productId, 'quantity' => $quantity, 'unit_price' => $unitPrice]);
        }
        
        // Actualizar el monto total de la cotización
        $marketRate->update(['total_amount' => $totalAmount]);
        Log::info('Monto total actualizado:', ['market_rate_id' => $marketRate->id, 'total_amount' => $totalAmount]);
    }
}
