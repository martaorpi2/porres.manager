<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\PurchaseOrderRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\User;
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
        // Bloquear acceso para role_responsable_area
        $user = backpack_user();
        if ($user && $user->hasRole('role_responsable_area')) {
            abort(403, 'No tienes permiso para acceder a órdenes de compra.');
        }
        
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
        
        // Ocultar botones de crear, editar y eliminar para role_admin_institucion, role_apoderado y role_representante_legal
        $user = backpack_user();
        if ($user && ($user->hasRole('role_admin_institucion', 'backpack') || $user->hasRole('role_apoderado', 'backpack') || $user->hasRole('role_representante_legal', 'backpack'))) {
            CRUD::removeButton('create');
            CRUD::removeButton('update');
            CRUD::removeButton('delete');
        } else {
            CRUD::removeButton('update');
            CRUD::removeButton('delete');
            CRUD::addButton('line', 'edit_purchase_order', 'view', 'crud::buttons.edit_purchase_order', 'beginning');
            CRUD::addButton('line', 'delete_purchase_order', 'view', 'crud::buttons.delete_purchase_order', 'end');
        }

        CRUD::addClause(function ($query) {
            $query->withCount([
                'paymentOrders as active_payment_orders_count' => function ($q) {
                    $q->where('status', '!=', 'Anulada');
                },
            ]);
        });
        
        // Habilitar el botón show para ver detalles
        // CRUD::removeButton('show');

        CRUD::column('number')->label('Numero');
        CRUD::column('date')->label('Fecha');
        CRUD::column('issue_date')->label('Fecha de Emisión');
        //CRUD::column('estimated_delivery_date')->label('Fecha Estimada de Entrega');
        CRUD::column('status')->label('Estado');
        CRUD::addColumn([
            'name' => 'supplier_display_name',
            'label' => 'Proveedor(es)',
            'type' => 'closure',
            'function' => function ($entry) {
                return e($entry->supplier_display_name);
            },
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

        // Desde dashboard (compras): OC con recepción conforme y sin orden de pago
        $hasPendienteOpTrasConforme = request()->query('pendiente_op_tras_conforme') == '1';
        $isPersistentRestore = request()->query('persistent-table') == 'true';
        if ($hasPendienteOpTrasConforme && ! $isPersistentRestore) {
            CRUD::addClause(function ($query) {
                $query->whereHas('receptions', function ($q) {
                    $q->where('according', 'Si');
                })->whereDoesntHave('paymentOrders');
            });
        }

        // Filtro personalizado por proveedor (orden o detalles)
        if (request()->has('proveedor')) {
            $proveedorId = request()->get('proveedor');
            if ($proveedorId) {
                CRUD::addClause('where', function ($query) use ($proveedorId) {
                    $query->where('supplier_id', $proveedorId)
                        ->orWhereHas('details', function ($q) use ($proveedorId) {
                            $q->where('supplier_id', $proveedorId);
                        });
                });
            }
        }

        CRUD::addClause('with', ['details.supplier']);
        
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
        $letter = 'X';
        $purchaseRequestId = request()->get('purchase_request_id');
        if ($purchaseRequestId) {
            $pr = PurchaseRequest::with('responsibilityArea')->find($purchaseRequestId);
            if ($pr?->responsibilityArea) {
                $letter = $pr->responsibilityArea->purchaseOrderLetter();
            }
        }
        $year = (int) date('Y');
        $corr = PurchaseOrder::nextCorrelativeForAreaAndYear($letter, $year);
        $nro = PurchaseOrder::formatPurchaseOrderNumber($letter, $year, $corr, 1);
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
            'name' => 'payment_conditions',
            'label' => 'Condiciones de Pago',
            'type' => 'text',
            'attributes' => [
                'placeholder' => 'Ej: 30 días fecha factura, Contado, etc.'
            ],
        ]);
        CRUD::addField([
            'name' => 'status',
            'label' => 'Estado',
            'type' => 'enum',
        ]);
        CRUD::addField([
            'name' => 'supplier_id',
            'label' => 'Proveedor (por defecto para los ítems si se replican desde solicitud)',
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
        $entry = $this->crud->getCurrentEntry();
        if ($entry && $entry->hasBlockingPaymentOrder()) {
            abort(403, 'No se puede editar una orden de compra que ya tiene una orden de pago generada.');
        }

        $this->setupCreateOperation();
        CRUD::addField([
            'name' => 'details_suppliers_edit',
            'label' => 'Proveedor por línea',
            'type' => 'view',
            'view' => 'vendor.backpack.crud.fields.edit_oc_details_suppliers',
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
        CRUD::column('number')->label('Número');
        CRUD::column('date')->label('Fecha');
        CRUD::column('status')->label('Estado');
        
        CRUD::addColumn([
            'name' => 'supplier_display_name',
            'label' => 'Proveedor(es)',
            'type' => 'closure',
            'function' => function ($entry) {
                return e($entry->supplier_display_name);
            },
        ]);
        
        CRUD::addColumn([
            'name' => 'authorizing_user_id',
            'label' => 'Usuario Autorizador',
            'type' => 'select',
            'entity' => 'user',
            'attribute' => 'name',
            'model' => 'App\Models\User',
        ]);

        // Mostrar la solicitud de compra asociada
        CRUD::addColumn([
            'name' => 'purchase_request_id',
            'label' => 'Solicitud de Compra',
            'type' => 'closure',
            'function' => function($entry) {
                if ($entry->purchaseRequest) {
                    $statusBadge = match($entry->purchaseRequest->status) {
                        'Pendiente' => 'bg-warning',
                        'Aprobada' => 'bg-success',
                        'Rechazada' => 'bg-danger',
                        'En Proceso' => 'bg-info',
                        'Completada' => 'bg-primary',
                        default => 'bg-secondary'
                    };
                    return '<a href="' . backpack_url('purchase-request/' . $entry->purchaseRequest->id . '/show') . '" class="text-primary">' . 
                           e($entry->purchaseRequest->request_number ?? 'N/A') . 
                           '</a><br><small><span class="badge ' . $statusBadge . '">' . 
                           e($entry->purchaseRequest->status ?? 'Sin estado') . 
                           '</span></small>';
                }
                return '<span class="text-muted">Sin solicitud asociada</span>';
            },
            'escaped' => false
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

        // Mostrar detalles de la orden de compra (con proveedor por línea)
        CRUD::addColumn([
            'name' => 'details',
            'label' => 'Detalles de la Orden',
            'type' => 'closure',
            'function' => function($entry) {
                $details = $entry->details()->with(['input', 'supplier'])->get();
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
                $html .= '<th style="width: 30%;">Proveedor</th>';
                $html .= '<th style="width: 30%;">Cantidad</th>';
                $html .= '</tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                
                foreach ($details as $detail) {
                    $supplierName = $detail->supplier ? e($detail->supplier->company_name) : '<span class="text-muted">—</span>';
                    $html .= '<tr>';
                    $html .= '<td><strong>' . e($detail->input->name) . '</strong><br><small class="text-muted">' . e($detail->input->description ?? 'Sin descripción') . '</small></td>';
                    $html .= '<td>' . $supplierName . '</td>';
                    $html .= '<td><span class="badge bg-info">' . $detail->quantity . '</span> <small class="text-muted">' . e($detail->input->unit) . '</small></td>';
                    $html .= '</tr>';
                }
                
                $html .= '</tbody>';
                $html .= '<tfoot class="table-light"><tr><td colspan="3" class="text-end"><strong>Monto total de la orden</strong></td></tr>';
                $html .= '<tr><td colspan="3" class="text-end"><span class="badge bg-success fs-6">$' . number_format($entry->total, 2) . '</span></td></tr></tfoot>';
                $html .= '</table>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                
                return $html;
            },
            'escaped' => false
        ]);
        
        // Agregar botón de PDF como columna personalizada
        CRUD::addColumn([
            'name' => 'pdf_button',
            'label' => 'Acciones',
            'type' => 'closure',
            'function' => function($entry) {
                return '<a href="' . route('purchase-order.pdf', $entry->id) . '" class="btn btn-sm btn-info" data-toggle="tooltip" title="Descargar PDF de Orden de Compra">
                    <i class="la la-file-pdf"></i> <span>PDF</span>
                </a>';
            },
            'escaped' => false
        ]);
        
        // Agregar botón de PDF en la vista previa (también en top)
        CRUD::addButton('top', 'pdf', 'view', 'crud::buttons.purchase_order_pdf', 'end');

        $entryShow = $this->crud->getCurrentEntry();
        if ($entryShow && $entryShow->hasBlockingPaymentOrder()) {
            CRUD::removeButton('update');
            CRUD::removeButton('delete');
        }
        
        // Botón Crear Orden de Pago: solo responsable de compras, cuando hay recepción conforme (3 conformidades + ARCA + comprobante) y sin OP aún
        CRUD::addColumn([
            'name' => 'create_payment_order',
            'label' => 'Acciones',
            'type' => 'closure',
            'function' => function($entry) {
                $user = backpack_user();
                if ($user && $user->hasRole('role_responsable_area', 'backpack')) {
                    return '';
                }
                if (! $user instanceof User || ! $user->hasResponsableComprasRole()) {
                    return '';
                }
                $entry->load(['purchaseRequest', 'receptions', 'paymentOrders']);
                if ($entry->paymentOrders->isNotEmpty()) {
                    return '';
                }
                $hasConformeReception = $entry->receptions->contains(fn (\App\Models\Reception $r) => $r->isAccordingComplete());
                if (! $hasConformeReception) {
                    return '<div class="mt-3"><span class="text-muted"><i class="la la-info-circle"></i> La orden de pago la genera el responsable de compras cuando la recepción esté conforme (tres conformidades en Sí, corroboración ARCA y comprobante válido).</span></div>';
                }
                $html = '<div class="mt-3">';
                $html .= '<a href="' . backpack_url('payment-order/create?purchase_order_id=' . $entry->id) . '" class="btn btn-success">';
                $html .= '<i class="la la-money-bill-wave"></i> Crear Orden de Pago';
                $html .= '</a>';
                $html .= '</div>';

                return $html;
            },
            'escaped' => false
        ]);
        
        // Mostrar órdenes de pago asociadas
        CRUD::addColumn([
            'name' => 'payment_orders_table',
            'label' => 'Órdenes de Pago Asociadas',
            'type' => 'custom_html',
            'value' => function($entry) {
                $entry->load('paymentOrders');
                $paymentOrders = $entry->paymentOrders;
                
                if ($paymentOrders->isEmpty()) {
                    return '<div class="alert alert-info">No hay órdenes de pago asociadas a esta orden de compra.</div>';
                }
                
                $html = '<div class="table-responsive">';
                $html .= '<table class="table table-striped table-bordered">';
                $html .= '<thead class="thead-dark">';
                $html .= '<tr>';
                $html .= '<th>Número</th>';
                $html .= '<th>Fecha</th>';
                $html .= '<th>Monto</th>';
                $html .= '<th>Estado</th>';
                $html .= '<th>Acciones</th>';
                $html .= '</tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                
                $sumPaymentOrders = 0;
                foreach ($paymentOrders as $paymentOrder) {
                    $sumPaymentOrders += $paymentOrder->total_amount ?? 0;
                    $statusBadge = match ($paymentOrder->status) {
                        'Pendiente' => 'bg-warning text-dark',
                        'Aprobada' => 'bg-info text-white',
                        'Ejecutada' => 'bg-success',
                        'Anulada' => 'bg-secondary',
                        'Rechazada' => 'bg-danger',
                        default => 'bg-secondary'
                    };

                    $html .= '<tr>';
                    $html .= '<td><strong>' . e($paymentOrder->payment_number ?? 'N/A') . '</strong></td>';
                    $html .= '<td>' . ($paymentOrder->date ? $paymentOrder->date->format('d/m/Y') : 'N/A') . '</td>';
                    $html .= '<td><strong>$' . number_format($paymentOrder->total_amount ?? 0, 2) . '</strong></td>';
                    $html .= '<td><span class="badge ' . $statusBadge . '">' . e($paymentOrder->status ?? 'N/A') . '</span></td>';
                    $html .= '<td>';
                    $html .= '<a href="' . backpack_url('payment-order/' . $paymentOrder->id . '/show') . '" class="btn btn-sm btn-info">';
                    $html .= '<i class="la la-eye"></i> Ver';
                    $html .= '</a>';
                    $html .= '</td>';
                    $html .= '</tr>';
                }
                
                $html .= '</tbody>';
                $html .= '<tfoot class="table-light">';
                $html .= '<tr>';
                $html .= '<th colspan="2" class="text-end">Suma imputada en órdenes de pago:</th>';
                $html .= '<th><strong>$' . number_format($sumPaymentOrders, 2) . '</strong></th>';
                $html .= '<th colspan="2">';
                $remaining = (float) $entry->total - $sumPaymentOrders;
                if ($remaining > 0.009) {
                    $html .= '<span class="badge bg-warning text-dark" title="La OC aún no alcanza su monto total con las OP generadas.">Saldo sin OP: $' . number_format($remaining, 2) . '</span>';
                } else {
                    $html .= '<span class="badge bg-success" title="La suma de las OP cubre el total de la orden de compra; no indica que el pago ya esté ejecutado.">Monto OC cubierto por OPs</span>';
                }
                $html .= '</th>';
                $html .= '</tr>';
                $html .= '</tfoot>';
                $html .= '</table>';
                $html .= '<p class="small text-muted mb-0 mt-2"><i class="la la-info-circle"></i> La columna <strong>Estado</strong> es el trámite de cada orden de pago (pendiente de aprobación, aprobada o ejecutada). La fila de totales compara montos de la OC frente a la suma de las OP; no reemplaza el estado de la fila.</p>';
                $html .= '</div>';

                return $html;
            }
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

        $data = $this->crud->getStrippedSaveRequest($request);
        $area = null;
        if (!empty($data['purchase_request_id'])) {
            $area = PurchaseRequest::find($data['purchase_request_id'])?->responsibilityArea;
        }
        $data['number'] = PurchaseOrder::allocateNextFormattedNumber($area, 1);

        // Insert the entry
        $item = $this->crud->create($data);
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
     * Update the specified resource in the database.
     * Incluye guardado de proveedor por línea (detail_supplier).
     */
    public function update()
    {
        $this->crud->hasAccessOrFail('update');

        $current = PurchaseOrder::query()->find($this->crud->getCurrentEntryId());
        if ($current && $current->hasBlockingPaymentOrder()) {
            \Alert::error('No se puede editar una orden de compra que ya tiene una orden de pago generada.')->flash();

            return redirect()->back();
        }

        $request = $this->crud->validateRequest();
        $this->crud->registerFieldEvents();
        $id = $request->get($this->crud->model->getKeyName());
        $item = $this->crud->update($id, $this->crud->getStrippedSaveRequest($request));
        $this->data['entry'] = $this->crud->entry = $item;

        foreach ($request->input('detail_supplier', []) as $detailId => $supplierId) {
            PurchaseOrderDetail::where('id', $detailId)->where('purchase_order_id', $id)->update(['supplier_id' => $supplierId]);
        }

        \Alert::success(trans('backpack::crud.update_success'))->flash();
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

                // Crear el detalle en la orden de compra (con proveedor: orden o por defecto)
                $orderDetail = PurchaseOrderDetail::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'supplier_id' => $purchaseOrder->supplier_id,
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

    protected function setupDeleteOperation()
    {
        $entry = $this->crud->getCurrentEntry();
        if ($entry && $entry->hasBlockingPaymentOrder()) {
            abort(403, 'No se puede eliminar una orden de compra que ya tiene una orden de pago generada.');
        }
    }

    public function destroy($id)
    {
        $this->crud->hasAccessOrFail('delete');

        $entry = PurchaseOrder::find($id);
        if ($entry && $entry->hasBlockingPaymentOrder()) {
            $message = 'No se puede eliminar una orden de compra que ya tiene una orden de pago generada.';
            if (request()->ajax()) {
                return response()->json(['error' => [$message]]);
            }
            \Alert::error($message)->flash();

            return redirect()->back();
        }

        return $this->crud->delete($id);
    }

    /**
     * Generate PDF for a purchase order
     */
    public function generatePdf($id)
    {
        $purchaseOrder = \App\Models\PurchaseOrder::with([
            'supplier',
            'details.input',
            'details.supplier',
            'purchaseRequest.selectedMarketRate',
            'purchaseRequest.marketRates',
        ])->findOrFail($id);

        $pdf = Pdf::loadView('purchase-order-pdf', compact('purchaseOrder'));
        
        return $pdf->stream('orden-compra-' . $purchaseOrder->number . '.pdf');
    }
}
