<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\RemitoRequest;
use App\Models\PurchaseOrder;
use App\Models\Remito;
use App\Models\Supplier;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class RemitoCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup(): void
    {
        CRUD::setModel(Remito::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/remito');
        CRUD::setEntityNameStrings('remito', 'remitos');
    }

    protected function setupListOperation(): void
    {
        CRUD::removeButton('show');
        CRUD::enableResponsiveTable();
        CRUD::addClause('with', ['supplier', 'purchaseOrder']);

        $user = backpack_user();
        if ($user && ($user->hasRole('role_admin_institucion', 'backpack') || $user->hasRole('role_representante_legal', 'backpack'))) {
            CRUD::removeButton('update');
            CRUD::removeButton('delete');
        }
        if ($user && $user->hasRole('role_representante_legal', 'backpack')) {
            CRUD::removeButton('create');
        }

        CRUD::column('number')->label('Nº remito');
        CRUD::column('date')->label('Fecha')->type('date');
        CRUD::addColumn([
            'name' => 'supplier_id',
            'label' => 'Proveedor',
            'type' => 'select',
            'entity' => 'supplier',
            'attribute' => 'company_name',
            'model' => Supplier::class,
        ]);
        CRUD::addColumn([
            'name' => 'purchase_order_id',
            'label' => 'Orden de compra',
            'type' => 'closure',
            'function' => function (Remito $entry) {
                return $entry->purchase_order_id
                    ? e($entry->purchaseOrder?->number ?? ('OC #'.$entry->purchase_order_id))
                    : '<span class="text-muted">—</span>';
            },
            'escaped' => false,
        ]);
    }

    protected function setupCreateOperation(): void
    {
        $user = backpack_user();
        if ($user && $user->hasRole('role_representante_legal', 'backpack')) {
            abort(403, 'No tienes permiso para crear remitos.');
        }

        CRUD::setValidation(RemitoRequest::class);

        $supplierOptions = Supplier::query()->orderBy('company_name')->pluck('company_name', 'id')->toArray();
        $poOptions = PurchaseOrder::query()->orderByDesc('id')->limit(400)->pluck('number', 'id')->toArray();

        CRUD::field('number')->label('Número de remito')
            ->wrapper(['class' => 'form-group col-sm-12 col-md-6']);
        CRUD::field('date')->label('Fecha')->type('date')->default(now()->format('Y-m-d'))
            ->wrapper(['class' => 'form-group col-sm-12 col-md-6']);
        CRUD::addField([
            'name' => 'supplier_id',
            'label' => 'Proveedor',
            'type' => 'select_from_array',
            'options' => $supplierOptions,
            'allows_null' => false,
        ]);
        CRUD::addField([
            'name' => 'purchase_order_id',
            'label' => 'Orden de compra (opcional)',
            'type' => 'select_from_array',
            'options' => $poOptions,
            'allows_null' => true,
        ]);
        CRUD::field('observations')->label('Observaciones')->type('textarea');
        CRUD::field('attachment')->label('Archivo del remito (PDF o imagen)')->type('upload')
            ->disk('public')
            ->path('remitos')
            ->hint('Opcional.');
    }

    protected function setupUpdateOperation(): void
    {
        $user = backpack_user();
        if ($user && $user->hasRole('role_representante_legal', 'backpack')) {
            abort(403, 'No tienes permiso para editar remitos.');
        }

        $this->setupCreateOperation();
    }
}
