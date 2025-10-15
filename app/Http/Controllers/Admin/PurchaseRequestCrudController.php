<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class PurchaseRequestCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class PurchaseRequestCrudController extends CrudController
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
        CRUD::setModel(\App\Models\PurchaseRequest::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/purchase-request');
        CRUD::setEntityNameStrings('solicitud de compra', 'solicitudes de compra');
    }

    /**
     * Define what happens when the List operation is loaded.
     * 
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::addClause('with', ['responsibilityArea', 'requestingUser', 'details']);
        
        CRUD::column('request_number')->label('Número de Solicitud');
        CRUD::column('request_date')->label('Fecha');
        CRUD::column('responsibilityArea.name')->label('Área');
        CRUD::column('requestingUser.name')->label('Solicitante');
        CRUD::column('status')->label('Estado');
        CRUD::column('priority')->label('Prioridad');
        CRUD::column('total_amount')->label('Monto Total')->type('number')->decimals(2)->prefix('$');
        
        // Agregar columna personalizada para mostrar cantidad de productos
        CRUD::column('details_count')->label('Productos')->type('custom_html')
            ->value(function($entry) {
                $count = $entry->details->count();
                return '<span class="badge bg-info">' . $count . ' productos</span>';
            });

        // Botón para generar planilla comparativa
        CRUD::addButton('line', 'comparative_excel', 'view', 'crud::buttons.comparative_excel', 'end');
    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        // Verificar si viene de una solicitud general
        $convertedFrom = request()->get('converted_from');
        $generalRequest = null;
        
        if ($convertedFrom) {
            $generalRequest = \App\Models\GeneralRequest::find($convertedFrom);
        }

        CRUD::field('request_number')->label('Número de Solicitud')->default(function() {
            return \App\Models\PurchaseRequest::generateNextNumber();
        })->attributes(['readonly' => 'readonly']);
        
        CRUD::field('request_date')->label('Fecha de Solicitud')->type('date')->default(now());
        
        CRUD::field('responsibility_area_id')->label('Área de Responsabilidad')
            ->type('select')
            ->model('App\Models\ResponsibilityArea')
            ->attribute('name')
            ->default($generalRequest ? $generalRequest->area_id : null)
            ->validationRules('required|exists:responsibility_areas,id');
            
        CRUD::field('requesting_user_id')->label('Usuario Solicitante')
            ->type('select')
            ->model('App\Models\User')
            ->attribute('name')
            ->default($generalRequest ? $generalRequest->created_by : auth()->id())
            ->validationRules('required|exists:users,id');
            
        CRUD::field('priority')->label('Prioridad')
            ->type('select_from_array')
            ->options([
                'Baja' => 'Baja',
                'Media' => 'Media',
                'Alta' => 'Alta',
                'Urgente' => 'Urgente'
            ])
            ->default($generalRequest ? $generalRequest->priority : 'Media');
            
        CRUD::field('justification')->label('Justificación')->type('textarea')
            ->default($generalRequest ? $generalRequest->description : null);
        CRUD::field('observations')->label('Observaciones')->type('textarea');
        
        // Campo oculto para la conversión
        if ($convertedFrom) {
            CRUD::field('converted_from_general_request_id')->type('hidden')->value($convertedFrom);
            
            // Mostrar información de la solicitud general
            CRUD::field('general_request_info')->label('Información de Solicitud General')->type('custom_html')
                ->value(function() use ($generalRequest) {
                    if ($generalRequest) {
                        return '<div class="alert alert-info">
                            <h5><i class="la la-info-circle"></i> Conversión desde Solicitud General</h5>
                            <p><strong>Número:</strong> ' . e($generalRequest->number) . '</p>
                            <p><strong>Título:</strong> ' . e($generalRequest->title) . '</p>
                            <p><strong>Descripción:</strong> ' . e($generalRequest->description) . '</p>
                        </div>';
                    }
                    return '';
                });
        }
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
        
        // Agregar campos adicionales para actualización
        CRUD::field('status')->label('Estado')
            ->type('select_from_array')
            ->options([
                'Pendiente' => 'Pendiente',
                'Aprobada' => 'Aprobada',
                'Rechazada' => 'Rechazada',
                'En Proceso' => 'En Proceso',
                'Completada' => 'Completada'
            ]);
            
        CRUD::field('approved_by')->label('Aprobado por')
            ->type('select')
            ->model('App\Models\User')
            ->attribute('name');
            
        CRUD::field('approved_date')->label('Fecha de Aprobación')->type('date');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store()
    {
        $this->crud->hasAccessOrFail('create');

        // execute the FormRequest authorization and validation, if one is required
        $request = $this->crud->validateRequest();

        // register any Model Events defined on fields
        $this->crud->registerFieldEvents();

        // insert item in the db
        $item = $this->crud->create($this->crud->getStrippedSaveRequest($request));
        $this->data['entry'] = $this->crud->entry = $item;

        // show a success message
        \Alert::success(trans('backpack::crud.insert_success'))->flash();

        // save the redirect choice for next time
        $this->crud->setSaveAction();

        // Si viene de una solicitud general, actualizar su estado
        if ($item->converted_from_general_request_id) {
            $generalRequest = \App\Models\GeneralRequest::find($item->converted_from_general_request_id);
            if ($generalRequest) {
                $generalRequest->update(['status' => 'convertida_a_compra']);
            }
        }

        return $this->crud->performSaveAction($item->getKey());
    }

    /**
     * Generate comparative Excel file for purchase request quotes
     */
    public function generateComparativeExcel($id)
    {
        $purchaseRequest = \App\Models\PurchaseRequest::with([
            'details.product',
            'responsibilityArea'
        ])->findOrFail($id);

        // Get all market rates for products in this purchase request
        $productIds = $purchaseRequest->details->pluck('product_id')->toArray();
        $marketRates = \App\Models\MarketRate::with([
            'supplier',
            'quoteDetails.product'
        ])->whereHas('quoteDetails', function($query) use ($productIds) {
            $query->whereIn('product_id', $productIds);
        })->get();

        // Group market rates by supplier
        $suppliers = $marketRates->groupBy('supplier_id');
        
        // Create Excel file
        $filename = 'Planilla_Comparativa_' . $purchaseRequest->request_number . '_' . date('Y-m-d') . '.xlsx';
        $filePath = 'comparative_sheets/' . $filename;
        
        $this->generateExcelFile($purchaseRequest, $suppliers, $filePath);
        
        // Update purchase request with attachment
        $attachments = $purchaseRequest->attachments ?? [];
        $attachments[] = [
            'filename' => $filename,
            'path' => $filePath,
            'type' => 'comparative_sheet',
            'created_at' => now()->toISOString()
        ];
        $purchaseRequest->update(['attachments' => $attachments]);
        
        // Return download response
        return response()->download(storage_path('app/public/' . $filePath), $filename);
    }

    /**
     * Generate Excel file with PhpSpreadsheet
     */
    private function generateExcelFile($purchaseRequest, $suppliers, $filePath)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set title
        $sheet->setTitle('Planilla Comparativa');
        
        // Header information
        $sheet->setCellValue('A1', 'PLANILLA COMPARATIVA DE COTIZACIONES');
        $sheet->setCellValue('A2', 'Solicitud de Compra: ' . $purchaseRequest->request_number);
        $sheet->setCellValue('A3', 'Área: ' . $purchaseRequest->responsibilityArea->name);
        $sheet->setCellValue('A4', 'Fecha: ' . date('d/m/Y'));
        
        // Merge title cells
        $sheet->mergeCells('A1:Z1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        // Start data from row 6
        $currentRow = 6;
        
        // Create header row
        $header = ['Producto', 'Cantidad', 'Unidad'];
        $supplierColumns = [];
        $col = 'D'; // Start from column D
        
        foreach ($suppliers as $supplierId => $marketRates) {
            $supplier = $marketRates->first()->supplier;
            $supplierColumns[$supplierId] = [
                'name' => $supplier->company_name,
                'price_col' => $col,
                'subtotal_col' => chr(ord($col) + 1),
                'delivery_col' => chr(ord($col) + 2)
            ];
            
            $header[] = $supplier->company_name . ' - Precio Unit.';
            $header[] = $supplier->company_name . ' - Subtotal';
            $header[] = $supplier->company_name . ' - Plazo';
            
            $col = chr(ord($col) + 3);
        }
        
        $header[] = 'Recomendación';
        $header[] = 'Observaciones';
        
        // Write header
        $col = 'A';
        foreach ($header as $headerText) {
            $sheet->setCellValue($col . $currentRow, $headerText);
            $sheet->getStyle($col . $currentRow)->getFont()->setBold(true);
            $sheet->getStyle($col . $currentRow)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('4472C4');
            $sheet->getStyle($col . $currentRow)->getFont()->getColor()->setRGB('FFFFFF');
            $col++;
        }
        
        $currentRow++;
        
        // Data rows
        foreach ($purchaseRequest->details as $detail) {
            $row = [
                $detail->product->name ?? 'Producto no encontrado',
                $detail->requested_quantity,
                $detail->product->unit ?? 'Unidad'
            ];
            
            $bestOption = null;
            $bestPrice = null;
            $bestScore = 0;
            $observations = [];
            $supplierData = [];
            $recommendations = [];
            
            // Collect data for each supplier
            foreach ($suppliers as $supplierId => $marketRates) {
                $supplier = $marketRates->first()->supplier;
                $quoteDetail = null;
                
                // Find quote detail for this product
                foreach ($marketRates as $marketRate) {
                    $quoteDetail = $marketRate->quoteDetails->where('product_id', $detail->product_id)->first();
                    if ($quoteDetail) break;
                }
                
                if ($quoteDetail) {
                    $unitPrice = $quoteDetail->unit_price;
                    $subtotal = $quoteDetail->quantity * $unitPrice;
                    $deliveryTime = 15; // Default days
                    
                    $supplierData[$supplierId] = [
                        'name' => $supplier->company_name,
                        'unit_price' => $unitPrice,
                        'subtotal' => $subtotal,
                        'delivery_time' => $deliveryTime,
                        'score' => $this->calculateSupplierScore($subtotal, $deliveryTime)
                    ];
                    
                    $observations[] = $supplier->company_name . ': $' . number_format($subtotal, 2) . ' (' . $deliveryTime . ' días)';
                    
                    // Add to recommendations
                    $recommendations[] = [
                        'name' => $supplier->company_name,
                        'price' => $subtotal,
                        'delivery' => $deliveryTime,
                        'score' => $this->calculateSupplierScore($subtotal, $deliveryTime)
                    ];
                } else {
                    $supplierData[$supplierId] = [
                        'name' => $supplier->company_name,
                        'unit_price' => null,
                        'subtotal' => null,
                        'delivery_time' => null,
                        'score' => 0
                    ];
                }
            }
            
            // Determine recommendation based on score (but don't auto-select)
            $recommendation = 'Sin recomendación';
            if (!empty($recommendations)) {
                // Sort by score (highest first)
                usort($recommendations, function($a, $b) {
                    return $b['score'] <=> $a['score'];
                });
                
                $topRecommendation = $recommendations[0];
                $recommendation = $topRecommendation['name'] . ' (Puntuación: ' . number_format($topRecommendation['score'], 1) . ')';
                
                // Add additional recommendations if there are multiple good options
                if (count($recommendations) > 1) {
                    $secondBest = $recommendations[1];
                    if ($secondBest['score'] > $topRecommendation['score'] * 0.8) { // Within 80% of best
                        $recommendation .= ' | ' . $secondBest['name'] . ' (Alt.)';
                    }
                }
            }
            
            // Write product data
            $col = 'A';
            $sheet->setCellValue($col . $currentRow, $row[0]);
            $col++;
            $sheet->setCellValue($col . $currentRow, $row[1]);
            $col++;
            $sheet->setCellValue($col . $currentRow, $row[2]);
            $col++;
            
            // Write supplier data
            foreach ($supplierColumns as $supplierId => $columns) {
                $data = $supplierData[$supplierId] ?? null;
                
                if ($data && $data['unit_price'] !== null) {
                    $sheet->setCellValue($columns['price_col'] . $currentRow, $data['unit_price']);
                    $sheet->setCellValue($columns['subtotal_col'] . $currentRow, $data['subtotal']);
                    $sheet->setCellValue($columns['delivery_col'] . $currentRow, $data['delivery_time'] . ' días');
                    
                    // Format currency
                    $sheet->getStyle($columns['price_col'] . $currentRow)->getNumberFormat()
                        ->setFormatCode('$#,##0.00');
                    $sheet->getStyle($columns['subtotal_col'] . $currentRow)->getNumberFormat()
                        ->setFormatCode('$#,##0.00');
                } else {
                    $sheet->setCellValue($columns['price_col'] . $currentRow, 'Sin cotización');
                    $sheet->setCellValue($columns['subtotal_col'] . $currentRow, 'Sin cotización');
                    $sheet->setCellValue($columns['delivery_col'] . $currentRow, 'Sin cotización');
                }
            }
            
            // Write recommendation and observations
            $sheet->setCellValue($col . $currentRow, $recommendation);
            $col++;
            $sheet->setCellValue($col . $currentRow, implode(' | ', $observations));
            
            // Highlight recommended supplier row (if any)
            if (!empty($recommendations)) {
                $sheet->getStyle('A' . $currentRow . ':' . $col . $currentRow)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFF2CC'); // Light yellow for recommendation
            }
            
            $currentRow++;
        }
        
        // Auto-size columns
        foreach (range('A', $col) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        // Add borders
        $sheet->getStyle('A6:' . $col . ($currentRow - 1))->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        
        // Save file
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save(storage_path('app/public/' . $filePath));
    }

    /**
     * Calculate supplier score based on price and delivery time
     */
    private function calculateSupplierScore($subtotal, $deliveryTime)
    {
        // Score based on price (lower is better) and delivery time (shorter is better)
        // Price weight: 70%, Delivery time weight: 30%
        $priceScore = max(0, 100 - ($subtotal / 100)); // Normalize price
        $deliveryScore = max(0, 100 - ($deliveryTime * 2)); // Normalize delivery time
        
        return ($priceScore * 0.7) + ($deliveryScore * 0.3);
    }

    /**
     * Select winning market rate for purchase request
     */
    public function selectMarketRate($id, $marketRateId)
    {
        $purchaseRequest = \App\Models\PurchaseRequest::findOrFail($id);
        $marketRate = \App\Models\MarketRate::findOrFail($marketRateId);
        
        $purchaseRequest->update([
            'selected_market_rate_id' => $marketRateId,
            'status' => 'Aprobada'
        ]);
        
        \Alert::success('Cotización seleccionada exitosamente.')->flash();
        
        return redirect()->back();
    }

    /**
     * Show form to select market rate with justification
     */
    public function showSelectMarketRateForm($id, $marketRateId)
    {
        $purchaseRequest = \App\Models\PurchaseRequest::with([
            'responsibilityArea',
            'details.product'
        ])->findOrFail($id);
        
        $marketRate = \App\Models\MarketRate::with([
            'supplier',
            'quoteDetails.product'
        ])->findOrFail($marketRateId);
        
        return view('admin.purchase-request.select-market-rate', compact('purchaseRequest', 'marketRate'));
    }

    /**
     * Store market rate selection with justification
     */
    public function storeMarketRateSelection($id, $marketRateId)
    {
        $purchaseRequest = \App\Models\PurchaseRequest::findOrFail($id);
        $marketRate = \App\Models\MarketRate::findOrFail($marketRateId);
        
        $request = request();
        
        $purchaseRequest->update([
            'selected_market_rate_id' => $marketRateId,
            'selection_justification' => $request->input('justification'),
            'selected_by' => auth()->id(),
            'selected_at' => now(),
            'status' => 'Aprobada'
        ]);
        
        \Alert::success('Cotización seleccionada y justificada exitosamente.')->flash();
        
        return redirect()->route('purchase-request.show', $id);
    }

    /**
     * Generate purchase order from selected market rate
     */
    public function generatePurchaseOrder($id)
    {
        $purchaseRequest = \App\Models\PurchaseRequest::with([
            'selectedMarketRate.supplier',
            'selectedMarketRate.quoteDetails.product',
            'responsibilityArea'
        ])->findOrFail($id);
        
        if (!$purchaseRequest->selected_market_rate_id) {
            \Alert::error('Debe seleccionar una cotización antes de generar la orden de compra.')->flash();
            return redirect()->back();
        }
        
        // Create purchase order
        $purchaseOrder = \App\Models\PurchaseOrder::create([
            'order_number' => \App\Models\PurchaseOrder::generateNextNumber(),
            'order_date' => now(),
            'supplier_id' => $purchaseRequest->selectedMarketRate->supplier_id,
            'status' => 'Pendiente',
            'total_amount' => $purchaseRequest->selectedMarketRate->total_amount,
            'delivery_date' => now()->addDays(15),
            'notes' => 'Generada desde solicitud: ' . $purchaseRequest->request_number
        ]);
        
        // Create purchase order details
        foreach ($purchaseRequest->selectedMarketRate->quoteDetails as $quoteDetail) {
            \App\Models\PurchaseOrderDetail::create([
                'purchase_order_id' => $purchaseOrder->id,
                'input_id' => $quoteDetail->product_id, // Assuming product maps to input
                'quantity' => $quoteDetail->quantity,
                'unit_cost' => $quoteDetail->unit_price,
                'total_cost' => $quoteDetail->quantity * $quoteDetail->unit_price
            ]);
        }
        
        // Update purchase request status
        $purchaseRequest->update(['status' => 'Completada']);
        
        \Alert::success('Orden de compra generada exitosamente: ' . $purchaseOrder->order_number)->flash();
        
        return redirect()->route('purchase-order.show', $purchaseOrder->id);
    }

    /**
     * Define what happens when the Show operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-show
     * @return void
     */
    protected function setupShowOperation()
    {
        CRUD::addClause('with', ['responsibilityArea', 'requestingUser', 'approvedBy', 'details.product']);
        
        CRUD::setFromDb();

        // Agregar campo personalizado para mostrar detalles de productos
        CRUD::field('details_table')->label('Detalles de Productos')->type('custom_html')
            ->value(function($entry) {
                $details = $entry->details;
                
                if ($details->isEmpty()) {
                    return '<div class="alert alert-info">No hay productos solicitados.</div>';
                }
                
                $html = '<div class="table-responsive">';
                $html .= '<table class="table table-striped table-bordered">';
                $html .= '<thead class="thead-dark">';
                $html .= '<tr>';
                $html .= '<th>Producto</th>';
                $html .= '<th>Cantidad</th>';
                $html .= '<th>Especificaciones</th>';
                $html .= '<th>Precio Estimado</th>';
                $html .= '<th>Total Estimado</th>';
                $html .= '<th>Estado</th>';
                $html .= '</tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                
                foreach ($details as $detail) {
                    $html .= '<tr>';
                    $html .= '<td><strong>' . e($detail->product->name ?? 'Producto no encontrado') . '</strong>';
                    if ($detail->product && $detail->product->description) {
                        $html .= '<br><small class="text-muted">' . e($detail->product->description) . '</small>';
                    }
                    $html .= '</td>';
                    $html .= '<td><span class="badge bg-primary">' . $detail->requested_quantity . '</span></td>';
                    $html .= '<td>' . e($detail->specifications ?? 'Sin especificaciones') . '</td>';
                    $html .= '<td class="text-end">' . ($detail->estimated_unit_price ? '$' . number_format($detail->estimated_unit_price, 2) : 'No definido') . '</td>';
                    $html .= '<td class="text-end">' . ($detail->estimated_total ? '$' . number_format($detail->estimated_total, 2) : 'No definido') . '</td>';
                    $html .= '<td><span class="badge bg-' . ($detail->status == 'Aprobada' ? 'success' : ($detail->status == 'Rechazada' ? 'danger' : 'warning')) . '">' . $detail->status . '</span></td>';
                    $html .= '</tr>';
                }
                
                $html .= '</tbody>';
                $html .= '</table>';
                $html .= '</div>';
                
                return $html;
            });

        // Agregar campo para mostrar cotizaciones disponibles
        CRUD::field('market_rates_table')->label('Cotizaciones Disponibles')->type('custom_html')
            ->value(function($entry) {
                $productIds = $entry->details->pluck('product_id')->toArray();
                $marketRates = \App\Models\MarketRate::with([
                    'supplier',
                    'quoteDetails.product'
                ])->whereHas('quoteDetails', function($query) use ($productIds) {
                    $query->whereIn('product_id', $productIds);
                })->get();
                
                if ($marketRates->isEmpty()) {
                    return '<div class="alert alert-warning">No hay cotizaciones disponibles para los productos de esta solicitud.</div>';
                }
                
                $html = '<div class="table-responsive">';
                $html .= '<table class="table table-striped table-bordered">';
                $html .= '<thead class="thead-dark">';
                $html .= '<tr>';
                $html .= '<th>Proveedor</th>';
                $html .= '<th>Fecha</th>';
                $html .= '<th>Total</th>';
                $html .= '<th>Productos</th>';
                $html .= '<th>Estado</th>';
                $html .= '<th>Acciones</th>';
                $html .= '</tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                
                foreach ($marketRates as $marketRate) {
                    $isSelected = $entry->selected_market_rate_id == $marketRate->id;
                    $rowClass = $isSelected ? 'table-success' : '';
                    
                    $html .= '<tr class="' . $rowClass . '">';
                    $html .= '<td><strong>' . e($marketRate->supplier->company_name) . '</strong></td>';
                    $html .= '<td>' . $marketRate->date->format('d/m/Y') . '</td>';
                    $html .= '<td class="text-end"><strong>$' . number_format($marketRate->total_amount, 2) . '</strong></td>';
                    $html .= '<td><span class="badge bg-info">' . $marketRate->quoteDetails->count() . ' productos</span></td>';
                    $html .= '<td>';
                    if ($isSelected) {
                        $html .= '<span class="badge bg-success">Seleccionada</span>';
                    } else {
                        $html .= '<span class="badge bg-secondary">Disponible</span>';
                    }
                    $html .= '</td>';
                    $html .= '<td>';
                    
                    if (!$isSelected && $entry->status != 'Completada') {
                        $html .= '<a href="' . route('purchase-request.show-select-market-rate', [$entry->id, $marketRate->id]) . '" class="btn btn-sm btn-success">';
                        $html .= '<i class="la la-check"></i> Seleccionar';
                        $html .= '</a>';
                    }
                    
                    $html .= '</td>';
                    $html .= '</tr>';
                }
                
                $html .= '</tbody>';
                $html .= '</table>';
                $html .= '</div>';
                
                // Botón para generar orden de compra si hay cotización seleccionada
                if ($entry->selected_market_rate_id && $entry->status != 'Completada') {
                    $html .= '<div class="mt-3">';
                    $html .= '<form method="POST" action="' . route('purchase-request.generate-purchase-order', $entry->id) . '">';
                    $html .= csrf_field();
                    $html .= '<button type="submit" class="btn btn-primary" onclick="return confirm(\'¿Está seguro de generar la orden de compra?\')">';
                    $html .= '<i class="la la-shopping-cart"></i> Generar Orden de Compra';
                    $html .= '</button>';
                    $html .= '</form>';
                    $html .= '</div>';
                }
                
                return $html;
            });

        // Agregar información de selección si existe
        CRUD::field('selection_info')->label('Información de Selección')->type('custom_html')
            ->value(function($entry) {
                if (!$entry->selected_market_rate_id) {
                    return '';
                }
                
                $html = '<div class="alert alert-success">';
                $html .= '<h5><i class="la la-check-circle"></i> Cotización Seleccionada</h5>';
                $html .= '<p><strong>Proveedor:</strong> ' . e($entry->selectedMarketRate->supplier->company_name) . '</p>';
                $html .= '<p><strong>Total:</strong> $' . number_format($entry->selectedMarketRate->total_amount, 2) . '</p>';
                $html .= '<p><strong>Seleccionado por:</strong> ' . e($entry->selectedBy->name ?? 'Usuario no encontrado') . '</p>';
                $html .= '<p><strong>Fecha de selección:</strong> ' . $entry->selected_at->format('d/m/Y H:i') . '</p>';
                if ($entry->selection_justification) {
                    $html .= '<p><strong>Justificación:</strong> ' . e($entry->selection_justification) . '</p>';
                }
                $html .= '</div>';
                return $html;
            });
    }
}

