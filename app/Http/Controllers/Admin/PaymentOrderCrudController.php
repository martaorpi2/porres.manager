<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\PaymentOrderRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Class PaymentOrderCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class PaymentOrderCrudController extends CrudController
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
            abort(403, 'No tienes permiso para acceder a órdenes de pago.');
        }
        
        CRUD::setModel(\App\Models\PaymentOrder::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/payment-order');
        CRUD::setEntityNameStrings('orden de pago', 'ordenes de pago');
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
        }
        
        // Habilitar el botón show para ver detalles
        // CRUD::removeButton('show');
        
        CRUD::column('payment_number')->label('Número');
        CRUD::column('date')->label('Fecha');
        CRUD::column('total_amount')->label('Monto Total');
        CRUD::column('status')->label('Estado');
        CRUD::addColumn([
            'name' => 'purchase_order_id',
            'label' => 'Orden de Compra',
            'type' => 'select',
            'entity' => 'purchase_order',
            'attribute' => 'number',
            'model' => 'App\Models\PurchaseOrder',
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
        CRUD::addButton('line', 'pdf', 'view', 'crud::buttons.payment_order_pdf', 'end');

        // Filtro personalizado por número usando parámetros de URL
        if (request()->has('numero')) {
            $numero = request()->get('numero');
            if ($numero) {
                CRUD::addClause('where', 'payment_number', 'like', '%' . $numero . '%');
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

        // Filtro personalizado por orden de compra usando parámetros de URL
        if (request()->has('orden_compra')) {
            $ordenCompraId = request()->get('orden_compra');
            if ($ordenCompraId) {
                CRUD::addClause('where', 'purchase_order_id', $ordenCompraId);
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
        CRUD::setValidation(PaymentOrderRequest::class);
        
        // Obtener purchase_order_id de la URL si existe
        $purchaseOrderId = request()->get('purchase_order_id');
        $purchaseOrder = null;
        $defaultTotal = 0;
        
        if ($purchaseOrderId) {
            $purchaseOrder = \App\Models\PurchaseOrder::with('details')->find($purchaseOrderId);
            if ($purchaseOrder) {
                $defaultTotal = $purchaseOrder->total ?? 0;
            }
        }
        
        $ultimo = \App\Models\PaymentOrder::max('id');
        $nro = 'OP-'.date('Y').'-'.str_pad(($ultimo + 1), 3, '0', STR_PAD_LEFT);
        CRUD::addField([
            'name'  => 'payment_number',
            'label' => 'Número',
            'type'  => 'text',
            'default' => $nro, 
            'attributes' => [
                'readonly' => 'readonly', 
            ],
        ]);
        CRUD::field('date')->label('Fecha');
        CRUD::field('total_amount')->label('Monto Total')->default($defaultTotal);
        CRUD::addField([
            'name' => 'status',
            'label' => 'Estado',
            'type' => 'enum',
        ]);
        CRUD::addField([
            'name' => 'purchase_order_id',
            'label' => 'Orden de Compra',
            'type' => 'select',
            'entity' => 'purchase_order',
            'attribute' => 'number',
            'model' => 'App\Models\PurchaseOrder',
            'default' => $purchaseOrderId,
            'attributes' => $purchaseOrderId ? ['readonly' => 'readonly'] : [],
        ]);
        
        // Mostrar información de la orden de compra si viene desde ahí
        if ($purchaseOrder) {
            CRUD::addField([
                'name' => 'purchase_order_info',
                'label' => 'Información de la Orden de Compra',
                'type' => 'custom_html',
                'value' => '<div class="alert alert-info">
                    <strong>Orden de Compra:</strong> ' . e($purchaseOrder->number) . '<br>
                    <strong>Proveedor:</strong> ' . e($purchaseOrder->supplier_display_name) . '<br>
                    <strong>Total de la Orden:</strong> $' . number_format($purchaseOrder->total ?? 0, 2) . '
                </div>',
            ]);
        }
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
        CRUD::addField([
            'name' => 'payment_method',
            'label' => 'Forma de Pago',
            'type' => 'text',
            'attributes' => [
                'placeholder' => 'Ej: Transferencia, Cheque, Efectivo, etc.'
            ],
        ]);
        CRUD::addField([
            'name' => 'bank',
            'label' => 'Banco',
            'type' => 'text',
            'attributes' => [
                'placeholder' => 'Ej: Banco Nación, Banco Provincia, etc.'
            ],
        ]);
        CRUD::addField([
            'name' => 'payment_date',
            'label' => 'Fecha de Pago',
            'type' => 'date',
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
        CRUD::column('payment_number')->label('Número de Orden de Pago');
        CRUD::column('date')->label('Fecha');
        CRUD::column('total_amount')->label('Monto Total');
        CRUD::column('status')->label('Estado');
        
        CRUD::addColumn([
            'name' => 'purchase_order_id',
            'label' => 'Orden de Compra Relacionada',
            'type' => 'select',
            'entity' => 'purchase_order',
            'attribute' => 'number',
            'model' => 'App\Models\PurchaseOrder',
        ]);
        
        CRUD::addColumn([
            'name' => 'authorizing_user_id',
            'label' => 'Usuario Autorizador',
            'type' => 'select',
            'entity' => 'user',
            'attribute' => 'name',
            'model' => 'App\Models\User',
        ]);
        
        CRUD::column('payment_method')->label('Forma de Pago');
        CRUD::column('bank')->label('Banco');
        CRUD::addColumn([
            'name' => 'payment_date',
            'label' => 'Fecha de Pago',
            'type' => 'date',
        ]);

        // Mostrar información de la orden de compra relacionada
        CRUD::addColumn([
            'name' => 'purchase_order_info',
            'label' => 'Información de la Orden de Compra',
            'type' => 'closure',
            'function' => function($entry) {
                $purchaseOrder = $entry->purchase_order;
                if ($purchaseOrder) {
                    $html = '<div class="card border-primary">';
                    $html .= '<div class="card-header bg-primary text-white">';
                    $html .= '<h6 class="mb-0"><i class="la la-shopping-cart"></i> Orden de Compra Relacionada</h6>';
                    $html .= '</div>';
                    $html .= '<div class="card-body">';
                    $html .= '<div class="row">';
                    $html .= '<div class="col-md-6">';
                    $html .= '<p class="mb-1"><strong>Número:</strong> ' . e($purchaseOrder->number) . '</p>';
                    $html .= '<p class="mb-1"><strong>Proveedor:</strong> ' . e($purchaseOrder->supplier_display_name) . '</p>';
                    $html .= '</div>';
                    $html .= '<div class="col-md-6">';
                    $html .= '<p class="mb-1"><strong>Fecha:</strong> ' . $purchaseOrder->date->format('d/m/Y') . '</p>';
                    $html .= '<p class="mb-1"><strong>Estado:</strong> <span class="badge bg-info">' . e($purchaseOrder->status) . '</span></p>';
                    $html .= '</div>';
                    $html .= '</div>';
                    $html .= '<div class="row mt-2">';
                    $html .= '<div class="col-12">';
                    $html .= '<p class="mb-0"><strong>Total:</strong> <span class="h5 text-success">$' . number_format($purchaseOrder->total, 2) . '</span></p>';
                    $html .= '</div>';
                    $html .= '</div>';
                    $html .= '</div></div>';
                    return $html;
                }
                return '<div class="alert alert-warning">No hay orden de compra relacionada</div>';
            },
            'escaped' => false
        ]);

        // Mostrar detalles de pagos
        CRUD::addColumn([
            'name' => 'payment_details',
            'label' => 'Detalles de Pagos',
            'type' => 'closure',
            'function' => function($entry) {
                $details = \Illuminate\Support\Facades\DB::table('op_details')
                    ->where('payment_order_id', $entry->id)
                    ->get();
                
                if ($details->isEmpty()) {
                    return '<div class="alert alert-info">No hay detalles de pago</div>';
                }
                
                $html = '<div class="card border-success">';
                $html .= '<div class="card-header bg-success text-white">';
                $html .= '<h6 class="mb-0"><i class="la la-credit-card"></i> Detalles de Pagos</h6>';
                $html .= '</div>';
                $html .= '<div class="card-body p-0">';
                $html .= '<div class="table-responsive">';
                $html .= '<table class="table table-sm table-bordered mb-0">';
                $html .= '<thead class="table-light">';
                $html .= '<tr>';
                $html .= '<th style="width: 20%;">Concepto</th>';
                $html .= '<th style="width: 15%;">Monto</th>';
                $html .= '<th style="width: 25%;">Método de Pago</th>';
                $html .= '<th style="width: 20%;">Vencimiento</th>';
                $html .= '<th style="width: 20%;">Estado</th>';
                $html .= '</tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                
                foreach ($details as $detail) {
                    $html .= '<tr>';
                    $html .= '<td><span class="badge bg-secondary">' . ucfirst(e($detail->concept)) . '</span></td>';
                    $html .= '<td class="text-end"><strong>$' . number_format($detail->amount, 2) . '</strong></td>';
                    $html .= '<td>' . e($detail->method_payment) . '</td>';
                    $html .= '<td>' . ($detail->expiration_date ? date('d/m/Y', strtotime($detail->expiration_date)) : '<span class="text-muted">N/A</span>') . '</td>';
                    
                    if ($detail->actual_payment_date) {
                        $html .= '<td><span class="badge bg-success"><i class="la la-check"></i> Pagado</span><br><small class="text-muted">' . date('d/m/Y', strtotime($detail->actual_payment_date)) . '</small></td>';
                    } else {
                        $html .= '<td><span class="badge bg-warning"><i class="la la-clock"></i> Pendiente</span></td>';
                    }
                    
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
     * Generate PDF for a payment order
     */
    public function generatePdf($id)
    {
        $paymentOrder = \App\Models\PaymentOrder::with(['purchase_order.supplier', 'purchase_order.details.supplier'])->findOrFail($id);

        $pdf = Pdf::loadView('payment-order-pdf', compact('paymentOrder'));

        return $pdf->stream('orden-pago-' . $paymentOrder->payment_number . '.pdf');
    }
}
