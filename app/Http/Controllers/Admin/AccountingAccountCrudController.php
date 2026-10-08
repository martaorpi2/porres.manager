<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\AccountingAccountRequest;
use App\Models\AccountingAccount;
use App\Models\User;
use App\Services\AccountingChartEditor;
use App\Services\AccountingChartException;
use App\Services\AccountingChartReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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

    public function index(Request $request, AccountingChartReport $report)
    {
        $this->authorizeAccounting();

        $user = backpack_user();

        $oldForm = null;
        if (session()->hasOldInput()) {
            $oldForm = [
                'mode' => session('account_form_mode', old('id') ? 'edit' : 'create'),
                'id' => old('id'),
                'code' => old('code'),
                'name' => old('name'),
                'account_type' => old('account_type'),
                'balance_nature' => old('balance_nature'),
                'is_grouping' => old('is_grouping', '0'),
                'is_active' => old('is_active', '1'),
                'parent_code' => old('parent_code'),
            ];
        }

        return view('admin.accounting.accounts', [
            'tree' => $report->tree(),
            'types' => AccountingAccount::typeOptions(),
            'balanceNatures' => AccountingAccount::balanceNatureOptions(),
            'balanceByType' => AccountingAccount::balanceNatureByType(),
            'canCreate' => true,
            'canUpdate' => $user instanceof User && ! $user->hasAdministradoraInstitucionRole(),
            'selectedCode' => $request->query('code'),
            'oldForm' => $oldForm,
            'pdfUrl' => backpack_url('accounting-account/export/pdf'),
            'saveUrl' => backpack_url('accounting-account/guardar'),
            'removeUrl' => backpack_url('accounting-account/quitar'),
            'title' => 'Cuentas',
            'breadcrumbs' => [
                trans('backpack::crud.admin') => backpack_url('dashboard'),
                'Cuentas' => false,
            ],
        ]);
    }

    public function saveAccount(Request $request, AccountingChartReport $report, AccountingChartEditor $editor)
    {
        $this->authorizeAccounting();

        $user = backpack_user();
        $id = $request->integer('id') ?: null;
        $canUpdate = $user instanceof User && ! $user->hasAdministradoraInstitucionRole();
        if ($id && ! $canUpdate) {
            abort(403, 'No tiene permiso para modificar cuentas.');
        }

        $existing = $id ? AccountingAccount::query()->find($id) : null;
        if ($id && ! $existing) {
            abort(404);
        }
        $isGrouping = $existing ? (bool) $existing->is_grouping : $request->boolean('is_grouping');

        $unique = 'unique:accounting_accounts,code';
        if ($id) {
            $unique .= ','.$id;
        }

        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:30', $unique],
            'name' => ['required', 'string', 'max:255'],
            'account_type' => ['nullable', 'in:activo,pasivo,patrimonio,ingreso,gasto,egreso'],
            'balance_nature' => $isGrouping
                ? ['nullable', 'in:deudor,acreedor']
                : ['required', 'in:deudor,acreedor'],
            'is_grouping' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'parent_code' => ['nullable', 'string', 'max:30'],
        ], [
            'code.unique' => 'Ya existe una cuenta con ese código.',
            'balance_nature.required' => 'Elegí si el saldo es acreedor o deudor.',
        ], [
            'code' => 'código',
            'name' => 'nombre',
            'account_type' => 'tipo de cuenta',
            'balance_nature' => 'tipo de saldo',
            'is_active' => 'activa',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors($validator)
                ->with('account_form_mode', $id ? 'edit' : 'create');
        }

        $data = $validator->validated();

        $code = trim($data['code']);
        $existingWithCode = AccountingAccount::query()->where('code', $code)->first();
        if ($report->isGroupingCode($code) && (! $existingWithCode || (int) $existingWithCode->id !== (int) $id)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('account_form_mode', $id ? 'edit' : 'create')
                ->withErrors(['code' => 'Ese código es un rubro de agrupación. Usá un código de cuenta imputable debajo de ese rubro.']);
        }

        $payload = [
            'code' => $code,
            'name' => trim($data['name']),
            'account_type' => $data['account_type'] ?: null,
            'balance_nature' => $isGrouping ? null : $data['balance_nature'],
            'is_active' => $request->boolean('is_active'),
        ];

        if ($id) {
            $account = AccountingAccount::query()->findOrFail($id);
            try {
                $moved = $editor->save($account, $payload);
            } catch (AccountingChartException $exception) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('account_form_mode', 'edit')
                    ->withErrors(['code' => $exception->getMessage()]);
            }
            $message = $isGrouping ? 'El rubro se modificó.' : 'La cuenta se modificó.';
            if ($moved > 0) {
                $message .= $moved === 1
                    ? ' Se actualizó el código de la cuenta hija.'
                    : ' Se actualizó el código de '.$moved.' cuentas hijas.';
            }
            \Alert::success($message)->flash();
        } else {
            $leafParent = $report->leafParentOf($code);
            if ($leafParent) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('account_form_mode', 'create')
                    ->withErrors(['code' => $leafParent->code.' '.$leafParent->name.' es una cuenta de último nivel y no admite cuentas hijas.']);
            }
            AccountingAccount::query()->create($payload + ['is_grouping' => $isGrouping]);
            \Alert::success($isGrouping ? 'El rubro se agregó.' : 'La cuenta se agregó.')->flash();
        }

        return redirect(backpack_url('accounting-account').'?code='.urlencode($code));
    }

    public function removeAccount(Request $request, AccountingChartEditor $editor)
    {
        $this->authorizeAccounting();

        $user = backpack_user();
        if (! $user instanceof User || $user->hasAdministradoraInstitucionRole()) {
            abort(403, 'No tiene permiso para quitar cuentas.');
        }

        $account = AccountingAccount::query()->findOrFail($request->integer('id'));

        try {
            $back = $editor->remove(
                $account,
                (string) $request->input('children_action', 'none'),
                $request->input('target_parent_code')
            );
        } catch (AccountingChartException $exception) {
            return redirect()
                ->back()
                ->withErrors(['code' => $exception->getMessage()]);
        }

        \Alert::success('La cuenta se quitó.')->flash();

        $url = backpack_url('accounting-account');
        if ($back !== '') {
            $url .= '?code='.urlencode($back);
        }

        return redirect($url);
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
        CRUD::addColumn([
            'name' => 'balance_nature',
            'label' => 'Tipo de saldo',
            'type' => 'closure',
            'function' => function ($entry) {
                return e($entry->balance_nature_label ?: '—');
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
        CRUD::field('is_grouping')->label('Rubro de agrupación')->type('boolean')
            ->hint('Un rubro o subrubro agrupa cuentas y no recibe movimientos.')
            ->wrapper(['class' => 'form-group col-sm-12 col-md-3']);
        CRUD::addField([
            'name' => 'balance_nature',
            'label' => 'Tipo de saldo',
            'type' => 'select_from_array',
            'options' => \App\Models\AccountingAccount::balanceNatureOptions(),
            'allows_null' => true,
            'hint' => 'Obligatorio en las cuentas. Los rubros no tienen saldo.',
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

    private function authorizeAccounting(): void
    {
        $user = backpack_user();
        if (! $user instanceof User || ! $user->canViewAccounting()) {
            abort(403, 'No tiene permiso para ver la contabilidad.');
        }
    }
}
