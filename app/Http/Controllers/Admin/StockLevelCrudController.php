<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\StockLevelRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class StockLevelCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class StockLevelCrudController extends CrudController
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
        CRUD::setModel(\App\Models\StockLevel::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/stock-level');
        CRUD::setEntityNameStrings('stock', 'stock');
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
        CRUD::column('location')->label('Depósito');
        CRUD::addColumn([
            'name' => 'quantity',
            'label' => 'Cantidad',
            'type' => 'number',
        ]);
        CRUD::addColumn([
            'name' => 'last_updated_by',
            'label' => 'Actualizado por',
            'type' => 'select',
            'entity' => 'lastUpdatedBy',
            'attribute' => 'name',
            'model' => 'App\Models\User',
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('lastUpdatedBy', function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%'.$searchTerm.'%');
                });
            },
        ]);

        // Filtro personalizado por depósito usando parámetros de URL
        if (request()->has('deposito')) {
            $depositoId = request()->get('deposito');
            if ($depositoId) {
                CRUD::addClause('where', 'location_id', $depositoId);
            }
        }

        // Filtro personalizado por nombre de producto usando parámetros de URL
        if (request()->has('producto')) {
            $producto = request()->get('producto');
            if ($producto) {
                CRUD::addClause('whereHas', 'product', function($query) use ($producto) {
                    $query->where('name', 'like', '%' . $producto . '%');
                });
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
        CRUD::setValidation(StockLevelRequest::class);
        CRUD::field('product')->label('Producto');
        
        // Solo mostrar las 4 ubicaciones que corresponden a áreas de responsabilidad
        CRUD::addField([
            'name' => 'location_id',
            'label' => 'Depósito',
            'type' => 'select',
            'entity' => 'location',
            'model' => 'App\Models\Location',
            'attribute' => 'name',
            'options' => function ($query) {
                // Filtrar solo las ubicaciones que corresponden a áreas de responsabilidad
                return $query->whereIn('name', [
                    'Insumos Generales',
                    'Mantenimiento',
                    'Insumos de Salud',
                    'Informática'
                ]);
            },
        ]);
        
        CRUD::addField([
            'name' => 'quantity',
            'label' => 'Cantidad',
            'type' => 'number',
            'attributes' => [
                'step' => 1,
                'min' => 0,
            ],
        ]);
        
        CRUD::addField([
            'name' => 'last_updated_by',
            'label' => 'Actualizado por',
            'type' => 'select',
            'entity' => 'lastUpdatedBy',
            'attribute' => 'name',
            'model' => 'App\Models\User',
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('lastUpdatedBy', function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%'.$searchTerm.'%');
                });
            },
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
