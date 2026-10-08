<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\AccountingPeriodRequest;
use App\Models\User;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class AccountingPeriodCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup(): void
    {
        $user = backpack_user();
        if (! $user instanceof User || ! $user->canViewAccounting()) {
            abort(403, 'No tiene permiso para ver la contabilidad.');
        }

        CRUD::setModel(\App\Models\AccountingPeriod::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/accounting-period');
        CRUD::setEntityNameStrings('período', 'períodos');
    }

    protected function setupListOperation(): void
    {
        CRUD::orderBy('start_date', 'desc');

        CRUD::addColumn([
            'name' => 'name',
            'label' => 'Nombre',
            'type' => 'closure',
            'function' => function ($entry) {
                $name = trim((string) $entry->name);

                return e($name !== '' ? $name : '—');
            },
        ]);
        CRUD::addColumn([
            'name' => 'start_date',
            'label' => 'Fecha de inicio',
            'type' => 'closure',
            'function' => function ($entry) {
                return e($entry->start_date?->format('d/m/Y') ?? '—');
            },
        ]);
        CRUD::addColumn([
            'name' => 'end_date',
            'label' => 'Fecha de fin',
            'type' => 'closure',
            'function' => function ($entry) {
                return e($entry->end_date?->format('d/m/Y') ?? '—');
            },
        ]);
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(AccountingPeriodRequest::class);

        CRUD::field('name')
            ->label('Nombre')
            ->type('text')
            ->hint('Opcional. Por ejemplo: Ejercicio 2026.')
            ->wrapper(['class' => 'form-group col-sm-12']);

        CRUD::field('start_date')
            ->label('Fecha de inicio')
            ->type('date')
            ->wrapper(['class' => 'form-group col-sm-12 col-md-6']);

        CRUD::field('end_date')
            ->label('Fecha de fin')
            ->type('date')
            ->wrapper(['class' => 'form-group col-sm-12 col-md-6']);
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }
}
