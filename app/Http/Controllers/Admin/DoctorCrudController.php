<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\DoctorRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class DoctorCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class DoctorCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Doctor::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/doctor');
        CRUD::setEntityNameStrings('médico', 'médicos');
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
        CRUD::column('last_name')->label('Apellido');  
        CRUD::column('first_name')->label('Nombre');  
        CRUD::column('license')->label('Matrícula');  
        CRUD::column('specialty')->label('Especialidad');  
        CRUD::column('phone')->label('Teléfono');  
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
        CRUD::setValidation(DoctorRequest::class);
        //CRUD::setFromDb(); // set fields from db columns.
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
            'label' => 'Matrícula',
            'type' => 'text',
            'name' => 'license',
            'wrapper'   => ['class' => 'col-12 col-lg-4'],
        ]);
        CRUD::addField([
            'label' => 'Especialidad',
            'type' => 'text',
            'name' => 'specialty',
            'wrapper'   => ['class' => 'col-12 col-lg-4'],
        ]);
        CRUD::addField([
            'label' => 'Teléfono',
            'type' => 'number',
            'name' => 'phone',
            'wrapper'   => ['class' => 'col-12 col-lg-4'],
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
