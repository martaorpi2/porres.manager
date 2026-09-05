<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\PaymentOrderInvoiceImputeRequest;
use App\Http\Requests\PaymentOrderRequest;
use App\Models\AccountingAccount;
use App\Models\OpDetail;
use App\Models\PaymentOrder;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Services\AccountingOutflowService;
use App\Services\PaymentOrderInvoiceImputationService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Class PaymentOrderCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class PaymentOrderCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation {
        edit as protected backpackEdit;
        update as protected backpackUpdate;
    }

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     * 
     * @return void
     */
    public function setup()
    {
        // Bloquear acceso para role_responsable_area
        $user = backpack_user();
        if ($user && ! ($user instanceof User && $user->isAdminSistema()) && $user->hasResponsableAreaOrInstituteAuthorityRole()) {
            abort(403, 'No tienes permiso para acceder a órdenes de pago.');
        }

        // Crear órdenes de pago: solo administradora del instituto
        if ($user instanceof User && ! $user->canActAsAdministradoraInstitucion()) {
            CRUD::denyAccess('create');
        }
        
        CRUD::setModel(\App\Models\PaymentOrder::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/payment-order');
        CRUD::setEntityNameStrings('orden de pago', 'ordenes de pago');
    }

    /**
     * Define what happens when the List operation is loaded.
     * 
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        // Habilitar tabla responsiva
        CRUD::enableResponsiveTable();
        
        // Las órdenes de pago no se eliminan; solo se pueden anular (solo la administradora).
        CRUD::removeButton('delete');
        CRUD::addClause('with', ['supplier', 'purchase_order.supplier', 'imputationAccount']);

        // Ocultar crear/editar para admin institucional / apoderado / representante legal, salvo que también sea responsable de compras (cuenta típica «compras» del seeder).
        $user = backpack_user();
        $esSoloPerfilRestringidoSinCompras = $user instanceof User
            && ! $user->isAdminSistema()
            && ! $user->hasResponsableComprasRole()
            && (
                $user->hasRole('role_admin_institucion', 'backpack')
                || $user->hasRole('role_apoderado', 'backpack')
                || $user->hasRole('role_representante_legal', 'backpack')
            );
        if ($esSoloPerfilRestringidoSinCompras) {
            CRUD::removeButton('update');
        }

        // Reemplazar botón Editar por uno que se oculta cuando está anulada
        CRUD::removeButton('update');
        CRUD::addButton('line', 'update', 'view', 'crud::buttons.payment_order_update', 'end');
        // Botón Anular: solo administradora (role_admin_institucion), solo si no está anulada
        CRUD::addButton('line', 'anular', 'view', 'crud::buttons.payment_order_anular', 'end');
        CRUD::addButton('line', 'imputar', 'view', 'crud::buttons.payment_order_impute', 'end');

        CRUD::column('payment_number')->label('Número');
        CRUD::column('date')->label('Fecha');
        CRUD::column('total_amount')->label('Monto Total');
        CRUD::addColumn([
            'name' => 'currency_code',
            'label' => 'Moneda',
            'type' => 'closure',
            'function' => function (PaymentOrder $entry) {
                $c = strtoupper(trim((string) ($entry->currency_code ?? '')));

                return e($c !== '' ? $c : 'ARS');
            },
        ]);
        CRUD::addColumn([
            'name' => 'billing_kind',
            'label' => 'Tipo',
            'type' => 'closure',
            'function' => function ($entry) {
                return $entry->billing_kind === 'anticipo'
                    ? '<span class="badge bg-info">Anticipo</span>'
                    : '<span class="badge bg-secondary">Normal</span>';
            },
            'escaped' => false,
        ]);
        CRUD::column('status')->label('Estado');
        CRUD::addColumn([
            'name' => 'supplier_id',
            'label' => 'Proveedor',
            'type' => 'closure',
            'function' => function (PaymentOrder $entry) {
                return e($entry->resolvedSupplierName());
            },
        ]);
        CRUD::addColumn([
            'name' => 'imputation_account_id',
            'label' => 'Cuenta imputación',
            'type' => 'select',
            'entity' => 'imputationAccount',
            'attribute' => 'identifying_label',
            'model' => AccountingAccount::class,
        ]);
        CRUD::addColumn([
            'name' => 'purchase_order_id',
            'label' => 'Orden de Compra',
            'type' => 'select',
            'entity' => 'purchase_order',
            'attribute' => 'number',
            'model' => 'App\Models\PurchaseOrder',
        ]);
        CRUD::addColumn([
            'name' => 'authorizing_user_id',
            'label' => 'Autoriza',
            'type' => 'select',
            'entity' => 'user',
            'attribute' => 'name',
            'model' => 'App\Models\User',
        ]);
        /*CRUD::addColumn([
            'name' => 'observations',
            'label' => 'Observaciones',
            'type' => 'text',
            'limit' => 50,
        ]);*/
        
        // Add PDF button
        CRUD::addButton('line', 'pdf', 'view', 'crud::buttons.payment_order_pdf', 'end');

        // Filtro personalizado por número usando parámetros de URL
        if (request()->has('numero')) {
            $numero = request()->get('numero');
            if ($numero) {
                CRUD::addClause('where', 'payment_number', 'like', '%' . $numero . '%');
            }
        }

        // Filtro personalizado por fecha usando parámetros de URL
        if (request()->has('fecha')) {
            $fecha = request()->get('fecha');
            if ($fecha) {
                CRUD::addClause('whereDate', 'date', $fecha);
            }
        }

        // Filtro personalizado por estado usando parámetros de URL
        if (request()->has('estado')) {
            $estado = request()->get('estado');
            if ($estado) {
                CRUD::addClause('where', 'status', $estado);
            }
        }

        // Filtro personalizado por orden de compra usando parámetros de URL
        if (request()->has('orden_compra')) {
            $ordenCompraId = request()->get('orden_compra');
            if ($ordenCompraId) {
                CRUD::addClause('where', 'purchase_order_id', $ordenCompraId);
            }
        }
        
        /**
         * Columns can be defined using the fluent syntax:
         * - CRUD::column('price')->type('number');
         */
    }

    /**
     * Al crear una orden de pago, asigna un número consecutivo dentro de una transacción.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store()
    {
        $this->crud->hasAccessOrFail('create');

        $user = backpack_user();
        if (! $user instanceof User || ! $user->canActAsAdministradoraInstitucion()) {
            \Alert::error('Solo la administradora del instituto puede crear órdenes de pago.')->flash();

            return redirect()->back()->withInput();
        }

        return DB::transaction(function () {
            $request = $this->crud->validateRequest();
            $this->crud->registerFieldEvents();

            $request->merge(['payment_number' => PaymentOrder::getNextPaymentNumber()]);

            $data = $this->crud->getStrippedSaveRequest($request);
            $paymentDetailsRaw = $request->input('payment_details', []);
            unset($data['payment_details']);
            $data = $this->fillSupplierFromPurchaseOrder($data);

            $item = $this->crud->create($data);
            $this->data['entry'] = $this->crud->entry = $item;

            $this->syncOpDetailsFromRequest(
                $item,
                is_array($paymentDetailsRaw) ? $paymentDetailsRaw : [],
                (string) ($request->input('payment_method') ?? '')
            );

            if ($item->purchase_order_id) {
                \App\Models\PurchaseOrder::find($item->purchase_order_id)?->markAsRecibidaIfHasConformeReception();
            }

            app(AccountingOutflowService::class)->syncForPaymentOrder($item);

            \Alert::success(trans('backpack::crud.insert_success'))->flash();
            $this->crud->setSaveAction();

            return $this->crud->performSaveAction($item->getKey());
        });
    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(PaymentOrderRequest::class);
        
        // OC: desde URL, desde el registro en edición, o desde el select (solo persistido en update)
        $purchaseOrderId = request()->get('purchase_order_id');
        $supplierId = request()->get('supplier_id');
        $entry = $this->crud->getCurrentEntry();
        if ($entry instanceof PaymentOrder && $entry->exists) {
            $purchaseOrderId = $purchaseOrderId ?: $entry->purchase_order_id;
            $supplierId = $supplierId ?: $entry->supplier_id;
        }
        $purchaseOrder = null;
        $defaultTotal = 0;

        if ($purchaseOrderId) {
            $purchaseOrder = \App\Models\PurchaseOrder::with(['details', 'supplierInvoices.supplier'])->find($purchaseOrderId);
        if ($purchaseOrder) {
            $defaultTotal = $purchaseOrder->total ?? 0;
            $supplierId = $supplierId ?: $purchaseOrder->supplier_id;
        }
        }
        
        $nro = PaymentOrder::getNextPaymentNumberPreview();
        CRUD::addField([
            'name'  => 'payment_number',
            'label' => 'Número',
            'type'  => 'text',
            'default' => $nro,
            'attributes' => [
                'readonly' => 'readonly',
            ],
        ]);
        CRUD::field('date')->label('Fecha');
        CRUD::addField([
            'name' => 'billing_kind',
            'label' => 'Tipo de orden de pago',
            'type' => 'select_from_array',
            'options' => [
                'normal' => 'Normal (cancela deuda al imputarse a la factura)',
                'anticipo' => 'Anticipo (pago sin factura; genera crédito hasta imputar)',
            ],
            'default' => 'normal',
            'allows_null' => false,
            'hint' => 'Anticipo: puede existir sin factura asociada; luego se imputa a factura desde Facturas proveedor o desde esta orden de pago (después de guardar).',
        ]);
        CRUD::field('total_amount')->label('Monto Total')->default($defaultTotal);
        CRUD::addField([
            'name' => 'status',
            'label' => 'Estado',
            'type' => 'enum',
        ]);
        CRUD::addField([
            'name' => 'purchase_order_id',
            'label' => 'Orden de Compra (opcional)',
            'type' => 'select',
            'entity' => 'purchase_order',
            'attribute' => 'number',
            'model' => 'App\Models\PurchaseOrder',
            'default' => $purchaseOrderId,
            'allows_null' => true,
            'hint' => 'No es obligatoria. Para un servicio o gasto sin compra (ej. internet, honorarios) deje este campo vacío y elija el proveedor.',
            'attributes' => ($purchaseOrderId && ! ($entry instanceof PaymentOrder && $entry->exists && ! $entry->purchase_order_id)) ? [] : [],
        ]);
        CRUD::addField([
            'name' => 'supplier_id',
            'label' => 'Proveedor',
            'type' => 'select',
            'entity' => 'supplier',
            'attribute' => 'company_name',
            'model' => 'App\Models\Supplier',
            'default' => $supplierId,
            'allows_null' => true,
            'hint' => 'Obligatorio si no hay orden de compra. Si elige una OC, se toma el proveedor de esa compra.',
        ]);
        
        // Mostrar información de la orden de compra si viene desde ahí
        if ($purchaseOrder) {
            CRUD::addField([
                'name' => 'purchase_order_info',
                'label' => 'Información de la Orden de Compra',
                'type' => 'custom_html',
                'value' => '<div class="alert alert-info">
                    <strong>Orden de Compra:</strong> ' . e($purchaseOrder->number) . '<br>
                    <strong>Proveedor:</strong> ' . e($purchaseOrder->supplier_display_name) . '<br>
                    <strong>Total de la Orden:</strong> $' . number_format($purchaseOrder->total ?? 0, 2) . '
                </div>',
            ]);
            CRUD::addField([
                'name' => 'oc_supplier_invoices_summary',
                'label' => 'Facturas e imputaciones (contexto OC)',
                'type' => 'custom_html',
                'value' => $this->htmlPurchaseOrderInvoicesSummary($purchaseOrder),
            ]);
        }
        if ($entry instanceof PaymentOrder && $entry->exists) {
            CRUD::addField([
                'name' => 'payment_order_imputaciones_summary',
                'label' => 'Imputaciones desde esta orden de pago',
                'type' => 'custom_html',
                'value' => $this->htmlPaymentOrderImputacionesSummary($entry),
            ]);
            $user = backpack_user();
            if ($user instanceof User && $user->canManageSupplierInvoices() && $entry->status !== 'Anulada') {
                CRUD::addField([
                    'name' => 'payment_order_imputar_action',
                    'label' => 'Imputar a factura',
                    'type' => 'custom_html',
                    'value' => $this->htmlPaymentOrderImputarAction($entry),
                ]);
            }
        }
        CRUD::addField([
            'name' => 'authorizing_user_id',
            'label' => 'Autoriza',
            'type' => 'select',
            'entity' => 'user',
            'attribute' => 'name',
            'model' => 'App\Models\User',
        ]);
        CRUD::addField([
            'name' => 'observations',
            'label' => 'Observaciones',
            'type' => 'textarea',
            'attributes' => [
                'rows' => 3,
                'placeholder' => 'Ingrese observaciones adicionales...'
            ],
        ]);
        CRUD::addField([
            'name' => 'payment_method',
            'label' => 'Forma de Pago',
            'type' => 'text',
            'attributes' => [
                'placeholder' => 'Ej: Transferencia, Cheque, Efectivo, etc.'
            ],
        ]);
        CRUD::addField([
            'name' => 'bank',
            'label' => 'Banco',
            'type' => 'text',
            'attributes' => [
                'placeholder' => 'Ej: Banco Nación, Banco Provincia, etc.'
            ],
        ]);

        $outflowService = app(AccountingOutflowService::class);
        $chartLoaded = $outflowService->chartIsLoaded();
        $defaultImputationId = $entry instanceof PaymentOrder && $entry->exists
            ? $entry->imputation_account_id
            : $outflowService->suggestedImputationAccountId($purchaseOrder, $supplierId ? (int) $supplierId : null);
        $defaultFundsId = $entry instanceof PaymentOrder && $entry->exists
            ? $entry->funds_account_id
            : null;

        CRUD::addField([
            'name' => 'accounting_outflow_hint',
            'label' => 'Impacto contable del egreso',
            'type' => 'custom_html',
            'value' => $chartLoaded
                ? '<div class="alert alert-info mb-0"><p class="mb-1">Al <strong>ejecutar</strong> la OP se genera un <strong>egreso</strong> (movimiento de fondos) y, con el plan cargado, el asiento: Banco disminuye / cuenta de imputación aumenta.</p><p class="mb-0 small">También puede registrar el egreso a mano desde Egresos, o respaldarlo con un comprobante interno.</p></div>'
                : '<div class="alert alert-warning mb-0"><p class="mb-1">Todavía no hay un plan de cuentas activo. Puede guardar la OP igual.</p><p class="mb-0 small">Cuando cargue las cuentas en <strong>Proveedores → Cuentas contables</strong>, el egreso pedirá cuenta de imputación (gasto o bien) y cuenta de fondos (caja/banco) y generará el asiento.</p></div>',
        ]);
        CRUD::addField([
            'name' => 'imputation_account_id',
            'label' => 'Cuenta de imputación (egreso)',
            'type' => 'select_from_array',
            'options' => AccountingAccount::optionsForSelect($defaultImputationId ? (int) $defaultImputationId : null),
            'allows_null' => true,
            'default' => $defaultImputationId,
            'hint' => $chartLoaded
                ? 'Obligatoria con el plan cargado. Ej.: Útiles y papelería, Servicios de comunicaciones, Equipamiento informático, Honorarios profesionales. Si hay factura de la OC con cuenta, se propone esa.'
                : 'Opcional hasta cargar el plan de cuentas. Si la factura o el proveedor ya tienen cuenta, se propone aquí.',
        ]);
        CRUD::addField([
            'name' => 'funds_account_id',
            'label' => 'Cuenta de fondos (origen)',
            'type' => 'select_from_array',
            'options' => AccountingAccount::optionsForSelect($defaultFundsId ? (int) $defaultFundsId : null),
            'allows_null' => true,
            'default' => $defaultFundsId,
            'hint' => $chartLoaded
                ? 'Caja o Banco de donde sale el dinero. Obligatoria para dejar la OP en estado Ejecutada (así se puede generar el asiento).'
                : 'Opcional hasta cargar el plan de cuentas (Caja o Banco).',
        ]);

        CRUD::addField([
            'name' => 'payment_date',
            'label' => 'Fecha de Pago',
            'type' => 'date',
        ]);

        CRUD::addField([
            'name' => 'payment_details',
            'label' => 'Detalle de pagos (opcional: varias cuotas o parcialidades)',
            'type' => 'view',
            'view' => 'vendor.backpack.crud.fields.payment_order_op_details',
            'fake' => true,
            'rows' => [],
            'hint' => 'Si agrega líneas, la suma de los montos debe coincidir con el <strong>monto total</strong> de la cabecera. Si no agrega líneas, use solo el monto total. Si queda vacío el método por línea, se usa la forma de pago de la cabecera.',
        ]);

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

        $entry = $this->crud->getCurrentEntry();
        if ($entry instanceof PaymentOrder) {
            $entry->load('opDetails');
            $default = $entry->opDetails->map(function (OpDetail $d) {
                return [
                    'concept' => $d->concept,
                    'amount' => (string) $d->amount,
                    'method_payment' => $d->method_payment,
                    'expiration_date' => $d->expiration_date ? $d->expiration_date->format('Y-m-d') : '',
                    'actual_payment_date' => $d->actual_payment_date ? $d->actual_payment_date->format('Y-m-d') : '',
                ];
            })->toArray();
            CRUD::modifyField('payment_details', ['rows' => $default]);
        }
    }

    /**
     * No se puede editar una orden de pago anulada.
     */
    public function edit($id)
    {
        $entry = PaymentOrder::findOrFail($id);
        if ($entry->status === 'Anulada') {
            abort(403, 'No se puede editar una orden de pago anulada.');
        }

        return $this->backpackEdit($id);
    }

    /**
     * No se puede actualizar una orden de pago anulada.
     */
    public function update()
    {
        $id = request()->input($this->crud->model->getKeyName());
        $entry = PaymentOrder::find($id);
        if ($entry && $entry->status === 'Anulada') {
            abort(403, 'No se puede editar una orden de pago anulada.');
        }

        return DB::transaction(function () {
            $id = request()->input($this->crud->model->getKeyName());
            $this->crud->hasAccessOrFail('update');
            $this->crud->registerFieldEvents();
            $request = $this->crud->validateRequest();

            $data = $this->crud->getStrippedSaveRequest($request);
            $paymentDetailsRaw = $request->input('payment_details', []);
            unset($data['payment_details']);
            $data = $this->fillSupplierFromPurchaseOrder($data);

            $this->crud->entry = $this->crud->update($id, $data);
            $this->data['entry'] = $this->crud->entry;

            $this->syncOpDetailsFromRequest(
                $this->crud->entry,
                is_array($paymentDetailsRaw) ? $paymentDetailsRaw : [],
                (string) ($request->input('payment_method') ?? '')
            );

            app(AccountingOutflowService::class)->syncForPaymentOrder($this->crud->entry);

            \Alert::success(trans('backpack::crud.update_success'))->flash();
            $this->crud->setSaveAction();

            return $this->crud->performSaveAction($this->crud->entry->getKey());
        });
    }

    /**
     * Persiste filas en op_details (reemplaza las existentes). Si no hay líneas, deja la tabla vacía.
     */
    protected function syncOpDetailsFromRequest(PaymentOrder $paymentOrder, array $rows, string $headerPaymentMethod): void
    {
        $paymentOrder->opDetails()->delete();

        $header = trim($headerPaymentMethod) !== '' ? trim($headerPaymentMethod) : '—';

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $amount = isset($row['amount']) ? (float) $row['amount'] : 0;
            if ($amount <= 0) {
                continue;
            }
            $concept = $row['concept'] ?? 'partiality';
            if (! in_array($concept, ['advance', 'residue', 'partiality'], true)) {
                $concept = 'partiality';
            }
            $method = trim((string) ($row['method_payment'] ?? ''));
            if ($method === '') {
                $method = $header;
            }

            $exp = $row['expiration_date'] ?? null;
            $exp = ($exp === '' || $exp === null) ? null : $exp;
            $paid = $row['actual_payment_date'] ?? null;
            $paid = ($paid === '' || $paid === null) ? null : $paid;

            OpDetail::create([
                'payment_order_id' => $paymentOrder->id,
                'concept' => $concept,
                'amount' => $amount,
                'method_payment' => $method !== '' ? $method : '—',
                'expiration_date' => $exp,
                'actual_payment_date' => $paid,
            ]);
        }
    }

    /**
     * Define what happens when the Show operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-show
     * @return void
     */
    protected function setupShowOperation()
    {
        CRUD::addClause('with', ['opDetails', 'supplierInvoices', 'imputationAccount', 'fundsAccount', 'accountingEntries.lines.account', 'internalVouchers', 'fundMovements', 'supplier', 'purchase_order.supplier']);

        // Configurar las columnas que se mostrarán en la vista de detalles
        CRUD::column('payment_number')->label('Número de Orden de Pago');
        CRUD::column('date')->label('Fecha');
        CRUD::column('total_amount')->label('Monto Total');
        CRUD::addColumn([
            'name' => 'billing_kind',
            'label' => 'Tipo',
            'type' => 'closure',
            'function' => function ($entry) {
                return $entry->billing_kind === 'anticipo'
                    ? '<span class="badge bg-info">Anticipo (crédito hasta imputar a factura)</span>'
                    : '<span class="badge bg-secondary">Normal</span>';
            },
            'escaped' => false,
        ]);
        CRUD::addColumn([
            'name' => 'imputaciones_facturas',
            'label' => 'Imputaciones a facturas',
            'type' => 'closure',
            'function' => function ($entry) {
                $rows = $entry->relationLoaded('supplierInvoices')
                    ? $entry->supplierInvoices
                    : $entry->supplierInvoices()->get();
                if ($rows->isEmpty()) {
                    $empty = '<p class="text-muted mb-0">Sin imputaciones a facturas registradas en la tabla pivote.</p>';
                    $user = backpack_user();
                    if ($user instanceof User && $user->canManageSupplierInvoices() && $entry->status !== 'Anulada') {
                        $empty .= $this->htmlPaymentOrderImputarAction($entry, true);
                    }

                    return $empty;
                }
                $html = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead><tr>'
                    . '<th>Factura</th><th>Fecha fact.</th><th>Monto imputado</th><th>Fecha imputación</th></tr></thead><tbody>';
                foreach ($rows as $inv) {
                    $html .= '<tr><td>' . e($inv->invoice_number) . '</td><td>' . e($inv->invoice_date?->format('d/m/Y') ?? '') . '</td>'
                        . '<td class="text-end">$' . number_format((float) $inv->pivot->amount_applied, 2) . '</td>'
                        . '<td>' . e($inv->pivot->imputed_at) . '</td></tr>';
                }
                $html .= '</tbody></table></div>';
                if ($entry->billing_kind === 'anticipo') {
                    $imputed = (float) $rows->sum(fn ($i) => (float) $i->pivot->amount_applied);
                    $avail = max(0, (float) $entry->total_amount - $imputed);
                    $html .= '<p class="mt-2 mb-0"><strong>Anticipo disponible:</strong> $' . number_format($avail, 2) . '</p>';
                }

                $user = backpack_user();
                if ($user instanceof User && $user->canManageSupplierInvoices() && $entry->status !== 'Anulada') {
                    $html .= $this->htmlPaymentOrderImputarAction($entry, true);
                }

                return $html;
            },
            'escaped' => false,
        ]);
        CRUD::addColumn([
            'name' => 'internal_vouchers',
            'label' => 'Comprobantes internos',
            'type' => 'closure',
            'function' => function (PaymentOrder $entry) {
                $user = backpack_user();
                if (! ($user instanceof User && $user->canManageInternalVouchers())) {
                    return '—';
                }
                $createUrl = $entry->status !== 'Anulada'
                    ? backpack_url('internal-voucher/create?payment_order_id='.$entry->id.($entry->purchase_order_id ? '&purchase_order_id='.$entry->purchase_order_id : ''))
                    : null;
                $rows = $entry->relationLoaded('internalVouchers')
                    ? $entry->internalVouchers
                    : $entry->internalVouchers()->get();

                return InternalVoucherCrudController::htmlRelatedTable($rows, $createUrl);
            },
            'escaped' => false,
        ]);
        CRUD::addColumn([
            'name' => 'fund_movements',
            'label' => 'Egresos',
            'type' => 'closure',
            'function' => function (PaymentOrder $entry) {
                $user = backpack_user();
                if (! ($user instanceof User && $user->canManageFundMovements())) {
                    return '—';
                }
                $createUrl = $entry->status !== 'Anulada'
                    ? backpack_url('fund-movement/create?payment_order_id='.$entry->id)
                    : null;
                $rows = $entry->relationLoaded('fundMovements')
                    ? $entry->fundMovements
                    : $entry->fundMovements()->get();

                return FundMovementCrudController::htmlRelatedTable($rows, $createUrl);
            },
            'escaped' => false,
        ]);
        CRUD::column('currency_code')->label('Moneda');
        CRUD::column('status')->label('Estado');
        
        CRUD::addColumn([
            'name' => 'supplier_id',
            'label' => 'Proveedor',
            'type' => 'closure',
            'function' => function (PaymentOrder $entry) {
                return e($entry->resolvedSupplierName());
            },
        ]);
        CRUD::addColumn([
            'name' => 'purchase_order_id',
            'label' => 'Orden de Compra Relacionada',
            'type' => 'select',
            'entity' => 'purchase_order',
            'attribute' => 'number',
            'model' => 'App\Models\PurchaseOrder',
        ]);
        
        CRUD::addColumn([
            'name' => 'authorizing_user_id',
            'label' => 'Usuario Autorizador',
            'type' => 'select',
            'entity' => 'user',
            'attribute' => 'name',
            'model' => 'App\Models\User',
        ]);
        
        CRUD::column('payment_method')->label('Forma de Pago');
        CRUD::column('bank')->label('Banco');
        CRUD::addColumn([
            'name' => 'payment_date',
            'label' => 'Fecha de Pago',
            'type' => 'date',
        ]);
        CRUD::addColumn([
            'name' => 'accounting_outflow',
            'label' => 'Impacto contable',
            'type' => 'closure',
            'function' => function ($entry) {
                return $this->htmlPaymentOrderAccountingOutflow($entry);
            },
            'escaped' => false,
        ]);

        // Información de anulación (si está anulada)
        CRUD::addColumn([
            'name' => 'annulment_info',
            'label' => 'Anulación',
            'type' => 'closure',
            'function' => function ($entry) {
                if ($entry->status !== 'Anulada') {
                    $user = backpack_user();
                    if ($user instanceof User && $user->canActAsAdministradoraInstitucion()) {
                        return '<a href="' . backpack_url('payment-order/' . $entry->id . '/anular') . '" class="btn btn-sm btn-warning"><i class="la la-ban"></i> Anular orden de pago</a>';
                    }
                    return '—';
                }
                $html = '<div class="alert alert-secondary mb-0">';
                $html .= '<p class="mb-1"><strong>Anulada el:</strong> ' . ($entry->annulled_at ? $entry->annulled_at->format('d/m/Y H:i') : '—') . '</p>';
                $html .= '<p class="mb-1"><strong>Motivo:</strong> ' . e($entry->annulment_reason ?? '—') . '</p>';
                if ($entry->annulledBy) {
                    $html .= '<p class="mb-0"><strong>Anulada por:</strong> ' . e($entry->annulledBy->name) . '</p>';
                }
                $html .= '</div>';
                return $html;
            },
            'escaped' => false,
        ]);

        // Mostrar información de la orden de compra relacionada
        CRUD::addColumn([
            'name' => 'purchase_order_info',
            'label' => 'Información de la Orden de Compra',
            'type' => 'closure',
            'function' => function($entry) {
                $purchaseOrder = $entry->purchase_order;
                if ($purchaseOrder) {
                    $html = '<div class="card border-primary">';
                    $html .= '<div class="card-header bg-primary text-white">';
                    $html .= '<h6 class="mb-0"><i class="la la-shopping-cart"></i> Orden de Compra Relacionada</h6>';
                    $html .= '</div>';
                    $html .= '<div class="card-body">';
                    $html .= '<div class="row">';
                    $html .= '<div class="col-md-6">';
                    $html .= '<p class="mb-1"><strong>Número:</strong> ' . e($purchaseOrder->number) . '</p>';
                    $html .= '<p class="mb-1"><strong>Proveedor:</strong> ' . e($purchaseOrder->supplier_display_name) . '</p>';
                    $html .= '</div>';
                    $html .= '<div class="col-md-6">';
                    $html .= '<p class="mb-1"><strong>Fecha:</strong> ' . $purchaseOrder->date->format('d/m/Y') . '</p>';
                    $html .= '<p class="mb-1"><strong>Estado:</strong> <span class="badge bg-info">' . e($purchaseOrder->status) . '</span></p>';
                    $html .= '</div>';
                    $html .= '</div>';
                    $html .= '<div class="row mt-2">';
                    $html .= '<div class="col-12">';
                    $html .= '<p class="mb-0"><strong>Total:</strong> <span class="h5 text-success">$' . number_format($purchaseOrder->total, 2) . '</span></p>';
                    $html .= '</div>';
                    $html .= '</div>';
                    $html .= '</div></div>';
                    return $html;
                }
                return '<div class="alert alert-warning">No hay orden de compra relacionada</div>';
            },
            'escaped' => false
        ]);

        // Mostrar detalles de pagos
        CRUD::addColumn([
            'name' => 'payment_details',
            'label' => 'Detalles de Pagos',
            'type' => 'closure',
            'function' => function($entry) {
                $details = $entry->relationLoaded('opDetails')
                    ? $entry->opDetails
                    : $entry->opDetails()->get();

                if ($details->isEmpty()) {
                    return '<div class="alert alert-info">No hay detalles de pago</div>';
                }
                
                $html = '<div class="card border-success">';
                $html .= '<div class="card-header bg-success text-white">';
                $html .= '<h6 class="mb-0"><i class="la la-credit-card"></i> Detalles de Pagos</h6>';
                $html .= '</div>';
                $html .= '<div class="card-body p-0">';
                $html .= '<div class="table-responsive">';
                $html .= '<table class="table table-sm table-bordered mb-0">';
                $html .= '<thead class="table-light">';
                $html .= '<tr>';
                $html .= '<th style="width: 20%;">Concepto</th>';
                $html .= '<th style="width: 15%;">Monto</th>';
                $html .= '<th style="width: 25%;">Método de Pago</th>';
                $html .= '<th style="width: 20%;">Vencimiento</th>';
                $html .= '<th style="width: 20%;">Estado</th>';
                $html .= '</tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                
                foreach ($details as $detail) {
                    $html .= '<tr>';
                    $html .= '<td><span class="badge bg-secondary">' . e(OpDetail::conceptLabel($detail->concept)) . '</span></td>';
                    $html .= '<td class="text-end"><strong>$' . number_format((float) $detail->amount, 2) . '</strong></td>';
                    $html .= '<td>' . e($detail->method_payment) . '</td>';
                    $exp = $detail->expiration_date;
                    $html .= '<td>' . ($exp ? $exp->format('d/m/Y') : '<span class="text-muted">N/A</span>') . '</td>';
                    
                    $paid = $detail->actual_payment_date;
                    if ($paid) {
                        $html .= '<td><span class="badge bg-success"><i class="la la-check"></i> Pagado</span><br><small class="text-muted">' . $paid->format('d/m/Y') . '</small></td>';
                    } else {
                        $html .= '<td><span class="badge bg-warning"><i class="la la-clock"></i> Pendiente</span></td>';
                    }
                    
                    $html .= '</tr>';
                }
                
                $html .= '</tbody>';
                $html .= '</table>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                
                return $html;
            },
            'escaped' => false
        ]);
    }

    /**
     * Resumen HTML: facturas de proveedor cargadas contra la OC (montos y saldo).
     */
    protected function htmlPurchaseOrderInvoicesSummary(\App\Models\PurchaseOrder $purchaseOrder): string
    {
        $invoices = $purchaseOrder->relationLoaded('supplierInvoices')
            ? $purchaseOrder->supplierInvoices
            : $purchaseOrder->supplierInvoices()->with('supplier')->orderByDesc('invoice_date')->get();

        if ($invoices->isEmpty()) {
            return '<div class="alert alert-warning mb-0"><p class="mb-1"><strong>Facturas en esta OC:</strong> todavía no hay facturas de proveedor vinculadas a esta orden de compra.</p>'
                . '<p class="mb-0 small text-muted">Registrelas en <strong>Facturas proveedor</strong> (con esta OC asociada) para poder imputar pagos.</p></div>';
        }

        $html = '<div class="card border-secondary mb-0"><div class="card-header py-2"><strong><i class="la la-file-invoice-dollar"></i> Facturas vinculadas a esta OC</strong></div>';
        $html .= '<div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-bordered mb-0">';
        $html .= '<thead class="table-light"><tr><th>Nº factura</th><th>Fecha</th><th>Proveedor</th><th class="text-end">Monto</th><th class="text-end">Saldo pend.</th></tr></thead><tbody>';
        foreach ($invoices as $inv) {
            $html .= '<tr><td>' . e($inv->invoice_number) . '</td><td>' . e($inv->invoice_date?->format('d/m/Y') ?? '—') . '</td>'
                . '<td>' . e($inv->supplier?->company_name ?? '—') . '</td>'
                . '<td class="text-end">$' . number_format((float) $inv->total_amount, 2) . '</td>'
                . '<td class="text-end">$' . number_format($inv->openBalance(), 2) . '</td></tr>';
        }
        $html .= '</tbody></table></div>';
        $html .= '<div class="p-2 small text-muted mb-0">Para aplicar esta u otras OP a una factura use <strong>Facturas proveedor</strong> → Ver → <em>Imputar orden de pago</em>, o <strong>Imputar a factura</strong> en esta orden de pago (después de guardar).</div></div></div>';

        return $html;
    }

    /**
     * Resumen HTML: montos de esta OP ya imputados a cada factura (tabla pivote).
     */
    protected function htmlPaymentOrderImputacionesSummary(PaymentOrder $paymentOrder): string
    {
        $paymentOrder->loadMissing('supplierInvoices');
        $rows = $paymentOrder->supplierInvoices;
        if ($rows->isEmpty()) {
            return '<div class="alert alert-light border mb-0"><strong>Imputaciones desde esta OP:</strong> todavía no hay montos imputados a facturas desde esta orden de pago.</div>';
        }

        $html = '<div class="card border-info mb-0"><div class="card-header py-2 bg-light"><strong><i class="la la-link"></i> Imputaciones registradas (esta OP → facturas)</strong></div>';
        $html .= '<div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-bordered mb-0">';
        $html .= '<thead class="table-light"><tr><th>Factura</th><th>Fecha fact.</th><th class="text-end">Monto imputado</th><th>Fecha imputación</th></tr></thead><tbody>';
        foreach ($rows as $inv) {
            $html .= '<tr><td>' . e($inv->invoice_number) . '</td><td>' . e($inv->invoice_date?->format('d/m/Y') ?? '—') . '</td>'
                . '<td class="text-end">$' . number_format((float) $inv->pivot->amount_applied, 2) . '</td>'
                . '<td>' . e($inv->pivot->imputed_at ?? '—') . '</td></tr>';
        }
        $html .= '</tbody></table></div></div></div>';

        return $html;
    }

    /**
     * Cuentas propuestas en la OP. El asiento se registra en el egreso confirmado.
     */
    protected function htmlPaymentOrderAccountingOutflow(PaymentOrder $paymentOrder): string
    {
        $service = app(AccountingOutflowService::class);
        $preview = $service->previewLines($paymentOrder);
        $html = '<p class="small mb-2">Cuentas de la OP (se copian al egreso al ejecutarla). El asiento de Caja/Banco se genera al <strong>confirmar el egreso</strong>.</p>';
        $html .= '<div class="table-responsive mb-2"><table class="table table-sm table-bordered mb-0"><thead><tr>'
            . '<th>Concepto</th><th>Cuenta</th><th>Efecto</th><th class="text-end">Debe</th><th class="text-end">Haber</th></tr></thead><tbody>';
        foreach ($preview as $line) {
            $html .= '<tr><td>'.e(ucfirst($line['role'])).'</td><td>'.e($line['account']).'</td><td>'.e($line['effect']).'</td>'
                . '<td class="text-end">$'.number_format((float) $line['debit'], 2).'</td>'
                . '<td class="text-end">$'.number_format((float) $line['credit'], 2).'</td></tr>';
        }
        $html .= '</tbody></table></div>';

        $legacy = $paymentOrder->relationLoaded('accountingEntries')
            ? $paymentOrder->accountingEntries
            : $paymentOrder->accountingEntries()->with('lines.account')->get();
        if ($legacy->isNotEmpty()) {
            $html .= '<p class="small mb-1"><strong>Asientos anteriores (directos de la OP)</strong></p>';
            foreach ($legacy as $entry) {
                $status = $entry->status === 'posted' ? 'Registrado' : 'Revertido';
                $html .= '<p class="mb-1 small">'.e($entry->entry_number).' — '.e($entry->kind).' — '.$status
                    .' — '.e($entry->date?->format('d/m/Y') ?? '').' — '.e($entry->description).'</p>';
            }
        } elseif (! $service->chartIsLoaded()) {
            $html .= '<p class="text-muted small mb-0">Sin asiento: el plan de cuentas todavía no está cargado.</p>';
        } elseif ($paymentOrder->status !== 'Ejecutada') {
            $html .= '<p class="text-muted small mb-0">Al pasar la OP a <strong>Ejecutada</strong> se crea o actualiza el egreso. Confírmelo (o déjelo confirmarse si hay ambas cuentas) para generar el asiento.</p>';
        } elseif (! $paymentOrder->imputation_account_id || ! $paymentOrder->funds_account_id) {
            $html .= '<p class="text-muted small mb-0">Falta la cuenta de imputación o la de fondos: el egreso queda pendiente hasta completarlas.</p>';
        }

        return $html;
    }

    /**
     * Botón o aviso para imputar esta OP a una factura (misma OC, saldo, misma moneda).
     */
    protected function htmlPaymentOrderImputarAction(PaymentOrder $paymentOrder, bool $compact = false): string
    {
        if ($paymentOrder->status === 'Anulada') {
            return '';
        }

        $wrapStart = $compact ? '<div class="mt-2">' : '';
        $wrapEnd = $compact ? '</div>' : '';

        $service = app(PaymentOrderInvoiceImputationService::class);
        $remaining = $service->remainingImputableCapacityOnPaymentOrder($paymentOrder);
        if ($remaining < 0.01) {
            return $wrapStart . '<p class="text-muted mb-0">Esta orden de pago no tiene saldo imputable.</p>' . $wrapEnd;
        }

        $candidates = $service->candidateInvoicesForPaymentOrder($paymentOrder);
        if ($candidates->isEmpty()) {
            return $wrapStart . '<div class="alert alert-warning mb-0">No hay facturas con saldo pendiente (mismo proveedor y moneda). Cárguelas en <strong>Facturas proveedor</strong>; no es necesario que tengan orden de compra.</div>' . $wrapEnd;
        }

        $btn = '<a href="' . backpack_url('payment-order/' . $paymentOrder->id . '/imputar') . '" class="btn btn-primary' . ($compact ? ' btn-sm' : '') . '"><i class="la la-link"></i> Imputar a factura</a>'
            . ' <span class="text-muted small">Saldo imputable: $' . number_format($remaining, 2) . '</span>';

        return $wrapStart . $btn . $wrapEnd;
    }

    /**
     * Muestra el formulario para imputar esta OP a una factura (total o parcial).
     */
    public function showImputeForm(int $id)
    {
        $user = backpack_user();
        if (! $user instanceof User || ! $user->canManageSupplierInvoices()) {
            abort(403, 'Solo la administradora del instituto, el sector de compras o el administrador del sistema pueden registrar imputaciones.');
        }

        $paymentOrder = PaymentOrder::with('purchase_order')->findOrFail($id);
        if ($paymentOrder->status === 'Anulada') {
            \Alert::warning('No se puede imputar una orden de pago anulada.')->flash();

            return redirect()->to(backpack_url('payment-order/' . $paymentOrder->id . '/show'));
        }

        $service = app(PaymentOrderInvoiceImputationService::class);
        $remaining = $service->remainingImputableCapacityOnPaymentOrder($paymentOrder);
        if ($remaining < 0.01) {
            \Alert::warning('Esta orden de pago no tiene saldo imputable.')->flash();

            return redirect()->to(backpack_url('payment-order/' . $paymentOrder->id . '/show'));
        }

        $candidates = $service->candidateInvoicesForPaymentOrder($paymentOrder);
        if ($candidates->isEmpty()) {
            \Alert::error('No hay facturas con saldo pendiente del mismo proveedor y moneda. Puede cargar la factura sin orden de compra.')->flash();

            return redirect()->to(backpack_url('payment-order/' . $paymentOrder->id . '/show'));
        }

        $options = [];
        foreach ($candidates as $invoice) {
            $saldo = $invoice->openBalance();
            $label = $invoice->invoice_number
                . ($invoice->invoice_date ? ' — ' . $invoice->invoice_date->format('d/m/Y') : '')
                . ' — saldo $' . number_format($saldo, 2);
            $options[$invoice->id] = [
                'label' => $label,
                'saldo' => $saldo,
            ];
        }

        $this->data['paymentOrder'] = $paymentOrder;
        $this->data['remaining'] = $remaining;
        $this->data['invoiceOptions'] = $options;
        $this->data['title'] = 'Imputar a factura — ' . $paymentOrder->payment_number;
        $this->data['breadcrumbs'] = [
            trans('backpack::crud.admin') => backpack_url('dashboard'),
            'Ordenes de pago' => backpack_url('payment-order'),
            $paymentOrder->payment_number => backpack_url('payment-order/' . $paymentOrder->id . '/show'),
            'Imputar' => false,
        ];

        return view('vendor.backpack.crud.payment_order_impute', $this->data);
    }

    public function storeImputation(int $id, PaymentOrderInvoiceImputeRequest $request)
    {
        $user = backpack_user();
        if (! $user instanceof User || ! $user->canManageSupplierInvoices()) {
            abort(403, 'Solo la administradora del instituto, el sector de compras o el administrador del sistema pueden registrar imputaciones.');
        }

        $paymentOrder = PaymentOrder::findOrFail($id);
        $validated = $request->validated();
        $invoice = SupplierInvoice::findOrFail((int) $validated['supplier_invoice_id']);

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

        return redirect()->to(backpack_url('payment-order/' . $paymentOrder->id . '/show'));
    }

    /**
     * Muestra el formulario para anular una orden de pago (solo administradora).
     */
    public function showAnularForm($id)
    {
        $paymentOrder = PaymentOrder::with('purchase_order')->findOrFail($id);
        $user = backpack_user();

        if (! $user instanceof User || ! $user->canActAsAdministradoraInstitucion()) {
            abort(403, 'Solo la administradora puede anular órdenes de pago.');
        }

        if ($paymentOrder->status === 'Anulada') {
            \Alert::warning('Esta orden de pago ya está anulada.')->flash();
            return redirect()->to(backpack_url('payment-order/' . $paymentOrder->id . '/show'));
        }

        $this->data['paymentOrder'] = $paymentOrder;
        $this->data['title'] = 'Anular orden de pago ' . $paymentOrder->payment_number;
        $this->data['crud'] = $this->crud;

        return view('vendor.backpack.crud.payment_order_anular_form', $this->data);
    }

    /**
     * Procesa la anulación de una orden de pago (solo administradora, motivo obligatorio).
     */
    public function anular($id)
    {
        $paymentOrder = PaymentOrder::findOrFail($id);
        $user = backpack_user();

        if (! $user instanceof User || ! $user->canActAsAdministradoraInstitucion()) {
            abort(403, 'Solo la administradora puede anular órdenes de pago.');
        }

        if ($paymentOrder->status === 'Anulada') {
            \Alert::warning('Esta orden de pago ya está anulada.')->flash();
            return redirect()->to(backpack_url('payment-order/' . $paymentOrder->id . '/show'));
        }

        $validated = request()->validate([
            'annulment_reason' => 'required|string|min:10|max:2000',
        ], [
            'annulment_reason.required' => 'El motivo de la anulación es obligatorio.',
            'annulment_reason.min' => 'El motivo debe tener al menos 10 caracteres.',
            'annulment_reason.max' => 'El motivo no puede superar 2000 caracteres.',
        ]);

        $paymentOrder->update([
            'status' => 'Anulada',
            'annulled_at' => now(),
            'annulment_reason' => $validated['annulment_reason'],
            'annulled_by_id' => $user->id,
        ]);

        app(AccountingOutflowService::class)->syncForPaymentOrder($paymentOrder);

        \Alert::success('La orden de pago ha sido anulada correctamente.')->flash();
        return redirect()->to(backpack_url('payment-order/' . $paymentOrder->id . '/show'));
    }

    /**
     * Generate PDF for a payment order
     */
    public function generatePdf($id)
    {
        $paymentOrder = PaymentOrder::with([
            'opDetails',
            'purchase_order.supplier',
            'purchase_order.details.supplier',
            'supplierInvoices',
            'imputationAccount',
            'fundsAccount',
        ])->findOrFail($id);

        $pdf = Pdf::loadView('payment-order-pdf', compact('paymentOrder'));

        return $pdf->stream('orden-pago-' . $paymentOrder->payment_number . '.pdf');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillSupplierFromPurchaseOrder(array $data): array
    {
        if (empty($data['supplier_id']) && ! empty($data['purchase_order_id'])) {
            $data['supplier_id'] = \App\Models\PurchaseOrder::query()
                ->whereKey($data['purchase_order_id'])
                ->value('supplier_id');
        }

        return $data;
    }
}
