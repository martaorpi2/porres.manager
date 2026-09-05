<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\SupplierInvoiceImputeRequest;
use App\Http\Requests\SupplierInvoiceRequest;
use App\Models\AccountingAccount;
use App\Models\PaymentOrder;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Services\PaymentOrderInvoiceImputationService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Facturas de proveedor (deuda contable) e imputación de órdenes de pago (normal / anticipo).
 *
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class SupplierInvoiceCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup(): void
    {
        $user = backpack_user();
        if (! $this->userCanManageSupplierInvoices($user)) {
            abort(403, 'Solo la administradora del instituto, el sector de compras o el administrador del sistema pueden gestionar facturas de proveedor.');
        }

        CRUD::setModel(SupplierInvoice::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/supplier-invoice');
        CRUD::setEntityNameStrings('factura de proveedor', 'facturas de proveedor');
    }

    protected function setupListOperation(): void
    {
        CRUD::enableResponsiveTable();
        CRUD::addClause('with', ['purchaseOrder', 'supplier', 'accountingAccount']);

        if (request()->filled('supplier_id')) {
            CRUD::addClause('where', 'supplier_id', (int) request()->query('supplier_id'));
        }

        if (request()->boolean('impagas_20_dias')) {
            CRUD::addClause('overdueUnpaid');
            $this->crud->setHeading('Facturas impagas ('.SupplierInvoice::UNPAID_ALERT_AFTER_DAYS.' días o más)');
        }

        CRUD::addColumn([
            'name' => 'purchase_order_id',
            'label' => 'Orden de compra',
            'type' => 'closure',
            'function' => function (SupplierInvoice $entry) {
                return $entry->purchase_order_id
                    ? e($entry->purchaseOrder?->number ?? ('OC #'.$entry->purchase_order_id))
                    : '<span class="text-muted">—</span>';
            },
            'escaped' => false,
        ]);
        CRUD::addColumn([
            'name' => 'supplier_id',
            'label' => 'Proveedor',
            'type' => 'select',
            'entity' => 'supplier',
            'attribute' => 'company_name',
            'model' => \App\Models\Supplier::class,
        ]);
        CRUD::addColumn([
            'name' => 'accounting_account_id',
            'label' => 'Cuenta contable',
            'type' => 'select',
            'entity' => 'accountingAccount',
            'attribute' => 'identifying_label',
            'model' => AccountingAccount::class,
        ]);
        CRUD::column('invoice_number')->label('Nº factura');
        CRUD::column('invoice_date')->label('Fecha')->type('date');
        CRUD::column('total_amount')->label('Monto total')->type('number')->decimals(2)->prefix('$');
        CRUD::column('currency_code')->label('Moneda');
        CRUD::addColumn([
            'name' => 'attachment',
            'label' => 'Adjunto',
            'type' => 'closure',
            'function' => function (SupplierInvoice $entry) {
                if (! $entry->attachment) {
                    return '<span class="text-muted">—</span>';
                }
                $url = \Storage::disk('public')->url($entry->attachment);

                return '<a href="'.e($url).'" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="la la-paperclip"></i> Ver</a>';
            },
            'escaped' => false,
        ]);
        CRUD::addColumn([
            'name' => 'open_balance',
            'label' => 'Saldo factura',
            'type' => 'closure',
            'function' => function (SupplierInvoice $entry) {
                return '$' . number_format($entry->openBalance(), 2);
            },
        ]);
        CRUD::addColumn([
            'name' => 'days_since_invoice',
            'label' => 'Días',
            'type' => 'closure',
            'function' => function (SupplierInvoice $entry) {
                $days = $entry->daysSinceInvoice();
                $overdue = $days >= SupplierInvoice::UNPAID_ALERT_AFTER_DAYS && $entry->openBalance() >= 0.01;
                $class = $overdue ? 'bg-danger' : 'bg-secondary';

                return '<span class="badge '.$class.'">'.$days.'</span>';
            },
            'escaped' => false,
        ]);
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(SupplierInvoiceRequest::class);

        $supplierIdFromUrl = request()->query('supplier_id') ? (int) request()->query('supplier_id') : null;
        $purchaseOrderIdFromUrl = request()->query('purchase_order_id') ? (int) request()->query('purchase_order_id') : null;

        $supplierQuery = Supplier::query()->orderBy('company_name');
        $user = backpack_user();
        if ($user && $user->hasResponsableAreaOrInstituteAuthorityRole()) {
            $supplierQuery->visibleForBackpackUser($user);
        }
        $supplierOptions = $supplierQuery->pluck('company_name', 'id')->toArray();

        CRUD::addField([
            'name' => 'supplier_id',
            'label' => 'Proveedor',
            'type' => 'select_from_array',
            'options' => $supplierOptions,
            'allows_null' => false,
            'default' => $supplierIdFromUrl,
        ]);

        $defaultAccountId = null;
        if ($this->crud->getOperation() === 'update') {
            $entry = $this->crud->getCurrentEntry();
            $defaultAccountId = $entry?->accounting_account_id
                ?: Supplier::query()->whereKey($entry?->supplier_id)->value('accounting_account_id');
        } elseif ($supplierIdFromUrl) {
            $defaultAccountId = Supplier::query()->whereKey($supplierIdFromUrl)->value('accounting_account_id');
        }

        CRUD::addField([
            'name' => 'accounting_account_id',
            'label' => 'Cuenta contable',
            'type' => 'select_from_array',
            'options' => AccountingAccount::optionsForSelect($defaultAccountId ? (int) $defaultAccountId : null),
            'allows_null' => true,
            'default' => $defaultAccountId,
            'hint' => 'Por defecto se toma la cuenta del proveedor. Puede rectificarla solo para esta factura.',
        ]);

        CRUD::addField([
            'name' => 'accounting_account_from_supplier_script',
            'type' => 'custom_html',
            'label' => false,
            'wrapper' => false,
            'value' => $this->accountingAccountFromSupplierScript(),
        ]);

        $poOptions = $this->purchaseOrderOptionsForSupplier($supplierIdFromUrl);
        CRUD::addField([
            'name' => 'purchase_order_id',
            'label' => 'Orden de compra (opcional)',
            'type' => 'select_from_array',
            'options' => $poOptions,
            'allows_null' => true,
            'default' => $purchaseOrderIdFromUrl,
            'hint' => 'Opcional. Puede registrar la factura solo por proveedor (honorarios, servicios, etc.) e imputar una orden de pago sin que ambos tengan la misma OC.',
        ]);
        CRUD::field('invoice_number')->label('Número de factura');
        CRUD::field('invoice_date')->label('Fecha de factura')->type('date');
        CRUD::field('total_amount')->label('Monto total')
            ->type('number')
            ->attributes(['step' => '0.01', 'min' => '0.01'])
            ->hint('Cargue el monto total de la factura, sin discriminar IVA.');
        CRUD::addField([
            'name' => 'currency_code',
            'label' => 'Moneda (ISO 4217)',
            'type' => 'text',
            'default' => 'ARS',
            'attributes' => ['maxlength' => 3, 'style' => 'text-transform:uppercase'],
            'hint' => 'Debe coincidir con la moneda indicada en la orden de pago al imputar.',
        ]);
        CRUD::field('observations')->label('Observaciones')->type('textarea');
        CRUD::field('attachment')->label('Archivo de factura (PDF o imagen)')->type('upload')
            ->disk('public')
            ->path('supplier-invoices')
            ->hint('Opcional.');
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }

    protected function setupShowOperation(): void
    {
        CRUD::addClause('with', ['purchaseOrder', 'supplier', 'paymentOrders', 'accountingAccount', 'fundMovements']);

        CRUD::column('invoice_number')->label('Número de factura');
        CRUD::column('invoice_date')->label('Fecha')->type('date');
        CRUD::column('total_amount')->label('Monto total')->type('number')->decimals(2)->prefix('$');
        CRUD::column('currency_code')->label('Moneda');
        CRUD::addColumn([
            'name' => 'open_balance',
            'label' => 'Saldo pendiente',
            'type' => 'closure',
            'function' => function (SupplierInvoice $entry) {
                return '$' . number_format($entry->openBalance(), 2);
            },
        ]);
        CRUD::addColumn([
            'name' => 'purchase_order_id',
            'label' => 'Orden de compra',
            'type' => 'closure',
            'function' => function (SupplierInvoice $entry) {
                return $entry->purchase_order_id
                    ? e($entry->purchaseOrder?->number ?? ('OC #'.$entry->purchase_order_id))
                    : '<span class="text-muted">—</span>';
            },
            'escaped' => false,
        ]);
        CRUD::addColumn([
            'name' => 'attachment',
            'label' => 'Adjunto',
            'type' => 'closure',
            'function' => function (SupplierInvoice $entry) {
                if (! $entry->attachment) {
                    return '—';
                }
                $url = \Storage::disk('public')->url($entry->attachment);

                return '<a href="'.e($url).'" target="_blank" rel="noopener">'.e(basename($entry->attachment)).'</a>';
            },
            'escaped' => false,
        ]);
        CRUD::addColumn([
            'name' => 'supplier_id',
            'label' => 'Proveedor',
            'type' => 'select',
            'entity' => 'supplier',
            'attribute' => 'company_name',
            'model' => \App\Models\Supplier::class,
        ]);
        CRUD::addColumn([
            'name' => 'accounting_account_id',
            'label' => 'Cuenta contable',
            'type' => 'select',
            'entity' => 'accountingAccount',
            'attribute' => 'identifying_label',
            'model' => AccountingAccount::class,
        ]);
        CRUD::column('observations')->label('Observaciones');

        CRUD::addColumn([
            'name' => 'imputaciones',
            'label' => 'Imputaciones (órdenes de pago)',
            'type' => 'closure',
            'function' => function (SupplierInvoice $entry) {
                $rows = $entry->paymentOrders()->where('payment_orders.status', '!=', 'Anulada')->get();
                if ($rows->isEmpty()) {
                    return '<p class="text-muted mb-0">Sin imputaciones registradas.</p>';
                }
                $html = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead><tr>'
                    . '<th>OP</th><th>Tipo</th><th>Monto imputado</th><th>Fecha imputación</th></tr></thead><tbody>';
                foreach ($rows as $po) {
                    $kind = $po->billing_kind === 'anticipo' ? 'Anticipo' : 'Normal';
                    $html .= '<tr><td>' . e($po->payment_number) . '</td><td>' . e($kind) . '</td>'
                        . '<td class="text-end">$' . number_format((float) $po->pivot->amount_applied, 2) . '</td>'
                        . '<td>' . e($po->pivot->imputed_at) . '</td></tr>';
                }
                $html .= '</tbody></table></div>';

                return $html;
            },
            'escaped' => false,
        ]);

        $user = backpack_user();
        if ($this->userCanManageSupplierInvoices($user)) {
            CRUD::addColumn([
                'name' => 'fund_movements',
                'label' => 'Egresos',
                'type' => 'closure',
                'escaped' => false,
                'function' => function (SupplierInvoice $entry) {
                    $user = backpack_user();
                    if (! ($user instanceof User && $user->canManageFundMovements())) {
                        return '—';
                    }
                    $rows = $entry->relationLoaded('fundMovements')
                        ? $entry->fundMovements
                        : $entry->fundMovements()->get();
                    $createUrl = backpack_url('fund-movement/create?supplier_invoice_id='.$entry->id);

                    return FundMovementCrudController::htmlRelatedTable($rows, $createUrl);
                },
            ]);
            CRUD::addColumn([
                'name' => 'imputar_action',
                'label' => '',
                'type' => 'closure',
                'function' => function (SupplierInvoice $entry) {
                    $html = '';
                    $user = backpack_user();
                    if ($entry->openBalance() < 0.01) {
                        $html .= '<p class="text-muted mb-2">Factura sin saldo pendiente.</p>';
                    } else {
                        $candidates = app(PaymentOrderInvoiceImputationService::class)
                            ->candidatePaymentOrdersForInvoice($entry);
                        if ($candidates->isEmpty()) {
                            $html .= '<div class="alert alert-warning mb-2">No hay órdenes de pago con saldo imputable del mismo proveedor y moneda. No hace falta que tengan la misma orden de compra.</div>';
                            if ($user instanceof User && $user->canActAsAdministradoraInstitucion()) {
                                $html .= '<a href="'.backpack_url('payment-order/create?supplier_id='.$entry->supplier_id).'" class="btn btn-outline-primary me-1"><i class="la la-plus"></i> Crear orden de pago</a>';
                            }
                        } else {
                            $html .= '<a href="'.backpack_url('supplier-invoice/'.$entry->id.'/imputar').'" class="btn btn-primary me-1"><i class="la la-link"></i> Imputar orden de pago a esta factura</a>';
                        }
                    }

                    return $html !== '' ? $html : '—';
                },
                'escaped' => false,
            ]);
        }
    }

    public function showImputeForm(int $id)
    {
        $user = backpack_user();
        if (! $this->userCanManageSupplierInvoices($user)) {
            abort(403, 'Solo la administradora del instituto, el sector de compras o el administrador del sistema pueden registrar imputaciones.');
        }

        $invoice = SupplierInvoice::with(['purchaseOrder', 'supplier'])->findOrFail($id);
        if ($invoice->openBalance() < 0.01) {
            \Alert::warning('Esta factura no tiene saldo pendiente.')->flash();

            return redirect()->to(backpack_url('supplier-invoice/' . $invoice->id . '/show'));
        }

        $service = app(PaymentOrderInvoiceImputationService::class);
        $candidates = $service->candidatePaymentOrdersForInvoice($invoice);

        if ($candidates->isEmpty()) {
            \Alert::error('No hay órdenes de pago con saldo imputable del mismo proveedor y moneda. Puede crear una OP sin orden de compra.')->flash();

            return redirect()->to(backpack_url('supplier-invoice/' . $invoice->id . '/show'));
        }

        $options = [];
        foreach ($candidates as $po) {
            $cap = $service->remainingImputableCapacityOnPaymentOrder($po);
            $label = $po->payment_number . ' — ' . ($po->billing_kind === 'anticipo' ? 'Anticipo' : 'Normal')
                . ' — disponible $' . number_format($cap, 2);
            $options[$po->id] = $label;
        }

        $this->data['invoice'] = $invoice;
        $this->data['paymentOrderOptions'] = $options;
        $this->data['title'] = 'Imputar pago a factura ' . $invoice->invoice_number;
        $this->data['breadcrumbs'] = [
            trans('backpack::crud.admin') => backpack_url('dashboard'),
            'Facturas proveedor' => backpack_url('supplier-invoice'),
            $invoice->invoice_number => backpack_url('supplier-invoice/' . $invoice->id . '/show'),
            'Imputar' => false,
        ];

        return view('vendor.backpack.crud.supplier_invoice_impute', $this->data);
    }

    public function storeImputation(int $id, SupplierInvoiceImputeRequest $request)
    {
        $user = backpack_user();
        if (! $this->userCanManageSupplierInvoices($user)) {
            abort(403, 'Solo la administradora del instituto, el sector de compras o el administrador del sistema pueden registrar imputaciones.');
        }

        $invoice = SupplierInvoice::findOrFail($id);
        $validated = $request->validated();

        $paymentOrder = PaymentOrder::findOrFail((int) $validated['payment_order_id']);

        try {
            app(PaymentOrderInvoiceImputationService::class)->apply(
                $paymentOrder,
                $invoice,
                (float) $validated['amount'],
                $validated['imputed_at']
            );
        } catch (\InvalidArgumentException $e) {
            \Alert::error($e->getMessage())->flash();

            return redirect()->back()->withInput();
        }

        \Alert::success('Imputación registrada correctamente.')->flash();

        return redirect()->to(backpack_url('supplier-invoice/' . $invoice->id . '/show'));
    }

    private function userCanManageSupplierInvoices($user): bool
    {
        return $user instanceof User && $user->canManageSupplierInvoices();
    }

    /**
     * @return array<int|string, string>
     */
    protected function purchaseOrderOptionsForSupplier(?int $supplierId): array
    {
        $q = PurchaseOrder::query()->orderByDesc('id')->limit(400);
        if ($supplierId) {
            $q->where(function ($w) use ($supplierId) {
                $w->where('supplier_id', $supplierId)
                    ->orWhereHas('details', fn ($d) => $d->where('supplier_id', $supplierId));
            });
        }

        return $q->pluck('number', 'id')->toArray();
    }

    /**
     * Al cambiar el proveedor, propone su cuenta contable. En edición no pisa el valor ya guardado hasta que cambien el proveedor.
     */
    protected function accountingAccountFromSupplierScript(): string
    {
        $map = Supplier::query()
            ->whereNotNull('accounting_account_id')
            ->pluck('accounting_account_id', 'id');

        $json = json_encode((object) $map->all(), JSON_UNESCAPED_UNICODE);
        $isUpdate = $this->crud->getOperation() === 'update' ? 'true' : 'false';

        return <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {
    var supplierAccounts = {$json};
    var isUpdate = {$isUpdate};
    var supplierSelect = document.querySelector('[name="supplier_id"]');
    var accountSelect = document.querySelector('[name="accounting_account_id"]');
    if (!supplierSelect || !accountSelect) {
        return;
    }

    function applySupplierAccount() {
        var supplierId = supplierSelect.value;
        var accountId = supplierAccounts[supplierId];
        accountSelect.value = (accountId === undefined || accountId === null) ? '' : String(accountId);
        if (window.jQuery) {
            jQuery(accountSelect).trigger('change');
        }
    }

    if (window.jQuery) {
        jQuery(supplierSelect).on('change', applySupplierAccount);
    } else {
        supplierSelect.addEventListener('change', applySupplierAccount);
    }

    if (!isUpdate && supplierSelect.value && !accountSelect.value) {
        applySupplierAccount();
    }
});
</script>
HTML;
    }
}
