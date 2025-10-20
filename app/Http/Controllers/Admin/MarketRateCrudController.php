<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\MarketRateRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class MarketRateCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class MarketRateCrudController extends CrudController
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
        CRUD::setModel(\App\Models\MarketRate::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/market-rate');
        CRUD::setEntityNameStrings('cotización', 'cotizaciones');
    }

    /**
     * Define what happens when the List operation is loaded.
     * 
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        // Cargar relaciones necesarias
        CRUD::addClause('with', ['supplier', 'quoteDetails']);
        
        CRUD::column('id')->label('ID');
        CRUD::column('supplier.company_name')->label('Proveedor');
        CRUD::column('application_id')->label('Aplicación');
        CRUD::column('date')->label('Fecha');
        CRUD::column('total_amount')->label('Monto Total')->type('number')->decimals(2)->prefix('$');
        
        // Agregar columna personalizada para mostrar detalles de cotización
        CRUD::column('quote_details_count')->label('Detalles')->type('custom_html')
            ->value(function($entry) {
                $count = $entry->quoteDetails->count();
                return '<span class="badge bg-info">' . $count . ' productos</span>';
            });
            
        // Agregar botón PDF en cada fila
        CRUD::addButton('line', 'pdf', 'view', 'crud::buttons.pdf', 'end');
    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(MarketRateRequest::class);
        CRUD::setFromDb(); // set fields from db columns.

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

    /**
     * Define what happens when the Show operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-show
     * @return void
     */
    protected function setupShowOperation()
    {
        // Cargar relaciones necesarias para la vista
        CRUD::addClause('with', ['supplier', 'quoteDetails.product']);
        
        CRUD::setFromDb(); // set fields from db columns.

        // Agregar campo personalizado para mostrar detalles de cotización (con estilo de PurchaseRequest)
        CRUD::column('quote_details_table')->label('Detalles de Cotización')->type('custom_html')
            ->value(function($entry) {
                $quoteDetails = $entry->quoteDetails;
                
                if ($quoteDetails->isEmpty()) {
                    return '<div class="alert alert-info">No hay detalles de cotización disponibles.</div>';
                }
                
                $html = '<div class="card border-primary">';
                $html .= '<div class="card-header bg-primary text-white">';
                $html .= '<h6 class="mb-0"><i class="la la-shopping-cart"></i> Productos Cotizados</h6>';
                $html .= '</div>';
                $html .= '<div class="card-body p-0">';
                $html .= '<div class="table-responsive">';
                $html .= '<table class="table table-sm table-bordered mb-0">';
                $html .= '<thead class="table-light">';
                $html .= '<tr>';
                $html .= '<th style="width: 35%;">Producto</th>';
                $html .= '<th style="width: 15%;">Cantidad</th>';
                $html .= '<th style="width: 20%;">Precio Unitario</th>';
                $html .= '<th style="width: 20%;">Subtotal</th>';
                $html .= '<th style="width: 10%;">Estado</th>';
                $html .= '</tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                
                $total = 0;
                foreach ($quoteDetails as $detail) {
                    $subtotal = $detail->quantity * $detail->unit_price;
                    $total += $subtotal;
                    
                    $html .= '<tr>';
                    $productName = $detail->product->name ?? 'Producto no encontrado';
                    if (is_array($productName)) {
                        $productName = 'Producto no encontrado';
                    }
                    $html .= '<td><strong>' . $productName . '</strong>';
                    if ($detail->product && $detail->product->description && !is_array($detail->product->description)) {
                        $html .= '<br><small class="text-muted">' . $detail->product->description . '</small>';
                    }
                    $html .= '</td>';
                    $html .= '<td><span class="badge bg-info">' . $detail->quantity . '</span>';
                    if ($detail->product && $detail->product->unit_measurement && !is_array($detail->product->unit_measurement)) {
                        $html .= '<br><small class="text-muted">' . $detail->product->unit_measurement . '</small>';
                    }
                    $html .= '</td>';
                    $html .= '<td class="text-end"><strong>$' . number_format($detail->unit_price, 2) . '</strong></td>';
                    $html .= '<td class="text-end"><span class="badge bg-success">$' . number_format($subtotal, 2) . '</span></td>';
                    $html .= '<td><span class="badge bg-success">Cotizado</span></td>';
                    $html .= '</tr>';
                }
                
                $html .= '</tbody>';
                $html .= '<tfoot class="table-light">';
                $html .= '<tr>';
                $html .= '<th colspan="3" class="text-end">Total:</th>';
                $html .= '<th class="text-end"><span class="badge bg-primary fs-6">$' . number_format($total, 2) . '</span></th>';
                $html .= '<th></th>';
                $html .= '</tr>';
                $html .= '</tfoot>';
                $html .= '</table>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                
                return $html;
            });
    }

    /**
     * Generate PDF for a market rate (cotización)
     */
    public function generatePdf($id)
    {
        $marketRate = \App\Models\MarketRate::with(['supplier', 'quoteDetails.product'])->findOrFail($id);
        
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('market-rate-pdf', compact('marketRate'));
        
        return $pdf->stream('cotizacion-' . str_pad($marketRate->id, 4, '0', STR_PAD_LEFT) . '.pdf');
    }
}
