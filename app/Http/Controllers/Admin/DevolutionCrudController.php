<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\DevolutionRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class DevolutionCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class DevolutionCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation { store as traitStore; }
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
        CRUD::setModel(\App\Models\Devolution::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/devolution');
        CRUD::setEntityNameStrings('devolución', 'devoluciones');
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
        CRUD::enableResponsiveTable();
        // Cargar relaciones para evitar N+1 queries
        CRUD::addClause('with', ['reception.purchase_order', 'user']);

        CRUD::addColumn([
            'name' => 'reception_id',
            'label' => 'Recepción',
            'type' => 'closure',
            'function' => function($entry) {
                if ($entry->reception && $entry->reception->purchase_order) {
                    return 'REC-' . $entry->reception->id . ' | OC-' . $entry->reception->purchase_order->number;
                }
                return 'REC-' . $entry->reception_id;
            },
            'escaped' => false,
        ]);
        CRUD::column('reason')->label('Motivo');
        CRUD::addColumn([
            'name' => 'user',
            'label' => 'Usuario que devolvió',
            'type' => 'select',
            'entity' => 'user',
            'attribute' => 'name',
            'model' => 'App\Models\User',
        ]);
        CRUD::column('date')->label('Fecha');

        // Filtro personalizado por recepción usando parámetros de URL
        if (request()->has('recepcion')) {
            $recepcionId = request()->get('recepcion');
            if ($recepcionId) {
                CRUD::addClause('where', 'reception_id', $recepcionId);
            }
        }

        // Filtro personalizado por fecha usando parámetros de URL
        if (request()->has('fecha')) {
            $fecha = request()->get('fecha');
            if ($fecha) {
                CRUD::addClause('whereDate', 'date', $fecha);
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
        CRUD::setValidation(DevolutionRequest::class);
        
        CRUD::addField([
            'name' => 'reception_id',
            'label' => 'Recepción',
            'type' => 'select',
            'entity' => 'reception',
            'attribute' => 'number',
            'model' => 'App\Models\Reception',
        ]);
        CRUD::field('reason')->label('Motivo');
        CRUD::field('date')->label('Fecha');
        
        // Campo hidden para asignar automáticamente el usuario actual
        $user = backpack_user();
        if ($user) {
            CRUD::addField([
                'name' => 'user_id',
                'type' => 'hidden',
                'value' => $user->id,
            ]);
        }
        
        // Asignar automáticamente el usuario actual
        CRUD::addClause('with', ['user']);
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
     * Store the resource in the database.
     */
    public function store()
    {
        // Asegurar que el user_id esté asignado antes de guardar
        $user = backpack_user();
        if ($user && !request()->has('user_id')) {
            request()->merge(['user_id' => $user->id]);
        }
        
        return $this->traitStore();
    }
}
