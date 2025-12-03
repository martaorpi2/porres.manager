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
        
        // Cargar relación location para evitar problemas con whereHas
        CRUD::addClause('with', ['location', 'product']);
        
        // Si el usuario tiene rol role_responsable_area, solo mostrar stock de sus áreas
        $user = backpack_user();
        
        // Debug: Log para verificar usuario
        \Log::info('StockLevelCrudController - setupListOperation', [
            'user_id' => $user ? $user->id : null,
            'user_email' => $user ? $user->email : null,
            'has_role' => $user ? $user->hasRole('role_responsable_area', 'backpack') : false,
        ]);
        
        if ($user && $user->hasRole('role_responsable_area', 'backpack')) {
            // Obtener las áreas de responsabilidad del usuario
            $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('name');
            
            \Log::info('StockLevelCrudController - Áreas del usuario', [
                'user_id' => $user->id,
                'areas' => $userAreas->toArray(),
            ]);
            
            if ($userAreas->isNotEmpty()) {
                // Mapear nombres de áreas a nombres de ubicaciones
                $areaLocationMap = [
                    'Informática' => 'Informática',
                    'Mantenimiento' => 'Mantenimiento',
                    'Salud' => 'Insumos de Salud',
                    'Insumos Generales' => 'Insumos Generales',
                ];
                
                $locationNames = [];
                foreach ($userAreas as $areaName) {
                    if (isset($areaLocationMap[$areaName])) {
                        $locationNames[] = $areaLocationMap[$areaName];
                    } else {
                        // Si no hay mapeo, usar el nombre del área directamente
                        $locationNames[] = $areaName;
                    }
                }
                
                \Log::info('StockLevelCrudController - Ubicaciones filtradas', [
                    'location_names' => $locationNames,
                ]);
                
                // Obtener IDs de ubicaciones para filtrar directamente
                $locationIds = \App\Models\Location::whereIn('name', $locationNames)->pluck('id');
                
                \Log::info('StockLevelCrudController - IDs de ubicaciones', [
                    'location_ids' => $locationIds->toArray(),
                ]);
                
                // Filtrar stock por IDs de ubicaciones (más eficiente que whereHas)
                if ($locationIds->isNotEmpty()) {
                    CRUD::addClause('whereIn', 'location_id', $locationIds);
                } else {
                    // Si no hay ubicaciones, no mostrar nada
                    CRUD::addClause('where', 'id', 0);
                }
            } else {
                // Si no tiene áreas asignadas, no mostrar nada
                \Log::warning('StockLevelCrudController - Usuario sin áreas asignadas', [
                    'user_id' => $user->id,
                ]);
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
        
        // Filtrar productos según el área del responsable
        $user = backpack_user();
        $productOptions = null;
        
        if ($user && $user->hasRole('role_responsable_area', 'backpack')) {
            // Obtener las áreas de responsabilidad del usuario con sus nombres
            $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->get();
            
            if ($userAreas->isNotEmpty()) {
                // Mapeo de áreas a categorías permitidas
                $areaCategoryMap = [
                    'Informática' => ['Equipos Informáticos', 'Software'],
                    'Salud' => ['Material Médico', 'Reactivos'],
                    'Insumos de Salud' => ['Material Médico', 'Reactivos'],
                    'Mantenimiento' => ['Herramientas', 'Repuestos'],
                    'Insumos Generales' => ['Material de Oficina', 'Limpieza', 'Insumos Generales'],
                ];
                
                // Obtener todas las categorías permitidas para las áreas del usuario
                $allowedCategoryNames = collect();
                foreach ($userAreas as $area) {
                    $areaName = $area->name;
                    if (isset($areaCategoryMap[$areaName])) {
                        $allowedCategoryNames = $allowedCategoryNames->merge($areaCategoryMap[$areaName]);
                    }
                }
                
                // Obtener los IDs de las categorías permitidas
                $categoryIds = \App\Models\Category::whereIn('name', $allowedCategoryNames->unique())
                    ->pluck('id');
                
                if ($categoryIds->isNotEmpty()) {
                    // Obtener los productos filtrados por categorías
                    $products = \App\Models\Product::whereIn('category_id', $categoryIds)
                        ->pluck('name', 'id')
                        ->toArray();
                    
                    if (!empty($products)) {
                        $productOptions = $products;
                    }
                }
            }
        }
        
        // Configurar el campo de producto
        if ($productOptions !== null && is_array($productOptions)) {
            CRUD::addField([
                'name' => 'product_id',
                'label' => 'Producto',
                'type' => 'select_from_array',
                'options' => $productOptions,
                'allows_null' => false,
            ]);
        } else {
            CRUD::field('product')->label('Producto');
        }
        
        // Si el usuario tiene rol role_responsable_area, solo mostrar ubicaciones de sus áreas
        $locationOptions = function ($query) use ($user) {
            if ($user && $user->hasRole('role_responsable_area', 'backpack')) {
                // Obtener las áreas de responsabilidad del usuario
                $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('name');
                
                if ($userAreas->isNotEmpty()) {
                    // Mapear nombres de áreas a nombres de ubicaciones
                    $areaLocationMap = [
                        'Informática' => 'Informática',
                        'Mantenimiento' => 'Mantenimiento',
                        'Salud' => 'Insumos de Salud',
                        'Insumos Generales' => 'Insumos Generales',
                    ];
                    
                    $locationNames = [];
                    foreach ($userAreas as $areaName) {
                        if (isset($areaLocationMap[$areaName])) {
                            $locationNames[] = $areaLocationMap[$areaName];
                        } else {
                            $locationNames[] = $areaName;
                        }
                    }
                    
                    // Filtrar solo las ubicaciones que coinciden con los nombres mapeados
                    return $query->whereIn('name', $locationNames)->get();
                } else {
                    // Si no tiene áreas asignadas, no mostrar ubicaciones
                    return collect();
                }
            } else {
                // Para otros roles, mostrar todas las ubicaciones de áreas de responsabilidad
                return $query->whereIn('name', [
                    'Informática',
                    'Mantenimiento',
                    'Insumos de Salud',
                    'Insumos Generales'
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
        if ($user && $user->hasRole('role_responsable_area', 'backpack')) {
            $locationId = request()->input('location_id');
            if ($locationId) {
                $location = \App\Models\Location::find($locationId);
                if ($location) {
                    // Obtener las áreas de responsabilidad del usuario
                    $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('name');
                    
                    // Mapear nombres de áreas a nombres de ubicaciones
                    $areaLocationMap = [
                        'Informática' => 'Informática',
                        'Mantenimiento' => 'Mantenimiento',
                        'Salud' => 'Insumos de Salud',
                        'Insumos Generales' => 'Insumos Generales',
                    ];
                    
                    $locationNames = [];
                    foreach ($userAreas as $areaName) {
                        if (isset($areaLocationMap[$areaName])) {
                            $locationNames[] = $areaLocationMap[$areaName];
                        } else {
                            $locationNames[] = $areaName;
                        }
                    }
                    
                    // Verificar que la ubicación pertenezca a una de sus áreas
                    if (!in_array($location->name, $locationNames)) {
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
        if ($user && $user->hasRole('role_responsable_area', 'backpack')) {
            $locationId = request()->input('location_id');
            if ($locationId) {
                $location = \App\Models\Location::find($locationId);
                if ($location) {
                    // Obtener las áreas de responsabilidad del usuario
                    $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('name');
                    
                    // Mapear nombres de áreas a nombres de ubicaciones
                    $areaLocationMap = [
                        'Informática' => 'Informática',
                        'Mantenimiento' => 'Mantenimiento',
                        'Salud' => 'Insumos de Salud',
                        'Insumos Generales' => 'Insumos Generales',
                    ];
                    
                    $locationNames = [];
                    foreach ($userAreas as $areaName) {
                        if (isset($areaLocationMap[$areaName])) {
                            $locationNames[] = $areaLocationMap[$areaName];
                        } else {
                            $locationNames[] = $areaName;
                        }
                    }
                    
                    // Verificar que la ubicación pertenezca a una de sus áreas
                    if (!in_array($location->name, $locationNames)) {
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
        if ($user && $user->hasRole('role_responsable_area', 'backpack')) {
            $entry = $this->crud->getCurrentEntry();
            if ($entry && $entry->location) {
                // Obtener las áreas de responsabilidad del usuario
                $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('name');
                
                // Mapear nombres de áreas a nombres de ubicaciones
                $areaLocationMap = [
                    'Informática' => 'Informática',
                    'Mantenimiento' => 'Mantenimiento',
                    'Salud' => 'Insumos de Salud',
                    'Insumos Generales' => 'Insumos Generales',
                ];
                
                $locationNames = [];
                foreach ($userAreas as $areaName) {
                    if (isset($areaLocationMap[$areaName])) {
                        $locationNames[] = $areaLocationMap[$areaName];
                    } else {
                        $locationNames[] = $areaName;
                    }
                }
                
                // Verificar que la ubicación del stock pertenezca a una de sus áreas
                if (!in_array($entry->location->name, $locationNames)) {
                    abort(403, 'No tienes permiso para eliminar el stock de esta ubicación. Solo puedes eliminar el stock de tus áreas de responsabilidad.');
                }
            }
        }
    }
}
