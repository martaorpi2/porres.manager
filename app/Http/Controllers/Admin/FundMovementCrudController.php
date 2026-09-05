<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\FundMovementRequest;
use App\Models\AccountingAccount;
use App\Models\FundMovement;
use App\Models\InternalVoucher;
use App\Models\PaymentOrder;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Services\AccountingOutflowService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Support\Facades\DB;

class FundMovementCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation {
        store as protected traitStore;
    }
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation {
        edit as protected backpackEdit;
        update as protected backpackUpdate;
    }

    public function setup(): void
    {
        $user = backpack_user();
        if (! $user instanceof User || ! $user->canManageFundMovements()) {
            abort(403, 'No tiene permiso para gestionar egresos.');
        }
        CRUD::setModel(FundMovement::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/fund-movement');
        CRUD::setEntityNameStrings('egreso', 'egresos');
        CRUD::denyAccess('delete');
    }

    protected function setupListOperation(): void
    {
        CRUD::enableResponsiveTable();
        CRUD::removeButton('delete');
        CRUD::addClause('with', ['fundsAccount', 'paymentOrder', 'internalVoucher', 'supplierInvoice']);
        CRUD::column('number')->label('Número');
        CRUD::column('date')->label('Fecha')->type('date');
        CRUD::addColumn([
            'name' => 'type',
            'label' => 'Tipo',
            'type' => 'closure',
            'function' => fn (FundMovement $e) => e($e->type_label),
        ]);
        CRUD::column('beneficiary')->label('Beneficiario');
        CRUD::column('amount')->label('Importe')->type('number')->decimals(2)->prefix('$');
        CRUD::addColumn([
            'name' => 'origin',
            'label' => 'Origen / documento',
            'type' => 'closure',
            'function' => fn (FundMovement $e) => e($e->origin_label),
        ]);
        CRUD::column('status')->label('Estado');
        CRUD::addButton('line', 'anular', 'view', 'crud::buttons.fund_movement_anular', 'end');
    }

    public function store()
    {
        $this->crud->hasAccessOrFail('create');

        return DB::transaction(function () {
            $request = $this->crud->validateRequest();
            $this->crud->registerFieldEvents();
            $request->merge(['number' => FundMovement::getNextNumber()]);
            $data = $this->crud->getStrippedSaveRequest($request);
            $imputations = $request->input('imputations', []);
            unset($data['imputations']);
            $user = backpack_user();
            $data['created_by_id'] = $user instanceof User ? $user->id : null;

            $item = $this->crud->create($data);
            $this->syncImputations($item, is_array($imputations) ? $imputations : []);
            app(AccountingOutflowService::class)->syncForFundMovement($item);

            $this->data['entry'] = $this->crud->entry = $item;
            \Alert::success(trans('backpack::crud.insert_success'))->flash();
            $this->crud->setSaveAction();

            return $this->crud->performSaveAction($item->getKey());
        });
    }

    public function edit($id)
    {
        $entry = FundMovement::findOrFail($id);
        if ($entry->isAnnulled()) {
            abort(403, 'No se puede editar un egreso anulado.');
        }

        return $this->backpackEdit($id);
    }

    public function update()
    {
        $id = request()->input($this->crud->model->getKeyName());
        $entry = FundMovement::find($id);
        if ($entry && $entry->isAnnulled()) {
            abort(403, 'No se puede editar un egreso anulado.');
        }

        return DB::transaction(function () {
            $this->crud->hasAccessOrFail('update');
            $this->crud->registerFieldEvents();
            $request = $this->crud->validateRequest();
            $id = request()->input($this->crud->model->getKeyName());
            $data = $this->crud->getStrippedSaveRequest($request);
            $imputations = $request->input('imputations', []);
            unset($data['imputations'], $data['number']);

            $this->crud->entry = $this->crud->update($id, $data);
            $this->syncImputations($this->crud->entry, is_array($imputations) ? $imputations : []);
            app(AccountingOutflowService::class)->syncForFundMovement($this->crud->entry);
            $this->data['entry'] = $this->crud->entry;

            \Alert::success(trans('backpack::crud.update_success'))->flash();
            $this->crud->setSaveAction();

            return $this->crud->performSaveAction($this->crud->entry->getKey());
        });
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(FundMovementRequest::class);
        $isUpdate = $this->crud->getOperation() === 'update';
        $entry = $isUpdate ? $this->crud->getCurrentEntry() : null;
        $isUpdate = $entry instanceof FundMovement && $entry->exists;
        if ($isUpdate) {
            $entry->loadMissing('imputations');
        }

        $paymentOrderId = request()->query('payment_order_id') ?: ($isUpdate ? $entry->payment_order_id : null);
        $voucherId = request()->query('internal_voucher_id') ?: ($isUpdate ? $entry->internal_voucher_id : null);
        $invoiceId = request()->query('supplier_invoice_id') ?: ($isUpdate ? $entry->supplier_invoice_id : null);

        $defaults = $this->defaultsFromSource($paymentOrderId, $voucherId, $invoiceId, $isUpdate ? $entry : null);

        CRUD::addField([
            'name' => 'number',
            'label' => 'Número',
            'type' => 'text',
            'default' => $isUpdate ? $entry->number : FundMovement::getNextNumberPreview(),
            'attributes' => ['readonly' => 'readonly'],
            'wrapper' => ['class' => 'form-group col-sm-12 col-md-4'],
        ]);
        CRUD::field('date')->label('Fecha')->type('date')->default($defaults['date'])
            ->wrapper(['class' => 'form-group col-sm-12 col-md-4']);
        CRUD::addField([
            'name' => 'status',
            'label' => 'Estado',
            'type' => 'select_from_array',
            'options' => FundMovement::statusLabels(),
            'default' => $isUpdate ? $entry->status : FundMovement::STATUS_PENDIENTE,
            'allows_null' => false,
            'hint' => 'Confirmado genera el asiento (Caja/Banco y cuentas de imputación).',
            'wrapper' => ['class' => 'form-group col-sm-12 col-md-4'],
        ]);
        CRUD::addField([
            'name' => 'type',
            'label' => 'Tipo',
            'type' => 'select_from_array',
            'options' => FundMovement::typeLabels(),
            'default' => $defaults['type'],
            'allows_null' => false,
            'wrapper' => ['class' => 'form-group col-sm-12 col-md-4'],
        ]);
        CRUD::field('beneficiary')->label('Beneficiario')->default($defaults['beneficiary'])
            ->wrapper(['class' => 'form-group col-sm-12 col-md-8']);
        CRUD::field('amount')->label('Importe')->type('number')->default($defaults['amount'])
            ->attributes(['step' => '0.01', 'min' => '0.01'])
            ->wrapper(['class' => 'form-group col-sm-12 col-md-4']);
        CRUD::addField([
            'name' => 'currency_code',
            'label' => 'Moneda',
            'type' => 'text',
            'default' => $defaults['currency_code'],
            'attributes' => ['maxlength' => 3, 'style' => 'text-transform:uppercase'],
            'wrapper' => ['class' => 'form-group col-sm-12 col-md-2'],
        ]);
        CRUD::field('payment_method')->label('Medio')->default($defaults['payment_method'])
            ->attributes(['placeholder' => 'Ej: Banco Galicia, Caja, Transferencia'])
            ->wrapper(['class' => 'form-group col-sm-12 col-md-6']);

        $fundsId = $isUpdate ? $entry->funds_account_id : $defaults['funds_account_id'];
        CRUD::addField([
            'name' => 'funds_account_id',
            'label' => 'Cuenta de fondos (Caja/Banco)',
            'type' => 'select_from_array',
            'options' => AccountingAccount::optionsForSelect($fundsId ? (int) $fundsId : null),
            'allows_null' => true,
            'default' => $fundsId,
            'hint' => 'De donde sale (o entra) el dinero. Obligatoria al confirmar si el plan de cuentas está cargado.',
        ]);

        $impRows = $isUpdate
            ? $entry->imputations->map(fn ($i) => [
                'accounting_account_id' => $i->accounting_account_id,
                'amount' => (string) $i->amount,
                'memo' => $i->memo,
            ])->all()
            : $defaults['imputations'];

        CRUD::addField([
            'name' => 'imputations',
            'label' => 'Imputaciones contables',
            'type' => 'view',
            'view' => 'vendor.backpack.crud.fields.fund_movement_imputations',
            'fake' => true,
            'rows' => $impRows,
            'account_options' => AccountingAccount::optionsForSelect(),
            'hint' => 'Una o más cuentas (útiles, honorarios, equipamiento…). La suma debe coincidir con el importe.',
        ]);

        CRUD::addField([
            'name' => 'payment_order_id',
            'label' => 'Orden de pago (opcional)',
            'type' => 'select_from_array',
            'options' => $this->relatedOptions(PaymentOrder::class, 'payment_number', $paymentOrderId),
            'allows_null' => true,
            'default' => $paymentOrderId,
            'wrapper' => ['class' => 'form-group col-sm-12 col-md-4'],
        ]);
        CRUD::addField([
            'name' => 'internal_voucher_id',
            'label' => 'Comprobante interno (opcional)',
            'type' => 'select_from_array',
            'options' => $this->relatedOptions(InternalVoucher::class, 'number', $voucherId),
            'allows_null' => true,
            'default' => $voucherId,
            'wrapper' => ['class' => 'form-group col-sm-12 col-md-4'],
        ]);
        CRUD::addField([
            'name' => 'supplier_invoice_id',
            'label' => 'Factura (opcional)',
            'type' => 'select_from_array',
            'options' => $this->relatedOptions(SupplierInvoice::class, 'invoice_number', $invoiceId),
            'allows_null' => true,
            'default' => $invoiceId,
            'wrapper' => ['class' => 'form-group col-sm-12 col-md-4'],
        ]);
        CRUD::field('observations')->label('Observaciones')->type('textarea')->default($defaults['observations']);
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }

    protected function setupShowOperation(): void
    {
        CRUD::addClause('with', ['fundsAccount', 'paymentOrder', 'internalVoucher', 'supplierInvoice', 'imputations.account', 'accountingEntries.lines.account']);
        CRUD::column('number')->label('Número');
        CRUD::addColumn(['name' => 'type', 'label' => 'Tipo', 'type' => 'closure', 'function' => fn (FundMovement $e) => e($e->type_label)]);
        CRUD::column('date')->label('Fecha')->type('date');
        CRUD::column('beneficiary')->label('Beneficiario');
        CRUD::column('amount')->label('Importe')->type('number')->decimals(2)->prefix('$');
        CRUD::column('payment_method')->label('Medio');
        CRUD::addColumn(['name' => 'origin', 'label' => 'Documento de origen', 'type' => 'closure', 'function' => fn (FundMovement $e) => e($e->origin_label)]);
        CRUD::addColumn([
            'name' => 'imputations_table',
            'label' => 'Imputaciones y asiento',
            'type' => 'closure',
            'escaped' => false,
            'function' => function (FundMovement $entry) {
                $html = '<div class="table-responsive"><table class="table table-sm table-bordered mb-2"><thead><tr><th>Cuenta</th><th class="text-end">Monto</th><th>Detalle</th></tr></thead><tbody>';
                foreach ($entry->imputations as $imp) {
                    $html .= '<tr><td>'.e($imp->account?->identifying_label ?? '—').'</td><td class="text-end">$'.number_format((float) $imp->amount, 2).'</td><td>'.e($imp->memo ?? '').'</td></tr>';
                }
                $html .= '</tbody></table></div>';
                $html .= '<p class="small mb-1"><strong>Fondos:</strong> '.e($entry->fundsAccount?->identifying_label ?? '—').'</p>';
                foreach ($entry->accountingEntries as $as) {
                    $st = $as->status === 'posted' ? 'Registrado' : 'Revertido';
                    $html .= '<p class="small mb-0">'.e($as->entry_number).' — '.$st.' — '.e($as->description).'</p>';
                }
                if ($entry->accountingEntries->isEmpty()) {
                    $html .= '<p class="text-muted small mb-0">El asiento se genera al confirmar, con cuenta de fondos e imputaciones.</p>';
                }

                return $html;
            },
        ]);
        CRUD::column('status')->label('Estado');
        CRUD::addColumn([
            'name' => 'annul_action',
            'label' => 'Anulación',
            'type' => 'closure',
            'escaped' => false,
            'function' => function (FundMovement $entry) {
                if ($entry->isAnnulled()) {
                    return '<div class="alert alert-secondary mb-0">Anulado el '.e($entry->annulled_at?->format('d/m/Y H:i') ?? '—').': '.e($entry->annulment_reason ?? '').'</div>';
                }

                return '<a href="'.backpack_url('fund-movement/'.$entry->id.'/anular').'" class="btn btn-sm btn-warning"><i class="la la-ban"></i> Anular egreso</a>';
            },
        ]);
    }

    public function showAnularForm($id)
    {
        $movement = FundMovement::findOrFail($id);
        if ($movement->isAnnulled()) {
            \Alert::warning('Este egreso ya está anulado.')->flash();

            return redirect()->to(backpack_url('fund-movement/'.$movement->id.'/show'));
        }
        $this->data['movement'] = $movement;
        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Anular egreso '.$movement->number;

        return view('vendor.backpack.crud.fund_movement_anular_form', $this->data);
    }

    public function anular($id)
    {
        $movement = FundMovement::findOrFail($id);
        $validated = request()->validate([
            'annulment_reason' => 'required|string|min:10|max:2000',
        ]);
        app(AccountingOutflowService::class)->annulFundMovement($movement, $validated['annulment_reason']);
        \Alert::success('El egreso ha sido anulado y el asiento revertido si existía.')->flash();

        return redirect()->to(backpack_url('fund-movement/'.$movement->id.'/show'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function syncImputations(FundMovement $movement, array $rows): void
    {
        $movement->imputations()->delete();
        foreach ($rows as $row) {
            $account = (int) ($row['accounting_account_id'] ?? 0);
            $amount = (float) ($row['amount'] ?? 0);
            if ($account < 1 || $amount <= 0) {
                continue;
            }
            $movement->imputations()->create([
                'accounting_account_id' => $account,
                'amount' => round($amount, 2),
                'memo' => $row['memo'] ?? null,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultsFromSource($paymentOrderId, $voucherId, $invoiceId, ?FundMovement $entry): array
    {
        if ($entry) {
            return [
                'date' => $entry->date?->toDateString() ?: now()->toDateString(),
                'type' => $entry->type ?: FundMovement::TYPE_EGRESO,
                'beneficiary' => $entry->beneficiary,
                'amount' => $entry->amount,
                'currency_code' => $entry->currency_code ?: 'ARS',
                'payment_method' => $entry->payment_method,
                'funds_account_id' => $entry->funds_account_id,
                'observations' => $entry->observations,
                'imputations' => [],
            ];
        }

        $defaults = [
            'date' => now()->toDateString(),
            'type' => FundMovement::TYPE_EGRESO,
            'beneficiary' => '',
            'amount' => null,
            'currency_code' => 'ARS',
            'payment_method' => '',
            'funds_account_id' => null,
            'observations' => '',
            'imputations' => [],
        ];
        if ($paymentOrderId) {
            $op = PaymentOrder::with(['supplier', 'purchase_order.supplier', 'imputationAccount'])->find($paymentOrderId);
            if ($op) {
                $defaults['date'] = $op->payment_date?->toDateString() ?: $op->date?->toDateString() ?: $defaults['date'];
                $defaults['beneficiary'] = $op->resolvedSupplierName();
                $defaults['amount'] = $op->total_amount;
                $defaults['currency_code'] = $op->currency_code ?: 'ARS';
                $defaults['payment_method'] = $op->payment_method ?: $op->bank;
                $defaults['funds_account_id'] = $op->funds_account_id;
                $defaults['observations'] = 'Egreso de OP '.$op->payment_number;
                if ($op->imputation_account_id) {
                    $defaults['imputations'][] = [
                        'accounting_account_id' => $op->imputation_account_id,
                        'amount' => (string) $op->total_amount,
                        'memo' => 'Desde OP',
                    ];
                }
            }
        } elseif ($voucherId) {
            $v = InternalVoucher::find($voucherId);
            if ($v) {
                $defaults['date'] = $v->date?->toDateString() ?: $defaults['date'];
                $defaults['type'] = match ($v->type) {
                    InternalVoucher::TYPE_INGRESO => FundMovement::TYPE_INGRESO,
                    InternalVoucher::TYPE_TRANSFERENCIA => FundMovement::TYPE_TRANSFERENCIA,
                    default => FundMovement::TYPE_EGRESO,
                };
                $defaults['beneficiary'] = $v->beneficiary;
                $defaults['amount'] = $v->amount;
                $defaults['currency_code'] = $v->currency_code ?: 'ARS';
                $defaults['payment_method'] = $v->payment_method;
                $defaults['observations'] = $v->concept;
                if ($v->accounting_account_id) {
                    $defaults['imputations'][] = [
                        'accounting_account_id' => $v->accounting_account_id,
                        'amount' => (string) $v->amount,
                        'memo' => $v->motive_label,
                    ];
                }
            }
        } elseif ($invoiceId) {
            $inv = SupplierInvoice::with('supplier')->find($invoiceId);
            if ($inv) {
                $defaults['beneficiary'] = $inv->supplier?->company_name ?? '';
                $defaults['amount'] = $inv->openBalance() >= 0.01 ? $inv->openBalance() : $inv->total_amount;
                $defaults['currency_code'] = $inv->currency_code ?: 'ARS';
                $defaults['observations'] = 'Pago factura '.$inv->invoice_number;
                if ($inv->accounting_account_id) {
                    $defaults['imputations'][] = [
                        'accounting_account_id' => $inv->accounting_account_id,
                        'amount' => (string) $defaults['amount'],
                        'memo' => 'Factura '.$inv->invoice_number,
                    ];
                }
            }
        }

        return $defaults;
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @return array<int, string>
     */
    protected function relatedOptions(string $model, string $labelColumn, $includeId): array
    {
        $rows = $model::query()->orderByDesc('id')->limit(300)->get();
        if ($includeId && ! $rows->contains('id', (int) $includeId)) {
            $extra = $model::query()->find($includeId);
            if ($extra) {
                $rows->prepend($extra);
            }
        }

        return $rows->mapWithKeys(fn ($row) => [$row->id => $row->{$labelColumn}])->all();
    }

    public static function htmlRelatedTable($movements, ?string $createUrl = null): string
    {
        $html = '<div class="card border-secondary mb-0"><div class="card-header"><h6 class="mb-0"><i class="la la-money-bill"></i> Egresos</h6></div><div class="card-body p-0">';
        if ($movements->isEmpty()) {
            $html .= '<div class="p-3"><p class="text-muted mb-0">No hay egresos asociados.</p></div>';
        } else {
            $html .= '<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr>';
            $html .= '<th>Número</th><th>Fecha</th><th>Beneficiario</th><th class="text-end">Importe</th><th>Estado</th></tr></thead><tbody>';
            foreach ($movements as $m) {
                $html .= '<tr><td><a href="'.backpack_url('fund-movement/'.$m->id.'/show').'">'.e($m->number).'</a></td>';
                $html .= '<td>'.e($m->date?->format('d/m/Y') ?? '').'</td><td>'.e($m->beneficiary).'</td>';
                $html .= '<td class="text-end">$'.number_format((float) $m->amount, 2).'</td><td>'.e($m->status).'</td></tr>';
            }
            $html .= '</tbody></table></div>';
        }
        if ($createUrl) {
            $html .= '<div class="p-3 pt-2"><a href="'.e($createUrl).'" class="btn btn-sm btn-primary"><i class="la la-plus"></i> Registrar egreso</a></div>';
        }
        $html .= '</div></div>';

        return $html;
    }
}
