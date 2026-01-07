<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ApplicationRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class ApplicationCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ApplicationCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Application::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/application');
        CRUD::setEntityNameStrings('solicitud', 'solicitudes');
    }

    /**
     * Define what happens when the List operation is loaded.
     * 
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        //CRUD::setFromDb(); // set columns from db columns.
        CRUD::enableResponsiveTable();
        
        // Ocultar botones de editar y eliminar para role_admin_institucion
        $user = backpack_user();
        if ($user && $user->hasRole('role_admin_institucion', 'backpack')) {
            CRUD::removeButton('update');
            CRUD::removeButton('delete');
        }
        
        CRUD::addColumn([
            'name' => 'user_id',
            'label' => 'Solicitante',
            'type' => 'select',
            'entity' => 'user',
            'attribute' => 'name',
            'model' => 'App\Models\User',
        ]);
        
        CRUD::column('status')->label('Estado');
        CRUD::column('created_at')->label('Fecha de Solicitud');
        
        /**
         * Columns can be defined using the fluent syntax:
         * - CRUD::column('price')->type('number');
         */
    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(ApplicationRequest::class);
        
        // Campo oculto para el usuario actual (se llena automáticamente)
        CRUD::addField([
            'name' => 'user_id',
            'type' => 'hidden',
            'value' => auth()->id(),
        ]);
        
        // Campo oculto para el estado (por defecto Pendiente)
        CRUD::addField([
            'name' => 'status',
            'type' => 'hidden',
            'value' => 'Pendiente',
        ]);

        // Sección de productos solicitados
        CRUD::addField([
            'name' => 'productos_solicitados',
            'label' => 'Productos Solicitados',
            'type' => 'custom_html',
            'value' => '<div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="la la-shopping-cart"></i> Seleccionar Productos</h5>
                </div>
                <div class="card-body">
                    <div id="productos-container">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Producto</label>
                                <select class="form-control producto-select" data-index="0">
                                    <option value="">Seleccionar producto...</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cantidad</label>
                                <input type="number" class="form-control cantidad-input" data-index="0" min="1" placeholder="Cantidad">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Stock Disponible</label>
                                <div class="stock-info" data-index="0">
                                    <span class="badge bg-secondary">Seleccione un producto</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-success btn-sm" id="agregar-producto">
                        <i class="la la-plus"></i> Agregar Producto
                    </button>
                </div>
            </div>',
        ]);

        // Campo oculto para almacenar los detalles de productos
        CRUD::addField([
            'name' => 'request_details_json',
            'type' => 'hidden',
        ]);

        // JavaScript para manejar la selección de productos
        CRUD::addField([
            'name' => 'javascript',
            'type' => 'custom_html',
            'value' => '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    console.log("Script de productos cargado");
                    let contador = 0;
                    
                    // Cargar productos disponibles
                    cargarProductos();
                    
                    // Agregar producto
                    document.getElementById("agregar-producto").addEventListener("click", function() {
                        contador++;
                        agregarFilaProducto(contador);
                    });
                    
                    // Eliminar producto
                    document.addEventListener("click", function(e) {
                        if (e.target.classList.contains("eliminar-producto") || e.target.closest(".eliminar-producto")) {
                            e.target.closest(".row").remove();
                            actualizarDetalles();
                        }
                    });
                    
                    // Cambio de producto
                    document.addEventListener("change", function(e) {
                        if (e.target.classList.contains("producto-select")) {
                            let index = e.target.getAttribute("data-index");
                            let productId = e.target.value;
                            actualizarStock(index, productId);
                        }
                    });
                    
                    // Cambio de cantidad
                    document.addEventListener("input", function(e) {
                        if (e.target.classList.contains("cantidad-input")) {
                            actualizarDetalles();
                        }
                    });
                    
                    // Actualizar detalles antes de enviar el formulario
                    document.addEventListener("submit", function(e) {
                        console.log("Formulario enviándose, actualizando detalles...");
                        actualizarDetalles();
                    });
                    
                    function cargarProductos() {
                        console.log("Cargando productos...");
                        fetch("/admin/api/productos")
                            .then(response => {
                                console.log("Respuesta recibida:", response.status);
                                return response.json();
                            })
                            .then(data => {
                                console.log("Productos cargados:", data);
                                let selects = document.querySelectorAll(".producto-select");
                                selects.forEach(function(select) {
                                    select.innerHTML = "<option value=\"\">Seleccionar producto...</option>";
                                    if (data && data.length > 0) {
                                        data.forEach(function(producto) {
                                            let option = document.createElement("option");
                                            option.value = producto.id;
                                            option.setAttribute("data-stock", producto.stock_total);
                                            option.textContent = producto.name + " (" + producto.category_name + ")";
                                            select.appendChild(option);
                                        });
                                    } else {
                                        let option = document.createElement("option");
                                        option.value = "";
                                        option.textContent = "No hay productos disponibles";
                                        select.appendChild(option);
                                    }
                                });
                            })
                            .catch(error => {
                                console.error("Error cargando productos:", error);
                                let selects = document.querySelectorAll(".producto-select");
                                selects.forEach(function(select) {
                                    select.innerHTML = "<option value=\"\">Error cargando productos</option>";
                                });
                            });
                    }
                    
                    function agregarFilaProducto(index) {
                        let container = document.getElementById("productos-container");
                        let html = `
                            <div class="row mb-3 producto-fila" data-index="${index}">
                                <div class="col-md-6">
                                    <select class="form-control producto-select" data-index="${index}">
                                        <option value="">Seleccionar producto...</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" class="form-control cantidad-input" data-index="${index}" min="1" placeholder="Cantidad">
                                </div>
                                <div class="col-md-3">
                                    <div class="stock-info" data-index="${index}">
                                        <span class="badge bg-secondary">Seleccione un producto</span>
                                    </div>
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-danger btn-sm eliminar-producto">
                                        <i class="la la-trash"></i>
                                    </button>
                                </div>
                            </div>`;
                        container.insertAdjacentHTML("beforeend", html);
                        
                        // Cargar productos en el nuevo select
                        fetch("/admin/api/productos")
                            .then(response => response.json())
                            .then(data => {
                                let select = document.querySelector(`.producto-select[data-index="${index}"]`);
                                select.innerHTML = "<option value=\"\">Seleccionar producto...</option>";
                                if (data && data.length > 0) {
                                    data.forEach(function(producto) {
                                        let option = document.createElement("option");
                                        option.value = producto.id;
                                        option.setAttribute("data-stock", producto.stock_total);
                                        option.textContent = producto.name + " (" + producto.category_name + ")";
                                        select.appendChild(option);
                                    });
                                }
                            })
                            .catch(error => {
                                console.error("Error cargando productos:", error);
                            });
                    }
                    
                    function actualizarStock(index, productId) {
                        let select = document.querySelector(`.producto-select[data-index="${index}"]`);
                        let stockInfo = document.querySelector(`.stock-info[data-index="${index}"]`);
                        let selectedOption = select.querySelector("option:checked");
                        let stock = selectedOption ? selectedOption.getAttribute("data-stock") || 0 : 0;
                        
                        if (productId) {
                            if (stock > 0) {
                                stockInfo.innerHTML = `<span class="badge bg-success">${stock} unidades</span>`;
                            } else {
                                stockInfo.innerHTML = `<span class="badge bg-warning">Sin stock</span>`;
                            }
                        } else {
                            stockInfo.innerHTML = `<span class="badge bg-secondary">Seleccione un producto</span>`;
                        }
                        
                        actualizarDetalles();
                    }
                    
                    function actualizarDetalles() {
                        let detalles = [];
                        let filas = document.querySelectorAll(".producto-fila");
                        
                        console.log("Actualizando detalles, filas encontradas:", filas.length);
                        
                        filas.forEach(function(fila, index) {
                            let productId = fila.querySelector(".producto-select").value;
                            let cantidad = fila.querySelector(".cantidad-input").value;
                            
                            console.log("Fila " + index + " - Producto ID:", productId, "Cantidad:", cantidad);
                            
                            if (productId && cantidad && productId !== "" && cantidad !== "") {
                                detalles.push({
                                    product_id: parseInt(productId),
                                    quantity: parseInt(cantidad)
                                });
                            }
                        });
                        
                        console.log("Detalles finales:", detalles);
                        
                        let hiddenInput = document.querySelector("input[name=\"request_details_json\"]");
                        if (hiddenInput) {
                            hiddenInput.value = JSON.stringify(detalles);
                            console.log("JSON guardado:", hiddenInput.value);
                        } else {
                            console.error("No se encontró el campo oculto request_details_json");
                        }
                    }
                });
            </script>',
        ]);
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
        
        // Obtener los detalles existentes para cargar en el formulario
        $entry = $this->crud->getCurrentEntry();
        $detallesExistentes = [];
        
        if ($entry) {
            $requestDetails = \App\Models\RequestDetail::where('application_id', $entry->id)
                ->with('product')
                ->get();
            
            foreach ($requestDetails as $detalle) {
                $detallesExistentes[] = [
                    'product_id' => $detalle->product_id,
                    'quantity' => $detalle->quantity,
                    'product_name' => $detalle->product->name,
                    'category_name' => $detalle->product->category->name ?? 'Sin categoría'
                ];
            }
        }
        
        // Actualizar el JavaScript para cargar los datos existentes
        CRUD::modifyField('javascript', [
            'value' => '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    console.log("Script de productos cargado (EDITAR)");
                    let contador = 0;
                    
                    // Datos existentes para cargar
                    let detallesExistentes = ' . json_encode($detallesExistentes) . ';
                    console.log("Detalles existentes:", detallesExistentes);
                    
                    // Cargar productos disponibles
                    cargarProductos();
                    
                    // Cargar datos existentes después de cargar productos
                    setTimeout(function() {
                        cargarDetallesExistentes();
                    }, 1000);
                    
                    // Agregar producto
                    document.getElementById("agregar-producto").addEventListener("click", function() {
                        contador++;
                        agregarFilaProducto(contador);
                    });
                    
                    // Eliminar producto
                    document.addEventListener("click", function(e) {
                        if (e.target.classList.contains("eliminar-producto") || e.target.closest(".eliminar-producto")) {
                            e.target.closest(".row").remove();
                            actualizarDetalles();
                        }
                    });
                    
                    // Cambio de producto
                    document.addEventListener("change", function(e) {
                        if (e.target.classList.contains("producto-select")) {
                            let index = e.target.getAttribute("data-index");
                            let productId = e.target.value;
                            actualizarStock(index, productId);
                        }
                    });
                    
                    // Cambio de cantidad
                    document.addEventListener("input", function(e) {
                        if (e.target.classList.contains("cantidad-input")) {
                            actualizarDetalles();
                        }
                    });
                    
                    // Actualizar detalles antes de enviar el formulario
                    document.addEventListener("submit", function(e) {
                        console.log("Formulario enviándose, actualizando detalles...");
                        actualizarDetalles();
                    });
                    
                    function cargarDetallesExistentes() {
                        console.log("Cargando detalles existentes...");
                        if (detallesExistentes && detallesExistentes.length > 0) {
                            // Limpiar el contenedor
                            document.getElementById("productos-container").innerHTML = "";
                            
                            detallesExistentes.forEach(function(detalle, index) {
                                agregarFilaProducto(index);
                                
                                // Esperar un poco para que se carguen los productos
                                setTimeout(function() {
                                    let select = document.querySelector(`.producto-select[data-index="${index}"]`);
                                    let cantidadInput = document.querySelector(`.cantidad-input[data-index="${index}"]`);
                                    
                                    if (select && cantidadInput) {
                                        select.value = detalle.product_id;
                                        cantidadInput.value = detalle.quantity;
                                        actualizarStock(index, detalle.product_id);
                                    }
                                }, 500);
                            });
                        }
                    }
                    
                    function cargarProductos() {
                        console.log("Cargando productos...");
                        fetch("/admin/api/productos")
                            .then(response => {
                                console.log("Respuesta recibida:", response.status);
                                return response.json();
                            })
                            .then(data => {
                                console.log("Productos cargados:", data);
                                let selects = document.querySelectorAll(".producto-select");
                                selects.forEach(function(select) {
                                    select.innerHTML = "<option value=\"\">Seleccionar producto...</option>";
                                    if (data && data.length > 0) {
                                        data.forEach(function(producto) {
                                            let option = document.createElement("option");
                                            option.value = producto.id;
                                            option.setAttribute("data-stock", producto.stock_total);
                                            option.textContent = producto.name + " (" + producto.category_name + ")";
                                            select.appendChild(option);
                                        });
                                    } else {
                                        let option = document.createElement("option");
                                        option.value = "";
                                        option.textContent = "No hay productos disponibles";
                                        select.appendChild(option);
                                    }
                                });
                            })
                            .catch(error => {
                                console.error("Error cargando productos:", error);
                                let selects = document.querySelectorAll(".producto-select");
                                selects.forEach(function(select) {
                                    select.innerHTML = "<option value=\"\">Error cargando productos</option>";
                                });
                            });
                    }
                    
                    function agregarFilaProducto(index) {
                        let container = document.getElementById("productos-container");
                        let html = `
                            <div class="row mb-3 producto-fila" data-index="${index}">
                                <div class="col-md-6">
                                    <select class="form-control producto-select" data-index="${index}">
                                        <option value="">Seleccionar producto...</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" class="form-control cantidad-input" data-index="${index}" min="1" placeholder="Cantidad">
                                </div>
                                <div class="col-md-3">
                                    <div class="stock-info" data-index="${index}">
                                        <span class="badge bg-secondary">Seleccione un producto</span>
                                    </div>
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-danger btn-sm eliminar-producto">
                                        <i class="la la-trash"></i>
                                    </button>
                                </div>
                            </div>`;
                        container.insertAdjacentHTML("beforeend", html);
                        
                        // Cargar productos en el nuevo select
                        fetch("/admin/api/productos")
                            .then(response => response.json())
                            .then(data => {
                                let select = document.querySelector(`.producto-select[data-index="${index}"]`);
                                select.innerHTML = "<option value=\"\">Seleccionar producto...</option>";
                                if (data && data.length > 0) {
                                    data.forEach(function(producto) {
                                        let option = document.createElement("option");
                                        option.value = producto.id;
                                        option.setAttribute("data-stock", producto.stock_total);
                                        option.textContent = producto.name + " (" + producto.category_name + ")";
                                        select.appendChild(option);
                                    });
                                }
                            })
                            .catch(error => {
                                console.error("Error cargando productos:", error);
                            });
                    }
                    
                    function actualizarStock(index, productId) {
                        let select = document.querySelector(`.producto-select[data-index="${index}"]`);
                        let stockInfo = document.querySelector(`.stock-info[data-index="${index}"]`);
                        let selectedOption = select.querySelector("option:checked");
                        let stock = selectedOption ? selectedOption.getAttribute("data-stock") || 0 : 0;
                        
                        if (productId) {
                            if (stock > 0) {
                                stockInfo.innerHTML = `<span class="badge bg-success">${stock} unidades</span>`;
                            } else {
                                stockInfo.innerHTML = `<span class="badge bg-warning">Sin stock</span>`;
                            }
                        } else {
                            stockInfo.innerHTML = `<span class="badge bg-secondary">Seleccione un producto</span>`;
                        }
                        
                        actualizarDetalles();
                    }
                    
                    function actualizarDetalles() {
                        let detalles = [];
                        let filas = document.querySelectorAll(".producto-fila");
                        
                        console.log("Actualizando detalles, filas encontradas:", filas.length);
                        
                        filas.forEach(function(fila, index) {
                            let productId = fila.querySelector(".producto-select").value;
                            let cantidad = fila.querySelector(".cantidad-input").value;
                            
                            console.log("Fila " + index + " - Producto ID:", productId, "Cantidad:", cantidad);
                            
                            if (productId && cantidad && productId !== "" && cantidad !== "") {
                                detalles.push({
                                    product_id: parseInt(productId),
                                    quantity: parseInt(cantidad)
                                });
                            }
                        });
                        
                        console.log("Detalles finales:", detalles);
                        
                        let hiddenInput = document.querySelector("input[name=\"request_details_json\"]");
                        if (hiddenInput) {
                            hiddenInput.value = JSON.stringify(detalles);
                            console.log("JSON guardado:", hiddenInput.value);
                        } else {
                            console.error("No se encontró el campo oculto request_details_json");
                        }
                    }
                });
            </script>',
        ]);
    }

    /**
     * Define what happens when the Show operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-show
     * @return void
     */
    protected function setupShowOperation()
    {
        // Configurar las columnas que se mostrarán en la vista de detalles
        CRUD::addColumn([
            'name' => 'user_id',
            'label' => 'Solicitante',
            'type' => 'select',
            'entity' => 'user',
            'attribute' => 'name',
            'model' => 'App\Models\User',
        ]);
        
        CRUD::column('status')->label('Estado');
        CRUD::column('created_at')->label('Fecha de Solicitud');
        CRUD::column('updated_at')->label('Última Actualización');

        // Mostrar detalles de productos solicitados
        CRUD::addColumn([
            'name' => 'request_details',
            'label' => 'Productos Solicitados',
            'type' => 'closure',
            'function' => function($entry) {
                $requestDetails = \App\Models\RequestDetail::where('application_id', $entry->id)
                    ->with('product')
                    ->get();
                
                if ($requestDetails->isEmpty()) {
                    return '<div class="alert alert-info">No hay productos solicitados</div>';
                }
                
                $html = '<div class="card border-primary">';
                $html .= '<div class="card-header bg-primary text-white">';
                $html .= '<h6 class="mb-0"><i class="la la-list"></i> Productos Solicitados</h6>';
                $html .= '</div>';
                $html .= '<div class="card-body p-0">';
                $html .= '<div class="table-responsive">';
                $html .= '<table class="table table-sm table-bordered mb-0">';
                $html .= '<thead class="table-light">';
                $html .= '<tr>';
                $html .= '<th style="width: 60%;">Producto</th>';
                $html .= '<th style="width: 20%;">Cantidad</th>';
                $html .= '<th style="width: 20%;">Categoría</th>';
                $html .= '</tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                
                foreach ($requestDetails as $detail) {
                    $html .= '<tr>';
                    $html .= '<td><strong>' . e($detail->product->name) . '</strong><br><small class="text-muted">' . e($detail->product->description ?? 'Sin descripción') . '</small></td>';
                    $html .= '<td><span class="badge bg-info">' . $detail->quantity . '</span> <small class="text-muted">' . e($detail->product->unit_measurement ?? 'unidad') . '</small></td>';
                    $html .= '<td><span class="badge bg-secondary">' . e($detail->product->category->name ?? 'Sin categoría') . '</span></td>';
                    $html .= '</tr>';
                }
                
                $html .= '</tbody>';
                $html .= '</table>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                
                return $html;
            },
            'escaped' => false
        ]);

        // Mostrar información adicional de la solicitud
        CRUD::addColumn([
            'name' => 'application_info',
            'label' => 'Información de la Solicitud',
            'type' => 'closure',
            'function' => function($entry) {
                $html = '<div class="card border-info">';
                $html .= '<div class="card-header bg-info text-white">';
                $html .= '<h6 class="mb-0"><i class="la la-info-circle"></i> Información Adicional</h6>';
                $html .= '</div>';
                $html .= '<div class="card-body">';
                $html .= '<div class="row">';
                $html .= '<div class="col-md-6">';
                $html .= '<p class="mb-2"><strong>ID de Solicitud:</strong> #' . $entry->id . '</p>';
                $html .= '<p class="mb-2"><strong>Estado Actual:</strong> <span class="badge bg-' . ($entry->status == 'Aprobada' ? 'success' : ($entry->status == 'Rechazada' ? 'danger' : 'warning')) . '">' . e($entry->status) . '</span></p>';
                $html .= '</div>';
                $html .= '<div class="col-md-6">';
                $html .= '<p class="mb-2"><strong>Fecha de Creación:</strong> ' . $entry->created_at->format('d/m/Y H:i') . '</p>';
                $html .= '<p class="mb-2"><strong>Última Actualización:</strong> ' . $entry->updated_at->format('d/m/Y H:i') . '</p>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '<div class="row mt-3">';
                $html .= '<div class="col-12">';
                $html .= '<p class="mb-0"><strong>Total de Productos Solicitados:</strong> <span class="badge bg-primary">' . \App\Models\RequestDetail::where('application_id', $entry->id)->count() . ' productos</span></p>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div></div>';
                
                return $html;
            },
            'escaped' => false
        ]);
    }

    /**
     * API endpoint para obtener productos con stock
     * Si se proporciona area_id, calcula el stock solo de la ubicación del área
     */
    public function getProductos()
    {
        $areaId = request()->get('area_id');
        $location = null;
        
        // Si se proporciona un área, obtener la ubicación correspondiente
        if ($areaId) {
            $area = \App\Models\ResponsibilityArea::find($areaId);
            if ($area) {
                // Mapeo entre nombres de áreas y nombres de ubicaciones
                $areaLocationMap = [
                    'Informática' => 'Informática',
                    'Mantenimiento' => 'Mantenimiento',
                    'Salud' => 'Insumos de Salud',
                    'Insumos Generales' => 'Insumos Generales',
                ];
                
                $areaName = $area->name;
                $locationName = $areaLocationMap[$areaName] ?? $areaName;
                $location = \App\Models\Location::where('name', $locationName)->first();
            }
        }
        
        try {
            $productos = \App\Models\Product::with(['category', 'stockLevels'])
                ->get()
                ->map(function($producto) use ($location) {
                    // Calcular stock según la ubicación si se proporcionó área
                    if ($location) {
                        $stockTotal = (int) \App\Models\StockLevel::where('product_id', $producto->id)
                            ->where('location_id', $location->id)
                            ->sum('quantity');
                    } else {
                        // Si no hay ubicación, sumar todas (comportamiento anterior)
                        $stockTotal = $producto->stockLevels->sum('quantity');
                    }
                    
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
                ->values(); // Asegurar que sea un array indexado

            return response()->json($productos);
        } catch (\Exception $e) {
            \Log::error('Error en getProductos: ' . $e->getMessage());
            return response()->json(['error' => 'Error al cargar productos'], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store()
    {
        $this->crud->hasAccessOrFail('create');

        // Execute the FormRequest authorization and validation, if one is required
        $request = $this->crud->validateRequest();

        try {
            // Insert item in the db
            $item = $this->crud->create($this->crud->getStrippedSaveRequest($request));

            // Procesar detalles de productos si existen
            $detallesCreados = 0;
            if ($request->has('request_details_json') && !empty($request->request_details_json)) {
                $detalles = json_decode($request->request_details_json, true);
                
                if (is_array($detalles) && count($detalles) > 0) {
                    foreach ($detalles as $detalle) {
                        if (isset($detalle['product_id']) && isset($detalle['quantity']) && 
                            is_numeric($detalle['product_id']) && is_numeric($detalle['quantity']) && 
                            $detalle['quantity'] > 0) {
                            
                            \App\Models\RequestDetail::create([
                                'application_id' => $item->id,
                                'product_id' => $detalle['product_id'],
                                'quantity' => $detalle['quantity'],
                            ]);
                            $detallesCreados++;
                        }
                    }
                }
            }

            $this->data['entry'] = $this->crud->entry = $item;

            // Show a success message with details
            if ($detallesCreados > 0) {
                \Alert::success('Solicitud creada exitosamente con ' . $detallesCreados . ' producto(s) solicitado(s).')->flash();
            } else {
                \Alert::warning('Solicitud creada pero sin productos seleccionados.')->flash();
            }

            // Save the redirect choice for next time
            $this->crud->setSaveAction();

            return $this->crud->performSaveAction($item->getKey());

        } catch (\Exception $e) {
            \Alert::error('Error al crear la solicitud: ' . $e->getMessage())->flash();
            return redirect()->back()->withInput();
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update()
    {
        $this->crud->hasAccessOrFail('update');

        // Execute the FormRequest authorization and validation, if one is required
        $request = $this->crud->validateRequest();

        try {
            // Update the item in the db
            $item = $this->crud->update($this->crud->getCurrentEntry()->getKey(), $this->crud->getStrippedSaveRequest($request));

            // Eliminar detalles existentes
            \App\Models\RequestDetail::where('application_id', $item->id)->delete();

            // Procesar nuevos detalles de productos si existen
            $detallesCreados = 0;
            if ($request->has('request_details_json') && !empty($request->request_details_json)) {
                $detalles = json_decode($request->request_details_json, true);
                
                if (is_array($detalles) && count($detalles) > 0) {
                    foreach ($detalles as $detalle) {
                        if (isset($detalle['product_id']) && isset($detalle['quantity']) && 
                            is_numeric($detalle['product_id']) && is_numeric($detalle['quantity']) && 
                            $detalle['quantity'] > 0) {
                            
                            \App\Models\RequestDetail::create([
                                'application_id' => $item->id,
                                'product_id' => $detalle['product_id'],
                                'quantity' => $detalle['quantity'],
                            ]);
                            $detallesCreados++;
                        }
                    }
                }
            }

            $this->data['entry'] = $this->crud->entry = $item;

            // Show a success message with details
            if ($detallesCreados > 0) {
                \Alert::success('Solicitud actualizada exitosamente con ' . $detallesCreados . ' producto(s) solicitado(s).')->flash();
            } else {
                \Alert::warning('Solicitud actualizada pero sin productos seleccionados.')->flash();
            }

            // Save the redirect choice for next time
            $this->crud->setSaveAction();

            return $this->crud->performSaveAction($item->getKey());

        } catch (\Exception $e) {
            \Alert::error('Error al actualizar la solicitud: ' . $e->getMessage())->flash();
            return redirect()->back()->withInput();
        }
    }
}
