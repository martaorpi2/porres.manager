<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\AccountingAccountRequest;
use App\Services\AccountingChartReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class AccountingAccountCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup()
    {
        CRUD::setModel(\App\Models\AccountingAccount::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/accounting-account');
        CRUD::setEntityNameStrings('cuenta contable', 'cuentas contables');
    }

    protected function setupListOperation()
    {
        CRUD::removeButton('show');
        CRUD::enableResponsiveTable();

        $user = backpack_user();
        if ($user && $user->hasRole('role_admin_institucion', 'backpack')) {
            CRUD::removeButton('update');
            CRUD::removeButton('delete');
        }

        CRUD::addButton('top', 'export_pdf', 'view', 'crud::buttons.accounting_account_export_pdf', 'end');

        CRUD::column('code')->label('Código');
        CRUD::column('name')->label('Nombre');
        CRUD::addColumn([
            'name' => 'account_type',
            'label' => 'Tipo',
            'type' => 'closure',
            'function' => function ($entry) {
                return e($entry->type_label);
            },
        ]);
        CRUD::column('is_active')->label('Activa')->type('boolean');
    }

    protected function setupCreateOperation()
    {
        CRUD::setValidation(AccountingAccountRequest::class);
        CRUD::field('code')->label('Código')->attributes(['placeholder' => 'Ej: 2110001'])
            ->wrapper(['class' => 'form-group col-sm-12 col-md-3']);
        CRUD::field('name')->label('Nombre')->attributes(['placeholder' => 'Ej: Útiles y papelería'])
            ->wrapper(['class' => 'form-group col-sm-12 col-md-5']);
        CRUD::addField([
            'name' => 'account_type',
            'label' => 'Tipo',
            'type' => 'select_from_array',
            'options' => \App\Models\AccountingAccount::typeOptions(),
            'allows_null' => true,
            'hint' => 'Permite distinguir Caja/Banco (activo) de un gasto (útiles, honorarios) o un bien (equipamiento).',
            'wrapper' => ['class' => 'form-group col-sm-12 col-md-4'],
        ]);
        CRUD::field('is_active')->label('Activa')->type('boolean')->default(true)
            ->wrapper(['class' => 'form-group col-sm-12 col-md-3']);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    public function exportPdf(AccountingChartReport $report)
    {
        $rows = $report->rows();
        $pdf = Pdf::loadView('accounting-account-pdf', compact('rows'))->setPaper('a4', 'portrait');
        $printedAt = now()->timezone('America/Argentina/Buenos_Aires')->format('d/m/Y H:i:s');
        $pdf->getDomPDF()->getCanvas()->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) use ($printedAt) {
            $font = $fontMetrics->getFont('DejaVu Sans');
            $text = $printedAt.'    Página '.$pageNumber;
            $width = $fontMetrics->getTextWidth($text, $font, 8);
            $canvas->text($canvas->get_width() - $width - 22, $canvas->get_height() - 22, $text, $font, 8);
        });

        return $pdf->stream('PLAN DE CUENTAS.pdf');
    }
}
