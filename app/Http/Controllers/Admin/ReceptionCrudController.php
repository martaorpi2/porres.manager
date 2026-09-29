<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ReceptionRequest;
use App\Services\PurchaseOrderStockService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use App\Models\Reception;
use App\Models\StockLevel;
use App\Models\Product;
use App\Models\Location;
use App\Models\Input;
use App\Models\GeneralRequestDetail;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Class ReceptionCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ReceptionCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Reception::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/reception');
        CRUD::setEntityNameStrings('recepción', 'recepciones');
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
        
        // Cargar relaciones para evitar N+1 queries
        CRUD::addClause('with', ['purchase_order', 'user']);
        
        // Ocultar botones de editar y eliminar para role_admin_institucion
        $user = backpack_user();
        if ($user && $user->hasRole('role_admin_institucion', 'backpack')) {
            CRUD::removeButton('update');
            CRUD::removeButton('delete');
            CRUD::addButton('line', 'edit_reception', 'view', 'crud::buttons.edit_reception', 'beginning');
        }
        
        // El responsable de área ve sus recepciones. Compras y administración ven todas, para corroborar ARCA.
        $seesAllReceptions = $user && (
            $user->hasResponsableComprasRole()
            || $user->isAdminSistema()
            || $user->hasAdministradoraInstitucionRole()
        );
        if ($user && $user->hasResponsableAreaOrInstituteAuthorityRole() && ! $seesAllReceptions) {
            CRUD::addClause('where', 'area_manager_id', $user->id);
        }
        
        // Botones de línea: ocultar edición/eliminación si recepción conforme (misma regla que isAccordingComplete)
        if ($user && $user->hasRole('role_responsable_compras', 'backpack')) {
            CRUD::removeButton('update');
            CRUD::addButton('line', 'edit_reception', 'view', 'crud::buttons.edit_reception', 'beginning');
            CRUD::removeButton('delete');
            CRUD::addButton('line', 'delete_reception', 'view', 'crud::buttons.delete_reception', 'end');
        } elseif (! $user || ! $user->hasRole('role_admin_institucion', 'backpack')) {
            CRUD::removeButton('update');
            CRUD::addButton('line', 'edit_reception', 'view', 'crud::buttons.edit_reception', 'beginning');
            CRUD::removeButton('delete');
            CRUD::addButton('line', 'delete_reception', 'view', 'crud::buttons.delete_reception', 'end');
        }

        // Columnas básicas para evitar errores
        CRUD::addColumn([
            'name' => 'purchase_order_id',
            'label' => 'Orden de Compra',
            'type' => 'select',
            'entity' => 'purchase_order',
            'attribute' => 'number',
            'model' => 'App\Models\PurchaseOrder',
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('purchase_order', function ($q) use ($searchTerm) {
                    $q->where('number', 'like', '%'.$searchTerm.'%');
                });
            },
        ]);
        CRUD::column('date')->label('Fecha');
        CRUD::column('according')->label('Conforme');
        CRUD::addColumn([
            'name' => 'corroborado_arca',
            'label' => 'ARCA',
            'type' => 'closure',
            'function' => function ($entry) {
                return $entry->corroborado_por_arca_at ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>';
            },
            'escaped' => false,
        ]);
        CRUD::addColumn([
            'name' => 'comprobante_valido',
            'label' => 'Comp. válido',
            'type' => 'closure',
            'function' => function ($entry) {
                return $entry->comprobante_valido_at ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>';
            },
            'escaped' => false,
        ]);
        CRUD::addColumn([
            'name' => 'area_manager_id',
            'label' => 'Responsable',
            'type' => 'select',
            'entity' => 'user',
            'attribute' => 'name',
            'model' => 'App\Models\User',
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('user', function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%'.$searchTerm.'%');
                });
            },
        ]);
        
        // Agregar botón PDF en la lista
        CRUD::addColumn([
            'name' => 'deliver_button',
            'label' => 'Entrega',
            'type' => 'closure',
            'function' => function ($entry) {
                return $this->receptionDeliveryActionHtml($entry, true);
            },
            'escaped' => false,
        ]);

        CRUD::addColumn([
            'name' => 'pdf_button',
            'label' => 'PDF',
            'type' => 'custom_html',
            'value' => function($entry) {
                return '<a href="' . route('reception.pdf', $entry->id) . '" class="btn btn-sm" target="_blank" data-toggle="tooltip" title="Descargar Comprobante de Recepción" style="background-color: #800020; border-color: #800020; color: white !important;">
                    <i class="la la-file-pdf" style="color: white !important;"></i> <span style="color: white !important;">PDF</span>
                </a>';
            },
            'escaped' => false,
        ]);

        // Filtro personalizado por orden de compra usando parámetros de URL
        if (request()->has('orden_compra')) {
            $ordenCompraId = request()->get('orden_compra');
            if ($ordenCompraId) {
                CRUD::addClause('where', 'purchase_order_id', $ordenCompraId);
            }
        }

        // Filtro personalizado por fecha usando parámetros de URL
        if (request()->has('fecha')) {
            $fecha = request()->get('fecha');
            if ($fecha) {
                CRUD::addClause('whereDate', 'date', $fecha);
            }
        }

        // Filtro personalizado por conformidad usando parámetros de URL
        if (request()->has('conformidad')) {
            $conformidad = request()->get('conformidad');
            if ($conformidad) {
                CRUD::addClause('where', 'according', $conformidad);
            }
        }

        // Filtro personalizado por responsable usando parámetros de URL
        if (request()->has('responsable')) {
            $responsableId = request()->get('responsable');
            if ($responsableId) {
                CRUD::addClause('where', 'area_manager_id', $responsableId);
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
        CRUD::setValidation(ReceptionRequest::class);
        CRUD::addField([
            'name' => 'purchase_order_id',
            'label' => 'Orden de Compra',
            'type' => 'select',
            'entity' => 'purchase_order',
            'attribute' => 'number',
            'model' => 'App\Models\PurchaseOrder',
            'options' => function ($query) {
                // Filtrar solo las órdenes de compra que no tienen recepción
                return $query->whereDoesntHave('receptions')->get();
            },
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('purchase_order', function ($q) use ($searchTerm) {
                    $q->where('number', 'like', '%'.$searchTerm.'%');
                });
            },
        ]);
        $camposRecepcionBloqueadosContabilidad = $this->userHasContabilidadRole(backpack_user());
        $attrsDisabledContabilidad = $camposRecepcionBloqueadosContabilidad
            ? ['attributes' => ['disabled' => 'disabled']]
            : [];

        if ($camposRecepcionBloqueadosContabilidad && $this->crud->getCurrentOperation() === 'update') {
            CRUD::modifyField('purchase_order_id', $attrsDisabledContabilidad);
        }

        CRUD::field('date')->label('Fecha');
        if ($camposRecepcionBloqueadosContabilidad) {
            CRUD::modifyField('date', $attrsDisabledContabilidad);
        }
        CRUD::addField(array_merge([
            'name' => 'conformidad_estado',
            'label' => 'Conformidad de estado',
            'type' => 'select_from_array',
            'options' => ['Si' => 'Sí', 'No' => 'No'],
            'default' => 'No',
            'hint' => 'Estado de la mercadería recibida',
        ], $attrsDisabledContabilidad));
        CRUD::addField(array_merge([
            'name' => 'conformidad_cantidad',
            'label' => 'Conformidad de cantidad',
            'type' => 'select_from_array',
            'options' => ['Si' => 'Sí', 'No' => 'No'],
            'default' => 'No',
            'hint' => 'Cantidad recibida conforme',
        ], $attrsDisabledContabilidad));
        CRUD::addField(array_merge([
            'name' => 'conformidad_factura',
            'label' => 'Conformidad de factura recibida',
            'type' => 'select_from_array',
            'options' => ['Si' => 'Sí', 'No' => 'No'],
            'default' => 'No',
            'hint' => 'Factura recibida conforme',
        ], $attrsDisabledContabilidad));
        CRUD::addField([
            'name' => 'according_info',
            'type' => 'custom_html',
            'value' => '<p class="text-muted small">La recepción queda <strong>conforme</strong> solo cuando las tres conformidades son <strong>Sí</strong> y se registró la <strong>corroboración ARCA</strong> (contabilidad, compras o administración).</p>',
        ]);
        
        // Campo oculto: en crear, responsable = usuario actual; en editar, no fijar «value» (Backpack usa el del modelo).
        $user = backpack_user();
        if ($user) {
            $areaField = [
                'name' => 'area_manager_id',
                'type' => 'hidden',
            ];
            if ($this->crud->getCurrentOperation() === 'create') {
                $areaField['default'] = $user->id;
            }
            CRUD::addField($areaField);
        }

        // Mismos checkboxes que en editar (solo en alta; en update se añaden después de setupCreateOperation)
        if ($this->crud->getCurrentOperation() === 'create') {
            $this->addReceptionArcaComprobanteCheckboxes();
        }
        /**
         * Fields can be defined using the fluent syntax:
         * - CRUD::field('price')->type('number');
         */
    }

    /**
     * Modelo de recepción durante el armado del formulario (create/update), si getCurrentEntry() aún no está disponible.
     */
    protected function resolveReceptionEntryForCheckboxState(): ?Reception
    {
        $entry = $this->crud->getCurrentEntry();
        if ($entry instanceof Reception) {
            return $entry;
        }

        $id = $this->crud->getCurrentEntryId();
        if ($id) {
            return Reception::find($id);
        }

        $routeId = request()->route('id');
        if ($routeId !== null && $routeId !== '') {
            return Reception::find($routeId);
        }

        return null;
    }

    /**
     * Comprueba rol contabilidad (ver {@see \App\Models\User::hasContabilidadRole()}).
     */
    protected function userHasContabilidadRole($user): bool
    {
        return $user instanceof \App\Models\User && $user->hasContabilidadRole();
    }

    protected function userCanRegisterArcaCorroboration($user): bool
    {
        return $user instanceof \App\Models\User && $user->canRegisterArcaCorroboration();
    }

    /**
     * Corroboración ARCA y comprobante válido (factura): ambos pasos corresponden al área de contabilidad. En create getCurrentEntry() es null: la visibilidad lo contempla.
     */
    protected function addReceptionArcaComprobanteCheckboxes(
        string $corroboradoArcaInfoHtml = '',
        string $comprobanteValidoInfoHtml = ''
    ): void {
        CRUD::addField([
            'name' => 'corroborado_arca_info',
            'type' => 'custom_html',
            'value' => $corroboradoArcaInfoHtml,
        ]);

        $user = backpack_user();
        $entry = $this->resolveReceptionEntryForCheckboxState();
        $esContabilidad = $this->userHasContabilidadRole($user);
        $puedeCorroborarArca = $this->userCanRegisterArcaCorroboration($user);

        // No usar solo `visible` en checkbox: en algunos entornos el campo igual se renderiza; solo registramos el campo si corresponde.
        if ($puedeCorroborarArca && (! $entry || ! $entry->corroborado_por_arca_at)) {
            CRUD::addField([
                'name' => 'marcar_corroborado_arca',
                'label' => 'Marcar como corroborado por ARCA',
                'type' => 'checkbox',
                'hint' => 'Pueden registrarla contabilidad, compras o administración.',
            ]);
        }

        CRUD::addField([
            'name' => 'comprobante_valido_info',
            'type' => 'custom_html',
            'value' => $comprobanteValidoInfoHtml,
        ]);

        // Misma idea que ARCA: mostrar el tilde hasta que el paso quede hecho (pueden marcarse ambos en un solo guardado; store/update aplican ARCA antes que el comprobante).
        if ($esContabilidad && (! $entry || ! $entry->comprobante_valido_at)) {
            CRUD::addField([
                'name' => 'marcar_comprobante_valido',
                'label' => 'Marcar como comprobante válido (factura)',
                'type' => 'checkbox',
                'hint' => 'Solo contabilidad. El comprobante válido se registra solo si la corroboración ARCA ya está hecha o la marca usted en el mismo guardado.',
            ]);
        }
    }

    /**
     * Define what happens when the Update operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        $entry = $this->crud->getCurrentEntry();

        if ($entry && $entry->isAccordingComplete()) {
            abort(403, 'No se puede editar una recepción que ya está conforme.');
        }
        
        $this->setupCreateOperation();

        // Precalcular HTML para custom_html: Backpack no ejecuta closures en `value` en el formulario de edición
        // (getUpdateFields conserva el closure y la vista intenta imprimirlo como string → error).
        $entryForInfo = $this->crud->getCurrentEntry();
        if ($entryForInfo) {
            $entryForInfo->loadMissing(['corroboradoPorArcaBy', 'comprobanteValidoBy']);
        }
        $corroboradoArcaInfoHtml = '';
        if ($entryForInfo && $entryForInfo->corroborado_por_arca_at) {
            $by = $entryForInfo->corroboradoPorArcaBy ? e($entryForInfo->corroboradoPorArcaBy->name) : '';
            $corroboradoArcaInfoHtml = '<div class="alert alert-success mb-0"><strong>Corroborado por ARCA</strong> el '
                . $entryForInfo->corroborado_por_arca_at->format('d/m/Y H:i')
                . ($by ? ' por ' . $by : '')
                . '</div>';
        }
        $comprobanteValidoInfoHtml = '';
        if ($entryForInfo && $entryForInfo->comprobante_valido_at) {
            $by = $entryForInfo->comprobanteValidoBy ? e($entryForInfo->comprobanteValidoBy->name) : '';
            $comprobanteValidoInfoHtml = '<div class="alert alert-success mb-0"><strong>Comprobante válido</strong> (factura) el '
                . $entryForInfo->comprobante_valido_at->format('d/m/Y H:i')
                . ($by ? ' por ' . $by : '')
                . '</div>';
        }
        
        // En el update, permitir mostrar la orden de compra actual además de las que no tienen recepción
        CRUD::modifyField('purchase_order_id', [
            'options' => function ($query) {
                // Intentar obtener el ID de la recepción actual desde la URL o el entry
                $currentReceptionId = request()->route('id') ?? $this->crud->getCurrentEntryId();
                $currentPurchaseOrderId = null;
                
                if ($currentReceptionId) {
                    $currentReception = Reception::find($currentReceptionId);
                    if ($currentReception) {
                        $currentPurchaseOrderId = $currentReception->purchase_order_id;
                    }
                }
                
                // Filtrar órdenes de compra que no tienen recepción O la orden de compra actual
                $query->where(function ($q) use ($currentPurchaseOrderId) {
                    $q->whereDoesntHave('receptions');
                    if ($currentPurchaseOrderId) {
                        $q->orWhere('id', $currentPurchaseOrderId);
                    }
                });
                
                return $query->get();
            },
        ]);

        $this->addReceptionArcaComprobanteCheckboxes($corroboradoArcaInfoHtml, $comprobanteValidoInfoHtml);

        $deliveryHtml = $this->receptionDeliveryActionHtml($entryForInfo instanceof Reception ? $entryForInfo : null);
        if ($deliveryHtml !== '') {
            CRUD::addField([
                'name' => 'delivery_action',
                'type' => 'custom_html',
                'value' => $deliveryHtml,
            ]);
        }
    }

    /**
     * Botón para registrar la entrega cuando la recepción ya tiene las tres conformidades y ARCA.
     */
    protected function receptionDeliveryActionHtml(?Reception $reception, bool $compact = false): string
    {
        $user = backpack_user();
        if (! $reception || ! $user || $user->hasRole('role_personal')) {
            return '';
        }
        $canDeliver = $user->canRegisterDeliveryForAnyArea() || $user->hasResponsableAreaOrInstituteAuthorityRole();
        if (! $canDeliver) {
            return '';
        }

        $ready = ($reception->conformidad_estado ?? null) === 'Si'
            && ($reception->conformidad_cantidad ?? null) === 'Si'
            && ($reception->conformidad_factura ?? null) === 'Si'
            && $reception->corroborado_por_arca_at;
        if (! $ready) {
            return '';
        }

        $reception->loadMissing('purchase_order');
        $purchaseRequestId = $reception->purchase_order->purchase_request_id ?? null;
        $url = backpack_url('delivery/create?reception_id='.$reception->id.($purchaseRequestId ? '&purchase_request_id='.$purchaseRequestId : ''));

        if ($compact) {
            return '<a href="'.e($url).'" class="btn btn-sm btn-success"><i class="la la-people-carry"></i> Entregar</a>';
        }

        return '<div class="card mb-0" style="border-left: 4px solid #28a745;">'
            .'<div class="card-body">'
            .'<h5 class="card-title"><i class="la la-people-carry"></i> Registrar entrega</h5>'
            .'<p class="card-text">Las conformidades y la corroboración ARCA ya están registradas. Desde aquí podés entregar los productos, también en forma parcial.</p>'
            .'<a href="'.e($url).'" class="btn btn-success"><i class="la la-plus"></i> Registrar entrega</a>'
            .'</div></div>';
    }

    protected function setupShowOperation()
    {
        CRUD::addClause('with', [
            'purchase_order.details.input',
            'purchase_order.details.supplier',
            'purchase_order.supplier',
            'purchase_order.purchaseRequest',
            'user',
            'corroboradoPorArcaBy',
            'comprobanteValidoBy',
        ]);

        CRUD::addColumn([
            'name' => 'number',
            'label' => 'Número',
            'type' => 'closure',
            'function' => function ($entry) {
                return e($entry->number);
            },
        ]);

        CRUD::addColumn([
            'name' => 'purchase_order_id',
            'label' => 'Orden de compra',
            'type' => 'closure',
            'function' => function ($entry) {
                $order = $entry->purchase_order;
                if (! $order) {
                    return '<span class="text-muted">Sin orden de compra</span>';
                }

                $html = '<a href="'.backpack_url('purchase-order/'.$order->id.'/show').'" class="text-primary"><strong>'
                    .e($order->number ?? 'OC-'.$order->id).'</strong></a>';
                $bits = array_filter([
                    $order->supplier_display_name,
                    $order->status,
                    $order->date ? $order->date->format('d/m/Y') : null,
                    '$'.number_format((float) $order->total, 2),
                ]);
                if ($bits !== []) {
                    $html .= '<br><small class="text-muted">'.e(implode(' · ', $bits)).'</small>';
                }
                if ($order->purchaseRequest) {
                    $html .= '<br><small>Solicitud de compra: <a href="'
                        .backpack_url('purchase-request/'.$order->purchaseRequest->id.'/show')
                        .'" class="text-primary">'.e($order->purchaseRequest->request_number ?? 'SC-'.$order->purchaseRequest->id).'</a></small>';
                }

                return $html;
            },
            'escaped' => false,
        ]);
        CRUD::column('date')->label('Fecha');
        CRUD::column('conformidad_estado')->label('Conformidad estado');
        CRUD::column('conformidad_cantidad')->label('Conformidad cantidad');
        CRUD::column('conformidad_factura')->label('Conformidad factura recibida');
        CRUD::column('according')->label('Conforme');
        CRUD::addColumn([
            'name' => 'corroborado_arca_show',
            'label' => 'Corroborado por ARCA',
            'type' => 'closure',
            'function' => function ($entry) {
                if (!$entry->corroborado_por_arca_at) return '—';
                $by = $entry->corroboradoPorArcaBy ? e($entry->corroboradoPorArcaBy->name) : '';
                return $entry->corroborado_por_arca_at->format('d/m/Y H:i') . ($by ? ' por ' . $by : '');
            },
        ]);
        CRUD::addColumn([
            'name' => 'comprobante_valido_show',
            'label' => 'Comprobante válido',
            'type' => 'closure',
            'function' => function ($entry) {
                if (!$entry->comprobante_valido_at) return '—';
                $by = $entry->comprobanteValidoBy ? e($entry->comprobanteValidoBy->name) : '';
                return $entry->comprobante_valido_at->format('d/m/Y H:i') . ($by ? ' por ' . $by : '');
            },
        ]);
        CRUD::addColumn([
            'name' => 'area_manager_id',
            'label' => 'Responsable',
            'type' => 'select',
            'entity' => 'user',
            'attribute' => 'name',
            'model' => 'App\Models\User',
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('user', function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%'.$searchTerm.'%');
                });
            },
        ]);
        
        // Agregar botón PDF en la vista show
        CRUD::addButton('top', 'pdf', 'view', 'crud::buttons.reception_pdf', 'end');

        CRUD::addColumn([
            'name' => 'received_detail',
            'label' => 'Detalle recibido',
            'type' => 'closure',
            'function' => function ($entry) {
                return $this->receptionReceivedDetailHtml($entry);
            },
            'escaped' => false,
        ]);

        CRUD::addColumn([
            'name' => 'delivery_action',
            'label' => 'Entrega',
            'type' => 'closure',
            'function' => function ($entry) {
                return $this->receptionDeliveryActionHtml($entry);
            },
            'escaped' => false,
        ]);
    }

    /**
     * Líneas de la orden de compra que cubre esta recepción.
     */
    private function receptionReceivedDetailHtml(Reception $entry): string
    {
        $order = $entry->purchase_order;
        if (! $order) {
            return '<div class="alert alert-info mb-0">Esta recepción no tiene una orden de compra asociada.</div>';
        }

        $order->loadMissing(['details.input', 'details.supplier', 'supplier']);
        $details = $order->details;

        if ($details->isEmpty()) {
            return '<div class="alert alert-info mb-0">La orden de compra no tiene productos cargados.</div>';
        }

        $html = '<div class="card border-primary mb-0">';
        $html .= '<div class="card-header bg-primary text-white">';
        $html .= '<h6 class="mb-0"><i class="la la-truck-loading"></i> Productos recibidos de '
            .e($order->number ?? 'OC-'.$order->id).'</h6>';
        $html .= '</div>';
        $html .= '<div class="card-body p-0"><div class="table-responsive">';
        $html .= '<table class="table table-sm table-bordered mb-0">';
        $html .= '<thead class="table-light"><tr>';
        $html .= '<th>Producto</th>';
        $html .= '<th>Proveedor</th>';
        $html .= '<th class="text-center">Cantidad</th>';
        $html .= '<th class="text-end">Precio unitario</th>';
        $html .= '<th class="text-end">Importe</th>';
        $html .= '</tr></thead><tbody>';

        $lineTotal = 0.0;
        foreach ($details as $detail) {
            $input = $detail->input;
            $name = $input?->name ?? 'Producto';
            $description = $input?->description ?? '';
            $unit = $input?->unit ?? '';
            $quantity = (float) $detail->quantity;
            $unitPrice = (float) ($detail->getAttributes()['unit_price'] ?? 0);
            $amount = $quantity * $unitPrice;
            $lineTotal += $amount;
            $supplierName = $detail->supplier?->company_name ?? $order->supplier?->company_name;

            $html .= '<tr>';
            $html .= '<td><strong>'.e($name).'</strong>';
            if ($description !== '') {
                $html .= '<br><small class="text-muted">'.e($description).'</small>';
            }
            $html .= '</td>';
            $html .= '<td>'.($supplierName ? e($supplierName) : '<span class="text-muted">—</span>').'</td>';
            $html .= '<td class="text-center"><span class="badge bg-info">'.e(rtrim(rtrim(number_format($quantity, 2, ',', '.'), '0'), ',')).'</span>';
            if ($unit !== '') {
                $html .= ' <small class="text-muted">'.e($unit).'</small>';
            }
            $html .= '</td>';
            $html .= '<td class="text-end">$'.number_format($unitPrice, 2, ',', '.').'</td>';
            $html .= '<td class="text-end">$'.number_format($amount, 2, ',', '.').'</td>';
            $html .= '</tr>';
        }

        $shownTotal = $lineTotal > 0 ? $lineTotal : (float) $order->total;
        $html .= '</tbody><tfoot class="table-light">';
        $html .= '<tr><td colspan="4" class="text-end"><strong>Total de la orden</strong></td>';
        $html .= '<td class="text-end"><span class="badge bg-success fs-6">$'.number_format($shownTotal, 2, ',', '.').'</span></td></tr>';
        $html .= '</tfoot></table></div></div></div>';

        return $html;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store()
    {
        $this->crud->hasAccessOrFail('create');

        // Execute the FormRequest authorization and validation
        $request = $this->crud->validateRequest();

        $this->crud->registerFieldEvents();
        
        // Asegurar que el area_manager_id esté asignado automáticamente al usuario logueado
        $user = backpack_user();
        if ($user && !$request->has('area_manager_id')) {
            $request->merge(['area_manager_id' => $user->id]);
        }
        
        // Verificar si ya existe una recepción para esta orden de compra (validación adicional)
        $purchaseOrderId = $request->input('purchase_order_id');
        if ($purchaseOrderId) {
            $existingReception = Reception::where('purchase_order_id', $purchaseOrderId)->first();
            if ($existingReception) {
                \Alert::error('Esta orden de compra ya tiene una recepción registrada.')->flash();
                return redirect()->back()->withInput();
            }
        }
        
        $marcarArca = $request->input('marcar_corroborado_arca');
        $marcarComprobante = $request->input('marcar_comprobante_valido');
        $request->request->remove('marcar_corroborado_arca');
        $request->request->remove('marcar_comprobante_valido');

        // getStrippedSaveRequest() solo incluye nombres de campos del CRUD; «according» no es un field → hay que añadirlo al array
        $data = $this->crud->getStrippedSaveRequest($request);
        $data['according'] = 'No';

        if ($this->userHasContabilidadRole($user)) {
            $data['date'] = now()->format('Y-m-d');
            $data['conformidad_estado'] = 'No';
            $data['conformidad_cantidad'] = 'No';
            $data['conformidad_factura'] = 'No';
        }

        // Insert the entry
        $entry = $this->crud->create($data);
        $this->data['entry'] = $this->crud->entry = $entry;

        $entry = Reception::find($entry->id);
        if ($marcarArca && $this->userCanRegisterArcaCorroboration($user) && $entry && ! $entry->corroborado_por_arca_at) {
            $entry->update([
                'corroborado_por_arca_at' => now(),
                'corroborado_por_arca_by_id' => $user->id,
            ]);
            \Alert::success('Marcado como corroborado por ARCA.')->flash();
            $entry->refresh();
        }
        if ($marcarComprobante && $this->userHasContabilidadRole($user) && $entry && $entry->corroborado_por_arca_at && ! $entry->comprobante_valido_at) {
            $entry->update([
                'comprobante_valido_at' => now(),
                'comprobante_valido_by_id' => $user->id,
            ]);
            \Alert::success('Marcado como comprobante válido (factura).')->flash();
            $entry->refresh();
        }

        $this->syncReceptionAccordingFlag($entry);
        $entry->refresh();

        $this->syncPurchaseOrderStock($entry);

        // Actualizar estado de detalles de solicitud general si la recepción está conforme
        if ($entry->according === 'Si') {
            $this->updateGeneralRequestDetailsStatus($entry);
        }

        if ($entry->according === 'Si') {
            $entry->load('purchase_order');
            $entry->purchase_order?->markAsRecibidaIfHasConformeReception();
        }

        // Show a success message
        \Alert::success(trans('backpack::crud.insert_success'))->flash();

        // Save the redirect choice for next time
        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($entry->getKey());
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update()
    {
        $this->crud->hasAccessOrFail('update');

        $existingReception = Reception::find($this->crud->getCurrentEntryId());
        if ($existingReception && $existingReception->isAccordingComplete()) {
            \Alert::error('No se puede editar una recepción que ya está conforme.')->flash();
            return redirect()->back();
        }

        // Igual que UpdateOperation de Backpack (eventos definidos en fields, si los hay)
        $this->crud->registerFieldEvents();

        // Execute the FormRequest authorization and validation
        $request = $this->crud->validateRequest();
        
        // Asegurar que el area_manager_id esté asignado automáticamente al usuario logueado
        $user = backpack_user();
        if ($user && !$request->has('area_manager_id')) {
            $request->merge(['area_manager_id' => $user->id]);
        }
        
        // Verificar si se está cambiando a una orden de compra que ya tiene recepción (validación adicional)
        $purchaseOrderId = $request->input('purchase_order_id');
        $currentReceptionId = $this->crud->getCurrentEntryId();
        
        if ($purchaseOrderId && $currentReceptionId) {
            $existingReception = Reception::where('purchase_order_id', $purchaseOrderId)
                ->where('id', '!=', $currentReceptionId)
                ->first();
            
            if ($existingReception) {
                \Alert::error('Esta orden de compra ya tiene una recepción registrada.')->flash();
                return redirect()->back()->withInput();
            }
        }

        $beforeAccording = $currentReceptionId
            ? Reception::query()->whereKey($currentReceptionId)->value('according')
            : null;

        $marcarArca = $request->input('marcar_corroborado_arca');
        $marcarComprobante = $request->input('marcar_comprobante_valido');
        $request->request->remove('marcar_corroborado_arca');
        $request->request->remove('marcar_comprobante_valido');

        $data = $this->crud->getStrippedSaveRequest($request);
        $data['according'] = 'No';

        if ($this->userHasContabilidadRole($user) && $currentReceptionId) {
            $orig = Reception::find($currentReceptionId);
            if ($orig) {
                $dateVal = $orig->date;
                if ($dateVal instanceof \Carbon\CarbonInterface) {
                    $dateVal = $dateVal->format('Y-m-d');
                }
                $data['purchase_order_id'] = $orig->purchase_order_id;
                $data['date'] = $dateVal;
                $data['conformidad_estado'] = $orig->conformidad_estado;
                $data['conformidad_cantidad'] = $orig->conformidad_cantidad;
                $data['conformidad_factura'] = $orig->conformidad_factura;
            }
        }

        // Update the entry
        $entry = $this->crud->update(
            $this->crud->getCurrentEntryId(),
            $data
        );
        $this->data['entry'] = $this->crud->entry = $entry;

        $entry = Reception::find($entry->id);
        if ($marcarArca && $this->userCanRegisterArcaCorroboration($user) && $entry && ! $entry->corroborado_por_arca_at) {
            $entry->update([
                'corroborado_por_arca_at' => now(),
                'corroborado_por_arca_by_id' => $user->id,
            ]);
            \Alert::success('Marcado como corroborado por ARCA.')->flash();
            $entry->refresh();
        }
        if ($marcarComprobante && $this->userHasContabilidadRole($user) && $entry && $entry->corroborado_por_arca_at && ! $entry->comprobante_valido_at) {
            $entry->update([
                'comprobante_valido_at' => now(),
                'comprobante_valido_by_id' => $user->id,
            ]);
            \Alert::success('Marcado como comprobante válido (factura).')->flash();
            $entry->refresh();
        }

        $this->syncReceptionAccordingFlag($entry);
        $entry->refresh();

        $this->syncPurchaseOrderStock($entry);

        // Actualizar estado de detalles de solicitud general si la recepción está conforme
        if ($entry->according === 'Si') {
            $this->updateGeneralRequestDetailsStatus($entry);
        }

        if ($entry->according === 'Si') {
            $entry->load('purchase_order');
            $entry->purchase_order?->markAsRecibidaIfHasConformeReception();
        }

        // Show a success message
        \Alert::success(trans('backpack::crud.update_success'))->flash();

        // Save the redirect choice for next time
        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($entry->getKey());
    }

    /**
     * Sincroniza el flag «according» con la regla de negocio (3 conformidades Sí + ARCA + comprobante válido).
     */
    protected function syncReceptionAccordingFlag(Reception $reception): void
    {
        $reception->refresh();
        $newAccording = $reception->isAccordingComplete() ? 'Si' : 'No';
        if ($reception->according !== $newAccording) {
            $reception->update(['according' => $newAccording]);
        }
    }

    /**
     * Ingresa el stock de la OC al cargar la recepción. Si la factura ya lo hizo, no suma de nuevo.
     */
    protected function syncPurchaseOrderStock(Reception $reception): void
    {
        if (! $reception->purchase_order_id) {
            return;
        }

        $result = app(PurchaseOrderStockService::class)->receiveOnce(
            (int) $reception->purchase_order_id,
            'recepción REC-'.$reception->id
        );

        if ($result === 'posted') {
            \Alert::success('El stock se actualizó con las cantidades de la orden de compra.')->flash();
        }
    }

    /**
     * Get location for reception based on area manager
     *
     * @param Reception $reception
     * @return Location|null
     */
    protected function getLocationForReception(Reception $reception)
    {
        // Intentar obtener la ubicación desde el área de responsabilidad del usuario
        $user = $reception->user;
        
        if ($user) {
            // Buscar áreas de responsabilidad que tengan este usuario como responsable
            $responsibilityArea = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->first();
            
            if ($responsibilityArea) {
                // Intentar encontrar una ubicación con el mismo nombre que el área de responsabilidad
                $location = Location::where('name', $responsibilityArea->name)->first();
                
                if ($location) {
                    return $location;
                }
            }
        }

        // Si no se encuentra, usar una ubicación por defecto (Insumos Generales)
        $defaultLocation = Location::where('name', 'Insumos Generales')->first();
        
        return $defaultLocation;
    }

    /**
     * Find or create product from input
     *
     * @param Input $input
     * @return Product|null
     */
    protected function findOrCreateProductFromInput(Input $input)
    {
        // Intentar encontrar un producto con el mismo nombre
        $product = Product::where('name', $input->name)->first();
        
        if ($product) {
            return $product;
        }

        // Si no existe, crear uno nuevo
        try {
            $product = Product::create([
                'name' => $input->name,
                'description' => $input->description,
                'unit_measurement' => $input->unit ?? 'unidad',
                'minimum_stock' => 0,
                'category_id' => 1, // Categoría por defecto
            ]);

            Log::info('Producto creado desde Input', [
                'input_id' => $input->id,
                'product_id' => $product->id,
                'name' => $product->name
            ]);

            return $product;
        } catch (\Exception $e) {
            Log::error('Error al crear producto desde Input', [
                'input_id' => $input->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Actualizar el estado de los detalles de solicitud general cuando se recepciona con conformidad
     *
     * @param Reception $reception
     * @return void
     */
    protected function updateGeneralRequestDetailsStatus(Reception $reception)
    {
        try {
            // Cargar la orden de compra con sus relaciones
            $purchaseOrder = $reception->purchase_order()
                ->with([
                    'details.input',
                    'purchaseRequest.convertedFromGeneralRequest.details.product'
                ])
                ->first();

            if (!$purchaseOrder) {
                Log::warning('Orden de compra no encontrada para actualizar detalles de solicitud general', [
                    'reception_id' => $reception->id
                ]);
                return;
            }

            // Obtener la solicitud de compra relacionada
            $purchaseRequest = $purchaseOrder->purchaseRequest;
            if (!$purchaseRequest) {
                Log::warning('Solicitud de compra no encontrada para actualizar detalles de solicitud general', [
                    'reception_id' => $reception->id,
                    'purchase_order_id' => $purchaseOrder->id
                ]);
                return;
            }

            // Obtener la solicitud general relacionada
            $generalRequest = $purchaseRequest->convertedFromGeneralRequest;
            if (!$generalRequest) {
                Log::warning('Solicitud general no encontrada para actualizar detalles', [
                    'reception_id' => $reception->id,
                    'purchase_request_id' => $purchaseRequest->id
                ]);
                return;
            }

            // Procesar cada detalle de la orden de compra
            foreach ($purchaseOrder->details as $orderDetail) {
                $input = $orderDetail->input;
                
                if (!$input) {
                    Log::warning('Input no encontrado para detalle de orden de compra', [
                        'detail_id' => $orderDetail->id,
                        'input_id' => $orderDetail->input_id
                    ]);
                    continue;
                }

                // Buscar o crear el producto correspondiente al input
                $product = $this->findOrCreateProductFromInput($input);
                
                if (!$product) {
                    Log::warning('No se pudo obtener o crear producto desde input', [
                        'input_id' => $input->id,
                        'input_name' => $input->name
                    ]);
                    continue;
                }

                // Buscar los detalles de la solicitud general que corresponden a este producto
                $generalRequestDetails = GeneralRequestDetail::where('general_request_id', $generalRequest->id)
                    ->where('product_id', $product->id)
                    ->where('status', '!=', 'Comprada') // Solo actualizar los que aún no están marcados como comprados
                    ->get();

                // Actualizar el estado de cada detalle a 'Comprada'
                foreach ($generalRequestDetails as $detail) {
                    $detail->status = 'Comprada';
                    $detail->save();

                    Log::info('Estado de detalle de solicitud general actualizado a Comprada', [
                        'reception_id' => $reception->id,
                        'general_request_id' => $generalRequest->id,
                        'general_request_detail_id' => $detail->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name
                    ]);
                }
            }

            Log::info('Proceso de actualización de detalles de solicitud general completado', [
                'reception_id' => $reception->id,
                'general_request_id' => $generalRequest->id
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar estado de detalles de solicitud general', [
                'reception_id' => $reception->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Generate PDF for a reception
     */
    public function generatePdf($id)
    {
        $reception = \App\Models\Reception::with([
            'purchase_order.supplier',
            'corroboradoPorArcaBy',
            'comprobanteValidoBy',
            'purchase_order.details.input',
            'purchase_order.details.supplier',
            'user'
        ])->findOrFail($id);
        
        $pdf = Pdf::loadView('reception-pdf', compact('reception'));
        
        return $pdf->stream('comprobante-recepcion-' . $reception->number . '.pdf');
    }
    
    /**
     * Setup delete operation - restrict for role_responsable_compras
     */
    protected function setupDeleteOperation()
    {
        // Verificar permisos para role_responsable_compras
        $user = backpack_user();
        $entry = $this->crud->getCurrentEntry();
        
        if ($user && $user->hasRole('role_responsable_compras', 'backpack')) {
            // El responsable de compras solo puede eliminar recepciones que creó
            if ($entry && $entry->area_manager_id != $user->id) {
                abort(403, 'Solo puedes eliminar las recepciones que creaste.');
            }
        }

        if ($entry && $entry->isAccordingComplete()) {
            abort(403, 'No se puede eliminar una recepción que ya está conforme.');
        }
    }

    /**
     * Recepción conforme: no se elimina (evita bypass por AJAX / URL directa).
     */
    public function destroy($id)
    {
        $this->crud->hasAccessOrFail('delete');

        $user = backpack_user();
        if ($user && $user->hasRole('role_responsable_compras', 'backpack')) {
            $entry = Reception::find($id);
            if ($entry && $entry->area_manager_id != $user->id) {
                if (request()->ajax()) {
                    return response()->json(['error' => ['Solo puedes eliminar las recepciones que creaste.']]);
                }
                abort(403, 'Solo puedes eliminar las recepciones que creaste.');
            }
        }

        $entry = Reception::find($id);
        if ($entry && $entry->isAccordingComplete()) {
            $message = 'No se puede eliminar una recepción que ya está conforme.';
            if (request()->ajax()) {
                return response()->json(['error' => [$message]]);
            }
            \Alert::error($message)->flash();
            return redirect()->back();
        }

        return $this->crud->delete($id);
    }
}
