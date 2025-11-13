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
        CRUD::addClause('with', ['reception', 'generalRequest', 'purchaseRequest', 'receivedBy', 'deliveredBy']);
        
        // Columna para mostrar la solicitud relacionada (General o Purchase)
        CRUD::addColumn([
            'name' => 'request_info',
            'label' => 'Solicitud',
            'type' => 'closure',
            'function' => function($entry) {
                if ($entry->generalRequest) {
                    return '<span class="badge bg-info">SG: ' . e($entry->generalRequest->number) . '</span>';
                } elseif ($entry->purchaseRequest) {
                    return '<span class="badge bg-primary">SC: ' . e($entry->purchaseRequest->request_number) . '</span>';
                }
                return '<span class="badge bg-secondary">Sin solicitud</span>';
            },
            'escaped' => false,
        ]);
        
        // Columna opcional para número de recepción (si existe)
        CRUD::addColumn([
            'name' => 'reception_id',
            'label' => 'Recepción',
            'type' => 'closure',
            'function' => function($entry) {
                if ($entry->reception) {
                    return '<span class="badge bg-success">REC-' . $entry->reception->id . '</span>';
                }
                return '<span class="badge bg-secondary">Sin recepción</span>';
            },
            'escaped' => false,
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
        
        // Campo informativo
        CRUD::addField([
            'name' => 'delivery_info',
            'label' => 'Información',
            'type' => 'custom_html',
            'value' => '<div class="alert alert-info">
                <i class="la la-info-circle"></i> <strong>Nota:</strong> Las entregas pueden realizarse desde stock disponible o desde una recepción. 
                Debe seleccionar una solicitud general o una solicitud de compra. La recepción es opcional.
            </div>',
        ]);
        
        // Campo para seleccionar tipo de solicitud
        CRUD::addField([
            'name' => 'request_type',
            'label' => 'Tipo de Solicitud',
            'type' => 'select_from_array',
            'options' => [
                'general' => 'Solicitud General',
                'purchase' => 'Solicitud de Compra',
            ],
            'default' => 'general',
            'allows_null' => false,
            'attributes' => [
                'id' => 'request_type_select',
            ],
        ]);
        
        // Campo para solicitud general (mostrar/ocultar según tipo)
        // Solo mostrar solicitudes que no tienen entregas asociadas
        CRUD::addField([
            'name' => 'general_request_id',
            'label' => 'Solicitud General',
            'type' => 'select',
            'model' => 'App\Models\GeneralRequest',
            'attribute' => 'number',
            'allows_null' => false,
            'options' => function ($query) {
                return $query->whereDoesntHave('deliveries')->get();
            },
            'attributes' => [
                'id' => 'general_request_select',
                'style' => 'display: block;',
            ],
        ]);
        
        // Campo para solicitud de compra (mostrar/ocultar según tipo)
        // Solo mostrar solicitudes que no tienen entregas asociadas
        CRUD::addField([
            'name' => 'purchase_request_id',
            'label' => 'Solicitud de Compra',
            'type' => 'select',
            'model' => 'App\Models\PurchaseRequest',
            'attribute' => 'request_number',
            'allows_null' => false,
            'options' => function ($query) {
                return $query->whereDoesntHave('deliveries')->get();
            },
            'attributes' => [
                'id' => 'purchase_request_select',
                'style' => 'display: none;',
            ],
        ]);
        
        // Campo opcional para recepción
        CRUD::addField([
            'name' => 'reception_id',
            'label' => 'Recepción (Opcional)',
            'type' => 'select',
            'entity' => 'reception',
            'attribute' => 'number',
            'model' => 'App\Models\Reception',
            'allows_null' => true,
            'hint' => 'Opcional: Solo si la entrega proviene de una recepción específica',
        ]);
        
        // Script para mostrar/ocultar campos según el tipo de solicitud
        CRUD::addField([
            'name' => 'request_type_script',
            'type' => 'custom_html',
            'value' => '
            <script>
            document.addEventListener("DOMContentLoaded", function() {
                const requestTypeSelect = document.getElementById("request_type_select");
                const generalRequestSelect = document.getElementById("general_request_select").closest(".form-group");
                const purchaseRequestSelect = document.getElementById("purchase_request_select").closest(".form-group");
                
                function toggleRequestFields() {
                    if (requestTypeSelect.value === "general") {
                        generalRequestSelect.style.display = "block";
                        purchaseRequestSelect.style.display = "none";
                        document.getElementById("purchase_request_select").value = "";
                    } else {
                        generalRequestSelect.style.display = "none";
                        purchaseRequestSelect.style.display = "block";
                        document.getElementById("general_request_select").value = "";
                    }
                }
                
                requestTypeSelect.addEventListener("change", toggleRequestFields);
                toggleRequestFields(); // Ejecutar al cargar
            });
            </script>
            ',
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
        
        // Establecer el valor del tipo de solicitud según la entrada actual
        $entryId = $this->crud->getCurrentEntryId();
        if ($entryId) {
            // Cargar el modelo explícitamente
            $entry = \App\Models\Delivery::find($entryId);
            if ($entry) {
                if ($entry->general_request_id) {
                    $generalRequestId = $entry->general_request_id;
                    CRUD::modifyField('request_type', ['default' => 'general']);
                    
                    // En edición, incluir la solicitud actual aunque tenga entregas
                    CRUD::modifyField('general_request_id', [
                        'options' => function ($query) use ($generalRequestId) {
                            return $query->whereDoesntHave('deliveries')
                                ->orWhere('id', $generalRequestId)
                                ->get();
                        },
                    ]);
                } elseif ($entry->purchase_request_id) {
                    $purchaseRequestId = $entry->purchase_request_id;
                    CRUD::modifyField('request_type', ['default' => 'purchase']);
                    
                    // En edición, incluir la solicitud actual aunque tenga entregas
                    CRUD::modifyField('purchase_request_id', [
                        'options' => function ($query) use ($purchaseRequestId) {
                            return $query->whereDoesntHave('deliveries')
                                ->orWhere('id', $purchaseRequestId)
                                ->get();
                        },
                    ]);
                }
            }
        }
    }
    
    /**
     * Store a newly created resource in storage.
     */
    public function store()
    {
        $this->crud->hasAccessOrFail('create');
        
        $request = $this->crud->validateRequest();
        $this->crud->registerFieldEvents();
        
        // Obtener datos y remover request_type (solo es para UI)
        $dataToSave = $this->crud->getStrippedSaveRequest($request);
        unset($dataToSave['request_type']);
        
        // Insert item in the db
        $item = $this->crud->create($dataToSave);
        $this->data['entry'] = $this->crud->entry = $item;
        
        \Alert::success(trans('backpack::crud.insert_success'))->flash();
        $this->crud->setSaveAction();
        
        // Asegurar que $item es un modelo antes de llamar getKey()
        $itemId = is_object($item) && method_exists($item, 'getKey') ? $item->getKey() : $item;
        return $this->crud->performSaveAction($itemId);
    }
    
    /**
     * Update the specified resource in storage.
     */
    public function update()
    {
        $this->crud->hasAccessOrFail('update');
        
        $request = $this->crud->validateRequest();
        $this->crud->registerFieldEvents();
        
        // Obtener datos y remover request_type (solo es para UI)
        $dataToSave = $this->crud->getStrippedSaveRequest($request);
        unset($dataToSave['request_type']);
        
        // Update item in the db
        $item = $this->crud->update($this->crud->getCurrentEntryId(), $dataToSave);
        $this->data['entry'] = $this->crud->entry = $item;
        
        \Alert::success(trans('backpack::crud.update_success'))->flash();
        $this->crud->setSaveAction();
        
        // Asegurar que $item es un modelo antes de llamar getKey()
        $itemId = is_object($item) && method_exists($item, 'getKey') ? $item->getKey() : $item;
        return $this->crud->performSaveAction($itemId);
    }

    protected function setupShowOperation()
    {
        // Cargar relaciones
        CRUD::addClause('with', ['reception', 'generalRequest', 'purchaseRequest', 'receivedBy', 'deliveredBy', 'details']);
        
        // Columna para mostrar la solicitud relacionada
        CRUD::addColumn([
            'name' => 'request_info',
            'label' => 'Solicitud',
            'type' => 'closure',
            'function' => function($entry) {
                $html = '';
                if ($entry->generalRequest) {
                    $html .= '<div class="mb-2">';
                    $html .= '<strong>Solicitud General:</strong> ';
                    $html .= '<a href="' . backpack_url('general-request/' . $entry->generalRequest->id . '/show') . '" class="badge bg-info">';
                    $html .= e($entry->generalRequest->number);
                    $html .= '</a>';
                    $html .= '</div>';
                }
                if ($entry->purchaseRequest) {
                    $html .= '<div class="mb-2">';
                    $html .= '<strong>Solicitud de Compra:</strong> ';
                    $html .= '<a href="' . backpack_url('purchase-request/' . $entry->purchaseRequest->id . '/show') . '" class="badge bg-primary">';
                    $html .= e($entry->purchaseRequest->request_number);
                    $html .= '</a>';
                    $html .= '</div>';
                }
                if (!$entry->generalRequest && !$entry->purchaseRequest) {
                    $html .= '<span class="badge bg-secondary">Sin solicitud asociada</span>';
                }
                return $html;
            },
            'escaped' => false,
        ]);
        
        // Columna opcional para recepción
        CRUD::addColumn([
            'name' => 'reception_info',
            'label' => 'Recepción',
            'type' => 'closure',
            'function' => function($entry) {
                if ($entry->reception) {
                    return '<a href="' . backpack_url('reception/' . $entry->reception->id . '/show') . '" class="badge bg-success">REC-' . $entry->reception->id . '</a>';
                }
                return '<span class="badge bg-secondary">Sin recepción (desde stock)</span>';
            },
            'escaped' => false,
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
