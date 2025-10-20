<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\SuppliersHeadingRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class SuppliersHeadingCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class SuppliersHeadingCrudController extends CrudController
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
        CRUD::setModel(\App\Models\SuppliersHeading::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/suppliers-heading');
        CRUD::setEntityNameStrings('rubro', 'rubros');
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

        CRUD::column('name')->label('Nombre');
        CRUD::column('description')->label('Descripción');

        // Botón para ver proveedores del rubro
        CRUD::addButton('line', 'view_suppliers', 'view', 'crud::buttons.view_suppliers', 'end');

        // Filtro personalizado por nombre usando parámetros de URL
        if (request()->has('nombre')) {
            $nombre = request()->get('nombre');
            if ($nombre) {
                CRUD::addClause('where', 'name', 'like', '%' . $nombre . '%');
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
        CRUD::setValidation(SuppliersHeadingRequest::class);
        CRUD::field('name')->label('Nombre');
        CRUD::field('description')->label('Descripción');
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
