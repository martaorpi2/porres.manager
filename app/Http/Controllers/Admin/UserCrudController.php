<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\UserRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\Hash;

/**
 * Class UserCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class UserCrudController extends CrudController
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
        CRUD::setModel(User::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/user');
        CRUD::setEntityNameStrings('usuario', 'usuarios');
    }

    /**
     * Define what happens when the List operation is loaded.
     * 
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::column('name')->label('Nombre');
        CRUD::column('email')->label('Email');
        
        // Mostrar roles del usuario
        CRUD::column([
            'name' => 'roles',
            'label' => 'Roles',
            'type' => 'select_multiple',
            'entity' => 'roles',
            'attribute' => 'name',
            'model' => 'App\Models\Role',
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
        CRUD::setValidation(UserRequest::class);
        
        CRUD::field('name')->label('Nombre')->type('text');
        CRUD::field('email')->label('Email')->type('email');
        CRUD::field('password')->label('Contraseña')->type('password');
        
        // Campo para roles (multiselect)
        CRUD::field([
            'name' => 'roles',
            'label' => 'Roles',
            'type' => 'checklist',
            'entity' => 'roles',
            'attribute' => 'name',
            'model' => 'App\Models\Role',
            'pivot' => true,
        ]);
        
        // Info sobre permisos derivados de roles
        CRUD::field([
            'name' => 'permissions_info',
            'label' => 'Permisos de Roles',
            'type' => 'custom_html',
            'value' => view('admin.user-permissions-info', ['roles' => Role::with('permissions')->get()]),
        ])->after('roles');
        
        // Campo para permisos individuales adicionales
        CRUD::field([
            'name' => 'permissions',
            'label' => 'Permisos Adicionales',
            'type' => 'checklist',
            'entity' => 'permissions',
            'attribute' => 'name',
            'model' => 'App\Models\Permission',
            'pivot' => true,
            'hint' => 'Estos permisos se agregarán además de los que vienen de los roles',
        ])->after('permissions_info');
    }

    /**
     * Define what happens when the Update operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        CRUD::setValidation(UserRequest::class);
        
        CRUD::field('name')->label('Nombre')->type('text');
        CRUD::field('email')->label('Email')->type('email');
        CRUD::field('password')->label('Contraseña')->type('password')->hint('Dejar en blanco si no desea cambiar la contraseña');
        
        // Campo para roles (multiselect)
        CRUD::field([
            'name' => 'roles',
            'label' => 'Roles',
            'type' => 'checklist',
            'entity' => 'roles',
            'attribute' => 'name',
            'model' => 'App\Models\Role',
            'pivot' => true,
        ]);
        
        // Info sobre permisos derivados de roles
        CRUD::field([
            'name' => 'permissions_info',
            'label' => 'Permisos de Roles',
            'type' => 'custom_html',
            'value' => view('admin.user-permissions-info', ['roles' => Role::with('permissions')->get()]),
        ])->after('roles');
        
        // Campo para permisos individuales adicionales
        CRUD::field([
            'name' => 'permissions',
            'label' => 'Permisos Adicionales',
            'type' => 'checklist',
            'entity' => 'permissions',
            'attribute' => 'name',
            'model' => 'App\Models\Permission',
            'pivot' => true,
            'hint' => 'Estos permisos se agregarán además de los que vienen de los roles',
        ])->after('permissions_info');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store()
    {
        $this->crud->hasAccessOrFail('create');

        // Execute the FormRequest authorization and validation, if one is required
        $request = $this->crud->validateRequest();

        // Encrypt password
        $data = $request->only(['name', 'email']);
        if ($request->has('password') && !empty($request->password)) {
            $data['password'] = Hash::make($request->password);
        }

        // Insert item in the db
        $item = User::create($data);
        $this->data['entry'] = $this->crud->entry = $item;

        // Sync roles - obtener de múltiples maneras posibles
        $roles = [];
        
        // Intentar obtener de request directo
        if ($request->has('roles')) {
            $roles = $request->input('roles', []);
        }
        
        // Intentar obtener de getStrippedSaveRequest
        $strippedRequest = $this->crud->getStrippedSaveRequest();
        if (isset($strippedRequest['roles'])) {
            $roles = $strippedRequest['roles'];
        }
        
        \Log::info('Todos los datos del request:', $request->all());
        
        if (is_array($roles) && !empty($roles)) {
            $roles = array_filter(array_map('intval', $roles));
            \Log::info('Guardando roles:', $roles);
            $item->syncRoles($roles);
        } else {
            \Log::info('No se encontraron roles');
        }
        
        // Sync permissions (permisos adicionales) - manejar como array
        if ($request->has('permissions')) {
            $permissions = $request->permissions;
            if (!is_array($permissions)) {
                $permissions = $permissions ? [$permissions] : [];
            }
            // Asegurar que todos los valores sean enteros válidos
            $permissions = array_filter(array_map('intval', $permissions));
            if (!empty($permissions)) {
                $item->syncPermissions($permissions);
            }
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

        // Execute the FormRequest authorization and validation, if one is required
        $request = $this->crud->validateRequest();

        // Get the user ID
        $id = $request->get('id');
        $item = User::findOrFail($id);

        // Encrypt password if provided
        $data = $request->only(['name', 'email']);
        if ($request->has('password') && !empty($request->password)) {
            $data['password'] = Hash::make($request->password);
        }

        // Update item in the db
        $item->update($data);
        $this->data['entry'] = $this->crud->entry = $item;

        // Sync roles - manejar como array
        if ($request->filled('roles')) {
            $roles = $request->roles;
            if (!is_array($roles)) {
                $roles = $roles ? [$roles] : [];
            }
            // Asegurar que todos los valores sean enteros válidos
            $roles = array_filter(array_map('intval', $roles));
            \Log::info('Guardando roles:', $roles);
            if (!empty($roles)) {
                $item->syncRoles($roles);
            } else {
                $item->syncRoles([]);
            }
        } else {
            \Log::info('No se recibieron roles en el request');
        }
        
        // Sync permissions (permisos adicionales) - manejar como array
        if ($request->has('permissions')) {
            $permissions = $request->permissions;
            if (!is_array($permissions)) {
                $permissions = $permissions ? [$permissions] : [];
            }
            // Asegurar que todos los valores sean enteros válidos
            $permissions = array_filter(array_map('intval', $permissions));
            if (!empty($permissions)) {
                $item->syncPermissions($permissions);
            }
        } else {
            // Si no se enviaron permisos, eliminar todos los permisos directos
            $item->syncPermissions([]);
        }

        // show a success message
        \Alert::success(trans('backpack::crud.update_success'))->flash();

        // save the redirect choice for next time
        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($item->id);
    }
}
