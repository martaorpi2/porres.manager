<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\PaymentOrderRequest;
use App\Models\OpDetail;
use App\Models\PaymentOrder;
use App\Models\User;
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
        if ($user && $user->hasRole('role_responsable_area')) {
            abort(403, 'No tienes permiso para acceder a órdenes de pago.');
        }

        // Crear órdenes de pago: solo administradora del instituto
        if ($user instanceof User && ! $user->hasAdministradoraInstitucionRole()) {
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

        // Ocultar crear/editar para admin institucional / apoderado / representante legal, salvo que también sea responsable de compras (cuenta típica «compras» del seeder).
        $user = backpack_user();
        $esSoloPerfilRestringidoSinCompras = $user instanceof User
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

        CRUD::column('payment_number')->label('Número');
        CRUD::column('date')->label('Fecha');
        CRUD::column('total_amount')->label('Monto Total');
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
        CRUD::column('currency_code')->label('Mon.');
        CRUD::column('status')->label('Estado');
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
        if (! $user instanceof User || ! $user->hasAdministradoraInstitucionRole()) {
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
        
        // Obtener purchase_order_id de la URL si existe
        $purchaseOrderId = request()->get('purchase_order_id');
        $purchaseOrder = null;
        $defaultTotal = 0;
        
        if ($purchaseOrderId) {
            $purchaseOrder = \App\Models\PurchaseOrder::with('details')->find($purchaseOrderId);
            if ($purchaseOrder) {
                $defaultTotal = $purchaseOrder->total ?? 0;
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
            'hint' => 'Anticipo: puede existir sin factura asociada; luego se vincula en Facturas proveedor → Imputar.',
        ]);
        CRUD::addField([
            'name' => 'currency_code',
            'label' => 'Moneda (ISO 4217)',
            'type' => 'text',
            'attributes' => ['maxlength' => 3, 'placeholder' => 'ARS', 'style' => 'text-transform:uppercase'],
            'hint' => 'Opcional; si queda vacío se asume ARS al comparar con facturas.',
        ]);
        CRUD::field('total_amount')->label('Monto Total')->default($defaultTotal);
        CRUD::addField([
            'name' => 'status',
            'label' => 'Estado',
            'type' => 'enum',
        ]);
        CRUD::addField([
            'name' => 'purchase_order_id',
            'label' => 'Orden de Compra',
            'type' => 'select',
            'entity' => 'purchase_order',
            'attribute' => 'number',
            'model' => 'App\Models\PurchaseOrder',
            'default' => $purchaseOrderId,
            'attributes' => $purchaseOrderId ? ['readonly' => 'readonly'] : [],
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
        CRUD::addField([
            'name' => 'payment_date',
            'label' => 'Fecha de Pago',
            'type' => 'date',
        ]);

        CRUD::addField([
            'name' => 'payment_details',
            'label' => 'Detalle de pagos (opcional: varias cuotas o parcialidades)',
            'type' => 'repeatable',
            'subfields' => [
                [
                    'name' => 'concept',
                    'label' => 'Concepto',
                    'type' => 'select_from_array',
                    'options' => [
                        'advance' => 'Anticipo',
                        'partiality' => 'Parcialidad',
                        'residue' => 'Saldo',
                    ],
                    'allows_null' => false,
                    'wrapper' => ['class' => 'form-group col-md-3'],
                ],
                [
                    'name' => 'amount',
                    'label' => 'Monto',
                    'type' => 'number',
                    'attributes' => ['step' => '0.01', 'min' => '0'],
                    'wrapper' => ['class' => 'form-group col-md-2'],
                ],
                [
                    'name' => 'method_payment',
                    'label' => 'Método (línea)',
                    'type' => 'text',
                    'hint' => 'Si queda vacío, se usa la forma de pago de la cabecera.',
                    'wrapper' => ['class' => 'form-group col-md-3'],
                ],
                [
                    'name' => 'expiration_date',
                    'label' => 'Vencimiento',
                    'type' => 'date',
                    'wrapper' => ['class' => 'form-group col-md-2'],
                ],
                [
                    'name' => 'actual_payment_date',
                    'label' => 'Pagado el',
                    'type' => 'date',
                    'wrapper' => ['class' => 'form-group col-md-2'],
                ],
            ],
            'init_rows' => 0,
            'min_rows' => 0,
            'new_item_label' => 'Agregar línea de pago',
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
            CRUD::modifyField('payment_details', ['default' => $default]);
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

            $this->crud->entry = $this->crud->update($id, $data);
            $this->data['entry'] = $this->crud->entry;

            $this->syncOpDetailsFromRequest(
                $this->crud->entry,
                is_array($paymentDetailsRaw) ? $paymentDetailsRaw : [],
                (string) ($request->input('payment_method') ?? '')
            );

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
        CRUD::addClause('with', ['opDetails', 'supplierInvoices']);

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
        CRUD::column('currency_code')->label('Moneda');
        CRUD::column('status')->label('Estado');
        
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
            'name' => 'imputaciones_facturas',
            'label' => 'Imputaciones a facturas',
            'type' => 'closure',
            'function' => function ($entry) {
                $rows = $entry->relationLoaded('supplierInvoices')
                    ? $entry->supplierInvoices
                    : $entry->supplierInvoices()->get();
                if ($rows->isEmpty()) {
                    return '<p class="text-muted mb-0">Sin imputaciones a facturas registradas en la tabla pivote.</p>';
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

                return $html;
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
                    if ($user instanceof User && $user->hasAdministradoraInstitucionRole()) {
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
     * Muestra el formulario para anular una orden de pago (solo administradora).
     */
    public function showAnularForm($id)
    {
        $paymentOrder = PaymentOrder::with('purchase_order')->findOrFail($id);
        $user = backpack_user();

        if (! $user instanceof User || ! $user->hasAdministradoraInstitucionRole()) {
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

        if (! $user instanceof User || ! $user->hasAdministradoraInstitucionRole()) {
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
        ])->findOrFail($id);

        $pdf = Pdf::loadView('payment-order-pdf', compact('paymentOrder'));

        return $pdf->stream('orden-pago-' . $paymentOrder->payment_number . '.pdf');
    }
}
