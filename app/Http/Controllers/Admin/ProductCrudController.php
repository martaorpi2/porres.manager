<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ProductRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class ProductCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ProductCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Product::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/product');
        CRUD::setEntityNameStrings('producto', 'productos');
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
        
        // Agregar botón personalizado de exportación
        CRUD::addButton('top', 'export_excel', 'view', 'crud::buttons.export_excel', 'end');
        CRUD::addButton('top', 'export_pdf', 'view', 'crud::buttons.export_pdf', 'end');
        CRUD::addColumn([
            'name' => 'category_id',
            'label' => 'Categoría',
            'type' => 'select',
            'entity' => 'category',
            'attribute' => 'name',
            'model' => 'App\Models\Category',
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('category', function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%'.$searchTerm.'%');
                });
            },
        ]);
        CRUD::column('name')->label('Nombre');
        CRUD::column('description')->label('Descripción');
        CRUD::column('unit_measurement')->label('Unidad Med.');
        CRUD::column('minimum_stock')->label('Stock Mín.');
        CRUD::column('expiration_date')->label('Fecha Vencimiento');
        CRUD::column('location')->label('Ubicación');
        CRUD::column('utilization_percentage')->label('% Utilización');

        // Filtro personalizado por categoría usando parámetros de URL
        if (request()->has('categoria')) {
            $categoriaId = request()->get('categoria');
            if ($categoriaId) {
                CRUD::addClause('where', 'category_id', $categoriaId);
            }
        }

        // Filtro personalizado por nombre usando parámetros de URL
        if (request()->has('nombre')) {
            $nombre = request()->get('nombre');
            if ($nombre) {
                CRUD::addClause('where', 'name', 'like', '%' . $nombre . '%');
            }
        }

        // Filtro personalizado por fecha de vencimiento usando parámetros de URL
        if (request()->has('fecha_vencimiento')) {
            $fechaVencimiento = request()->get('fecha_vencimiento');
            if ($fechaVencimiento) {
                CRUD::addClause('where', 'expiration_date', '<=', $fechaVencimiento);
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
        CRUD::setValidation(ProductRequest::class);
        CRUD::addField([
            'name' => 'category_id',
            'label' => 'Categoría',
            'type' => 'select',
            'entity' => 'category',
            'model' => 'App\Models\Category',
            'attribute' => 'name',
        ]);
        CRUD::field('name')->label('Nombre');
        CRUD::field('description')->label('Descripción');
        CRUD::field('unit_measurement')->label('Unidad Med.');
        CRUD::field('minimum_stock')->label('Stock Mín.');
        CRUD::field('expiration_date')->label('Fecha Vencimiento');
        CRUD::field('location')->label('Ubicación');
        CRUD::field('utilization_percentage')->label('% Utilización');
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
     * Export products to Excel
     */
    public function exportExcel()
    {
        $query = \App\Models\Product::with('category');
        
        // Aplicar los mismos filtros que en el listado
        if (request()->has('categoria')) {
            $categoriaId = request()->get('categoria');
            if ($categoriaId) {
                $query->where('category_id', $categoriaId);
            }
        }

        if (request()->has('nombre')) {
            $nombre = request()->get('nombre');
            if ($nombre) {
                $query->where('name', 'like', '%' . $nombre . '%');
            }
        }

        if (request()->has('fecha_vencimiento')) {
            $fechaVencimiento = request()->get('fecha_vencimiento');
            if ($fechaVencimiento) {
                $query->where('expiration_date', '<=', $fechaVencimiento);
            }
        }

        $products = $query->get();

        $filename = 'productos_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        return response()->streamDownload(function() use ($products) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Headers
            $sheet->setCellValue('A1', 'Categoría');
            $sheet->setCellValue('B1', 'Nombre');
            $sheet->setCellValue('C1', 'Descripción');
            $sheet->setCellValue('D1', 'Unidad Med.');
            $sheet->setCellValue('E1', 'Stock Mín.');
            $sheet->setCellValue('F1', 'Fecha Vencimiento');
            $sheet->setCellValue('G1', 'Ubicación');
            $sheet->setCellValue('H1', '% Utilización');
            
            // Data
            $row = 2;
            foreach ($products as $product) {
                $sheet->setCellValue('A' . $row, $product->category->name ?? '');
                $sheet->setCellValue('B' . $row, $product->name);
                $sheet->setCellValue('C' . $row, $product->description);
                $sheet->setCellValue('D' . $row, $product->unit_measurement);
                $sheet->setCellValue('E' . $row, $product->minimum_stock);
                $sheet->setCellValue('F' . $row, $product->expiration_date ? $product->expiration_date->format('d/m/Y') : '');
                $sheet->setCellValue('G' . $row, $product->location);
                $sheet->setCellValue('H' . $row, $product->utilization_percentage);
                $row++;
            }
            
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Export products to PDF
     */
    public function exportPdf()
    {
        $query = \App\Models\Product::with('category');
        
        // Aplicar los mismos filtros que en el listado
        if (request()->has('categoria')) {
            $categoriaId = request()->get('categoria');
            if ($categoriaId) {
                $query->where('category_id', $categoriaId);
            }
        }

        if (request()->has('nombre')) {
            $nombre = request()->get('nombre');
            if ($nombre) {
                $query->where('name', 'like', '%' . $nombre . '%');
            }
        }

        if (request()->has('fecha_vencimiento')) {
            $fechaVencimiento = request()->get('fecha_vencimiento');
            if ($fechaVencimiento) {
                $query->where('expiration_date', '<=', $fechaVencimiento);
            }
        }

        $products = $query->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('product-pdf', compact('products'));
        $filename = 'productos_' . date('Y-m-d_H-i-s') . '.pdf';
        
        return $pdf->download($filename);
    }
}
