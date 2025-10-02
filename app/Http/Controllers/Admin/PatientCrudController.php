<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\PatientRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class PatientCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class PatientCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Patient::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/patient');
        CRUD::setEntityNameStrings('paciente', 'pacientes');
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
        CRUD::column('dni')->label('DNI');  
        CRUD::column('last_name')->label('Apellido');  
        CRUD::column('first_name')->label('Nombre');  
        CRUD::column('birth_date')->label('Fecha de Nacimiento');  
        CRUD::column('gender')->label('Sexo');  
        CRUD::column('address')->label('Dirección');  
        CRUD::column('phone')->label('Teléfono');  
        CRUD::column('email')->label('Correo');
        CRUD::column('social_work')->label('Obra Social');
        CRUD::column('affiliate_number')->label('Nro Afiliado');
        /**
         * Columns can be defined using the fluent syntax:
         * - CRUD::column('price')->type('number');
         */
        /*$this->crud->addFilter([
            'name'  => 'social_work_id',
            'type'  => 'dropdown',
            'label' => 'Obra Social'
        ],
        \App\Models\SocialWork::pluck('name', 'id')->toArray(),
        function($value) {
            $this->crud->addClause('where', 'social_work_id', $value);
        });

        $this->crud->addFilter([
            'name'  => 'last_name',
            'type'  => 'text',
            'label' => 'Apellido',
        ],
        false,
        function($value) {
            $this->crud->addClause('where', 'last_name', 'LIKE', "%$value%");
        });*/
    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(PatientRequest::class);
        //CRUD::setFromDb(); // set fields from db columns.
        CRUD::addField([
            'label' => 'DNI',
            'type' => 'number',
            //'attributes' => ['step' => 0.01],
            'name' => 'dni',
            'wrapper'   => [
                'class' => 'col-12 col-lg-4'
            ],
        ]);
        CRUD::addField([
            'label' => 'Apellido',
            'type' => 'text',
            'name' => 'last_name',
            'wrapper'   => ['class' => 'col-12 col-lg-4'],
        ]);
        CRUD::addField([
            'label' => 'Nombre',
            'type' => 'text',
            'name' => 'first_name',
            'wrapper'   => ['class' => 'col-12 col-lg-4'],
        ]);
        CRUD::addField([
            'label' => 'Fecha de Nacimiento',
            'type' => 'date',
            'name' => 'birth_date',
            'wrapper'   => ['class' => 'col-12 col-lg-4'],
        ]);
        CRUD::addField([
            'label' => 'Sexo',
            'type' => 'enum',
            'name' => 'gender',
            'wrapper'   => ['class' => 'col-12 col-lg-4'],
        ]);
        CRUD::addField([
            'label' => 'Teléfono',
            'type' => 'number',
            'name' => 'phone',
            'wrapper'   => ['class' => 'col-12 col-lg-4'],
        ]);
        CRUD::addField([
            'label' => 'Dirección',
            'type' => 'textarea',
            'name' => 'address',
        ]);
        CRUD::addField([
            'label' => 'Correo',
            'type' => 'text',
            'name' => 'email',
            'wrapper'   => ['class' => 'col-12 col-lg-4'],
        ]);
        CRUD::addField([
            'label'     => 'Obra Social',
            'type'      => 'select',
            'name'      => 'social_work_id',
            'entity'    => 'social_work',
            'attribute' => 'name',
            'model'     => \App\Models\SocialWork::class,
            'wrapper'   => [
                'class' => 'form-group col-12 col-lg-4'
            ],
        ]);
        CRUD::addField([
            'label' => 'Nro de Afiliado',
            'type' => 'number',
            'name' => 'affiliate_number',
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
