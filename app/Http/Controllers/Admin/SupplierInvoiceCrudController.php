<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\SupplierInvoiceImputeRequest;
use App\Http\Requests\SupplierInvoiceRequest;
use App\Models\PaymentOrder;
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
        if ($user && $user->hasRole('role_responsable_area')) {
            abort(403, 'No tienes permiso para acceder a facturas de proveedor.');
        }

        CRUD::setModel(SupplierInvoice::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/supplier-invoice');
        CRUD::setEntityNameStrings('factura de proveedor', 'facturas de proveedor');

        if (! $user instanceof User || ! $user->hasAdministradoraInstitucionRole()) {
            CRUD::denyAccess(['create', 'update', 'delete']);
        }
    }

    protected function setupListOperation(): void
    {
        CRUD::enableResponsiveTable();
        CRUD::addClause('with', ['purchaseOrder', 'supplier']);

        CRUD::addColumn([
            'name' => 'purchase_order_id',
            'label' => 'Orden de compra',
            'type' => 'select',
            'entity' => 'purchaseOrder',
            'attribute' => 'number',
            'model' => \App\Models\PurchaseOrder::class,
        ]);
        CRUD::addColumn([
            'name' => 'supplier_id',
            'label' => 'Proveedor',
            'type' => 'select',
            'entity' => 'supplier',
            'attribute' => 'company_name',
            'model' => \App\Models\Supplier::class,
        ]);
        CRUD::column('invoice_number')->label('Nº factura');
        CRUD::column('invoice_date')->label('Fecha')->type('date');
        CRUD::column('total_amount')->label('Total');
        CRUD::column('currency_code')->label('Moneda');
        CRUD::addColumn([
            'name' => 'open_balance',
            'label' => 'Saldo factura',
            'type' => 'closure',
            'function' => function (SupplierInvoice $entry) {
                return '$' . number_format($entry->openBalance(), 2);
            },
        ]);
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(SupplierInvoiceRequest::class);

        $purchaseOrderId = request()->get('purchase_order_id');
        CRUD::addField([
            'name' => 'purchase_order_id',
            'label' => 'Orden de compra',
            'type' => 'select',
            'entity' => 'purchaseOrder',
            'attribute' => 'number',
            'model' => \App\Models\PurchaseOrder::class,
            'default' => $purchaseOrderId,
            'attributes' => $purchaseOrderId ? ['readonly' => 'readonly'] : [],
        ]);
        CRUD::addField([
            'name' => 'supplier_id',
            'label' => 'Proveedor (debe coincidir con la OC)',
            'type' => 'select',
            'entity' => 'supplier',
            'attribute' => 'company_name',
            'model' => \App\Models\Supplier::class,
        ]);
        CRUD::field('invoice_number')->label('Número de factura');
        CRUD::field('invoice_date')->label('Fecha de factura')->type('date');
        CRUD::field('total_amount')->label('Importe total');
        CRUD::addField([
            'name' => 'currency_code',
            'label' => 'Moneda (ISO 4217)',
            'type' => 'text',
            'default' => 'ARS',
            'attributes' => ['maxlength' => 3, 'style' => 'text-transform:uppercase'],
            'hint' => 'Debe coincidir con la moneda indicada en la orden de pago al imputar.',
        ]);
        CRUD::field('observations')->label('Observaciones')->type('textarea');
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }

    protected function setupShowOperation(): void
    {
        CRUD::addClause('with', ['purchaseOrder', 'supplier', 'paymentOrders']);

        CRUD::column('invoice_number')->label('Número de factura');
        CRUD::column('invoice_date')->label('Fecha')->type('date');
        CRUD::column('total_amount')->label('Total');
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
            'type' => 'select',
            'entity' => 'purchaseOrder',
            'attribute' => 'number',
            'model' => \App\Models\PurchaseOrder::class,
        ]);
        CRUD::addColumn([
            'name' => 'supplier_id',
            'label' => 'Proveedor',
            'type' => 'select',
            'entity' => 'supplier',
            'attribute' => 'company_name',
            'model' => \App\Models\Supplier::class,
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
        if ($user instanceof User && $user->hasAdministradoraInstitucionRole()) {
            CRUD::addColumn([
                'name' => 'imputar_action',
                'label' => '',
                'type' => 'closure',
                'function' => function (SupplierInvoice $entry) {
                    if ($entry->openBalance() < 0.01) {
                        return '<p class="text-muted mb-0">Factura sin saldo pendiente.</p>';
                    }
                    $service = app(PaymentOrderInvoiceImputationService::class);
                    $candidates = PaymentOrder::query()
                        ->where('purchase_order_id', $entry->purchase_order_id)
                        ->where('status', '!=', 'Anulada')
                        ->orderBy('id')
                        ->get()
                        ->filter(function (PaymentOrder $po) use ($service, $entry) {
                            if (! $service->currenciesMatch($po->currency_code, $entry->currency_code)) {
                                return false;
                            }

                            return $service->remainingImputableCapacityOnPaymentOrder($po) >= 0.01;
                        });
                    if ($candidates->isEmpty()) {
                        return '<div class="alert alert-warning mb-0">No hay órdenes de pago con saldo imputable (misma moneda) para esta OC.</div>';
                    }

                    return '<a href="' . backpack_url('supplier-invoice/' . $entry->id . '/imputar') . '" class="btn btn-primary"><i class="la la-link"></i> Imputar orden de pago a esta factura</a>';
                },
                'escaped' => false,
            ]);
        }
    }

    public function showImputeForm(int $id)
    {
        $user = backpack_user();
        if (! $user instanceof User || ! $user->hasAdministradoraInstitucionRole()) {
            abort(403, 'Solo la administradora del instituto puede registrar imputaciones.');
        }

        $invoice = SupplierInvoice::with(['purchaseOrder', 'supplier'])->findOrFail($id);
        if ($invoice->openBalance() < 0.01) {
            \Alert::warning('Esta factura no tiene saldo pendiente.')->flash();

            return redirect()->to(backpack_url('supplier-invoice/' . $invoice->id . '/show'));
        }

        $service = app(PaymentOrderInvoiceImputationService::class);
        $candidates = PaymentOrder::query()
            ->where('purchase_order_id', $invoice->purchase_order_id)
            ->where('status', '!=', 'Anulada')
            ->orderByDesc('id')
            ->get()
            ->filter(function (PaymentOrder $po) use ($service, $invoice) {
                if (! $service->currenciesMatch($po->currency_code, $invoice->currency_code)) {
                    return false;
                }

                return $service->remainingImputableCapacityOnPaymentOrder($po) >= 0.01;
            })
            ->values();

        if ($candidates->isEmpty()) {
            \Alert::error('No hay órdenes de pago con saldo imputable en la misma moneda para esta orden de compra.')->flash();

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
        if (! $user instanceof User || ! $user->hasAdministradoraInstitucionRole()) {
            abort(403, 'Solo la administradora del instituto puede registrar imputaciones.');
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
}
