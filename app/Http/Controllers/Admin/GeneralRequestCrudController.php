<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class GeneralRequestCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup()
    {
        CRUD::setModel(\App\Models\GeneralRequest::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/general-request');
        CRUD::setEntityNameStrings('solicitud general', 'solicitudes generales');
    }

    protected function setupListOperation()
    {
        CRUD::addClause('with', ['createdBy', 'area']);

        CRUD::column('number')->label('Número');
        CRUD::column('title')->label('Título');
        CRUD::column('createdBy.name')->label('Creado por');
        CRUD::column('area.name')->label('Área');
        CRUD::column('priority')->label('Prioridad');
        CRUD::column('status')->label('Estado');
        CRUD::column('created_at')->label('Fecha de Creación');

        // Botón para convertir a solicitud de compra
        CRUD::addButton('line', 'convert_to_purchase', 'view', 'crud::buttons.convert_to_purchase', 'end');
    }

    protected function setupCreateOperation()
    {
        CRUD::field('number')->label('Número de Solicitud')->default(\App\Models\GeneralRequest::generateNextNumber())->attributes(['readonly' => 'readonly']);

        CRUD::field('title')->label('Título')->validationRules('required|string|max:255');
        
        CRUD::field('description')->label('Descripción')->type('textarea')->validationRules('required');
        
        CRUD::field('area_id')->label('Área')
            ->type('select')
            ->model('App\Models\ResponsibilityArea')
            ->attribute('name')
            ->validationRules('nullable|exists:responsibility_areas,id');

        CRUD::field('created_by')->label('Creado por')
            ->type('select')
            ->model('App\Models\User')
            ->attribute('name')
            ->default(auth()->id() ?? 1)
            ->validationRules('required|exists:users,id');

        CRUD::field('priority')->label('Prioridad')
            ->type('select_from_array')
            ->options([
                'Baja' => 'Baja',
                'Media' => 'Media',
                'Alta' => 'Alta',
                'Urgente' => 'Urgente'
            ])
            ->default('Media');

        CRUD::field('status')->label('Estado')
            ->type('select_from_array')
            ->options([
                'creada' => 'Creada',
                'revisada_area' => 'Revisada por Área',
                'archivada' => 'Archivada',
                'convertida_a_compra' => 'Convertida a Compra'
            ])
            ->default('creada');
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    protected function setupShowOperation()
    {
        CRUD::addClause('with', ['createdBy', 'area', 'purchaseRequests']);

        CRUD::setFromDb();

        // Mostrar solicitudes de compra relacionadas
        CRUD::field('purchase_requests_table')->label('Solicitudes de Compra Relacionadas')->type('custom_html')
            ->value(function($entry) {
                $purchaseRequests = $entry->purchaseRequests;
                
                if ($purchaseRequests->isEmpty()) {
                    return '<div class="alert alert-info">No hay solicitudes de compra relacionadas.</div>';
                }
                
                $html = '<div class="table-responsive">';
                $html .= '<table class="table table-striped table-bordered">';
                $html .= '<thead class="thead-dark">';
                $html .= '<tr>';
                $html .= '<th>Número</th>';
                $html .= '<th>Fecha</th>';
                $html .= '<th>Estado</th>';
                $html .= '<th>Prioridad</th>';
                $html .= '<th>Monto Total</th>';
                $html .= '</tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                
                foreach ($purchaseRequests as $pr) {
                    $html .= '<tr>';
                    $html .= '<td><strong>' . e($pr->request_number) . '</strong></td>';
                    $html .= '<td>' . $pr->request_date->format('d/m/Y') . '</td>';
                    $html .= '<td><span class="badge bg-info">' . e($pr->status) . '</span></td>';
                    $html .= '<td><span class="badge bg-warning">' . e($pr->priority) . '</span></td>';
                    $html .= '<td class="text-end"><strong>$' . number_format($pr->total_amount, 2) . '</strong></td>';
                    $html .= '</tr>';
                }
                
                $html .= '</tbody>';
                $html .= '</table>';
                $html .= '</div>';
                
                return $html;
            });
    }
}
