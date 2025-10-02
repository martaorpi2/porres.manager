<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\AnalysisOrderRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class AnalysisOrderCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class AnalysisOrderCrudController extends CrudController
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
        CRUD::setModel(\App\Models\AnalysisOrder::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/analysis-order');
        CRUD::setEntityNameStrings('orden de análisis', 'ordenes de análisis');
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
        //CRUD::setFromDb(); // set columns from db columns.
        CRUD::column('patient_id')->label('Paciente');  
        CRUD::column('doctor_id')->label('Médico Solicitante');  
        CRUD::column('date')->label('Fecha');  
        CRUD::column('total_price')->label('Monto Total');  
        CRUD::column('priority')->label('Prioridad');  
        CRUD::column('observations')->label('Observaciones');  
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
        CRUD::setValidation(AnalysisOrderRequest::class);
        //CRUD::setFromDb(); // set fields from db columns.
        CRUD::addField([
            'label'     => 'Paciente',
            'type'      => 'select',
            'name'      => 'patient_id',
            'entity'    => 'patient',
            'attribute' => 'full_name',
            'model'     => \App\Models\Patient::class,
            'wrapper'   => [
                'class' => 'form-group col-12 col-lg-4'
            ],
        ]);
        CRUD::addField([
            'label'     => 'Médico Solicitante',
            'type'      => 'select',
            'name'      => 'doctor_id',
            'entity'    => 'doctor',
            'attribute' => 'full_name',
            'model'     => \App\Models\Doctor::class,
            'wrapper'   => [
                'class' => 'form-group col-12 col-lg-4'
            ],
        ]);
        CRUD::addField([
            'label' => 'Fecha',
            'type' => 'date',
            'name' => 'date',
            'wrapper'   => ['class' => 'col-12 col-lg-4'],
        ]);
        CRUD::addField([
            'label' => 'Monto Total',
            'type' => 'number',
            'attributes' => ['step' => 0.01],
            'name' => 'total_price', // the method that defines the relationship in your Model
            'wrapper'   => [
                'class' => 'col-12 col-lg-4'
            ],
        ]);
        CRUD::addField([
            'label' => 'Prioridad',
            'type' => 'enum',
            'name' => 'priority',
            'wrapper'   => ['class' => 'col-12 col-lg-4'],
        ]);
        CRUD::addField([
            'label' => 'Observaciones',
            'type' => 'textarea',
            'name' => 'observations',
        ]);
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
        $this->setupCreateOperation();
    }
}
