<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\DevolutionRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Class DevolutionCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class DevolutionCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Devolution::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/devolution');
        CRUD::setEntityNameStrings('devolución', 'devoluciones');
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
        CRUD::enableResponsiveTable();
        // Cargar relaciones para evitar N+1 queries
        CRUD::addClause('with', ['reception.purchase_order', 'user']);
        
        // Si el usuario es role_responsable_compras, usar botón de editar personalizado
        $user = backpack_user();
        if ($user && $user->hasRole('role_responsable_compras', 'backpack')) {
            CRUD::removeButton('update');
            CRUD::addButton('line', 'edit_devolution', 'view', 'crud::buttons.edit_devolution', 'beginning');
            CRUD::removeButton('delete');
            CRUD::addButton('line', 'delete_devolution', 'view', 'crud::buttons.delete_devolution', 'end');
        }

        CRUD::addColumn([
            'name' => 'reception_id',
            'label' => 'Recepción',
            'type' => 'closure',
            'function' => function($entry) {
                if ($entry->reception && $entry->reception->purchase_order) {
                    return 'REC-' . $entry->reception->id . ' | OC-' . $entry->reception->purchase_order->number;
                }
                return 'REC-' . $entry->reception_id;
            },
            'escaped' => false,
        ]);
        CRUD::column('reason')->label('Motivo');
        CRUD::addColumn([
            'name' => 'user',
            'label' => 'Usuario que devolvió',
            'type' => 'select',
            'entity' => 'user',
            'attribute' => 'name',
            'model' => 'App\Models\User',
        ]);
        CRUD::column('date')->label('Fecha');
        
        // Agregar botón PDF en la lista
        CRUD::addColumn([
            'name' => 'pdf_button',
            'label' => 'PDF',
            'type' => 'custom_html',
            'value' => function($entry) {
                return '<a href="' . route('devolution.pdf', $entry->id) . '" class="btn btn-sm" target="_blank" data-toggle="tooltip" title="Descargar Comprobante de Devolución" style="background-color: #800020; border-color: #800020; color: white !important;">
                    <i class="la la-file-pdf" style="color: white !important;"></i> <span style="color: white !important;">PDF</span>
                </a>';
            },
            'escaped' => false,
        ]);

        // Filtro personalizado por recepción usando parámetros de URL
        if (request()->has('recepcion')) {
            $recepcionId = request()->get('recepcion');
            if ($recepcionId) {
                CRUD::addClause('where', 'reception_id', $recepcionId);
            }
        }

        // Filtro personalizado por fecha usando parámetros de URL
        if (request()->has('fecha')) {
            $fecha = request()->get('fecha');
            if ($fecha) {
                CRUD::addClause('whereDate', 'date', $fecha);
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
        CRUD::setValidation(DevolutionRequest::class);
        
        CRUD::addField([
            'name' => 'reception_id',
            'label' => 'Recepción',
            'type' => 'select',
            'entity' => 'reception',
            'attribute' => 'number',
            'model' => 'App\Models\Reception',
        ]);
        CRUD::field('reason')->label('Motivo');
        CRUD::field('date')->label('Fecha');
        
        // Campo hidden para asignar automáticamente el usuario actual
        $user = backpack_user();
        if ($user) {
            CRUD::addField([
                'name' => 'user_id',
                'type' => 'hidden',
                'value' => $user->id,
            ]);
        }
        
        // Asignar automáticamente el usuario actual
        CRUD::addClause('with', ['user']);
    }

    /**
     * Define what happens when the Update operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        // Verificar permisos para role_responsable_compras
        $user = backpack_user();
        $entry = $this->crud->getCurrentEntry();
        
        if ($user && $user->hasRole('role_responsable_compras', 'backpack')) {
            // El responsable de compras solo puede editar devoluciones que creó
            if ($entry && $entry->user_id != $user->id) {
                abort(403, 'Solo puedes editar las devoluciones que creaste.');
            }
        }
        
        $this->setupCreateOperation();
    }

    protected function setupShowOperation()
    {
        CRUD::addColumn([
            'name' => 'reception_id',
            'label' => 'Recepción',
            'type' => 'closure',
            'function' => function($entry) {
                if ($entry->reception && $entry->reception->purchase_order) {
                    return 'REC-' . $entry->reception->id . ' | ' . $entry->reception->purchase_order->number;
                }
                return 'REC-' . $entry->reception_id;
            },
            'escaped' => false,
        ]);
        CRUD::column('reason')->label('Motivo');
        CRUD::addColumn([
            'name' => 'user',
            'label' => 'Usuario que devolvió',
            'type' => 'select',
            'entity' => 'user',
            'attribute' => 'name',
            'model' => 'App\Models\User',
        ]);
        CRUD::column('date')->label('Fecha');
        
        // Agregar botón PDF en la vista show
        CRUD::addButton('top', 'pdf', 'view', 'crud::buttons.devolution_pdf', 'end');
    }
    
    /**
     * Generate PDF for a devolution
     */
    public function generatePdf($id)
    {
        $devolution = \App\Models\Devolution::with([
            'reception.purchase_order.supplier',
            'reception.purchase_order.details.input',
            'reception.user',
            'user'
        ])->findOrFail($id);
        
        $pdf = Pdf::loadView('devolution-pdf', compact('devolution'));
        
        return $pdf->stream('comprobante-devolucion-' . $devolution->id . '.pdf');
    }

    /**
     * Store the resource in the database.
     */
    public function store()
    {
        // Asegurar que el user_id esté asignado antes de guardar
        $user = backpack_user();
        if ($user && !request()->has('user_id')) {
            request()->merge(['user_id' => $user->id]);
        }
        
        return $this->traitStore();
    }
    
    /**
     * Setup delete operation - restrict for role_responsable_compras
     */
    protected function setupDeleteOperation()
    {
        // Verificar permisos para role_responsable_compras
        $user = backpack_user();
        $entry = $this->crud->getCurrentEntry();
        
        if ($user && $user->hasRole('role_responsable_compras', 'backpack')) {
            // El responsable de compras solo puede eliminar devoluciones que creó
            if ($entry && $entry->user_id != $user->id) {
                abort(403, 'Solo puedes eliminar las devoluciones que creaste.');
            }
        }
    }
}
