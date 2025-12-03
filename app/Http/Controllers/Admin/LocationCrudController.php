<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\LocationRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class LocationCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class LocationCrudController extends CrudController
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
        // Bloquear acceso completo para role_admin_institucion (pero permitir ver para role_representante_legal)
        $user = backpack_user();
        if ($user && $user->hasRole('role_admin_institucion', 'backpack')) {
            abort(403, 'No tienes permiso para acceder a ubicaciones.');
        }
        
        CRUD::setModel(\App\Models\Location::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/location');
        CRUD::setEntityNameStrings('ubicación', 'ubicaciones');
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
        
        // Ocultar botones de crear, editar y eliminar para role_admin_institucion y role_representante_legal
        $user = backpack_user();
        if ($user && ($user->hasRole('role_admin_institucion', 'backpack') || $user->hasRole('role_representante_legal', 'backpack'))) {
            CRUD::removeButton('create');
            CRUD::removeButton('update');
            CRUD::removeButton('delete');
        }

        CRUD::column('name')->label('Nombre');
        CRUD::column('description')->label('Descripción');

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
        // Bloquear creación para role_representante_legal
        $user = backpack_user();
        if ($user && $user->hasRole('role_representante_legal', 'backpack')) {
            abort(403, 'No tienes permiso para crear ubicaciones.');
        }
        
        CRUD::setValidation(LocationRequest::class);
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
        // Bloquear edición para role_representante_legal
        $user = backpack_user();
        if ($user && $user->hasRole('role_representante_legal', 'backpack')) {
            abort(403, 'No tienes permiso para editar ubicaciones.');
        }
        
        $this->setupCreateOperation();
    }
}
