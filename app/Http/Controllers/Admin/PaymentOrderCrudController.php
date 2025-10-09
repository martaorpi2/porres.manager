<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\PaymentOrderRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

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
        CRUD::removeButton('show');
        
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
        CRUD::field('total_amount')->label('Monto Total');
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
}
