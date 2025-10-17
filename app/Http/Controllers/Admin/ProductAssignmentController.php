<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeneralRequest;
use App\Models\GeneralRequestDetail;
use App\Models\StockLevel;
use App\Models\InventoryMovement;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class ProductAssignmentController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\GeneralRequest::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/product-assignment');
        CRUD::setEntityNameStrings('asignación de productos', 'asignaciones de productos');
    }

    /**
     * Define what happens when the List operation is loaded.
     */
    protected function setupListOperation()
    {
        // Filter only requests available for assignment
        CRUD::addClause('whereIn', 'status', ['creada', 'revisada_area']);
        
        // Add columns
        CRUD::column('number')->label('Número');
        CRUD::column('title')->label('Título');
        CRUD::addColumn([
            'name' => 'area',
            'label' => 'Área',
            'type' => 'select',
            'entity' => 'area',
            'attribute' => 'name',
            'model' => 'App\Models\ResponsibilityArea',
        ]);
        CRUD::addColumn([
            'name' => 'createdBy',
            'label' => 'Solicitante',
            'type' => 'select',
            'entity' => 'createdBy',
            'attribute' => 'name',
            'model' => 'App\Models\User',
        ]);
        CRUD::addColumn([
            'name' => 'priority',
            'label' => 'Prioridad',
            'type' => 'select_from_array',
            'options' => [
                'low' => 'Baja',
                'medium' => 'Media',
                'high' => 'Alta',
            ],
        ]);
        CRUD::addColumn([
            'name' => 'details_count',
            'label' => 'Productos',
            'type' => 'custom_html',
            'value' => function($entry) {
                $count = $entry->details->count();
                return '<span class="badge bg-info">' . $count . ' productos</span>';
            },
        ]);
        CRUD::column('created_at')->label('Fecha');
        CRUD::addColumn([
            'name' => 'status',
            'label' => 'Estado',
            'type' => 'select_from_array',
            'options' => [
                'creada' => 'Creada',
                'revisada_area' => 'Revisada por Área',
            ],
        ]);
        
        // Add custom button for assignment
        CRUD::addButton('line', 'assign', 'view', 'crud::buttons.assign_products', 'beginning');
    }

    /**
     * Show the assignment form for a specific general request.
     */
    public function showAssignment(GeneralRequest $generalRequest)
    {
        $generalRequest->load(['details.product', 'area', 'createdBy']);
        
        // Get all locations (deposits) for the dropdown
        $locations = Location::all();
        
        // Get stock levels for each product in the request
        $stockLevels = [];
        foreach ($generalRequest->details as $detail) {
            $stockLevels[$detail->product_id] = StockLevel::where('product_id', $detail->product_id)
                ->with('location')
                ->get();
        }

        return view('admin.product-assignment.assign', compact('generalRequest', 'locations', 'stockLevels'));
    }

    /**
     * Process the product assignment.
     */
    public function assign(Request $request, GeneralRequest $generalRequest)
    {
        $request->validate([
            'assignments' => 'required|array',
            'assignments.*.product_id' => 'required|exists:products,id',
            'assignments.*.location_id' => 'required|exists:locations,id',
            'assignments.*.assigned_quantity' => 'nullable|integer|min:0',
        ]);

        DB::beginTransaction();
        
        try {
            \Log::info('Iniciando procesamiento de asignaciones:', $request->assignments);
            
            foreach ($request->assignments as $assignment) {
                \Log::info('Procesando asignación:', $assignment);
                
                // Solo procesar productos con cantidad asignada
                if (empty($assignment['assigned_quantity']) || $assignment['assigned_quantity'] <= 0) {
                    \Log::info('Producto sin cantidad asignada, saltando:', $assignment);
                    continue;
                }
                
                $productId = $assignment['product_id'];
                $locationId = $assignment['location_id'];
                $assignedQuantity = $assignment['assigned_quantity'];
                
                \Log::info('Procesando producto seleccionado:', [
                    'product_id' => $productId,
                    'location_id' => $locationId,
                    'assigned_quantity' => $assignedQuantity
                ]);
                
                // Check total stock across all locations
                $totalStock = StockLevel::where('product_id', $productId)->sum('quantity');
                
                if ($totalStock < $assignedQuantity) {
                    throw new \Exception("No hay suficiente stock total para el producto. Stock disponible: {$totalStock}");
                }
                
                // Get the stock level for this product and location
                $stockLevel = StockLevel::where('product_id', $productId)
                    ->where('location_id', $locationId)
                    ->first();
                
                if (!$stockLevel || $stockLevel->quantity < $assignedQuantity) {
                    // If there's enough total stock but not in selected location, 
                    // we'll assign from multiple locations
                    $remainingToAssign = $assignedQuantity;
                    $stockLevels = StockLevel::where('product_id', $productId)
                        ->where('quantity', '>', 0)
                        ->orderBy('quantity', 'desc')
                        ->get();
                    
                    foreach ($stockLevels as $stock) {
                        if ($remainingToAssign <= 0) break;
                        
                        $toAssignFromThisLocation = min($remainingToAssign, $stock->quantity);
                        $stock->quantity -= $toAssignFromThisLocation;
                        $stock->last_updated_by = Auth::id();
                        $stock->save();
                        
                        \Log::info('Stock actualizado en ubicación:', [
                            'location_id' => $stock->location_id,
                            'cantidad_asignada' => $toAssignFromThisLocation,
                            'stock_restante' => $stock->quantity
                        ]);
                        
                        $remainingToAssign -= $toAssignFromThisLocation;
                        
                        // Create inventory movement for this location
                        $movement = InventoryMovement::create([
                            'product_id' => $productId,
                            'location_id' => $stock->location_id,
                            'quantity' => -$toAssignFromThisLocation,
                            'type' => 'uso',
                            'reference' => $generalRequest->number,
                            'user_id' => Auth::id(),
                            'notes' => "Asignación desde solicitud general: {$generalRequest->title} (ubicación: {$stock->location->name})",
                        ]);
                        
                        \Log::info('Movimiento de inventario creado desde múltiples ubicaciones:', $movement->toArray());
                    }
                } else {
                    // Update stock level (subtract assigned quantity)
                    $stockLevel->quantity -= $assignedQuantity;
                    $stockLevel->last_updated_by = Auth::id();
                    $stockLevel->save();
                }
                
                // Create inventory movement record only if assigned from selected location
                if ($stockLevel && $stockLevel->quantity >= $assignedQuantity) {
                    $movement = InventoryMovement::create([
                        'product_id' => $productId,
                        'location_id' => $locationId,
                        'quantity' => -$assignedQuantity, // Negative for usage
                        'type' => 'uso',
                        'reference' => $generalRequest->number,
                        'user_id' => Auth::id(),
                        'notes' => "Asignación desde solicitud general: {$generalRequest->title}",
                    ]);
                    \Log::info('Movimiento de inventario creado desde ubicación seleccionada:', $movement->toArray());
                }
                
                // Update the general request detail status
                $detail = GeneralRequestDetail::where('general_request_id', $generalRequest->id)
                    ->where('product_id', $productId)
                    ->first();
                
                if ($detail) {
                    $detail->status = 'Aprobada';
                    $detail->save();
                }
            }
            
            // Check if all details are assigned
            $unassignedDetails = GeneralRequestDetail::where('general_request_id', $generalRequest->id)
                ->where('status', '!=', 'Aprobada')
                ->count();
            
            if ($unassignedDetails == 0) {
                $generalRequest->status = 'completed';
                $generalRequest->save();
            }
            
            DB::commit();
            
            return redirect()->route('product-assignment.index')
                ->with('success', 'Productos asignados exitosamente.');
                
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Error al asignar productos: ' . $e->getMessage());
        }
    }

    /**
     * Get available stock for a product in a specific location.
     */
    public function getStock(Request $request)
    {
        $productId = $request->product_id;
        $locationId = $request->location_id;
        
        \Log::info('Buscando stock para producto:', ['product_id' => $productId, 'location_id' => $locationId]);
        
        // Buscar stock en la ubicación específica
        $stockLevel = StockLevel::where('product_id', $productId)
            ->where('location_id', $locationId)
            ->first();
        
        $availableQuantity = $stockLevel ? $stockLevel->quantity : 0;
        
        // También obtener el stock total en todas las ubicaciones
        $totalStock = StockLevel::where('product_id', $productId)->sum('quantity');
        
        // Obtener todas las ubicaciones donde hay stock para este producto
        $stockLocations = StockLevel::where('product_id', $productId)
            ->where('quantity', '>', 0)
            ->with('location')
            ->get();
        
        \Log::info('Stock encontrado:', [
            'product_id' => $productId, 
            'location_id' => $locationId,
            'stock_in_location' => $availableQuantity,
            'total_stock' => $totalStock,
            'stock_locations' => $stockLocations->pluck('location.name', 'location_id')->toArray()
        ]);
        
        return response()->json([
            'available_quantity' => $availableQuantity,
            'total_stock' => $totalStock,
            'stock_locations' => $stockLocations->map(function($stock) {
                return [
                    'location_id' => $stock->location_id,
                    'location_name' => $stock->location->name,
                    'quantity' => $stock->quantity
                ];
            })
        ]);
    }
}