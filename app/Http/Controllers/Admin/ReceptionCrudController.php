<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ReceptionRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use App\Models\Reception;
use App\Models\StockLevel;
use App\Models\Product;
use App\Models\Location;
use App\Models\Input;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Class ReceptionCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ReceptionCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Reception::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/reception');
        CRUD::setEntityNameStrings('recepción', 'recepciones');
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
        
        // Cargar relaciones para evitar N+1 queries
        CRUD::addClause('with', ['purchase_order', 'user']);

        // Columnas básicas para evitar errores
        CRUD::addColumn([
            'name' => 'purchase_order_id',
            'label' => 'Orden de Compra',
            'type' => 'select',
            'entity' => 'purchase_order',
            'attribute' => 'number',
            'model' => 'App\Models\PurchaseOrder',
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('purchase_order', function ($q) use ($searchTerm) {
                    $q->where('number', 'like', '%'.$searchTerm.'%');
                });
            },
        ]);
        CRUD::column('date')->label('Fecha');
        CRUD::column('according')->label('Conforme');
        CRUD::addColumn([
            'name' => 'area_manager_id',
            'label' => 'Responsable',
            'type' => 'select',
            'entity' => 'user',
            'attribute' => 'name',
            'model' => 'App\Models\User',
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('user', function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%'.$searchTerm.'%');
                });
            },
        ]);

        // Filtro personalizado por orden de compra usando parámetros de URL
        if (request()->has('orden_compra')) {
            $ordenCompraId = request()->get('orden_compra');
            if ($ordenCompraId) {
                CRUD::addClause('where', 'purchase_order_id', $ordenCompraId);
            }
        }

        // Filtro personalizado por fecha usando parámetros de URL
        if (request()->has('fecha')) {
            $fecha = request()->get('fecha');
            if ($fecha) {
                CRUD::addClause('whereDate', 'date', $fecha);
            }
        }

        // Filtro personalizado por conformidad usando parámetros de URL
        if (request()->has('conformidad')) {
            $conformidad = request()->get('conformidad');
            if ($conformidad) {
                CRUD::addClause('where', 'according', $conformidad);
            }
        }

        // Filtro personalizado por responsable usando parámetros de URL
        if (request()->has('responsable')) {
            $responsableId = request()->get('responsable');
            if ($responsableId) {
                CRUD::addClause('where', 'area_manager_id', $responsableId);
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
        CRUD::setValidation(ReceptionRequest::class);
        CRUD::addField([
            'name' => 'purchase_order_id',
            'label' => 'Orden de Compra',
            'type' => 'select',
            'entity' => 'purchase_order',
            'attribute' => 'number',
            'model' => 'App\Models\PurchaseOrder',
            'options' => function ($query) {
                // Filtrar solo las órdenes de compra que no tienen recepción
                return $query->whereDoesntHave('receptions')->get();
            },
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('purchase_order', function ($q) use ($searchTerm) {
                    $q->where('number', 'like', '%'.$searchTerm.'%');
                });
            },
        ]);
        CRUD::field('date')->label('Fecha');
        CRUD::field('according')->label('Conforme');
        CRUD::addField([
            'name' => 'area_manager_id',
            'label' => 'Responsable',
            'type' => 'select',
            'entity' => 'user',
            'attribute' => 'name',
            'model' => 'App\Models\User',
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('user', function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%'.$searchTerm.'%');
                });
            },
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
        
        // En el update, permitir mostrar la orden de compra actual además de las que no tienen recepción
        CRUD::modifyField('purchase_order_id', [
            'options' => function ($query) {
                // Intentar obtener el ID de la recepción actual desde la URL o el entry
                $currentReceptionId = request()->route('id') ?? $this->crud->getCurrentEntryId();
                $currentPurchaseOrderId = null;
                
                if ($currentReceptionId) {
                    $currentReception = Reception::find($currentReceptionId);
                    if ($currentReception) {
                        $currentPurchaseOrderId = $currentReception->purchase_order_id;
                    }
                }
                
                // Filtrar órdenes de compra que no tienen recepción O la orden de compra actual
                $query->where(function ($q) use ($currentPurchaseOrderId) {
                    $q->whereDoesntHave('receptions');
                    if ($currentPurchaseOrderId) {
                        $q->orWhere('id', $currentPurchaseOrderId);
                    }
                });
                
                return $query->get();
            },
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store()
    {
        $this->crud->hasAccessOrFail('create');
        
        // Execute the FormRequest authorization and validation
        $request = $this->crud->validateRequest();
        
        // Verificar si ya existe una recepción para esta orden de compra (validación adicional)
        $purchaseOrderId = $request->input('purchase_order_id');
        if ($purchaseOrderId) {
            $existingReception = Reception::where('purchase_order_id', $purchaseOrderId)->first();
            if ($existingReception) {
                \Alert::error('Esta orden de compra ya tiene una recepción registrada.')->flash();
                return redirect()->back()->withInput();
            }
        }
        
        // Insert the entry
        $entry = $this->crud->create($this->crud->getStrippedSaveRequest($request));
        $this->data['entry'] = $this->crud->entry = $entry;

        // Process stock level deduction (es una recepción nueva)
        $this->processStockLevelDeduction($entry, true);

        // Show a success message
        \Alert::success(trans('backpack::crud.insert_success'))->flash();

        // Save the redirect choice for next time
        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($entry->getKey());
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update()
    {
        $this->crud->hasAccessOrFail('update');
        
        // Execute the FormRequest authorization and validation
        $request = $this->crud->validateRequest();
        
        // Verificar si se está cambiando a una orden de compra que ya tiene recepción (validación adicional)
        $purchaseOrderId = $request->input('purchase_order_id');
        $currentReceptionId = $this->crud->getCurrentEntryId();
        
        if ($purchaseOrderId && $currentReceptionId) {
            $existingReception = Reception::where('purchase_order_id', $purchaseOrderId)
                ->where('id', '!=', $currentReceptionId)
                ->first();
            
            if ($existingReception) {
                \Alert::error('Esta orden de compra ya tiene una recepción registrada.')->flash();
                return redirect()->back()->withInput();
            }
        }
        
        // Update the entry
        $entry = $this->crud->update(
            $this->crud->getCurrentEntryId(),
            $this->crud->getStrippedSaveRequest($request)
        );
        $this->data['entry'] = $this->crud->entry = $entry;

        // Process stock level deduction (es una actualización)
        // Solo procesar si la recepción fue creada recientemente (mismo timestamp)
        $isNew = $entry->created_at->eq($entry->updated_at);
        $this->processStockLevelDeduction($entry, $isNew);

        // Show a success message
        \Alert::success(trans('backpack::crud.update_success'))->flash();

        // Save the redirect choice for next time
        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($entry->getKey());
    }

    /**
     * Process stock level deduction for reception
     *
     * @param Reception $reception
     * @param bool $isNew Indica si es una recepción nueva
     * @return void
     */
    protected function processStockLevelDeduction(Reception $reception, $isNew = false)
    {
        try {
            // Solo procesar si es una recepción nueva o si no se ha procesado antes
            // Para recepciones existentes, verificamos si fue creada y actualizada al mismo tiempo
            if (!$isNew && $reception->created_at->ne($reception->updated_at)) {
                // La recepción fue actualizada después de ser creada
                // Por ahora, solo procesamos si es nueva para evitar descuentos duplicados
                // TODO: Implementar lógica para revertir y recalcular si es necesario
                Log::info('Recepción actualizada - saltando procesamiento de stock para evitar descuentos duplicados', [
                    'reception_id' => $reception->id
                ]);
                return;
            }

            // Cargar la orden de compra con sus detalles
            $purchaseOrder = $reception->purchase_order()->with('details.input')->first();
            
            if (!$purchaseOrder) {
                Log::warning('Orden de compra no encontrada para recepción', ['reception_id' => $reception->id]);
                return;
            }

            // Obtener la ubicación basándose en el área de responsabilidad
            // Intentar obtener la ubicación desde el área de responsabilidad del usuario
            $location = $this->getLocationForReception($reception);
            
            if (!$location) {
                Log::warning('Ubicación no encontrada para recepción', ['reception_id' => $reception->id]);
                return;
            }

            $currentUser = backpack_user();
            
            // Procesar cada detalle de la orden de compra
            foreach ($purchaseOrder->details as $detail) {
                $input = $detail->input;
                
                if (!$input) {
                    Log::warning('Input no encontrado para detalle de orden de compra', [
                        'detail_id' => $detail->id,
                        'input_id' => $detail->input_id
                    ]);
                    continue;
                }

                // Buscar o crear el producto correspondiente al input
                $product = $this->findOrCreateProductFromInput($input);
                
                if (!$product) {
                    Log::warning('No se pudo obtener o crear producto desde input', [
                        'input_id' => $input->id,
                        'input_name' => $input->name
                    ]);
                    continue;
                }

                // Buscar el stock level para este producto y ubicación
                $stockLevel = StockLevel::where('product_id', $product->id)
                    ->where('location_id', $location->id)
                    ->first();

                if ($stockLevel) {
                    // Descontar la cantidad del stock
                    $quantityToDeduct = $detail->quantity;
                    $newQuantity = max(0, $stockLevel->quantity - $quantityToDeduct);
                    
                    $stockLevel->quantity = $newQuantity;
                    $stockLevel->last_updated_by = $currentUser ? $currentUser->id : null;
                    $stockLevel->save();

                    Log::info('Stock descontado exitosamente', [
                        'reception_id' => $reception->id,
                        'product_id' => $product->id,
                        'location_id' => $location->id,
                        'quantity_deducted' => $quantityToDeduct,
                        'new_quantity' => $newQuantity
                    ]);
                } else {
                    Log::warning('Stock level no encontrado para producto y ubicación', [
                        'product_id' => $product->id,
                        'location_id' => $location->id,
                        'product_name' => $product->name,
                        'location_name' => $location->name
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error al procesar descuento de stock en recepción', [
                'reception_id' => $reception->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Get location for reception based on area manager
     *
     * @param Reception $reception
     * @return Location|null
     */
    protected function getLocationForReception(Reception $reception)
    {
        // Intentar obtener la ubicación desde el área de responsabilidad del usuario
        $user = $reception->user;
        
        if ($user) {
            // Buscar áreas de responsabilidad que tengan este usuario como responsable
            $responsibilityArea = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->first();
            
            if ($responsibilityArea) {
                // Intentar encontrar una ubicación con el mismo nombre que el área de responsabilidad
                $location = Location::where('name', $responsibilityArea->name)->first();
                
                if ($location) {
                    return $location;
                }
            }
        }

        // Si no se encuentra, usar una ubicación por defecto (Insumos Generales)
        $defaultLocation = Location::where('name', 'Insumos Generales')->first();
        
        return $defaultLocation;
    }

    /**
     * Find or create product from input
     *
     * @param Input $input
     * @return Product|null
     */
    protected function findOrCreateProductFromInput(Input $input)
    {
        // Intentar encontrar un producto con el mismo nombre
        $product = Product::where('name', $input->name)->first();
        
        if ($product) {
            return $product;
        }

        // Si no existe, crear uno nuevo
        try {
            $product = Product::create([
                'name' => $input->name,
                'description' => $input->description,
                'unit_measurement' => $input->unit ?? 'unidad',
                'minimum_stock' => 0,
                'category_id' => 1, // Categoría por defecto
            ]);

            Log::info('Producto creado desde Input', [
                'input_id' => $input->id,
                'product_id' => $product->id,
                'name' => $product->name
            ]);

            return $product;
        } catch (\Exception $e) {
            Log::error('Error al crear producto desde Input', [
                'input_id' => $input->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
