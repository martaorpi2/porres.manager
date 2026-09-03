<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\SupplierRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class SupplierCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class SupplierCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Supplier::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/supplier');
        CRUD::setEntityNameStrings('proveedor', 'proveedores');
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
        
        // Filtrar proveedores por área si el usuario es responsable de área
        $user = backpack_user();
        if ($user && $user->hasResponsableAreaOrInstituteAuthorityRole()) {
            // Obtener las áreas de responsabilidad del usuario con sus nombres
            $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->get();
            
            if ($userAreas->isNotEmpty()) {
                // Mapeo de áreas a rubros permitidos
                $areaRubroMap = [
                    'Informática' => ['Tecnología', 'Plataforma de e-commerce', 'Plataforma e-commerce'],
                    'Salud' => ['Salud'],
                    'Insumos de Salud' => ['Salud'],
                    'Mantenimiento' => ['Herramientas'],
                    'Insumos Generales' => ['Oficina', 'Insumos Generales'],
                ];
                
                // Obtener todos los rubros permitidos para las áreas del usuario
                $allowedRubroNames = collect();
                
                foreach ($userAreas as $area) {
                    $areaName = $area->name;
                    if (isset($areaRubroMap[$areaName])) {
                        $allowedRubroNames = $allowedRubroNames->merge($areaRubroMap[$areaName]);
                    }
                }
                
                // Obtener los IDs de rubros permitidos
                $allowedRubroIds = \App\Models\SuppliersHeading::whereIn('name', $allowedRubroNames->unique())
                    ->pluck('id');
                
                // Filtrar proveedores por rubro
                if ($allowedRubroIds->isNotEmpty()) {
                    CRUD::addClause('whereIn', 'supplier_heading_id', $allowedRubroIds);
                } else {
                    // Si no hay rubros relacionados, no mostrar ningún proveedor
                    CRUD::addClause('where', 'id', 0);
                }
            } else {
                // Si no tiene áreas asignadas, no mostrar ningún proveedor
                CRUD::addClause('where', 'id', 0);
            }
        }
        
        CRUD::addButton('line', 'invoices', 'view', 'crud::buttons.supplier_invoices', 'end');

        // Agregar botón personalizado de exportación
        CRUD::addButton('top', 'export_excel', 'view', 'crud::buttons.export_excel', 'end');
        CRUD::addButton('top', 'export_pdf', 'view', 'crud::buttons.export_pdf', 'end');
        //CRUD::setFromDb(); // set columns from db columns.
        CRUD::column('company_name')->label('Nombre');
        CRUD::column('cuit')->label('Cuit');
        CRUD::column('address')->label('Dirección');
        CRUD::column('cvu')->label('CBU/CVU');
        CRUD::column('alias')->label('Alias');
        CRUD::addColumn([
            'name' => 'supplier_heading_id',
            'label' => 'Rubro',
            'type' => 'select',
            'entity' => 'heading',
            'attribute' => 'name',
            'model' => 'App\Models\SuppliersHeading',
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('heading', function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%'.$searchTerm.'%');
                });
            },
        ]);
        CRUD::addColumn([
            'name' => 'average_rating',
            'label' => 'Calificación',
            'type' => 'closure',
            'function' => function($entry) {
                $avg = $entry->average_rating;
                $total = $entry->total_ratings;
                
                if ($total == 0) {
                    return '<span class="text-muted">Sin calificaciones</span>';
                }
                
                $stars = '';
                for ($i = 1; $i <= 5; $i++) {
                    if ($i <= round($avg)) {
                        $stars .= '<i class="la la-star text-warning"></i>';
                    } else {
                        $stars .= '<i class="la la-star text-secondary"></i>';
                    }
                }
                
                return $stars . ' <small class="text-muted">(' . number_format($avg, 1) . '/5)</small> <br><small class="text-muted">' . $total . ' evaluación(es)</small>';
            },
            'escaped' => false,
        ]);

        // Filtro personalizado por rubro usando parámetros de URL
        if (request()->has('rubro')) {
            $rubroId = request()->get('rubro');
            if ($rubroId) {
                CRUD::addClause('where', 'supplier_heading_id', $rubroId);
            }
        }

        // Filtro personalizado por nombre usando parámetros de URL
        if (request()->has('nombre')) {
            $nombre = request()->get('nombre');
            if ($nombre) {
                CRUD::addClause('where', 'company_name', 'like', '%' . $nombre . '%');
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
        CRUD::setValidation(SupplierRequest::class);
        $this->crud->removeAllFields();
        //CRUD::setFromDb(); // set fields from db columns.
        CRUD::field('company_name')->label('Nombre')
            ->wrapper(['class' => 'form-group col-sm-12 col-md-6']);
        CRUD::field('cuit')->label('Cuit')
            ->wrapper(['class' => 'form-group col-sm-12 col-md-6']);
        CRUD::field('address')->label('Dirección')
            ->wrapper(['class' => 'form-group col-sm-12']);
        CRUD::field('email')->label('Email')->type('email')
            ->wrapper(['class' => 'form-group col-sm-12 col-md-6']);
        CRUD::field('contact')->label('Teléfono')
            ->wrapper(['class' => 'form-group col-sm-12 col-md-6']);
        CRUD::field('cvu')->label('CBU/CVU')->attributes(['placeholder' => 'Ej: 0000003100123456789012'])
            ->wrapper(['class' => 'form-group col-sm-12 col-md-6']);
        CRUD::field('alias')->label('Alias')->attributes(['placeholder' => 'Ej: proveedor.cbu.alias'])
            ->wrapper(['class' => 'form-group col-sm-12 col-md-6']);
        
        // Filtrar rubros según el área del responsable
        $user = backpack_user();
        $rubroOptions = null;
        
        if ($user && $user->hasResponsableAreaOrInstituteAuthorityRole()) {
            // Obtener las áreas de responsabilidad del usuario con sus nombres
            $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->get();
            
            if ($userAreas->isNotEmpty()) {
                // Mapeo de áreas a rubros permitidos
                $areaRubroMap = [
                    'Informática' => ['Tecnología', 'Plataforma de e-commerce', 'Plataforma e-commerce'],
                    'Salud' => ['Salud'],
                    'Insumos de Salud' => ['Salud'],
                    'Mantenimiento' => ['Herramientas'],
                    'Insumos Generales' => ['Oficina', 'Insumos Generales'],
                ];
                
                // Obtener todos los rubros permitidos para las áreas del usuario
                $allowedRubroNames = collect();
                
                foreach ($userAreas as $area) {
                    $areaName = $area->name;
                    if (isset($areaRubroMap[$areaName])) {
                        $allowedRubroNames = $allowedRubroNames->merge($areaRubroMap[$areaName]);
                    }
                }
                
                // Obtener los rubros permitidos
                $rubros = \App\Models\SuppliersHeading::whereIn('name', $allowedRubroNames->unique())
                    ->pluck('name', 'id')
                    ->toArray();
                
                if (!empty($rubros)) {
                    $rubroOptions = $rubros;
                }
            }
        }
        
        // Configurar el campo de rubro
        if ($rubroOptions !== null && is_array($rubroOptions)) {
            CRUD::addField([
                'name' => 'supplier_heading_id',
                'label' => 'Rubro',
                'type' => 'select_from_array',
                'options' => $rubroOptions,
                'allows_null' => false,
                'wrapper' => ['class' => 'form-group col-sm-12'],
            ]);
        } else {
            CRUD::addField([
                'name' => 'supplier_heading_id',
                'label' => 'Rubro',
                'type' => 'select',
                'entity' => 'heading',
                'model' => 'App\Models\SuppliersHeading',
                'attribute' => 'name',
                'wrapper' => ['class' => 'form-group col-sm-12'],
            ]);
        }
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
     * Export suppliers to Excel
     */
    public function exportExcel()
    {
        $query = \App\Models\Supplier::with(['heading']);
        
        // Filtrar proveedores por área si el usuario es responsable de área
        $user = backpack_user();
        if ($user && $user->hasResponsableAreaOrInstituteAuthorityRole()) {
            // Obtener las áreas de responsabilidad del usuario con sus nombres
            $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->get();
            
            if ($userAreas->isNotEmpty()) {
                // Mapeo de áreas a rubros permitidos
                $areaRubroMap = [
                    'Informática' => ['Tecnología', 'Plataforma de e-commerce', 'Plataforma e-commerce'],
                    'Salud' => ['Salud'],
                    'Insumos de Salud' => ['Salud'],
                    'Mantenimiento' => ['Herramientas'],
                    'Insumos Generales' => ['Oficina', 'Insumos Generales'],
                ];
                
                // Obtener todos los rubros permitidos para las áreas del usuario
                $allowedRubroNames = collect();
                
                foreach ($userAreas as $area) {
                    $areaName = $area->name;
                    if (isset($areaRubroMap[$areaName])) {
                        $allowedRubroNames = $allowedRubroNames->merge($areaRubroMap[$areaName]);
                    }
                }
                
                // Obtener los IDs de rubros permitidos
                $allowedRubroIds = \App\Models\SuppliersHeading::whereIn('name', $allowedRubroNames->unique())
                    ->pluck('id');
                
                // Filtrar proveedores por rubro
                if ($allowedRubroIds->isNotEmpty()) {
                    $query->whereIn('supplier_heading_id', $allowedRubroIds);
                } else {
                    // Si no hay rubros relacionados, no mostrar ningún proveedor
                    $query->where('id', 0);
                }
            } else {
                // Si no tiene áreas asignadas, no mostrar ningún proveedor
                $query->where('id', 0);
            }
        }
        
        // Aplicar los mismos filtros que en el listado
        if (request()->has('rubro')) {
            $rubroId = request()->get('rubro');
            if ($rubroId) {
                $query->where('supplier_heading_id', $rubroId);
            }
        }

        if (request()->has('nombre')) {
            $nombre = request()->get('nombre');
            if ($nombre) {
                $query->where('company_name', 'like', '%' . $nombre . '%');
            }
        }

        $suppliers = $query->get();

        $filename = 'proveedores_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        return response()->streamDownload(function() use ($suppliers) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Headers
            $sheet->setCellValue('A1', 'Nombre');
            $sheet->setCellValue('B1', 'CUIT');
            $sheet->setCellValue('C1', 'Dirección');
            $sheet->setCellValue('D1', 'Rubro');
            
            // Data
            $row = 2;
            foreach ($suppliers as $supplier) {
                $sheet->setCellValue('A' . $row, $supplier->company_name);
                $sheet->setCellValue('B' . $row, $supplier->cuit);
                $sheet->setCellValue('C' . $row, $supplier->address);
                $sheet->setCellValue('D' . $row, $supplier->heading->name ?? '');
                $row++;
            }
            
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Export suppliers to PDF
     */
    public function exportPdf()
    {
        $query = \App\Models\Supplier::with(['heading']);
        
        // Filtrar proveedores por área si el usuario es responsable de área
        $user = backpack_user();
        if ($user && $user->hasResponsableAreaOrInstituteAuthorityRole()) {
            // Obtener las áreas de responsabilidad del usuario con sus nombres
            $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->get();
            
            if ($userAreas->isNotEmpty()) {
                // Mapeo de áreas a rubros permitidos
                $areaRubroMap = [
                    'Informática' => ['Tecnología', 'Plataforma de e-commerce', 'Plataforma e-commerce'],
                    'Salud' => ['Salud'],
                    'Insumos de Salud' => ['Salud'],
                    'Mantenimiento' => ['Herramientas'],
                    'Insumos Generales' => ['Oficina', 'Insumos Generales'],
                ];
                
                // Obtener todos los rubros permitidos para las áreas del usuario
                $allowedRubroNames = collect();
                
                foreach ($userAreas as $area) {
                    $areaName = $area->name;
                    if (isset($areaRubroMap[$areaName])) {
                        $allowedRubroNames = $allowedRubroNames->merge($areaRubroMap[$areaName]);
                    }
                }
                
                // Obtener los IDs de rubros permitidos
                $allowedRubroIds = \App\Models\SuppliersHeading::whereIn('name', $allowedRubroNames->unique())
                    ->pluck('id');
                
                // Filtrar proveedores por rubro
                if ($allowedRubroIds->isNotEmpty()) {
                    $query->whereIn('supplier_heading_id', $allowedRubroIds);
                } else {
                    // Si no hay rubros relacionados, no mostrar ningún proveedor
                    $query->where('id', 0);
                }
            } else {
                // Si no tiene áreas asignadas, no mostrar ningún proveedor
                $query->where('id', 0);
            }
        }
        
        // Aplicar los mismos filtros que en el listado
        if (request()->has('rubro')) {
            $rubroId = request()->get('rubro');
            if ($rubroId) {
                $query->where('supplier_heading_id', $rubroId);
            }
        }

        if (request()->has('nombre')) {
            $nombre = request()->get('nombre');
            if ($nombre) {
                $query->where('company_name', 'like', '%' . $nombre . '%');
            }
        }

        $suppliers = $query->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('supplier-pdf', compact('suppliers'));
        $filename = 'proveedores_' . date('Y-m-d_H-i-s') . '.pdf';
        
        return $pdf->download($filename);
    }
}
