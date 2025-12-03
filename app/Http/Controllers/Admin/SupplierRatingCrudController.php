<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\SupplierRatingRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class SupplierRatingCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class SupplierRatingCrudController extends CrudController
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
        CRUD::setModel(\App\Models\SupplierRating::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/supplier-rating');
        CRUD::setEntityNameStrings('calificación de proveedor', 'calificaciones de proveedores');
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
        
        // Habilitar tabla responsiva
        CRUD::enableResponsiveTable();
        
        // Filtrar calificaciones para role_admin_institucion (solo sus propias calificaciones)
        $user = backpack_user();
        if ($user && $user->hasRole('role_admin_institucion', 'backpack')) {
            CRUD::addClause('where', 'rated_by', $user->id);
        }
        
        // Reemplazar botones de editar y eliminar con versiones personalizadas para role_admin_institucion
        if ($user && $user->hasRole('role_admin_institucion', 'backpack')) {
            CRUD::removeButton('update');
            CRUD::removeButton('delete');
            CRUD::addButton('line', 'edit_rating', 'view', 'crud::buttons.edit_supplier_rating', 'beginning');
            CRUD::addButton('line', 'delete_rating', 'view', 'crud::buttons.delete_supplier_rating', 'end');
        }
        
        // Cargar relaciones para evitar N+1 queries
        CRUD::addClause('with', ['supplier', 'ratedBy', 'purchaseOrder']);

        CRUD::addColumn([
            'name' => 'supplier',
            'label' => 'Proveedor',
            'type' => 'closure',
            'function' => function($entry) {
                return $entry->supplier ? $entry->supplier->company_name : 'N/A';
            },
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('supplier', function ($q) use ($searchTerm) {
                    $q->where('company_name', 'like', '%'.$searchTerm.'%');
                });
            },
        ]);

        CRUD::column('evaluation_date')->label('Fecha de Evaluación')
            ->type('date');

        CRUD::addColumn([
            'name' => 'quality_rating',
            'label' => 'Calidad',
            'type' => 'closure',
            'function' => function($entry) {
                $rating = $entry->quality_rating ?? 0;
                $stars = '';
                for ($i = 1; $i <= 5; $i++) {
                    if ($i <= $rating) {
                        $stars .= '<i class="la la-star text-warning"></i>';
                    } else {
                        $stars .= '<i class="la la-star text-secondary"></i>';
                    }
                }
                return $stars;
            },
            'escaped' => false,
        ]);

        CRUD::addColumn([
            'name' => 'price_rating',
            'label' => 'Precio',
            'type' => 'closure',
            'function' => function($entry) {
                $rating = $entry->price_rating ?? 0;
                $stars = '';
                for ($i = 1; $i <= 5; $i++) {
                    if ($i <= $rating) {
                        $stars .= '<i class="la la-star text-warning"></i>';
                    } else {
                        $stars .= '<i class="la la-star text-secondary"></i>';
                    }
                }
                return $stars;
            },
            'escaped' => false,
        ]);

        CRUD::addColumn([
            'name' => 'delivery_time_rating',
            'label' => 'Tiempo Entrega',
            'type' => 'closure',
            'function' => function($entry) {
                $rating = $entry->delivery_time_rating ?? 0;
                $stars = '';
                for ($i = 1; $i <= 5; $i++) {
                    if ($i <= $rating) {
                        $stars .= '<i class="la la-star text-warning"></i>';
                    } else {
                        $stars .= '<i class="la la-star text-secondary"></i>';
                    }
                }
                return $stars;
            },
            'escaped' => false,
        ]);

        CRUD::addColumn([
            'name' => 'service_rating',
            'label' => 'Servicio',
            'type' => 'closure',
            'function' => function($entry) {
                $rating = $entry->service_rating ?? 0;
                $stars = '';
                for ($i = 1; $i <= 5; $i++) {
                    if ($i <= $rating) {
                        $stars .= '<i class="la la-star text-warning"></i>';
                    } else {
                        $stars .= '<i class="la la-star text-secondary"></i>';
                    }
                }
                return $stars;
            },
            'escaped' => false,
        ]);

        CRUD::addColumn([
            'name' => 'overall_rating',
            'label' => 'Calificación General',
            'type' => 'closure',
            'function' => function($entry) {
                $rating = $entry->overall_rating ?? 0;
                $stars = '';
                for ($i = 1; $i <= 5; $i++) {
                    if ($i <= $rating) {
                        $stars .= '<i class="la la-star text-warning"></i>';
                    } else {
                        $stars .= '<i class="la la-star text-secondary"></i>';
                    }
                }
                $avg = $entry->average_rating ?? 0;
                return $stars . ' <small class="text-muted">(' . number_format($avg, 1) . ')</small>';
            },
            'escaped' => false,
        ]);

        CRUD::addColumn([
            'name' => 'ratedBy',
            'label' => 'Evaluado por',
            'type' => 'closure',
            'function' => function($entry) {
                return $entry->ratedBy ? $entry->ratedBy->name : 'N/A';
            },
        ]);

        CRUD::addColumn([
            'name' => 'purchaseOrder',
            'label' => 'Orden de Compra',
            'type' => 'closure',
            'function' => function($entry) {
                return $entry->purchaseOrder ? $entry->purchaseOrder->number : 'N/A';
            },
        ]);

        // Filtros personalizados usando parámetros de URL
        if (request()->has('proveedor')) {
            $proveedorId = request()->get('proveedor');
            if ($proveedorId) {
                CRUD::addClause('where', 'supplier_id', $proveedorId);
            }
        }

        // Filtro personalizado por fecha desde
        if (request()->has('fecha_desde')) {
            $fechaDesde = request()->get('fecha_desde');
            if ($fechaDesde) {
                CRUD::addClause('where', 'evaluation_date', '>=', $fechaDesde);
            }
        }

        // Filtro personalizado por fecha hasta
        if (request()->has('fecha_hasta')) {
            $fechaHasta = request()->get('fecha_hasta');
            if ($fechaHasta) {
                CRUD::addClause('where', 'evaluation_date', '<=', $fechaHasta);
            }
        }
    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(SupplierRatingRequest::class);

        CRUD::addField([
            'name' => 'supplier_id',
            'label' => 'Proveedor',
            'type' => 'select',
            'entity' => 'supplier',
            'model' => 'App\Models\Supplier',
            'attribute' => 'company_name',
            'allows_null' => false,
        ]);

        CRUD::addField([
            'name' => 'purchase_order_id',
            'label' => 'Orden de Compra (Opcional)',
            'type' => 'select',
            'entity' => 'purchaseOrder',
            'model' => 'App\Models\PurchaseOrder',
            'attribute' => 'number',
            'allows_null' => true,
        ]);

        CRUD::addField([
            'name' => 'evaluation_date',
            'label' => 'Fecha de Evaluación',
            'type' => 'date',
            'default' => now()->format('Y-m-d'),
        ]);

        CRUD::addField([
            'name' => 'quality_rating',
            'label' => 'Calificación de Calidad',
            'type' => 'select_from_array',
            'options' => [1 => '1 - Muy Malo', 2 => '2 - Malo', 3 => '3 - Regular', 4 => '4 - Bueno', 5 => '5 - Excelente'],
            'default' => 3,
            'allows_null' => false,
        ]);

        CRUD::addField([
            'name' => 'price_rating',
            'label' => 'Calificación de Precio',
            'type' => 'select_from_array',
            'options' => [1 => '1 - Muy Malo', 2 => '2 - Malo', 3 => '3 - Regular', 4 => '4 - Bueno', 5 => '5 - Excelente'],
            'default' => 3,
            'allows_null' => false,
        ]);

        CRUD::addField([
            'name' => 'delivery_time_rating',
            'label' => 'Calificación de Tiempo de Entrega',
            'type' => 'select_from_array',
            'options' => [1 => '1 - Muy Malo', 2 => '2 - Malo', 3 => '3 - Regular', 4 => '4 - Bueno', 5 => '5 - Excelente'],
            'default' => 3,
            'allows_null' => false,
        ]);

        CRUD::addField([
            'name' => 'service_rating',
            'label' => 'Calificación de Servicio al Cliente',
            'type' => 'select_from_array',
            'options' => [1 => '1 - Muy Malo', 2 => '2 - Malo', 3 => '3 - Regular', 4 => '4 - Bueno', 5 => '5 - Excelente'],
            'default' => 3,
            'allows_null' => false,
        ]);

        CRUD::addField([
            'name' => 'overall_rating',
            'label' => 'Calificación General',
            'type' => 'select_from_array',
            'options' => [1 => '1 - Muy Malo', 2 => '2 - Malo', 3 => '3 - Regular', 4 => '4 - Bueno', 5 => '5 - Excelente'],
            'default' => 3,
            'allows_null' => false,
        ]);

        CRUD::addField([
            'name' => 'comments',
            'label' => 'Comentarios',
            'type' => 'textarea',
            'attributes' => [
                'rows' => 4,
                'placeholder' => 'Comentarios adicionales sobre la evaluación del proveedor...',
            ],
        ]);

        // Establecer automáticamente el usuario que califica
        CRUD::field('rated_by')->type('hidden')->default(backpack_user()->id);
    }

    /**
     * Define what happens when the Update operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        // Verificar que el usuario solo pueda editar sus propias calificaciones
        $user = backpack_user();
        $entry = $this->crud->getCurrentEntry();
        
        if ($user && $user->hasRole('role_admin_institucion', 'backpack')) {
            if ($entry && $entry->rated_by != $user->id) {
                abort(403, 'Solo puedes editar tus propias calificaciones.');
            }
        }
        
        $this->setupCreateOperation();
    }
    
    /**
     * Update the specified resource in storage.
     */
    public function update()
    {
        $this->crud->hasAccessOrFail('update');
        
        // Verificar que el usuario solo pueda editar sus propias calificaciones
        $user = backpack_user();
        $entry = $this->crud->getCurrentEntry();
        
        if ($user && $user->hasRole('role_admin_institucion', 'backpack')) {
            if ($entry && $entry->rated_by != $user->id) {
                abort(403, 'Solo puedes editar tus propias calificaciones.');
            }
        }
        
        return $this->crud->update();
    }
    
    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $this->crud->hasAccessOrFail('delete');
        
        // Verificar que el usuario solo pueda eliminar sus propias calificaciones
        $user = backpack_user();
        $entry = $this->crud->getEntry($id);
        
        if ($user && $user->hasRole('role_admin_institucion', 'backpack')) {
            if ($entry && $entry->rated_by != $user->id) {
                abort(403, 'Solo puedes eliminar tus propias calificaciones.');
            }
        }
        
        return $this->crud->delete($id);
    }

    /**
     * Define what happens when the Show operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-show
     * @return void
     */
    protected function setupShowOperation()
    {
        CRUD::column('supplier')->label('Proveedor')
            ->attribute('company_name');

        CRUD::column('evaluation_date')->label('Fecha de Evaluación')
            ->type('date');

        CRUD::addColumn([
            'name' => 'quality_rating',
            'label' => 'Calidad',
            'type' => 'closure',
            'function' => function($entry) {
                $rating = $entry->quality_rating ?? 0;
                $stars = '';
                for ($i = 1; $i <= 5; $i++) {
                    if ($i <= $rating) {
                        $stars .= '<i class="la la-star text-warning"></i>';
                    } else {
                        $stars .= '<i class="la la-star text-secondary"></i>';
                    }
                }
                return $stars . ' (' . $rating . '/5)';
            },
            'escaped' => false,
        ]);

        CRUD::addColumn([
            'name' => 'price_rating',
            'label' => 'Precio',
            'type' => 'closure',
            'function' => function($entry) {
                $rating = $entry->price_rating ?? 0;
                $stars = '';
                for ($i = 1; $i <= 5; $i++) {
                    if ($i <= $rating) {
                        $stars .= '<i class="la la-star text-warning"></i>';
                    } else {
                        $stars .= '<i class="la la-star text-secondary"></i>';
                    }
                }
                return $stars . ' (' . $rating . '/5)';
            },
            'escaped' => false,
        ]);

        CRUD::addColumn([
            'name' => 'delivery_time_rating',
            'label' => 'Tiempo de Entrega',
            'type' => 'closure',
            'function' => function($entry) {
                $rating = $entry->delivery_time_rating ?? 0;
                $stars = '';
                for ($i = 1; $i <= 5; $i++) {
                    if ($i <= $rating) {
                        $stars .= '<i class="la la-star text-warning"></i>';
                    } else {
                        $stars .= '<i class="la la-star text-secondary"></i>';
                    }
                }
                return $stars . ' (' . $rating . '/5)';
            },
            'escaped' => false,
        ]);

        CRUD::addColumn([
            'name' => 'service_rating',
            'label' => 'Servicio',
            'type' => 'closure',
            'function' => function($entry) {
                $rating = $entry->service_rating ?? 0;
                $stars = '';
                for ($i = 1; $i <= 5; $i++) {
                    if ($i <= $rating) {
                        $stars .= '<i class="la la-star text-warning"></i>';
                    } else {
                        $stars .= '<i class="la la-star text-secondary"></i>';
                    }
                }
                return $stars . ' (' . $rating . '/5)';
            },
            'escaped' => false,
        ]);

        CRUD::addColumn([
            'name' => 'overall_rating',
            'label' => 'Calificación General',
            'type' => 'closure',
            'function' => function($entry) {
                $rating = $entry->overall_rating ?? 0;
                $stars = '';
                for ($i = 1; $i <= 5; $i++) {
                    if ($i <= $rating) {
                        $stars .= '<i class="la la-star text-warning"></i>';
                    } else {
                        $stars .= '<i class="la la-star text-secondary"></i>';
                    }
                }
                return $stars . ' (' . $rating . '/5)';
            },
            'escaped' => false,
        ]);

        CRUD::addColumn([
            'name' => 'average_rating',
            'label' => 'Promedio General',
            'type' => 'closure',
            'function' => function($entry) {
                return '<strong>' . number_format($entry->average_rating, 2) . '</strong> - ' . $entry->rating_label;
            },
            'escaped' => false,
        ]);

        CRUD::column('comments')->label('Comentarios')
            ->type('textarea');

        CRUD::column('ratedBy')->label('Evaluado por')
            ->attribute('name');

        CRUD::column('purchaseOrder')->label('Orden de Compra')
            ->attribute('number')
            ->default('N/A');

        CRUD::column('created_at')->label('Creado')
            ->type('datetime');

        CRUD::column('updated_at')->label('Actualizado')
            ->type('datetime');
    }

    /**
     * Render stars for rating display (static method for closures)
     */
    private function renderStarsColumn($rating)
    {
        $stars = '';
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $rating) {
                $stars .= '<i class="la la-star text-warning"></i>';
            } else {
                $stars .= '<i class="la la-star text-secondary"></i>';
            }
        }
        return $stars;
    }

    /**
     * Render stars for rating display
     */
    private function renderStars($rating)
    {
        return $this->renderStarsColumn($rating);
    }
}

