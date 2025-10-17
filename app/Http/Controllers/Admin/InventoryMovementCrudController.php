<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\InventoryMovementRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class InventoryMovementCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class InventoryMovementCrudController extends CrudController
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
        CRUD::setModel(\App\Models\InventoryMovement::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/inventory-movement');
        CRUD::setEntityNameStrings('movimiento de inventario', 'movimientos de inventario');
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

        CRUD::column('product')->label('Producto');
        CRUD::column('location')->label('Ubicación');
        CRUD::addColumn([
            'name' => 'quantity',
            'label' => 'Cantidad',
            'type' => 'number',
        ]);
        CRUD::addColumn([
            'name' => 'type',
            'label' => 'Tipo',
            'type' => 'enum',
        ]);
        CRUD::column('reference')->label('Referencia');
        CRUD::addColumn([
            'name' => 'user',
            'label' => 'Usuario',
            'type' => 'select',
            'entity' => 'user',
            'attribute' => 'name',
            'model' => 'App\Models\User',
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('user', function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%'.$searchTerm.'%');
                });
            },
        ]);
        CRUD::column('notes')->label('Observaciones');
        // Override the type column to show the human-readable labels
        /*CRUD::modifyColumn('type', [
            'type' => 'select_from_array',
            'options' => \App\Models\InventoryMovement::getTypes(),
        ]);*/

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
        CRUD::setValidation(InventoryMovementRequest::class);
        
        CRUD::field('product')->label('Producto');
        CRUD::field('location')->label('Ubicación');
        CRUD::addField([
            'name' => 'quantity',
            'label' => 'Cantidad',
            'type' => 'number',
        ]);
        CRUD::addField([
            'name' => 'type',
            'label' => 'Tipo',
            'type' => 'enum',
        ]);
        CRUD::field('reference')->label('Referencia');
        CRUD::addField([
            'name' => 'user',
            'label' => 'Usuario',
            'type' => 'select',
            'entity' => 'user',
            'attribute' => 'name',
            'model' => 'App\Models\User',
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('user', function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%'.$searchTerm.'%');
                });
            },
        ]);
        CRUD::field('notes')->label('Observaciones');
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
