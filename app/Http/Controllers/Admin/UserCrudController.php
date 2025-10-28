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
        $this->crud->addClause('with', ['roles', 'permissions']);
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
        CRUD::field('password_confirmation')->label('Confirmar Contraseña')->type('password');
        
        // Campo para roles (multiselect)
        CRUD::field([
            'name' => 'roles',
            'label' => 'Roles',
            'type' => 'checklist',
            'entity' => 'roles',
            'attribute' => 'name',
            'model' => Role::class,
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
            'model' => Permission::class,
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
        CRUD::field('password')->label('Nueva Contraseña')->type('password')->hint('Opcional: dejar en blanco para mantener la actual');
        CRUD::field('password_confirmation')->label('Confirmar Nueva Contraseña')->type('password');
        
        // Campo para roles (multiselect)
        CRUD::field([
            'name' => 'roles',
            'label' => 'Roles',
            'type' => 'checklist',
            'entity' => 'roles',
            'attribute' => 'name',
            'model' => Role::class,
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
            'model' => Permission::class,
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
        $request = $this->crud->validateRequest();

        $data = $request->only(['name', 'email']);
        if ($request->password) {
            $data['password'] = Hash::make($request->password);
        }

        $item = User::create($data);
        $this->data['entry'] = $this->crud->entry = $item;

        // Procesar roles
        $roles = $request->input('roles', []);
        $roleIds = [];
        
        if (is_array($roles)) {
            foreach ($roles as $role) {
                if (is_string($role) && $role !== '[]' && $role !== '') {
                    $decoded = json_decode($role, true);
                    if (is_array($decoded)) {
                        $roleIds = array_merge($roleIds, $decoded);
                    } else {
                        $roleIds[] = $role;
                    }
                } elseif (is_numeric($role)) {
                    $roleIds[] = intval($role);
                }
            }
            $roleIds = array_filter(array_unique($roleIds));
            
            // Obtener los modelos de roles por ID
            $roleModels = [];
            foreach ($roleIds as $roleId) {
                $role = Role::find($roleId);
                if ($role) {
                    $roleModels[] = $role;
                }
            }
            $item->syncRoles($roleModels);
        }
        
        // Procesar permisos
        $permissions = $request->input('permissions', []);
        $permissionIds = [];
        
        if (is_array($permissions)) {
            foreach ($permissions as $permission) {
                if (is_string($permission) && $permission !== '[]' && $permission !== '') {
                    $decoded = json_decode($permission, true);
                    if (is_array($decoded)) {
                        $permissionIds = array_merge($permissionIds, $decoded);
                    } else {
                        $permissionIds[] = $permission;
                    }
                } elseif (is_numeric($permission)) {
                    $permissionIds[] = intval($permission);
                }
            }
            $permissionIds = array_filter(array_unique($permissionIds));
            
            // Obtener los modelos de permisos por ID
            $permissionModels = [];
            foreach ($permissionIds as $permissionId) {
                $permission = Permission::find($permissionId);
                if ($permission) {
                    $permissionModels[] = $permission;
                }
            }
            $item->syncPermissions($permissionModels);
        }

        \Alert::success(trans('backpack::crud.insert_success'))->flash();
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
        $request = $this->crud->validateRequest();

        $item = User::findOrFail($request->id);
        $data = $request->only(['name', 'email']);
        
        if ($request->password) {
            $data['password'] = Hash::make($request->password);
        }

        $item->update($data);
        $this->data['entry'] = $this->crud->entry = $item;

        // Procesar roles
        $roles = $request->input('roles', []);
        $roleIds = [];
        
        if (is_array($roles)) {
            foreach ($roles as $role) {
                // Los roles pueden venir como string JSON o como número
                if (is_string($role) && $role !== '[]' && $role !== '') {
                    // Si viene como JSON string, decodificar
                    $decoded = json_decode($role, true);
                    if (is_array($decoded)) {
                        $roleIds = array_merge($roleIds, $decoded);
                    } else {
                        $roleIds[] = $role;
                    }
                } elseif (is_numeric($role)) {
                    $roleIds[] = intval($role);
                }
            }
            $roleIds = array_filter(array_unique($roleIds));
            \Log::info('Syncing roles:', ['roles' => $roleIds]);
            
            // Obtener los modelos de roles por ID
            $roleModels = [];
            foreach ($roleIds as $roleId) {
                $role = Role::find($roleId);
                if ($role) {
                    $roleModels[] = $role;
                }
            }
            $item->syncRoles($roleModels);
        }
        
        // Procesar permisos
        $permissions = $request->input('permissions', []);
        $permissionIds = [];
        
        if (is_array($permissions)) {
            foreach ($permissions as $permission) {
                if (is_string($permission) && $permission !== '[]' && $permission !== '') {
                    $decoded = json_decode($permission, true);
                    if (is_array($decoded)) {
                        $permissionIds = array_merge($permissionIds, $decoded);
                    } else {
                        $permissionIds[] = $permission;
                    }
                } elseif (is_numeric($permission)) {
                    $permissionIds[] = intval($permission);
                }
            }
            $permissionIds = array_filter(array_unique($permissionIds));
            \Log::info('Syncing permissions:', ['permissions' => $permissionIds]);
            
            // Obtener los modelos de permisos por ID
            $permissionModels = [];
            foreach ($permissionIds as $permissionId) {
                $permission = Permission::find($permissionId);
                if ($permission) {
                    $permissionModels[] = $permission;
                }
            }
            $item->syncPermissions($permissionModels);
        }

        \Alert::success(trans('backpack::crud.update_success'))->flash();
        $this->crud->setSaveAction();
        return $this->crud->performSaveAction($item->id);
    }
}
