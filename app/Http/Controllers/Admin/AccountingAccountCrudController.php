<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\AccountingAccountRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class AccountingAccountCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup()
    {
        CRUD::setModel(\App\Models\AccountingAccount::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/accounting-account');
        CRUD::setEntityNameStrings('cuenta contable', 'cuentas contables');
    }

    protected function setupListOperation()
    {
        CRUD::removeButton('show');
        CRUD::enableResponsiveTable();

        $user = backpack_user();
        if ($user && $user->hasRole('role_admin_institucion', 'backpack')) {
            CRUD::removeButton('update');
            CRUD::removeButton('delete');
        }

        CRUD::column('code')->label('Código');
        CRUD::column('name')->label('Nombre');
        CRUD::column('is_active')->label('Activa')->type('boolean');
    }

    protected function setupCreateOperation()
    {
        CRUD::setValidation(AccountingAccountRequest::class);
        CRUD::field('code')->label('Código')->attributes(['placeholder' => 'Ej: 2110001'])
            ->wrapper(['class' => 'form-group col-sm-12 col-md-4']);
        CRUD::field('name')->label('Nombre')->attributes(['placeholder' => 'Ej: Proveedores varios'])
            ->wrapper(['class' => 'form-group col-sm-12 col-md-5']);
        CRUD::field('is_active')->label('Activa')->type('boolean')->default(true)
            ->wrapper(['class' => 'form-group col-sm-12 col-md-3']);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}
