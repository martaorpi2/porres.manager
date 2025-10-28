<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\PermissionRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use App\Models\Permission;

/**
 * Class PermissionCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class PermissionCrudController extends CrudController
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
        CRUD::setModel(Permission::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/permission');
        CRUD::setEntityNameStrings('permiso', 'permisos');
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

        CRUD::column('name')->label('Nombre del Permiso');
        //CRUD::column('guard_name')->label('Guard');
        
        // Mostrar roles que tienen este permiso
        CRUD::column([
            'name' => 'roles',
            'label' => 'Roles',
            'type' => 'select_multiple',
            'entity' => 'roles',
            'attribute' => 'name',
            'model' => 'App\Models\Role',
        ]);

    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(PermissionRequest::class);
        
        CRUD::field('name')->label('Nombre del Permiso')->type('text')
            ->hint('Formato sugerido: modulo.accion (ej: solicitud.crear, compra.aprobar)');
        //CRUD::field('guard_name')->label('Guard')->type('text')->default('web');
        
        // Campo para roles (multiselect)
        CRUD::field([
            'name' => 'roles',
            'label' => 'Roles',
            'type' => 'checklist',
            'entity' => 'roles',
            'attribute' => 'name',
            'model' => 'App\Models\Role',
            'pivot' => true,
            'hint' => 'Seleccione los roles que tendrán este permiso',
        ]);
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
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store()
    {
        $this->crud->hasAccessOrFail('create');

        // Execute the FormRequest authorization and validation
        $request = $this->crud->validateRequest();

        // Get validated data
        $data = $request->only(['name', 'guard_name']);
        
        // Insert item in the db
        $item = Permission::create($data);
        $this->data['entry'] = $this->crud->entry = $item;

        // Sync roles
        if ($request->has('roles')) {
            $item->syncRoles($request->roles);
        }

        // show a success message
        \Alert::success(trans('backpack::crud.insert_success'))->flash();

        // save the redirect choice for next time
        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($item->id);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update()
    {
        $this->crud->hasAccessOrFail('update');

        // Execute the FormRequest authorization and validation
        $request = $this->crud->validateRequest();

        // Get the permission ID
        $id = $request->get('id');
        $item = Permission::findOrFail($id);

        // Update item in the db
        $item->update($request->only(['name', 'guard_name']));
        $this->data['entry'] = $this->crud->entry = $item;

        // Sync roles
        if ($request->has('roles')) {
            $item->syncRoles($request->roles);
        }

        // show a success message
        \Alert::success(trans('backpack::crud.update_success'))->flash();

        // save the redirect choice for next time
        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($item->id);
    }
}

