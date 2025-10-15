<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\PurchaseOrderRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

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
        // Habilitar el botón show para ver detalles
        // CRUD::removeButton('show');

        CRUD::column('number')->label('Numero');
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
            'label' => 'Autoriza',
            'type' => 'select',
            'entity' => 'user',
            'attribute' => 'name',
            'model' => 'App\Models\User',
        ]);

        // Add PDF button
        CRUD::addButton('line', 'pdf', 'view', 'crud::buttons.purchase_order_pdf', 'end');
        
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
     * Generate PDF for a purchase order
     */
    public function generatePdf($id)
    {
        $purchaseOrder = \App\Models\PurchaseOrder::with(['supplier', 'details.input'])->findOrFail($id);
        
        $pdf = Pdf::loadView('purchase-order-pdf', compact('purchaseOrder'));
        
        return $pdf->stream('orden-compra-' . $purchaseOrder->number . '.pdf');
    }
}
