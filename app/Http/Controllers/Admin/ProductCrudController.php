<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ProductRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class ProductCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ProductCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Product::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/product');
        CRUD::setEntityNameStrings('producto', 'productos');
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
        CRUD::addColumn([
            'name' => 'category_id',
            'label' => 'Categoría',
            'type' => 'select',
            'entity' => 'category',
            'attribute' => 'name',
            'model' => 'App\Models\Category',
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('category', function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%'.$searchTerm.'%');
                });
            },
        ]);
        CRUD::column('name')->label('Nombre');
        CRUD::column('description')->label('Descripción');
        CRUD::column('unit_measurement')->label('Unidad Med.');
        CRUD::column('minimum_stock')->label('Stock Mín.');
        CRUD::column('expiration_date')->label('Fecha Vencimiento');
        CRUD::column('location')->label('Ubicación');
        CRUD::column('utilization_percentage')->label('% Utilización');
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
        CRUD::setValidation(ProductRequest::class);
        CRUD::addField([
            'name' => 'category_id',
            'label' => 'Categoría',
            'type' => 'select',
            'entity' => 'category',
            'model' => 'App\Models\Category',
            'attribute' => 'name',
        ]);
        CRUD::field('name')->label('Nombre');
        CRUD::field('description')->label('Descripción');
        CRUD::field('unit_measurement')->label('Unidad Med.');
        CRUD::field('minimum_stock')->label('Stock Mín.');
        CRUD::field('expiration_date')->label('Fecha Vencimiento');
        CRUD::field('location')->label('Ubicación');
        CRUD::field('utilization_percentage')->label('% Utilización');
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
