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
        
        // Habilitar tabla responsiva
        CRUD::enableResponsiveTable();
        
        // Si el usuario tiene rol role_responsable_area, solo mostrar stock de sus áreas
        $user = backpack_user();
        if ($user && $user->hasRole('role_responsable_area')) {
            // Obtener las áreas de responsabilidad del usuario
            $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('name');
            
            if ($userAreas->isNotEmpty()) {
                // Filtrar stock por ubicaciones que coinciden con los nombres de las áreas
                CRUD::addClause('whereHas', 'location', function($query) use ($userAreas) {
                    $query->whereIn('name', $userAreas);
                });
            } else {
                // Si no tiene áreas asignadas, no mostrar nada
                CRUD::addClause('where', 'id', 0);
            }
        }
        
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
        
        // Si el usuario tiene rol role_responsable_area, solo mostrar ubicaciones de sus áreas
        $user = backpack_user();
        $locationOptions = function ($query) use ($user) {
            if ($user && $user->hasRole('role_responsable_area')) {
                // Obtener las áreas de responsabilidad del usuario
                $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('name');
                
                if ($userAreas->isNotEmpty()) {
                    // Filtrar solo las ubicaciones que coinciden con los nombres de las áreas del usuario
                    return $query->whereIn('name', $userAreas)->get();
                } else {
                    // Si no tiene áreas asignadas, no mostrar ubicaciones
                    return collect();
                }
            } else {
                // Para otros roles, mostrar todas las ubicaciones de áreas de responsabilidad
                return $query->whereIn('name', [
                    'Insumos Generales',
                    'Mantenimiento',
                    'Insumos de Salud',
                    'Informática'
                ])->get();
            }
        };
        
        CRUD::addField([
            'name' => 'location_id',
            'label' => 'Depósito',
            'type' => 'select',
            'entity' => 'location',
            'model' => 'App\Models\Location',
            'attribute' => 'name',
            'options' => $locationOptions,
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
        
        // Campo hidden para asignar automáticamente el usuario actual
        $user = backpack_user();
        if ($user) {
            CRUD::addField([
                'name' => 'last_updated_by',
                'type' => 'hidden',
                'value' => $user->id,
            ]);
        }
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
        
        // En actualización, también asignar automáticamente el usuario actual
        $user = backpack_user();
        if ($user) {
            CRUD::modifyField('last_updated_by', [
                'value' => $user->id,
            ]);
        }
    }

    /**
     * Store the resource in the database.
     */
    public function store()
    {
        // Si el usuario tiene rol role_responsable_area, verificar que solo pueda crear stock en sus áreas
        $user = backpack_user();
        if ($user && $user->hasRole('role_responsable_area')) {
            $locationId = request()->input('location_id');
            if ($locationId) {
                $location = \App\Models\Location::find($locationId);
                if ($location) {
                    // Obtener las áreas de responsabilidad del usuario
                    $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('name');
                    
                    // Verificar que la ubicación pertenezca a una de sus áreas
                    if (!$userAreas->contains($location->name)) {
                        abort(403, 'No tienes permiso para crear stock en esta ubicación. Solo puedes crear stock en tus áreas de responsabilidad.');
                    }
                }
            }
        }
        
        // Asegurar que el last_updated_by esté asignado antes de guardar
        if ($user && !request()->has('last_updated_by')) {
            request()->merge(['last_updated_by' => $user->id]);
        }
        
        return $this->traitStore();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update()
    {
        // Si el usuario tiene rol role_responsable_area, verificar que solo pueda actualizar stock de sus áreas
        $user = backpack_user();
        if ($user && $user->hasRole('role_responsable_area')) {
            $locationId = request()->input('location_id');
            if ($locationId) {
                $location = \App\Models\Location::find($locationId);
                if ($location) {
                    // Obtener las áreas de responsabilidad del usuario
                    $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('name');
                    
                    // Verificar que la ubicación pertenezca a una de sus áreas
                    if (!$userAreas->contains($location->name)) {
                        abort(403, 'No tienes permiso para modificar el stock de esta ubicación. Solo puedes modificar el stock de tus áreas de responsabilidad.');
                    }
                }
            }
        }
        
        // Asegurar que el last_updated_by esté asignado antes de actualizar
        if ($user) {
            request()->merge(['last_updated_by' => $user->id]);
        }
        
        return parent::update();
    }
    
    /**
     * Setup delete operation - verificar permisos para role_responsable_area
     */
    protected function setupDeleteOperation()
    {
        // Si el usuario tiene rol role_responsable_area, verificar que solo pueda eliminar stock de sus áreas
        $user = backpack_user();
        if ($user && $user->hasRole('role_responsable_area')) {
            $entry = $this->crud->getCurrentEntry();
            if ($entry && $entry->location) {
                // Obtener las áreas de responsabilidad del usuario
                $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('name');
                
                // Verificar que la ubicación del stock pertenezca a una de sus áreas
                if (!$userAreas->contains($entry->location->name)) {
                    abort(403, 'No tienes permiso para eliminar el stock de esta ubicación. Solo puedes eliminar el stock de tus áreas de responsabilidad.');
                }
            }
        }
    }
}
