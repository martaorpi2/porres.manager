<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\SupplierRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class SupplierCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class SupplierCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Supplier::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/supplier');
        CRUD::setEntityNameStrings('proveedor', 'proveedores');
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
        //CRUD::setFromDb(); // set columns from db columns.
        CRUD::column('company_name')->label('Nombre');
        CRUD::column('cuit')->label('Cuit');
        CRUD::column('address')->label('Dirección');
        CRUD::addColumn([
            'name' => 'supplier_heading_id',
            'label' => 'Rubro',
            'type' => 'select',
            'entity' => 'heading',
            'attribute' => 'name',
            'model' => 'App\Models\SuppliersHeading',
        ]);
        CRUD::addColumn([
            'name'  => 'sectors',
            'label' => 'Sectores',
            'type'  => 'closure',
            'function' => function($entry) {
                $html = '';
                foreach ($entry->sectors as $sector) {
                    $html .= '<span class="badge rounded-pill bg-secondary me-1" style="font-size:0.8rem;">' 
                            . e($sector->name) . '</span>';
                }
                return $html;
            },
            'escaped' => false, // permitimos HTML para mostrar las badges
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
        CRUD::setValidation(SupplierRequest::class);
        //CRUD::setFromDb(); // set fields from db columns.
        CRUD::field('company_name')->label('Nombre');
        CRUD::field('cuit')->label('Cuit');
        CRUD::field('address')->label('Dirección');
        CRUD::addField([
            'name' => 'supplier_heading_id',
            'label' => 'Rubro',
            'type' => 'select',
            'entity' => 'heading',
            'model' => 'App\Models\SuppliersHeading',
            'attribute' => 'name',
        ]);
        CRUD::addField([
            'name' => 'sectors',
            'label' => 'Sectores',
            'type' => 'select_multiple',
            'entity' => 'sectors',
            'attribute' => 'name',
            'model' => 'App\Models\Sector',
            'pivot' => true,
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
