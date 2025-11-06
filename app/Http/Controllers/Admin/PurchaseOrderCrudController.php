<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\PurchaseOrderRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestDetail;
use App\Models\Product;
use App\Models\Input;
use Illuminate\Support\Facades\Log;

/**
 * Class PurchaseOrderCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class PurchaseOrderCrudController extends CrudController
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
        CRUD::setModel(\App\Models\PurchaseOrder::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/purchase-order');
        CRUD::setEntityNameStrings('orden de compra', 'ordenes de compra');
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
        
        // Habilitar el botón show para ver detalles
        // CRUD::removeButton('show');

        CRUD::column('number')->label('Numero');
        CRUD::column('date')->label('Fecha');
        CRUD::column('issue_date')->label('Fecha de Emisión');
        //CRUD::column('estimated_delivery_date')->label('Fecha Estimada de Entrega');
        CRUD::column('status')->label('Estado');
        CRUD::addColumn([
            'name' => 'supplier_id',
            'label' => 'Proveedor',
            'type' => 'select',
            'entity' => 'supplier',
            'attribute' => 'company_name',
            'model' => 'App\Models\Supplier',
        ]);
        CRUD::addColumn([
            'name' => 'authorizing_user_id',
            'label' => 'Autoriza',
            'type' => 'select',
            'entity' => 'user',
            'attribute' => 'name',
            'model' => 'App\Models\User',
        ]);
        /*CRUD::addColumn([
            'name' => 'observations',
            'label' => 'Observaciones',
            'type' => 'text',
            'limit' => 50,
        ]);*/

        // Add PDF button
        CRUD::addButton('line', 'pdf', 'view', 'crud::buttons.purchase_order_pdf', 'end');

        // Filtro personalizado por número usando parámetros de URL
        if (request()->has('numero')) {
            $numero = request()->get('numero');
            if ($numero) {
                CRUD::addClause('where', 'number', 'like', '%' . $numero . '%');
            }
        }

        // Filtro personalizado por fecha usando parámetros de URL
        if (request()->has('fecha')) {
            $fecha = request()->get('fecha');
            if ($fecha) {
                CRUD::addClause('whereDate', 'date', $fecha);
            }
        }

        // Filtro personalizado por estado usando parámetros de URL
        if (request()->has('estado')) {
            $estado = request()->get('estado');
            if ($estado) {
                CRUD::addClause('where', 'status', $estado);
            }
        }

        // Filtro personalizado por proveedor usando parámetros de URL
        if (request()->has('proveedor')) {
            $proveedorId = request()->get('proveedor');
            if ($proveedorId) {
                CRUD::addClause('where', 'supplier_id', $proveedorId);
            }
        }
        
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
        CRUD::setValidation(PurchaseOrderRequest::class);
        $ultimo = \App\Models\PurchaseOrder::max('id');
        $nro = 'OC-'.date('Y').'-'.str_pad(($ultimo + 1), 3, '0', STR_PAD_LEFT);
        CRUD::addField([
            'name'  => 'number',
            'label' => 'Número',
            'type'  => 'text',
            'default' => $nro, 
            'attributes' => [
                'readonly' => 'readonly', 
            ],
        ]);
        CRUD::field('date')->label('Fecha');
        CRUD::field('issue_date')->label('Fecha de Emisión');
        CRUD::field('estimated_delivery_date')->label('Fecha Estimada de Entrega');
        CRUD::addField([
            'name' => 'status',
            'label' => 'Estado',
            'type' => 'enum',
        ]);
        CRUD::field('supplier_id')->label('Proveedor');
        CRUD::addField([
            'name' => 'supplier_id',
            'label' => 'Proveedor',
            'type' => 'select',
            'entity' => 'supplier',
            'attribute' => 'company_name',
            'model' => 'App\Models\Supplier',
        ]);
        CRUD::addField([
            'name' => 'authorizing_user_id',
            'label' => 'Autoriza',
            'type' => 'select',
            'entity' => 'user',
            'attribute' => 'name',
            'model' => 'App\Models\User',
        ]);
        CRUD::addField([
            'name' => 'observations',
            'label' => 'Observaciones',
            'type' => 'textarea',
            'attributes' => [
                'rows' => 3,
                'placeholder' => 'Ingrese observaciones adicionales...'
            ],
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
        CRUD::column('number')->label('Número');
        CRUD::column('date')->label('Fecha');
        CRUD::column('status')->label('Estado');
        
        CRUD::addColumn([
            'name' => 'supplier_id',
            'label' => 'Proveedor',
            'type' => 'select',
            'entity' => 'supplier',
            'attribute' => 'company_name',
            'model' => 'App\Models\Supplier',
        ]);
        
        CRUD::addColumn([
            'name' => 'authorizing_user_id',
            'label' => 'Usuario Autorizador',
            'type' => 'select',
            'entity' => 'user',
            'attribute' => 'name',
            'model' => 'App\Models\User',
        ]);

        // Agregar columna calculada para el total
        CRUD::addColumn([
            'name' => 'total',
            'label' => 'Total',
            'type' => 'closure',
            'function' => function($entry) {
                return '$' . number_format($entry->total, 2);
            }
        ]);

        // Mostrar detalles de la orden de compra
        CRUD::addColumn([
            'name' => 'details',
            'label' => 'Detalles de la Orden',
            'type' => 'closure',
            'function' => function($entry) {
                $details = $entry->details()->with('input')->get();
                if ($details->isEmpty()) {
                    return '<div class="alert alert-info">No hay detalles de productos</div>';
                }
                
                $html = '<div class="card border-primary">';
                $html .= '<div class="card-header bg-primary text-white">';
                $html .= '<h6 class="mb-0"><i class="la la-shopping-cart"></i> Productos de la Orden</h6>';
                $html .= '</div>';
                $html .= '<div class="card-body p-0">';
                $html .= '<div class="table-responsive">';
                $html .= '<table class="table table-sm table-bordered mb-0">';
                $html .= '<thead class="table-light">';
                $html .= '<tr>';
                $html .= '<th style="width: 40%;">Producto</th>';
                $html .= '<th style="width: 20%;">Cantidad</th>';
                $html .= '<th style="width: 20%;">Precio Unit.</th>';
                $html .= '<th style="width: 20%;">Subtotal</th>';
                $html .= '</tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                
                foreach ($details as $detail) {
                    $html .= '<tr>';
                    $html .= '<td><strong>' . e($detail->input->name) . '</strong><br><small class="text-muted">' . e($detail->input->description ?? 'Sin descripción') . '</small></td>';
                    $html .= '<td><span class="badge bg-info">' . $detail->quantity . '</span> <small class="text-muted">' . e($detail->input->unit) . '</small></td>';
                    $html .= '<td class="text-end"><strong>$' . number_format($detail->unit_price, 2) . '</strong></td>';
                    $html .= '<td class="text-end"><span class="badge bg-success">$' . number_format($detail->subtotal, 2) . '</span></td>';
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
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store()
    {
        $this->crud->hasAccessOrFail('create');

        // Execute the FormRequest authorization and validation
        $request = $this->crud->validateRequest();

        // Register any Model Events defined on fields
        $this->crud->registerFieldEvents();

        // Insert the entry
        $item = $this->crud->create($this->crud->getStrippedSaveRequest($request));
        $this->data['entry'] = $this->crud->entry = $item;

        // Si viene de una solicitud de compra, replicar automáticamente los productos
        if ($item->purchase_request_id) {
            $this->replicateProductsFromPurchaseRequest($item);
        }

        // Show a success message
        \Alert::success(trans('backpack::crud.insert_success'))->flash();

        // Save the redirect choice for next time
        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($item->getKey());
    }

    /**
     * Replicar productos desde la solicitud de compra a la orden de compra
     */
    protected function replicateProductsFromPurchaseRequest($purchaseOrder)
    {
        try {
            $purchaseRequest = PurchaseRequest::with('details.product')
                ->find($purchaseOrder->purchase_request_id);

            if (!$purchaseRequest) {
                Log::warning('Solicitud de compra no encontrada para replicar productos', [
                    'purchase_request_id' => $purchaseOrder->purchase_request_id
                ]);
                return;
            }

            // Verificar si ya hay detalles en la orden de compra
            $existingDetailsCount = PurchaseOrderDetail::where('purchase_order_id', $purchaseOrder->id)->count();
            
            if ($existingDetailsCount > 0) {
                Log::info('La orden de compra ya tiene productos. No se replicarán automáticamente.', [
                    'purchase_order_id' => $purchaseOrder->id,
                    'existing_details_count' => $existingDetailsCount
                ]);
                return;
            }

            $replicatedCount = 0;

            // Replicar cada detalle de la solicitud de compra
            foreach ($purchaseRequest->details as $requestDetail) {
                if (!$requestDetail->product) {
                    Log::warning('Producto no encontrado en detalle de solicitud de compra', [
                        'purchase_request_detail_id' => $requestDetail->id
                    ]);
                    continue;
                }

                // Buscar o crear el Input correspondiente al Product
                $input = $this->findOrCreateInputFromProduct($requestDetail->product);
                
                if (!$input) {
                    Log::warning('No se pudo obtener o crear input desde producto', [
                        'product_id' => $requestDetail->product_id,
                        'product_name' => $requestDetail->product->name
                    ]);
                    continue;
                }

                // Crear el detalle en la orden de compra
                $orderDetail = PurchaseOrderDetail::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'input_id' => $input->id,
                    'quantity' => $requestDetail->requested_quantity,
                    'unit_price' => $requestDetail->estimated_unit_price ?? 0,
                ]);

                $replicatedCount++;

                Log::info('Producto replicado desde solicitud de compra', [
                    'purchase_request_detail_id' => $requestDetail->id,
                    'purchase_order_detail_id' => $orderDetail->id,
                    'product_id' => $requestDetail->product_id,
                    'input_id' => $input->id,
                    'product_name' => $requestDetail->product->name ?? 'N/A'
                ]);
            }

            Log::info('Productos replicados exitosamente desde solicitud de compra', [
                'purchase_request_id' => $purchaseRequest->id,
                'purchase_order_id' => $purchaseOrder->id,
                'products_replicated' => $replicatedCount
            ]);

            \Alert::info($replicatedCount . ' producto(s) replicado(s) desde la solicitud de compra ' . $purchaseRequest->request_number)->flash();

        } catch (\Exception $e) {
            Log::error('Error al replicar productos desde solicitud de compra', [
                'purchase_order_id' => $purchaseOrder->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Find or create input from product
     *
     * @param Product $product
     * @return Input|null
     */
    protected function findOrCreateInputFromProduct(Product $product)
    {
        // Intentar encontrar un input con el mismo nombre
        $input = Input::where('name', $product->name)->first();
        
        if ($input) {
            return $input;
        }

        // Si no existe, crear uno nuevo
        try {
            $input = Input::create([
                'name' => $product->name,
                'description' => $product->description,
                'unit' => $product->unit_measurement ?? 'unidad',
                'price' => 0, // El precio se establecerá en el detalle de la orden
            ]);

            Log::info('Input creado desde Product', [
                'product_id' => $product->id,
                'input_id' => $input->id,
                'name' => $input->name
            ]);

            return $input;
        } catch (\Exception $e) {
            Log::error('Error al crear input desde Product', [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Generate PDF for a purchase order
     */
    public function generatePdf($id)
    {
        $purchaseOrder = \App\Models\PurchaseOrder::with(['supplier', 'details.input'])->findOrFail($id);
        
        $pdf = Pdf::loadView('purchase-order-pdf', compact('purchaseOrder'));
        
        return $pdf->stream('orden-compra-' . $purchaseOrder->number . '.pdf');
    }
}
