<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\RoleRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use App\Models\Role;
use App\Models\Permission;

/**
 * Class RoleCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class RoleCrudController extends CrudController
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
        CRUD::setModel(Role::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/role');
        CRUD::setEntityNameStrings('rol', 'roles');
    }

    /**
     * Define what happens when the List operation is loaded.
     * 
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::column('name')->label('Nombre del Rol');
        CRUD::column('guard_name')->label('Guard');
        
        // Mostrar permisos del rol
        CRUD::column([
            'name' => 'permissions',
            'label' => 'Permisos',
            'type' => 'select_multiple',
            'entity' => 'permissions',
            'attribute' => 'name',
            'model' => 'App\Models\Permission',
        ]);

        CRUD::column('created_at')->label('Creado');
        CRUD::column('updated_at')->label('Actualizado');
    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(RoleRequest::class);
        
        CRUD::field('name')->label('Nombre del Rol')->type('text');
        CRUD::field('guard_name')->label('Guard')->type('text')->default('web');
        
        // Campo para permisos (multiselect)
        CRUD::field([
            'name' => 'permissions',
            'label' => 'Permisos',
            'type' => 'checklist',
            'entity' => 'permissions',
            'attribute' => 'name',
            'model' => 'App\Models\Permission',
            'pivot' => true,
            'hint' => 'Seleccione los permisos que tendrá este rol',
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
        $item = Role::create($data);
        $this->data['entry'] = $this->crud->entry = $item;

        // Sync permissions
        if ($request->has('permissions')) {
            $item->syncPermissions($request->permissions);
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

        // Get the role ID
        $id = $request->get('id');
        $item = Role::findOrFail($id);

        // Update item in the db
        $item->update($request->only(['name', 'guard_name']));
        $this->data['entry'] = $this->crud->entry = $item;

        // Sync permissions
        if ($request->has('permissions')) {
            $item->syncPermissions($request->permissions);
        }

        // show a success message
        \Alert::success(trans('backpack::crud.update_success'))->flash();

        // save the redirect choice for next time
        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($item->id);
    }
}

