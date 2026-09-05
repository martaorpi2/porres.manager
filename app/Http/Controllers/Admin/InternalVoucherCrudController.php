<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\InternalVoucherRequest;
use App\Models\AccountingAccount;
use App\Models\InternalVoucher;
use App\Models\PaymentOrder;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\AccountingOutflowService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

/**
 * Comprobantes internos: documentan movimientos de dinero sin factura de proveedor.
 *
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class InternalVoucherCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation {
        edit as protected backpackEdit;
        update as protected backpackUpdate;
    }
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup(): void
    {
        $user = backpack_user();
        if (! $user instanceof User || ! $user->canManageInternalVouchers()) {
            abort(403, 'Solo la administradora del instituto o el sector de compras pueden gestionar comprobantes internos.');
        }

        CRUD::setModel(InternalVoucher::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/internal-voucher');
        CRUD::setEntityNameStrings('comprobante interno', 'comprobantes internos');
        CRUD::denyAccess('delete');
    }

    protected function setupListOperation(): void
    {
        CRUD::enableResponsiveTable();
        CRUD::removeButton('delete');
        CRUD::removeButton('update');
        CRUD::addButton('line', 'update', 'view', 'crud::buttons.internal_voucher_update', 'end');
        CRUD::addButton('line', 'anular', 'view', 'crud::buttons.internal_voucher_anular', 'end');
        CRUD::addButton('line', 'pdf', 'view', 'crud::buttons.internal_voucher_pdf', 'end');
        CRUD::addClause('with', ['accountingAccount', 'purchaseOrder', 'paymentOrder']);

        if (request()->filled('numero')) {
            CRUD::addClause('where', 'number', 'like', '%'.request()->get('numero').'%');
        }
        if (request()->filled('tipo')) {
            CRUD::addClause('where', 'type', request()->get('tipo'));
        }
        if (request()->filled('estado')) {
            CRUD::addClause('where', 'status', request()->get('estado'));
        }
        if (request()->filled('orden_compra')) {
            CRUD::addClause('where', 'purchase_order_id', (int) request()->get('orden_compra'));
        }
        if (request()->filled('orden_pago')) {
            CRUD::addClause('where', 'payment_order_id', (int) request()->get('orden_pago'));
        }

        CRUD::column('number')->label('Número');
        CRUD::column('date')->label('Fecha')->type('date');
        CRUD::addColumn([
            'name' => 'type',
            'label' => 'Tipo',
            'type' => 'closure',
            'function' => function (InternalVoucher $entry) {
                $class = match ($entry->type) {
                    InternalVoucher::TYPE_INGRESO => 'bg-success',
                    InternalVoucher::TYPE_TRANSFERENCIA => 'bg-info',
                    default => 'bg-secondary',
                };

                return '<span class="badge '.$class.'">'.e($entry->type_label).'</span>';
            },
            'escaped' => false,
        ]);
        CRUD::column('beneficiary')->label('Beneficiario')->limit(40);
        CRUD::column('amount')->label('Importe')->type('number')->decimals(2)->prefix('$');
        CRUD::addColumn([
            'name' => 'accounting_account_id',
            'label' => 'Cuenta contable',
            'type' => 'select',
            'entity' => 'accountingAccount',
            'attribute' => 'identifying_label',
            'model' => AccountingAccount::class,
        ]);
        CRUD::column('payment_method')->label('Medio de pago');
        CRUD::addColumn([
            'name' => 'purchase_order_id',
            'label' => 'OC',
            'type' => 'closure',
            'function' => function (InternalVoucher $entry) {
                return $entry->purchase_order_id
                    ? e($entry->purchaseOrder?->number ?? ('OC #'.$entry->purchase_order_id))
                    : '<span class="text-muted">—</span>';
            },
            'escaped' => false,
        ]);
        CRUD::addColumn([
            'name' => 'payment_order_id',
            'label' => 'OP',
            'type' => 'closure',
            'function' => function (InternalVoucher $entry) {
                return $entry->payment_order_id
                    ? e($entry->paymentOrder?->payment_number ?? ('OP #'.$entry->payment_order_id))
                    : '<span class="text-muted">—</span>';
            },
            'escaped' => false,
        ]);
        CRUD::column('status')->label('Estado');
    }

    public function store()
    {
        $this->crud->hasAccessOrFail('create');

        return DB::transaction(function () {
            $request = $this->crud->validateRequest();
            $this->crud->registerFieldEvents();
            $request->merge(['number' => InternalVoucher::getNextNumber()]);

            $item = $this->crud->create($this->crud->getStrippedSaveRequest($request));
            $this->data['entry'] = $this->crud->entry = $item;

            \Alert::success(trans('backpack::crud.insert_success'))->flash();
            $this->crud->setSaveAction();

            return $this->crud->performSaveAction($item->getKey());
        });
    }

    public function edit($id)
    {
        $entry = InternalVoucher::findOrFail($id);
        if ($entry->isAnnulled()) {
            abort(403, 'No se puede editar un comprobante interno anulado.');
        }

        return $this->backpackEdit($id);
    }

    public function update()
    {
        $id = request()->input($this->crud->model->getKeyName());
        $entry = InternalVoucher::find($id);
        if ($entry && $entry->isAnnulled()) {
            abort(403, 'No se puede editar un comprobante interno anulado.');
        }

        return DB::transaction(function () {
            $this->crud->hasAccessOrFail('update');
            $this->crud->registerFieldEvents();
            $request = $this->crud->validateRequest();
            $id = request()->input($this->crud->model->getKeyName());
            $data = $this->crud->getStrippedSaveRequest($request);
            unset($data['number']);

            $this->crud->entry = $this->crud->update($id, $data);
            $this->data['entry'] = $this->crud->entry;

            \Alert::success(trans('backpack::crud.update_success'))->flash();
            $this->crud->setSaveAction();

            return $this->crud->performSaveAction($this->crud->entry->getKey());
        });
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(InternalVoucherRequest::class);

        $isUpdate = $this->crud->getOperation() === 'update';
        $entry = $isUpdate ? $this->crud->getCurrentEntry() : null;
        $isUpdate = $entry instanceof InternalVoucher && $entry->exists;

        $purchaseOrderId = request()->query('purchase_order_id') ?: ($isUpdate ? $entry->purchase_order_id : null);
        $paymentOrderId = request()->query('payment_order_id') ?: ($isUpdate ? $entry->payment_order_id : null);
        if ($paymentOrderId && ! $purchaseOrderId) {
            $purchaseOrderId = PaymentOrder::query()->whereKey((int) $paymentOrderId)->value('purchase_order_id');
        }

        CRUD::addField([
            'name' => 'number',
            'label' => 'Número',
            'type' => 'text',
            'default' => $isUpdate ? $entry->number : InternalVoucher::getNextNumberPreview(),
            'attributes' => ['readonly' => 'readonly'],
            'wrapper' => ['class' => 'form-group col-sm-12 col-md-4'],
        ]);
        CRUD::field('date')->label('Fecha')->type('date')->default(now()->toDateString())
            ->wrapper(['class' => 'form-group col-sm-12 col-md-4']);
        CRUD::addField([
            'name' => 'status',
            'label' => 'Estado',
            'type' => 'select_from_array',
            'options' => InternalVoucher::statusLabels(),
            'default' => InternalVoucher::STATUS_EMITIDO,
            'allows_null' => false,
            'wrapper' => ['class' => 'form-group col-sm-12 col-md-4'],
        ]);
        CRUD::addField([
            'name' => 'type',
            'label' => 'Tipo',
            'type' => 'select_from_array',
            'options' => InternalVoucher::typeLabels(),
            'default' => InternalVoucher::TYPE_EGRESO,
            'allows_null' => false,
            'wrapper' => ['class' => 'form-group col-sm-12 col-md-6'],
        ]);
        CRUD::addField([
            'name' => 'motive',
            'label' => 'Motivo',
            'type' => 'select_from_array',
            'options' => InternalVoucher::motiveLabels(),
            'allows_null' => false,
            'wrapper' => ['class' => 'form-group col-sm-12 col-md-6'],
        ]);
        CRUD::field('concept')->label('Concepto')->type('textarea')
            ->hint('Describe el movimiento. Ej: Reintegro de gastos por actividad institucional.');
        CRUD::field('beneficiary')->label('Beneficiario')
            ->hint('Texto libre: persona, caja, banco u otro destinatario.')
            ->wrapper(['class' => 'form-group col-sm-12 col-md-6']);
        CRUD::field('amount')->label('Importe')->type('number')
            ->attributes(['step' => '0.01', 'min' => '0.01'])
            ->wrapper(['class' => 'form-group col-sm-12 col-md-6']);

        $accountId = $isUpdate ? $entry->accounting_account_id : null;
        CRUD::addField([
            'name' => 'accounting_account_id',
            'label' => 'Cuenta contable',
            'type' => 'select_from_array',
            'options' => AccountingAccount::optionsForSelect($accountId ? (int) $accountId : null),
            'allows_null' => true,
            'default' => $accountId,
            'hint' => AccountingAccount::chartIsLoaded()
                ? 'Cuenta a la que se imputa el movimiento.'
                : 'El plan de cuentas aún no tiene cuentas activas. Puede cargarla después desde Cuentas contables.',
            'wrapper' => ['class' => 'form-group col-sm-12 col-md-6'],
        ]);
        CRUD::field('payment_method')->label('Medio de pago')
            ->attributes(['placeholder' => 'Ej: Caja, Transferencia, Cheque'])
            ->wrapper(['class' => 'form-group col-sm-12 col-md-6']);

        CRUD::addField([
            'name' => 'authorizing_user_id',
            'label' => 'Autorizado por',
            'type' => 'select',
            'entity' => 'authorizingUser',
            'attribute' => 'name',
            'model' => User::class,
            'default' => backpack_user()?->id,
        ]);

        CRUD::addField([
            'name' => 'purchase_order_id',
            'label' => 'Orden de compra (opcional)',
            'type' => 'select_from_array',
            'options' => $this->purchaseOrderOptions(),
            'allows_null' => true,
            'default' => $purchaseOrderId,
            'hint' => 'Si el movimiento se relaciona con una compra, asócielo aquí.',
            'wrapper' => ['class' => 'form-group col-sm-12 col-md-6'],
        ]);
        CRUD::addField([
            'name' => 'payment_order_id',
            'label' => 'Orden de pago (opcional)',
            'type' => 'select_from_array',
            'options' => $this->paymentOrderOptions($isUpdate ? (int) $entry->payment_order_id : ($paymentOrderId ? (int) $paymentOrderId : null)),
            'allows_null' => true,
            'default' => $paymentOrderId,
            'hint' => 'Si elige una OP, se completa la OC correspondiente.',
            'wrapper' => ['class' => 'form-group col-sm-12 col-md-6'],
        ]);
        CRUD::addField([
            'name' => 'op_oc_link_script',
            'type' => 'custom_html',
            'label' => false,
            'wrapper' => false,
            'value' => $this->opOcLinkScript(),
        ]);

        CRUD::field('observations')->label('Observaciones')->type('textarea');
        CRUD::field('attachment')->label('Adjunto (PDF o imagen)')->type('upload')
            ->disk('public')
            ->path('internal-vouchers')
            ->hint('Opcional: ticket, extracto u otro respaldo.');
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }

    protected function setupShowOperation(): void
    {
        CRUD::addClause('with', [
            'accountingAccount',
            'authorizingUser',
            'purchaseOrder',
            'paymentOrder',
            'annulledBy',
            'fundMovements',
        ]);

        CRUD::column('number')->label('Número');
        CRUD::addColumn([
            'name' => 'document_title',
            'label' => 'Documento',
            'type' => 'closure',
            'function' => fn (InternalVoucher $entry) => e($entry->document_title),
        ]);
        CRUD::column('date')->label('Fecha')->type('date');
        CRUD::addColumn([
            'name' => 'type',
            'label' => 'Tipo',
            'type' => 'closure',
            'function' => fn (InternalVoucher $entry) => e($entry->type_label),
        ]);
        CRUD::addColumn([
            'name' => 'motive',
            'label' => 'Motivo',
            'type' => 'closure',
            'function' => fn (InternalVoucher $entry) => e($entry->motive_label),
        ]);
        CRUD::column('concept')->label('Concepto');
        CRUD::column('beneficiary')->label('Beneficiario');
        CRUD::column('amount')->label('Importe')->type('number')->decimals(2)->prefix('$');
        CRUD::column('currency_code')->label('Moneda');
        CRUD::addColumn([
            'name' => 'accounting_account_id',
            'label' => 'Cuenta contable',
            'type' => 'select',
            'entity' => 'accountingAccount',
            'attribute' => 'identifying_label',
            'model' => AccountingAccount::class,
        ]);
        CRUD::column('payment_method')->label('Medio de pago');
        CRUD::addColumn([
            'name' => 'authorizing_user_id',
            'label' => 'Autorizado por',
            'type' => 'select',
            'entity' => 'authorizingUser',
            'attribute' => 'name',
            'model' => User::class,
        ]);
        CRUD::addColumn([
            'name' => 'purchase_order_id',
            'label' => 'Orden de compra',
            'type' => 'closure',
            'function' => function (InternalVoucher $entry) {
                if (! $entry->purchase_order_id) {
                    return '—';
                }
                $label = e($entry->purchaseOrder?->number ?? ('OC #'.$entry->purchase_order_id));

                return '<a href="'.backpack_url('purchase-order/'.$entry->purchase_order_id.'/show').'">'.$label.'</a>';
            },
            'escaped' => false,
        ]);
        CRUD::addColumn([
            'name' => 'payment_order_id',
            'label' => 'Orden de pago',
            'type' => 'closure',
            'function' => function (InternalVoucher $entry) {
                if (! $entry->payment_order_id) {
                    return '—';
                }
                $label = e($entry->paymentOrder?->payment_number ?? ('OP #'.$entry->payment_order_id));

                return '<a href="'.backpack_url('payment-order/'.$entry->payment_order_id.'/show').'">'.$label.'</a>';
            },
            'escaped' => false,
        ]);
        CRUD::addColumn([
            'name' => 'fund_movements',
            'label' => 'Egresos',
            'type' => 'closure',
            'escaped' => false,
            'function' => function (InternalVoucher $entry) {
                $user = backpack_user();
                if (! ($user instanceof User && $user->canManageFundMovements())) {
                    return '—';
                }
                $createUrl = ! $entry->isAnnulled()
                    ? backpack_url('fund-movement/create?internal_voucher_id='.$entry->id)
                    : null;
                $rows = $entry->relationLoaded('fundMovements')
                    ? $entry->fundMovements
                    : $entry->fundMovements()->get();

                return FundMovementCrudController::htmlRelatedTable($rows, $createUrl);
            },
        ]);
        CRUD::column('status')->label('Estado');
        CRUD::column('observations')->label('Observaciones');
        CRUD::addColumn([
            'name' => 'attachment',
            'label' => 'Adjunto',
            'type' => 'closure',
            'function' => function (InternalVoucher $entry) {
                if (! $entry->attachment) {
                    return '—';
                }
                $url = \Storage::disk('public')->url($entry->attachment);

                return '<a href="'.e($url).'" target="_blank" rel="noopener">'.e(basename($entry->attachment)).'</a>';
            },
            'escaped' => false,
        ]);
        CRUD::addColumn([
            'name' => 'annulment_info',
            'label' => 'Anulación',
            'type' => 'closure',
            'function' => function (InternalVoucher $entry) {
                if (! $entry->isAnnulled()) {
                    $user = backpack_user();
                    if ($user instanceof User && $user->canManageInternalVouchers()) {
                        return '<a href="'.backpack_url('internal-voucher/'.$entry->id.'/anular').'" class="btn btn-sm btn-warning"><i class="la la-ban"></i> Anular comprobante</a>';
                    }

                    return '—';
                }
                $html = '<div class="alert alert-secondary mb-0">';
                $html .= '<p class="mb-1"><strong>Anulado el:</strong> '.($entry->annulled_at ? $entry->annulled_at->format('d/m/Y H:i') : '—').'</p>';
                $html .= '<p class="mb-1"><strong>Motivo:</strong> '.e($entry->annulment_reason ?? '—').'</p>';
                if ($entry->annulledBy) {
                    $html .= '<p class="mb-0"><strong>Anulado por:</strong> '.e($entry->annulledBy->name).'</p>';
                }
                $html .= '</div>';

                return $html;
            },
            'escaped' => false,
        ]);
        CRUD::addColumn([
            'name' => 'pdf_action',
            'label' => 'PDF',
            'type' => 'closure',
            'function' => function (InternalVoucher $entry) {
                return '<a href="'.route('internal-voucher.pdf', $entry->id).'" class="btn btn-sm btn-info" target="_blank"><i class="la la-file-pdf"></i> Ver PDF</a>';
            },
            'escaped' => false,
        ]);
    }

    public function generatePdf($id)
    {
        $voucher = InternalVoucher::with([
            'accountingAccount',
            'authorizingUser',
            'purchaseOrder',
            'paymentOrder',
            'annulledBy',
        ])->findOrFail($id);

        $pdf = Pdf::loadView('internal-voucher-pdf', compact('voucher'));

        return $pdf->stream('comprobante-interno-'.$voucher->number.'.pdf');
    }

    public function showAnularForm($id)
    {
        $voucher = InternalVoucher::findOrFail($id);
        $user = backpack_user();
        if (! $user instanceof User || ! $user->canManageInternalVouchers()) {
            abort(403, 'No tiene permiso para anular comprobantes internos.');
        }
        if ($voucher->isAnnulled()) {
            \Alert::warning('Este comprobante interno ya está anulado.')->flash();

            return redirect()->to(backpack_url('internal-voucher/'.$voucher->id.'/show'));
        }

        $this->data['voucher'] = $voucher;
        $this->data['title'] = 'Anular comprobante interno '.$voucher->number;
        $this->data['crud'] = $this->crud;

        return view('vendor.backpack.crud.internal_voucher_anular_form', $this->data);
    }

    public function anular($id)
    {
        $voucher = InternalVoucher::findOrFail($id);
        $user = backpack_user();
        if (! $user instanceof User || ! $user->canManageInternalVouchers()) {
            abort(403, 'No tiene permiso para anular comprobantes internos.');
        }
        if ($voucher->isAnnulled()) {
            \Alert::warning('Este comprobante interno ya está anulado.')->flash();

            return redirect()->to(backpack_url('internal-voucher/'.$voucher->id.'/show'));
        }

        $validated = request()->validate([
            'annulment_reason' => 'required|string|min:10|max:2000',
        ], [
            'annulment_reason.required' => 'El motivo de la anulación es obligatorio.',
            'annulment_reason.min' => 'El motivo debe tener al menos 10 caracteres.',
            'annulment_reason.max' => 'El motivo no puede superar 2000 caracteres.',
        ]);

        DB::transaction(function () use ($voucher, $validated, $user): void {
            $voucher->update([
                'status' => InternalVoucher::STATUS_ANULADO,
                'annulled_at' => now(),
                'annulment_reason' => $validated['annulment_reason'],
                'annulled_by_id' => $user->id,
            ]);
            $service = app(AccountingOutflowService::class);
            foreach ($voucher->fundMovements()->where('status', '!=', \App\Models\FundMovement::STATUS_ANULADO)->get() as $movement) {
                $service->annulFundMovement($movement, 'Anulación del comprobante interno '.$voucher->number);
            }
        });

        \Alert::success('El comprobante interno ha sido anulado. Los egresos asociados también se anularon.')->flash();

        return redirect()->to(backpack_url('internal-voucher/'.$voucher->id.'/show'));
    }

    /**
     * @return array<int, string>
     */
    protected function purchaseOrderOptions(): array
    {
        return PurchaseOrder::query()
            ->orderByDesc('id')
            ->limit(500)
            ->pluck('number', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function paymentOrderOptions(?int $includeId = null): array
    {
        return PaymentOrder::query()
            ->with('purchase_order')
            ->where(function ($q) use ($includeId) {
                $q->where('status', '!=', 'Anulada');
                if ($includeId) {
                    $q->orWhere('id', $includeId);
                }
            })
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->mapWithKeys(function (PaymentOrder $po) {
                $oc = $po->purchase_order?->number;
                $label = $po->payment_number.($oc ? ' — OC '.$oc : '');

                return [$po->id => $label];
            })
            ->all();
    }

    protected function opOcLinkScript(): string
    {
        $map = PaymentOrder::query()
            ->whereNotNull('purchase_order_id')
            ->pluck('purchase_order_id', 'id');
        $json = json_encode((object) $map->all(), JSON_UNESCAPED_UNICODE);

        return <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {
    var opToOc = {$json};
    var ocSelect = document.querySelector('[name="purchase_order_id"]');
    var opSelect = document.querySelector('[name="payment_order_id"]');
    if (!ocSelect || !opSelect) {
        return;
    }

    function filterOps() {
        var ocId = ocSelect.value;
        Array.prototype.forEach.call(opSelect.options, function (opt) {
            if (!opt.value) {
                opt.hidden = false;
                opt.disabled = false;
                return;
            }
            var mapped = opToOc[opt.value];
            var hide = !!(ocId && String(mapped || '') !== String(ocId));
            opt.hidden = hide;
            opt.disabled = hide;
        });
        var selected = opSelect.options[opSelect.selectedIndex];
        if (selected && selected.hidden) {
            opSelect.value = '';
            if (window.jQuery) {
                jQuery(opSelect).trigger('change');
            }
        }
    }

    function applyOcFromOp() {
        var ocId = opToOc[opSelect.value];
        if (ocId) {
            ocSelect.value = String(ocId);
            if (window.jQuery) {
                jQuery(ocSelect).trigger('change.select2');
            }
        }
        filterOps();
    }

    if (window.jQuery) {
        jQuery(opSelect).on('change', applyOcFromOp);
        jQuery(ocSelect).on('change', filterOps);
    } else {
        opSelect.addEventListener('change', applyOcFromOp);
        ocSelect.addEventListener('change', filterOps);
    }

    filterOps();
});
</script>
HTML;
    }

    public static function htmlRelatedTable($vouchers, ?string $createUrl = null): string
    {
        $html = '<div class="card border-secondary mb-0"><div class="card-header"><h6 class="mb-0"><i class="la la-file-alt"></i> Comprobantes internos</h6></div><div class="card-body p-0">';
        if ($vouchers->isEmpty()) {
            $html .= '<div class="p-3"><p class="text-muted mb-0">No hay comprobantes internos asociados.</p></div>';
        } else {
            $html .= '<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr>';
            $html .= '<th>Número</th><th>Fecha</th><th>Tipo</th><th>Beneficiario</th><th class="text-end">Importe</th><th>Estado</th></tr></thead><tbody>';
            foreach ($vouchers as $voucher) {
                $html .= '<tr>';
                $html .= '<td><a href="'.backpack_url('internal-voucher/'.$voucher->id.'/show').'">'.e($voucher->number).'</a></td>';
                $html .= '<td>'.e($voucher->date?->format('d/m/Y') ?? '').'</td>';
                $html .= '<td>'.e($voucher->type_label).'</td>';
                $html .= '<td>'.e($voucher->beneficiary).'</td>';
                $html .= '<td class="text-end">$'.number_format((float) $voucher->amount, 2).'</td>';
                $html .= '<td>'.e($voucher->status).'</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table></div>';
        }
        if ($createUrl) {
            $html .= '<div class="p-3 pt-2"><a href="'.e($createUrl).'" class="btn btn-sm btn-primary"><i class="la la-plus"></i> Nuevo comprobante interno</a></div>';
        }
        $html .= '</div></div>';

        return $html;
    }
}
