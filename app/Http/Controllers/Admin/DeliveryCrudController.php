<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\DeliveryRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class DeliveryCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class DeliveryCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Delivery::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/delivery');
        CRUD::setEntityNameStrings('entrega', 'entregas');
    }

    /**
     * Define what happens when the List operation is loaded.
     * 
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        // Cargar relaciones para evitar N+1 queries
        CRUD::addClause('with', ['reception', 'generalRequest', 'receivedBy', 'deliveredBy']);
        
        // Columna para número de recepción con prefijo REC-
        CRUD::addColumn([
            'name' => 'reception_id',
            'label' => 'Nro Recepción',
            'type' => 'closure',
            'function' => function($entry) {
                if ($entry->reception) {
                    return 'REC-' . $entry->reception->id;
                }
                return 'REC-' . $entry->reception_id;
            },
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('reception', function ($q) use ($searchTerm) {
                    $q->where('id', 'like', '%' . str_replace('REC-', '', $searchTerm) . '%');
                });
            },
        ]);
        
        // Columna para número de solicitud general
        CRUD::addColumn([
            'name' => 'general_request_id',
            'label' => 'Nro Solicitud',
            'type' => 'select',
            'entity' => 'generalRequest',
            'attribute' => 'number',
            'model' => 'App\Models\GeneralRequest',
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('generalRequest', function ($q) use ($searchTerm) {
                    $q->where('number', 'like', '%'.$searchTerm.'%');
                });
            },
        ]);
        
        CRUD::column('delivery_date')->label('Fecha');
        
        CRUD::addColumn([
            'name' => 'delivered_by',
            'label' => 'Entregado por',
            'type' => 'select',
            'entity' => 'deliveredBy',
            'attribute' => 'name',
            'model' => 'App\Models\User',
        ]);

        CRUD::addColumn([
            'name' => 'received_by',
            'label' => 'Recibido por',
            'type' => 'select',
            'entity' => 'receivedBy',
            'attribute' => 'name',
            'model' => 'App\Models\User',
        ]);
        
        CRUD::column('status')->label('Estado');
    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(DeliveryRequest::class);
        
        CRUD::addField([
            'name' => 'reception_id',
            'label' => 'Nro Recepción',
            'type' => 'select',
            'entity' => 'reception',
            'attribute' => 'number',
            'model' => 'App\Models\Reception',
            /*'options' => function ($query) {
                return $query->whereDoesntHave('receptions')->get();
            },
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('purchase_order', function ($q) use ($searchTerm) {
                    $q->where('number', 'like', '%'.$searchTerm.'%');
                });
            },*/
        ]);
        CRUD::addField([
            'name' => 'general_request_id',
            'label' => 'Nro Solicitud',
            'type' => 'select',
            'entity' => 'generalRequest',
            'attribute' => 'number',
            'model' => 'App\Models\GeneralRequest',
        ]);
        CRUD::field('delivery_date')->label('Fecha');
        
        // Campo oculto para asignar automáticamente el usuario logueado como entregado por
        $user = backpack_user();
        if ($user) {
            CRUD::addField([
                'name' => 'delivered_by',
                'type' => 'hidden',
                'value' => $user->id,
            ]);
        }
        
        CRUD::addField([
            'name' => 'received_by',
            'label' => 'Recibido por',
            'type' => 'select',
            'entity' => 'receivedBy',
            'attribute' => 'name',
            'model' => 'App\Models\User',
        ]);
        CRUD::addField([
            'name' => 'status',
            'label' => 'Estado',
            'type' => 'enum',
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

    protected function setupShowOperation()
    {
        CRUD::addColumn([
            'name' => 'reception_id',
            'label' => 'Nro Recepción',
            'type' => 'closure',
            'function' => function($entry) {
                if ($entry->reception) {
                    return 'REC-' . $entry->reception->id;
                }
                return 'REC-' . $entry->reception_id;
            },
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('reception', function ($q) use ($searchTerm) {
                    $q->where('id', 'like', '%' . str_replace('REC-', '', $searchTerm) . '%');
                });
            },
        ]);
        
        // Columna para número de solicitud general
        CRUD::addColumn([
            'name' => 'general_request_id',
            'label' => 'Nro Solicitud',
            'type' => 'select',
            'entity' => 'generalRequest',
            'attribute' => 'number',
            'model' => 'App\Models\GeneralRequest',
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('generalRequest', function ($q) use ($searchTerm) {
                    $q->where('number', 'like', '%'.$searchTerm.'%');
                });
            },
        ]);
        
        CRUD::column('delivery_date')->label('Fecha');
        
        CRUD::addColumn([
            'name' => 'delivered_by',
            'label' => 'Entregado por',
            'type' => 'select',
            'entity' => 'deliveredBy',
            'attribute' => 'name',
            'model' => 'App\Models\User',
        ]);
        
        CRUD::addColumn([
            'name' => 'received_by',
            'label' => 'Recibido por',
            'type' => 'select',
            'entity' => 'receivedBy',
            'attribute' => 'name',
            'model' => 'App\Models\User',
        ]);
        
        CRUD::column('status')->label('Estado');
    }
}
