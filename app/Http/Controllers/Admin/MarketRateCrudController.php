<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\MarketRateRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Class MarketRateCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class MarketRateCrudController extends CrudController
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
        CRUD::setModel(\App\Models\MarketRate::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/market-rate');
        CRUD::setEntityNameStrings('cotización', 'cotizaciones');
        
        // Permitir creación de cotizaciones a compras, admin y responsables de área.
        $user = backpack_user();
        if ($user && ! $user->hasRole('role_responsable_compras', 'backpack') && ! $user->hasRole('role_admin_sistema', 'backpack') && ! $user->hasRole('role_admin_institucion', 'backpack') && ! $user->hasResponsableAreaOrInstituteAuthorityRole()) {
            CRUD::denyAccess('create');
        }
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

        // No mostrar botón de agregar cotización en el listado (las cotizaciones se agregan desde la solicitud de compra)
        CRUD::removeButton('create');
        
        // Ocultar botones de editar y eliminar solo para apoderado y representante_legal (admin_institucion y responsable_compras pueden editar)
        $user = backpack_user();
        if ($user && ($user->hasRole('role_apoderado', 'backpack') || $user->hasRole('role_representante_legal', 'backpack'))) {
            CRUD::removeButton('update');
            CRUD::removeButton('delete');
        }

        // Área / autoridad: cotizaciones de solicitudes que registraron o donde son solicitantes nominales (legado).
        if ($user && $user->hasResponsableAreaOrInstituteAuthorityRole()) {
            CRUD::addClause('whereHas', 'purchaseRequest', function ($query) use ($user) {
                $query->where(function ($q) use ($user) {
                    $q->where('requesting_user_id', $user->id);
                    if (Schema::hasColumn('purchase_requests', 'created_by')) {
                        $q->orWhere('created_by', $user->id);
                    }
                });
            });
        }
        
        // Cargar relaciones necesarias
        CRUD::addClause('with', ['supplier', 'quoteDetails', 'purchaseRequest']);
        
        CRUD::column('supplier.company_name')->label('Proveedor');
        CRUD::column('purchaseRequest.status')->label('Estado de la Solicitud')->type('custom_html')
            ->value(function($entry) {
                $status = $entry->purchaseRequest->status ?? 'Sin estado';
                $badgeClass = match($status) {
                    'Pendiente' => 'bg-warning',
                    'Aprobada' => 'bg-success',
                    'Rechazada' => 'bg-danger',
                    'En Proceso' => 'bg-info',
                    'Completada' => 'bg-primary',
                    default => 'bg-secondary'
                };
                return '<span class="badge ' . $badgeClass . '">' . $status . '</span>';
            });
        CRUD::column('date')->label('Fecha');
        CRUD::column('delivery_date')->label('Fecha de entrega')->type('date');
        CRUD::column('delivery_term')->label('Plazo de entrega');
        CRUD::column('payment_method')->label('Forma de pago');
        CRUD::column('validity_term')->label('Validez de la cotización');
        CRUD::column('total_amount')->label('Monto Total')->type('number')->decimals(2)->prefix('$');
        CRUD::column('vat_amount')->label('IVA')->type('number')->decimals(2)->prefix('$');
        CRUD::column('total_amount_with_vat')->label('Total + IVA')->type('number')->decimals(2)->prefix('$');

        CRUD::column('supporting_badge')->label('Adj.')->type('custom_html')
            ->value(function (\App\Models\MarketRate $entry) {
                $hasFiles = is_array($entry->document_files) && count($entry->document_files) > 0;
                $hasLinks = filled($entry->reference_links);

                return ($hasFiles || $hasLinks)
                    ? '<span class="badge bg-secondary">Sí</span>'
                    : '<span class="text-muted">—</span>';
            });
        
        // Agregar columna personalizada para mostrar detalles de cotización
        CRUD::column('quote_details_count')->label('Detalles')->type('custom_html')
            ->value(function($entry) {
                $count = $entry->quoteDetails->count();
                return '<span class="badge bg-info">' . $count . ' productos</span>';
            });
            
        // Agregar botón PDF en cada fila
        CRUD::addButton('line', 'pdf', 'view', 'crud::buttons.pdf', 'end');
    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(MarketRateRequest::class);
        $user = backpack_user();
        
        // Obtener purchase_request_id de la URL si existe
        $purchaseRequestId = request()->get('purchase_request_id');
        
        // Validar que la solicitud de compra no esté aprobada si se proporciona el ID
        if ($purchaseRequestId) {
            $purchaseRequest = \App\Models\PurchaseRequest::with(['marketRates', 'details'])->find($purchaseRequestId);
            if ($purchaseRequest && $purchaseRequest->status === 'Aprobada') {
                \Alert::error('No se pueden agregar cotizaciones a una solicitud de compra que ya está aprobada.')->flash();
                return redirect()->back();
            }
            if (
                $purchaseRequest
                && $user
                && $user->hasResponsableAreaOrInstituteAuthorityRole()
                && $purchaseRequest->hasQuotationSelectionResolved()
            ) {
                \Alert::error('No puede cargar nuevas cotizaciones: el sector de compras ya seleccionó cotización(es) en esta solicitud.')->flash();

                return redirect()->back();
            }
        }
        
        // Cargar productos de la solicitud de compra si se proporciona el ID
        $purchaseRequestProducts = [];
        if ($purchaseRequestId) {
            $purchaseRequest = \App\Models\PurchaseRequest::with('details.product')->find($purchaseRequestId);
            if ($purchaseRequest && $purchaseRequest->details) {
                $purchaseRequestProducts = $purchaseRequest->details->map(function($detail) {
                    return [
                        'product_id' => $detail->product_id,
                        'product_name' => $detail->product ? ($detail->product->name . ' (' . ($detail->product->unit_measurement ?? 'unidad') . ')') : 'Producto no encontrado',
                        'quantity' => $detail->requested_quantity ?? 0,
                        'unit_price' => 0, // Precio inicial en 0, el usuario debe ingresarlo
                        'unit' => $detail->product ? ($detail->product->unit_measurement ?? 'unidad') : 'unidad',
                        'description' => $detail->product ? ($detail->product->description ?? '') : ''
                    ];
                })->toArray();
            }
        }
        
        $col3 = ['class' => 'form-group col-sm-12 col-md-4 mb-3'];
        $colHalf = ['class' => 'form-group col-sm-12 col-md-6 mb-3'];
        $colOptional4 = ['class' => 'form-group col-sm-12 col-md-3 mb-3'];

        $infoMessage = '<div class="alert alert-info mb-0">'
            .'<i class="la la-info-circle"></i> '
            .'<strong>Información:</strong> Por favor, ingrese los precios unitarios para cada producto o el monto total.<br>'
            .'Puede adjuntar archivos (PDF, imágenes, etc.) y/o pegar enlaces.'
            .'</div>';

        CRUD::field([
            'name' => 'purchase_request_info',
            'label' => 'Información',
            'type' => 'custom_html',
            'value' => $infoMessage,
            'wrapper' => ['class' => 'form-group col-sm-12 mb-3'],
        ]);

        // Campo para seleccionar proveedor
        CRUD::field([
            'name' => 'supplier_id',
            'label' => 'Proveedor',
            'type' => 'select',
            'entity' => 'supplier',
            'attribute' => 'company_name',
            'model' => 'App\Models\Supplier',
            'wrapper' => $col3,
        ]);
        
        // Campo para seleccionar solicitud de compra (solo solicitudes no aprobadas)
        CRUD::field([
            'name' => 'purchase_request_id',
            'label' => 'Solicitud de Compra',
            'type' => 'select',
            'entity' => 'purchaseRequest',
            'attribute' => 'request_number',
            'model' => 'App\Models\PurchaseRequest',
            'default' => $purchaseRequestId,
            'wrapper' => $col3,
            'options' => function ($query) use ($user) {
                // Filtrar solo solicitudes que no estén aprobadas o completadas
                $query->where('status', '!=', 'Aprobada')
                    ->where('status', '!=', 'Completada');

                // Responsable de área: solo sus propias solicitudes.
                if ($user && $user->hasResponsableAreaOrInstituteAuthorityRole()) {
                    $query->where(function ($q) use ($user) {
                        $q->where('requesting_user_id', $user->id);
                        if (Schema::hasColumn('purchase_requests', 'created_by')) {
                            $q->orWhere('created_by', $user->id);
                        }
                    });
                }

                return $query->get();
            },
        ]);

        CRUD::field('date')->label('Fecha')->type('date')->default(now()->format('Y-m-d'))
            ->wrapper($col3);

        [$optionalDetailsHtml, $optionalDetailsScript] = $this->getMarketRateOptionalDetailsBlock();

        CRUD::field([
            'name' => 'market_rate_optional_details_head',
            'type' => 'custom_html',
            'label' => false,
            'wrapper' => ['class' => 'form-group col-sm-12 mb-0'],
            'value' => $optionalDetailsHtml,
        ]);

        CRUD::field('delivery_date')->label('Entrega estimada')->type('date')
            ->wrapper($colOptional4);
        CRUD::field('delivery_term')->label('Plazo')->type('text')
            ->placeholder('Ej.: 5–7 días hábiles')
            ->wrapper($colOptional4);
        CRUD::field('payment_method')->label('Forma de pago')->type('text')
            ->placeholder('Ej.: contado, 30 días factura')
            ->wrapper($colOptional4);
        CRUD::field('validity_term')->label('Validez')->type('text')
            ->placeholder('Ej.: 30 días desde emisión')
            ->wrapper($colOptional4);

        CRUD::field([
            'name' => 'market_rate_optional_details_group_script',
            'type' => 'custom_html',
            'label' => false,
            'wrapper' => false,
            'value' => $optionalDetailsScript,
        ]);

        CRUD::field([
            'name' => 'document_files',
            'label' => 'Archivos de la cotización',
            'type' => 'upload_multiple',
            'upload' => true,
            'disk' => 'public',
            'path' => 'cotizaciones',
            'wrapper' => $colHalf,
        ]);

        CRUD::field([
            'name' => 'reference_links',
            'label' => 'Enlaces (Mercado Libre u otros)',
            'type' => 'textarea',
            'attributes' => [
                'rows' => 2,
                'placeholder' => "https://articulo.mercadolibre.com.ar/...\nhttps://...",
            ],
            'wrapper' => $colHalf,
        ]);

        CRUD::field('total_amount')->label('Monto Total')->type('text')
            ->attributes(['inputmode' => 'decimal', 'placeholder' => '0,00 o 0.00'])
            ->hint('Obligatorio. Puede usar coma o punto como separador decimal.')
            ->wrapper($col3);
        CRUD::field('vat_amount')->label('IVA')->type('text')
            ->attributes(['inputmode' => 'decimal', 'placeholder' => '0,00 o 0.00'])
            ->hint('Opcional: importe de IVA de la cotización.')
            ->wrapper($col3);
        CRUD::field('total_amount_with_vat')->label('Monto Total + IVA')->type('text')
            ->attributes(['inputmode' => 'decimal', 'placeholder' => '0,00 o 0.00'])
            ->hint('Opcional: total final con IVA incluido.')
            ->wrapper($col3);

        // Campo dinámico para agregar items de cotización
        CRUD::field([
            'name' => 'quote_items_selection',
            'label' => 'Items de la Cotización',
            'type' => 'custom_html',
            'value' => $this->getQuoteItemsSelectionHtml($purchaseRequestProducts),
        ]);
        
        // Campo oculto para almacenar los items seleccionados
        CRUD::field([
            'name' => 'selected_quote_items',
            'type' => 'hidden',
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
        if ($entry) {
            // Incluir la solicitud de compra actual en el select aunque esté Aprobada/Completada (solo si el campo existe para evitar array offset on null)
            $purchaseRequestField = $this->crud->firstFieldWhere('name', 'purchase_request_id');
            if ($purchaseRequestField !== null) {
                CRUD::modifyField('purchase_request_id', [
                    'options' => [$this, 'getPurchaseRequestOptionsForEdit'],
                ]);
            }

            // Cargar los items existentes para edición (quoteDetails puede ser colección vacía, no null si está cargada)
            $quoteDetails = $entry->quoteDetails ?? collect();
            $existingItems = $quoteDetails->map(function($detail) {
                return [
                    'product_id' => $detail->product_id,
                    'product_name' => $detail->product->name ?? 'Producto no encontrado',
                    'quantity' => $detail->quantity,
                    'unit_price' => $detail->unit_price,
                    'product_description' => $detail->product_description ?? ($detail->product->description ?? ''),
                ];
            })->toArray();
            
            if ($this->crud->firstFieldWhere('name', 'quote_items_selection') !== null) {
                CRUD::modifyField('quote_items_selection', [
                    'value' => $this->getQuoteItemsSelectionHtml($existingItems),
                ]);
            }
        }
    }

    /**
     * HTML del bloque desplegable "Datos adicionales" y script que coloca los cuatro campos dentro del contenedor.
     *
     * @return array{0: string, 1: string}
     */
    private function getMarketRateOptionalDetailsBlock(): array
    {
        $entry = $this->crud->getCurrentEntry();
        $openOptional = false;
        if ($entry) {
            $openOptional = filled($entry->delivery_date)
                || filled($entry->delivery_term)
                || filled($entry->payment_method)
                || filled($entry->validity_term);
        }
        $openAttr = $openOptional ? ' open' : '';

        $openHtml = '<details class="mb-3 border rounded p-2 bg-light"'.$openAttr.'>'
            .'<summary class="fw-semibold small mb-0" style="cursor:pointer;">'
            .'<strong>Datos adicionales</strong> <span class="text-muted fw-normal">(opcional)</span>'
            .'</summary>'
            .'<div class="row pt-2 g-2 mt-0" id="market-rate-optional-fields-slot"></div>'
            .'</details>';

        $script = '<script>'
            .'(function(){var slot=document.getElementById("market-rate-optional-fields-slot");if(!slot)return;'
            .'["delivery_date","delivery_term","payment_method","validity_term"].forEach(function(name){'
            .'var el=document.querySelector("[bp-field-name=\""+name+"\"]");'
            .'if(el&&el.parentNode!==slot)slot.appendChild(el);'
            .'});})();'
            .'</script>';

        return [$openHtml, $script];
    }

    /**
     * Define what happens when the Show operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-show
     * @return void
     */
    protected function setupShowOperation()
    {
        // Cargar relaciones necesarias para la vista
        CRUD::addClause('with', ['supplier', 'quoteDetails.product', 'purchaseRequest']);
        
        CRUD::setFromDb(); // set fields from db columns.

        CRUD::removeColumn('document_files');
        CRUD::removeColumn('reference_links');
        CRUD::removeColumn('is_selected');
        CRUD::column('supporting_material')->label('Archivos y enlaces')->type('custom_html')
            ->value(fn (\App\Models\MarketRate $entry) => self::supportingMaterialAdminHtml($entry));

        // Proveedor: label en español y mostrar nombre (company_name)
        CRUD::modifyColumn('supplier_id', [
            'label' => 'Proveedor',
            'type' => 'custom_html',
            'value' => function ($entry) {
                $name = $entry->supplier && $entry->supplier->company_name
                    ? e($entry->supplier->company_name)
                    : '—';
                return $name;
            },
        ]);

        // Fecha de la cotización
        CRUD::modifyColumn('date', [
            'label' => 'Fecha de la cotización',
            'type' => 'date',
        ]);

        // Monto total
        CRUD::modifyColumn('total_amount', [
            'label' => 'Monto total',
            'type' => 'number',
            'decimals' => 2,
            'prefix' => '$',
        ]);
        CRUD::modifyColumn('vat_amount', [
            'label' => 'IVA',
            'type' => 'number',
            'decimals' => 2,
            'prefix' => '$',
        ]);
        CRUD::modifyColumn('total_amount_with_vat', [
            'label' => 'Monto total + IVA',
            'type' => 'number',
            'decimals' => 2,
            'prefix' => '$',
        ]);

        // Formatear fecha de entrega en vista show
        CRUD::modifyColumn('delivery_date', [
            'label' => 'Fecha de entrega',
            'type' => 'date',
        ]);

        CRUD::modifyColumn('delivery_term', [
            'label' => 'Plazo de entrega',
        ]);

        // Formatear forma de pago
        CRUD::modifyColumn('payment_method', [
            'label' => 'Forma de pago',
        ]);
        CRUD::modifyColumn('validity_term', [
            'label' => 'Validez de la cotización',
        ]);
        
        // Mostrar el estado de la solicitud de compra
        CRUD::modifyColumn('purchase_request_id', [
            'label' => 'Estado de la Solicitud de Compra',
            'type' => 'custom_html',
            'value' => function($entry) {
                $status = $entry->purchaseRequest->status ?? 'Sin estado';
                $requestNumber = $entry->purchaseRequest->request_number ?? 'N/A';
                $badgeClass = match($status) {
                    'Pendiente' => 'bg-warning',
                    'Aprobada' => 'bg-success',
                    'Rechazada' => 'bg-danger',
                    'En Proceso' => 'bg-info',
                    'Completada' => 'bg-primary',
                    default => 'bg-secondary'
                };
                return '<div>
                    <strong>Solicitud:</strong> ' . $requestNumber . '<br>
                    <span class="badge ' . $badgeClass . ' fs-6">' . $status . '</span>
                </div>';
            }
        ]);

        // Agregar campo personalizado para mostrar detalles de cotización (con estilo de PurchaseRequest)
        CRUD::column('quote_details_table')->label('Detalles de Cotización')->type('custom_html')
            ->value(function($entry) {
                $quoteDetails = $entry->quoteDetails;
                
                if ($quoteDetails->isEmpty()) {
                    return '<div class="alert alert-info">No hay detalles de cotización disponibles.</div>';
                }
                
                $html = '<div class="card border-primary">';
                $html .= '<div class="card-header bg-primary text-white">';
                $html .= '<h6 class="mb-0"><i class="la la-shopping-cart"></i> Productos Cotizados</h6>';
                $html .= '</div>';
                $html .= '<div class="card-body p-0">';
                $html .= '<div class="table-responsive">';
                $html .= '<table class="table table-sm table-bordered mb-0">';
                $html .= '<thead class="table-light">';
                $html .= '<tr>';
                $html .= '<th style="width: 35%;">Producto</th>';
                $html .= '<th style="width: 15%;">Cantidad</th>';
                $html .= '<th style="width: 20%;">Precio Unitario</th>';
                $html .= '<th style="width: 20%;">Subtotal</th>';
                $html .= '<th style="width: 10%;">Estado</th>';
                $html .= '</tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                
                $total = 0;
                foreach ($quoteDetails as $detail) {
                    $subtotal = $detail->quantity * $detail->unit_price;
                    $total += $subtotal;
                    
                    $html .= '<tr>';
                    $productName = $detail->product->name ?? 'Producto no encontrado';
                    if (is_array($productName)) {
                        $productName = 'Producto no encontrado';
                    }
                    $html .= '<td><strong>' . $productName . '</strong>';
                    $detailDescription = $detail->product_description ?? ($detail->product->description ?? null);
                    if ($detailDescription && !is_array($detailDescription)) {
                        $html .= '<br><small class="text-muted">' . e($detailDescription) . '</small>';
                    }
                    $html .= '</td>';
                    $html .= '<td><span class="badge bg-info">' . $detail->quantity . '</span>';
                    if ($detail->product && $detail->product->unit_measurement && !is_array($detail->product->unit_measurement)) {
                        $html .= '<br><small class="text-muted">' . $detail->product->unit_measurement . '</small>';
                    }
                    $html .= '</td>';
                    $html .= '<td class="text-end"><strong>$' . number_format($detail->unit_price, 2) . '</strong></td>';
                    $html .= '<td class="text-end"><span class="badge bg-success">$' . number_format($subtotal, 2) . '</span></td>';
                    $html .= '<td><span class="badge bg-success">Cotizado</span></td>';
                    $html .= '</tr>';
                }
                
                $html .= '</tbody>';
                $html .= '<tfoot class="table-light">';
                $html .= '<tr>';
                $html .= '<th colspan="3" class="text-end">Total:</th>';
                $html .= '<th class="text-end"><span class="badge bg-primary fs-6">$' . number_format($total, 2) . '</span></th>';
                $html .= '<th></th>';
                $html .= '</tr>';
                $html .= '</tfoot>';
                $html .= '</table>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                
                return $html;
            });
    }

    /**
     * Opciones del select Solicitud de Compra en edición: incluye la solicitud actual aunque esté Aprobada/Completada.
     * Callable desde la vista select.blade.php (evita pasar closure que falla como callback).
     */
    public function getPurchaseRequestOptionsForEdit($query)
    {
        $list = $query->where('status', '!=', 'Aprobada')
                      ->where('status', '!=', 'Completada')
                      ->get();
        $entry = $this->crud->getCurrentEntry();
        if ($entry && $entry->purchase_request_id && $list->where('id', $entry->purchase_request_id)->isEmpty()) {
            $current = \App\Models\PurchaseRequest::find($entry->purchase_request_id);
            if ($current) {
                $list = $list->push($current);
            }
        }
        return $list;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store()
    {
        // Verificar que el rol tenga permitido crear cotizaciones.
        $user = backpack_user();
        $adminRoles = ['role_admin_sistema', 'role_admin_institucion', 'role_responsable_compras', 'role_responsable_area', 'role_autoridad_instituto'];
        $isAdmin = false;
        foreach ($adminRoles as $role) {
            if ($user && $user->hasRole($role, 'backpack')) {
                $isAdmin = true;
                break;
            }
        }
        
        if (!$isAdmin) {
            abort(403, 'No tienes permisos para crear cotizaciones.');
        }
        
        $this->crud->hasAccessOrFail('create');

        // execute the FormRequest authorization and validation, if one is required
        $request = $this->crud->validateRequest();

        // register any Model Events defined on fields
        $this->crud->registerFieldEvents();

        // Obtener datos para guardar
        $dataToSave = $this->crud->getStrippedSaveRequest($request) ?? [];

        unset($dataToSave['document_files']);
        $this->assertValidQuoteUploads($request);
        $dataToSave['document_files'] = $this->mergeMarketRateDocumentFiles($request, null);
        if (array_key_exists('reference_links', $dataToSave)) {
            $dataToSave['reference_links'] = $this->normalizeReferenceLinksField($dataToSave['reference_links'] ?? null);
        }
        
        // Si no se seleccionó una solicitud de compra, buscar una pendiente por defecto
        if (!isset($dataToSave['purchase_request_id']) || empty($dataToSave['purchase_request_id'])) {
            $pendingRequest = \App\Models\PurchaseRequest::where('status', 'Pendiente')->first();
            if ($pendingRequest) {
                $dataToSave['purchase_request_id'] = $pendingRequest->id;
            }
        }

        // Validar que la solicitud de compra no esté aprobada
        if (isset($dataToSave['purchase_request_id']) && !empty($dataToSave['purchase_request_id'])) {
            $purchaseRequest = \App\Models\PurchaseRequest::with(['marketRates', 'details'])->find($dataToSave['purchase_request_id']);
            if ($purchaseRequest && $purchaseRequest->status === 'Aprobada') {
                \Alert::error('No se pueden agregar cotizaciones a una solicitud de compra que ya está aprobada.')->flash();
                return redirect()->back()->withInput();
            }

            // Área / autoridad: solo solicitudes que registraron en el sistema (o legado: solicitante nominal).
            if (
                $purchaseRequest
                && $user
                && $user->hasResponsableAreaOrInstituteAuthorityRole()
                && ! $purchaseRequest->isActingAsCreatingUser((int) $user->id)
            ) {
                abort(403, 'Solo puedes cargar cotizaciones en solicitudes de compra que registraste.');
            }

            if (
                $purchaseRequest
                && $user
                && $user->hasResponsableAreaOrInstituteAuthorityRole()
                && $purchaseRequest->hasQuotationSelectionResolved()
            ) {
                \Alert::error('No puede cargar nuevas cotizaciones: el sector de compras ya seleccionó cotización(es) en esta solicitud.')->flash();

                return redirect()->back()->withInput();
            }
        }

        // Total desde ítems (líneas) y/o monto manual del formulario (ej. cotización Mercado Libre sin precios por ítem)
        $selectedItems = $request->input('selected_quote_items');
        $calculatedTotal = $this->sumTotalFromSelectedQuoteItemsJson($selectedItems);
        $parsedManual = $this->parseTotalAmountInput($dataToSave['total_amount'] ?? null);
        $parsedVat = $this->parseTotalAmountInput($dataToSave['vat_amount'] ?? null);
        $parsedTotalWithVat = $this->parseTotalAmountInput($dataToSave['total_amount_with_vat'] ?? null);
        if ($parsedManual !== null) {
            $dataToSave['total_amount'] = $parsedManual;
        } else {
            $dataToSave['total_amount'] = $calculatedTotal;
        }
        $dataToSave['vat_amount'] = $parsedVat;
        if ($parsedTotalWithVat !== null && $parsedTotalWithVat > 0) {
            $dataToSave['total_amount_with_vat'] = $parsedTotalWithVat;
        } elseif (($dataToSave['total_amount'] ?? null) !== null && $parsedVat !== null) {
            $dataToSave['total_amount_with_vat'] = (float) $dataToSave['total_amount'] + $parsedVat;
        }
        
        // insert item in the db
        $item = $this->crud->create($dataToSave);
        $this->data['entry'] = $this->crud->entry = $item;

        // Procesar los items de cotización seleccionados (esto también actualizará el total_amount)
        $this->processSelectedQuoteItems($item, $request);

        // show a success message
        \Alert::success(trans('backpack::crud.insert_success'))->flash();

        $purchaseRequestId = (int) ($item->purchase_request_id ?? 0);
        if ($purchaseRequestId > 0) {
            return redirect()->route('purchase-request.show', $purchaseRequestId);
        }

        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($item->getKey());
    }

    /**
     * Update the specified resource in storage.
     */
    public function update()
    {
        $this->crud->hasAccessOrFail('update');
        $user = backpack_user();

        // execute the FormRequest authorization and validation, if one is required
        $request = $this->crud->validateRequest();

        // register any Model Events defined on fields
        $this->crud->registerFieldEvents();

        // Obtener la cotización actual
        $currentEntry = $this->crud->getCurrentEntry();
        
        // Advertir pero permitir editar si la solicitud está aprobada (solo bloqueamos asociar a otra solicitud aprobada más abajo)
        // Así se pueden corregir fecha de entrega, forma de pago, etc. sin bloquear todo el guardado.

        // Obtener datos para guardar
        $dataToSave = $this->crud->getStrippedSaveRequest($request) ?? [];

        unset($dataToSave['document_files']);
        $this->assertValidQuoteUploads($request);
        $dataToSave['document_files'] = $this->mergeMarketRateDocumentFiles($request, $currentEntry);
        if (array_key_exists('reference_links', $dataToSave)) {
            $dataToSave['reference_links'] = $this->normalizeReferenceLinksField($dataToSave['reference_links'] ?? null);
        }

        $selectedItems = $request->input('selected_quote_items');
        $calculatedTotal = $this->sumTotalFromSelectedQuoteItemsJson($selectedItems);
        $parsedManual = $this->parseTotalAmountInput($dataToSave['total_amount'] ?? null);
        $parsedVat = $this->parseTotalAmountInput($dataToSave['vat_amount'] ?? null);
        $parsedTotalWithVat = $this->parseTotalAmountInput($dataToSave['total_amount_with_vat'] ?? null);
        if ($parsedManual !== null) {
            $dataToSave['total_amount'] = $parsedManual;
        } else {
            $dataToSave['total_amount'] = $calculatedTotal > 0 ? $calculatedTotal : (float) ($currentEntry ? ($currentEntry->total_amount ?? 0) : 0);
        }
        $dataToSave['vat_amount'] = $parsedVat;
        if ($parsedTotalWithVat !== null && $parsedTotalWithVat > 0) {
            $dataToSave['total_amount_with_vat'] = $parsedTotalWithVat;
        } elseif (($dataToSave['total_amount'] ?? null) !== null && $parsedVat !== null) {
            $dataToSave['total_amount_with_vat'] = (float) $dataToSave['total_amount'] + $parsedVat;
        }

        // Validar también si se está cambiando la solicitud de compra a una aprobada
        if (isset($dataToSave['purchase_request_id']) && !empty($dataToSave['purchase_request_id'])) {
            $purchaseRequest = \App\Models\PurchaseRequest::find($dataToSave['purchase_request_id']);
            if ($purchaseRequest && $purchaseRequest->status === 'Aprobada') {
                \Alert::error('No se puede asociar una cotización a una solicitud de compra que ya está aprobada.')->flash();
                return redirect()->back()->withInput();
            }
        }

        // Área / autoridad: solo editar cotizaciones ligadas a solicitudes que registraron (o legado).
        if ($user && $user->hasResponsableAreaOrInstituteAuthorityRole()) {
            $targetPurchaseRequestId = $dataToSave['purchase_request_id'] ?? ($currentEntry->purchase_request_id ?? null);
            if ($targetPurchaseRequestId) {
                $targetPurchaseRequest = \App\Models\PurchaseRequest::find($targetPurchaseRequestId);
                if ($targetPurchaseRequest && ! $targetPurchaseRequest->isActingAsCreatingUser((int) $user->id)) {
                    abort(403, 'Solo puedes editar cotizaciones de solicitudes de compra que registraste.');
                }
            }
        }

        // update item in the db
        $item = $this->crud->update($this->crud->getCurrentEntry()->getKey(), $dataToSave);
        $this->data['entry'] = $this->crud->entry = $item;

        // Procesar los items de cotización (eliminar existentes y crear nuevos)
        $this->processSelectedQuoteItems($item, $request, true);

        // show a success message
        \Alert::success(trans('backpack::crud.update_success'))->flash();

        // save the redirect choice for next time
        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($item->getKey());
    }

    /**
     * Generate PDF for a market rate (cotización)
     */
    public function generatePdf($id)
    {
        $marketRate = \App\Models\MarketRate::with(['supplier', 'quoteDetails.product', 'purchaseRequest'])->findOrFail($id);
        
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('market-rate-pdf', compact('marketRate'));
        
        return $pdf->stream('cotizacion-' . str_pad($marketRate->id, 4, '0', STR_PAD_LEFT) . '.pdf');
    }

    /**
     * Mostrar archivo adjunto subido para una cotización.
     */
    public function showUploadedFile($id, $index = null)
    {
        $marketRate = \App\Models\MarketRate::findOrFail($id);
        $files = \App\Models\MarketRate::normalizeDocumentFilesToPathList($marketRate->document_files);

        $fileIndex = max(0, (int) ($index ?? 0));
        if (! isset($files[$fileIndex])) {
            abort(404, 'Archivo adjunto no encontrado.');
        }

        $stored = $files[$fileIndex];
        if (is_string($stored)) {
            $trimmed = trim($stored);
            if ($trimmed !== '' && str_starts_with($trimmed, '[')) {
                $inner = json_decode($trimmed, true);
                if (is_array($inner) && isset($inner[0]) && is_string($inner[0]) && count($inner) === 1) {
                    $stored = $inner[0];
                }
            }
        }

        $relative = self::normalizeDocumentFileToPublicDiskRelativePath($stored);
        if ($relative !== null && Storage::disk('public')->exists($relative)) {
            $downloadName = basename(str_replace('\\', '/', $relative));

            return Storage::disk('public')->response($relative, $downloadName, [
                'Content-Disposition' => 'inline; filename="'.$downloadName.'"',
            ]);
        }

        $normalizedFs = str_replace('\\', '/', trim((string) $stored));
        if ($normalizedFs !== '' && ! str_contains($normalizedFs, '..') && is_file($normalizedFs) && is_readable($normalizedFs)) {
            return response()->file($normalizedFs);
        }

        abort(404, 'El archivo no existe en el almacenamiento.');
    }

    /**
     * Pasa el valor guardado en document_files a una ruta relativa al disco "public" (root storage/app/public).
     * Acepta: "cotizaciones/archivo.pdf", "/storage/cotizaciones/...", URL completa, o ruta absoluta bajo .../storage/app/public/...
     */
    private static function normalizeDocumentFileToPublicDiskRelativePath(string $stored): ?string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return null;
        }

        $asUrl = str_replace('\\', '/', $stored);
        if (preg_match('#^https?://#i', $asUrl)) {
            $pathPart = parse_url($asUrl, PHP_URL_PATH);
            if (! is_string($pathPart) || $pathPart === '') {
                return null;
            }
            $pathPart = str_replace('\\', '/', $pathPart);
            if (preg_match('#/storage/(.+)$#i', $pathPart, $m)) {
                return self::stripDirectoryTraversal(urldecode($m[1]));
            }

            return null;
        }

        $unix = str_replace('\\', '/', $stored);
        if (preg_match('#(?:/|\\\\)storage/app/public/(.+)$#i', $unix, $m)) {
            return self::stripDirectoryTraversal($m[1]);
        }

        $rel = ltrim($unix, '/');
        if (str_starts_with($rel, 'storage/')) {
            $rel = substr($rel, strlen('storage/'));
        }
        if (str_starts_with($rel, 'public/')) {
            $rel = substr($rel, strlen('public/'));
        }

        return self::stripDirectoryTraversal($rel);
    }

    private static function stripDirectoryTraversal(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        return ltrim($path, '/');
    }

    /**
     * Generate HTML for quote items selection (similar to products in general requests)
     */
    private function getQuoteItemsSelectionHtml($existingItems = [])
    {
        $existingItemsJson = json_encode($existingItems);
        
        return '
        <div id="quote-items-container">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="product-select" class="form-label">Seleccionar Producto</label>
                    <select id="product-select" class="form-control">
                        <option value="">Seleccionar un producto...</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="item-quantity" class="form-label">Cantidad</label>
                    <input type="number" id="item-quantity" class="form-control" min="1" value="1">
                </div>
                <div class="col-md-2">
                    <label for="item-price" class="form-label">Precio Unitario</label>
                    <input type="number" id="item-price" class="form-control" min="0" step="0.01" value="0">
                </div>
                <div class="col-md-2">
                    <label for="item-description" class="form-label">Descripción</label>
                    <input type="text" id="item-description" class="form-control" placeholder="Opcional">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Subtotal</label>
                    <div id="subtotal-info" class="form-control-plaintext">
                        <span class="badge bg-secondary">$0.00</span>
                    </div>
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="button" id="add-item-btn" class="btn btn-primary btn-block">
                        <i class="la la-plus"></i> Agregar
                    </button>
                </div>
            </div>
            <div id="selected-items-list"></div>
            <div class="row mt-3">
                <div class="col-12">
                    <div class="alert alert-info">
                        <strong>Total de la Cotización:</strong> <span id="total-amount-display">$0.00</span>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Cargar productos existentes
            fetchProducts();
            
            // Event listeners
            document.getElementById("add-item-btn").addEventListener("click", addQuoteItem);
            document.getElementById("product-select").addEventListener("change", updateProductInfo);
            document.getElementById("item-quantity").addEventListener("input", calculateSubtotal);
            document.getElementById("item-price").addEventListener("input", calculateSubtotal);
            const vatField = getVatAmountField();
            if (vatField) {
                vatField.addEventListener("input", updateTotalWithVatField);
                vatField.addEventListener("change", updateTotalWithVatField);
            }
            
            // No forzamos el total en submit para no pisar montos manuales.
            
            // Cargar items existentes si hay
            const existingItems = ' . $existingItemsJson . ';
            if (existingItems && existingItems.length > 0) {
                existingItems.forEach(item => {
                    addItemToList(item.product_id, item.product_name, item.quantity, item.unit_price, "unidad", item.product_description || "");
                });
            }
            
            function fetchProducts() {
                const productsUrl = ' . json_encode(backpack_url('api/productos')) . ';
                fetch(productsUrl, { credentials: "same-origin" })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error("HTTP " + response.status);
                        }
                        return response.json();
                    })
                    .then(products => {
                        const select = document.getElementById("product-select");
                        if (!select) {
                            return;
                        }
                        select.innerHTML = "<option value=\"\">Seleccionar un producto...</option>";
                        if (!Array.isArray(products) || products.length === 0) {
                            select.innerHTML += "<option value=\"\" disabled>No hay productos disponibles</option>";
                            return;
                        }
                        products.forEach(product => {
                            const option = document.createElement("option");
                            option.value = product.id;
                            option.textContent = product.name;
                            option.setAttribute("data-unit", product.unit_measurement || "unidad");
                            option.setAttribute("data-description", product.description || "");
                            select.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error("Error cargando productos:", error);
                        const select = document.getElementById("product-select");
                        if (select) {
                            select.innerHTML = "<option value=\"\">Error al cargar productos</option>";
                        }
                    });
            }
            
            function updateProductInfo() {
                const select = document.getElementById("product-select");
                const selectedOption = select.options[select.selectedIndex];
                
                if (selectedOption.value) {
                    const description = selectedOption.getAttribute("data-description");
                    const descriptionInput = document.getElementById("item-description");
                    if (descriptionInput && !descriptionInput.value) {
                        descriptionInput.value = description || "";
                    }
                    calculateSubtotal();
                }
            }
            
            function calculateSubtotal() {
                const quantity = parseFloat(document.getElementById("item-quantity").value) || 0;
                const price = parseFloat(document.getElementById("item-price").value) || 0;
                const subtotal = quantity * price;
                
                document.getElementById("subtotal-info").innerHTML = 
                    `<span class="badge bg-info">$${subtotal.toFixed(2)}</span>`;
            }
            
            function addQuoteItem() {
                const select = document.getElementById("product-select");
                const quantity = document.getElementById("item-quantity");
                const price = document.getElementById("item-price");
                const descriptionInput = document.getElementById("item-description");
                
                if (!select.value) {
                    alert("Por favor seleccione un producto");
                    return;
                }
                
                if (!quantity.value || quantity.value < 1) {
                    alert("Por favor ingrese una cantidad válida");
                    return;
                }
                
                if (!price.value || price.value < 0) {
                    alert("Por favor ingrese un precio válido");
                    return;
                }
                
                const selectedOption = select.options[select.selectedIndex];
                const productId = select.value;
                const productName = selectedOption.textContent;
                const unit = selectedOption.getAttribute("data-unit");
                const description = descriptionInput ? descriptionInput.value : "";
                
                addItemToList(productId, productName, quantity.value, price.value, unit, description);
                
                // Limpiar campos
                select.value = "";
                quantity.value = 1;
                price.value = 0;
                if (descriptionInput) {
                    descriptionInput.value = "";
                }
                calculateSubtotal();
            }
            
            function addItemToList(productId, productName, quantity, unitPrice, unit = "unidad", description = "") {
                const container = document.getElementById("selected-items-list");
                const itemDiv = document.createElement("div");
                itemDiv.className = "selected-item-item border p-3 mb-2";
                itemDiv.setAttribute("data-product-id", productId);
                
                const subtotal = quantity * unitPrice;
                
                itemDiv.innerHTML = `
                    <div class="row">
                        <div class="col-md-3">
                            <strong>${productName}</strong>
                            ${description ? `<br><small class="text-muted">${description}</small>` : ""}
                            <br><small class="text-muted">Unidad: ${unit}</small>
                        </div>
                        <div class="col-md-2">
                            <label>Cantidad:</label>
                            <input type="number" class="form-control item-quantity" value="${quantity}" min="1">
                        </div>
                        <div class="col-md-2">
                            <label>Precio Unitario:</label>
                            <input type="number" class="form-control item-price" value="${unitPrice}" min="0" step="0.01">
                        </div>
                        <div class="col-md-2">
                            <label>Descripción:</label>
                            <input type="text" class="form-control item-description" value="${description || ""}">
                        </div>
                        <div class="col-md-2">
                            <label>Subtotal:</label>
                            <div class="form-control-plaintext">
                                <span class="badge bg-success item-subtotal">$${subtotal.toFixed(2)}</span>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-danger btn-sm remove-item">
                                <i class="la la-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
                
                container.appendChild(itemDiv);
                
                // Event listener para remover item
                itemDiv.querySelector(".remove-item").addEventListener("click", function() {
                    itemDiv.remove();
                    updateHiddenFields();
                    updateTotalAmount();
                });
                
                // Event listeners para actualizar cantidades y precios
                itemDiv.querySelector(".item-quantity").addEventListener("input", function() {
                    updateItemSubtotal(itemDiv);
                    updateHiddenFields();
                    updateTotalAmount();
                });
                
                itemDiv.querySelector(".item-price").addEventListener("input", function() {
                    updateItemSubtotal(itemDiv);
                    updateHiddenFields();
                    updateTotalAmount();
                });

                const descriptionField = itemDiv.querySelector(".item-description");
                if (descriptionField) {
                    descriptionField.addEventListener("input", function() {
                        updateHiddenFields();
                    });
                }
                
                updateHiddenFields();
                updateTotalAmount();
            }
            
            function updateItemSubtotal(itemDiv) {
                const quantity = parseFloat(itemDiv.querySelector(".item-quantity").value) || 0;
                const price = parseFloat(itemDiv.querySelector(".item-price").value) || 0;
                const subtotal = quantity * price;
                
                itemDiv.querySelector(".item-subtotal").textContent = `$${subtotal.toFixed(2)}`;
            }
            
            function updateHiddenFields() {
                const items = [];
                const itemDivs = document.querySelectorAll(".selected-item-item");
                
                itemDivs.forEach(itemDiv => {
                    const productId = itemDiv.getAttribute("data-product-id");
                    const quantity = itemDiv.querySelector(".item-quantity").value;
                    const unitPrice = itemDiv.querySelector(".item-price").value;
                    const productName = itemDiv.querySelector("strong").textContent;
                    const productDescriptionField = itemDiv.querySelector(".item-description");
                    const productDescription = productDescriptionField ? productDescriptionField.value : "";
                    
                    items.push({
                        product_id: productId,
                        product_name: productName,
                        quantity: quantity,
                        unit_price: unitPrice,
                        product_description: productDescription
                    });
                });
                
                document.querySelector("input[name=\'selected_quote_items\']").value = JSON.stringify(items);
            }
            
            function updateTotalAmount() {
                let total = 0;
                const itemDivs = document.querySelectorAll(".selected-item-item");
                
                itemDivs.forEach(itemDiv => {
                    const quantity = parseFloat(itemDiv.querySelector(".item-quantity").value) || 0;
                    const price = parseFloat(itemDiv.querySelector(".item-price").value) || 0;
                    total += quantity * price;
                });
                
                document.getElementById("total-amount-display").textContent = `$${total.toFixed(2)}`;
                
                // Actualizar también el campo de monto total del formulario
                // Intentar múltiples selectores para asegurar que encontremos el campo
                let totalAmountField = document.querySelector("input[name=\'total_amount\']");
                if (!totalAmountField) {
                    totalAmountField = document.querySelector("input[name=\'total_amount\']");
                }
                if (!totalAmountField) {
                    // Buscar por ID si existe
                    totalAmountField = document.getElementById("total_amount");
                }
                if (!totalAmountField) {
                    // Buscar cualquier input con name que contenga total_amount
                    totalAmountField = document.querySelector("input[name*=\'total_amount\']");
                }
                if (totalAmountField) {
                    const currentRaw = (totalAmountField.value || "").trim();
                    const currentValue = parseFloat(currentRaw.replace(",", "."));
                    const hasManualValue = currentRaw !== "" && !Number.isNaN(currentValue) && currentValue > 0;
                    // Solo autocompletamos si hay total calculado o si el campo esta vacio/no valido.
                    if (total > 0 || !hasManualValue) {
                        totalAmountField.value = total.toFixed(2);
                        // Disparar evento change para asegurar que el valor se registre
                        totalAmountField.dispatchEvent(new Event(\'change\', { bubbles: true }));
                        totalAmountField.dispatchEvent(new Event(\'input\', { bubbles: true }));
                    }
                    updateTotalWithVatField();
                } else {
                    console.warn("No se encontró el campo total_amount para actualizar");
                }
            }

            function getVatAmountField() {
                let field = document.querySelector("input[name=\'vat_amount\']");
                if (!field) {
                    field = document.getElementById("vat_amount");
                }
                if (!field) {
                    field = document.querySelector("input[name*=\'vat_amount\']");
                }
                return field;
            }

            function getTotalWithVatField() {
                let field = document.querySelector("input[name=\'total_amount_with_vat\']");
                if (!field) {
                    field = document.getElementById("total_amount_with_vat");
                }
                if (!field) {
                    field = document.querySelector("input[name*=\'total_amount_with_vat\']");
                }
                return field;
            }

            function updateTotalWithVatField() {
                let totalAmountField = document.querySelector("input[name=\'total_amount\']");
                if (!totalAmountField) {
                    totalAmountField = document.querySelector("input[name*=\'total_amount\']");
                }
                const vatAmountField = getVatAmountField();
                const totalWithVatField = getTotalWithVatField();
                if (!totalAmountField || !vatAmountField || !totalWithVatField) {
                    return;
                }

                const subtotal = parseFloat(String(totalAmountField.value || "0").replace(",", ".")) || 0;
                const vat = parseFloat(String(vatAmountField.value || "0").replace(",", ".")) || 0;
                const totalWithVat = subtotal + vat;
                totalWithVatField.value = totalWithVat.toFixed(2);
                totalWithVatField.dispatchEvent(new Event(\'change\', { bubbles: true }));
                totalWithVatField.dispatchEvent(new Event(\'input\', { bubbles: true }));
            }
        });
        </script>';
    }

    /**
     * Process selected quote items and create quote details
     */
    private function processSelectedQuoteItems($marketRate, $request, $isUpdate = false)
    {
        // Si es una actualización, eliminar items existentes
        if ($isUpdate) {
            Log::info('Eliminando items existentes de cotización:', ['id' => $marketRate->id]);
            $marketRate->quoteDetails()->delete();
        }

        $manualTotal = $this->parseTotalAmountInput($request->input('total_amount'));
        
        $selectedItems = $request->input('selected_quote_items');
        
        if (!$selectedItems) {
            Log::info('No hay items de cotización seleccionados');
            if ($manualTotal !== null) {
                $marketRate->update(['total_amount' => $manualTotal]);
            }
            return;
        }
        
        $items = json_decode($selectedItems, true);
        
        if (!is_array($items) || empty($items)) {
            Log::warning('Items de cotización no válidos o vacíos:', ['selectedItems' => $selectedItems]);
            if ($manualTotal !== null) {
                $marketRate->update(['total_amount' => $manualTotal]);
            }
            return;
        }
        
        Log::info('Items de cotización seleccionados:', $items);
        
        $totalAmount = 0;
        
        foreach ($items as $itemData) {
            if (!is_array($itemData)) {
                Log::warning('Item de cotización no es un array, omitiendo:', ['itemData' => $itemData]);
                continue;
            }
            $productId = $itemData['product_id'] ?? null;
            $quantity = floatval($itemData['quantity'] ?? 0);
            $unitPrice = floatval($itemData['unit_price'] ?? 0);
            $productDescription = isset($itemData['product_description']) ? trim((string) $itemData['product_description']) : null;
            if ($productDescription === '') {
                $productDescription = null;
            }
            
            if (!$productId || $quantity <= 0 || $unitPrice < 0) {
                Log::warning('Item de cotización inválido, omitiendo:', $itemData);
                continue;
            }
            
            // Crear el detalle de la cotización
            try {
                $detail = \App\Models\QuoteDetail::create([
                    'market_rate_id' => $marketRate->id,
                    'product_id' => $productId,
                    'product_description' => $productDescription,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                ]);
                
                $totalAmount += $quantity * $unitPrice;
                Log::info('Detalle de cotización creado:', ['id' => $detail->id, 'product_id' => $productId, 'quantity' => $quantity, 'unit_price' => $unitPrice]);
            } catch (\Exception $e) {
                Log::error('Error al crear detalle de cotización:', [
                    'error' => $e->getMessage(),
                    'item_data' => $itemData
                ]);
            }
        }
        
        $finalTotal = $totalAmount;
        if ($finalTotal <= 0 && $manualTotal !== null && $manualTotal > 0) {
            $finalTotal = $manualTotal;
        }

        $marketRate->refresh();
        $vatAmount = $this->parseTotalAmountInput($request->input('vat_amount'));
        $totalWithVatInput = $this->parseTotalAmountInput($request->input('total_amount_with_vat'));
        $updateData = ['total_amount' => $finalTotal];
        if ($vatAmount !== null) {
            $updateData['vat_amount'] = $vatAmount;
        }
        if ($totalWithVatInput !== null && $totalWithVatInput > 0) {
            $updateData['total_amount_with_vat'] = $totalWithVatInput;
        } elseif ($vatAmount !== null) {
            $updateData['total_amount_with_vat'] = $finalTotal + $vatAmount;
        }
        $marketRate->update($updateData);
        Log::info('Monto total actualizado:', ['market_rate_id' => $marketRate->id, 'total_amount' => $finalTotal, 'from_lines' => $totalAmount, 'manual' => $manualTotal]);
    }

    private function parseTotalAmountInput($raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $v = $raw;
        if (is_string($v)) {
            $v = trim($v);
            if ($v === '') {
                return null;
            }
            if (str_contains($v, ',')) {
                $v = str_replace('.', '', $v);
                $v = str_replace(',', '.', $v);
            }
        }
        if (! is_numeric($v)) {
            return null;
        }

        return (float) $v;
    }

    private function sumTotalFromSelectedQuoteItemsJson(?string $selectedItems): float
    {
        $calculatedTotal = 0.0;
        if (! $selectedItems) {
            return $calculatedTotal;
        }
        $items = json_decode($selectedItems, true);
        if (! is_array($items)) {
            return $calculatedTotal;
        }
        foreach ($items as $itemData) {
            if (! is_array($itemData)) {
                continue;
            }
            $quantity = floatval($itemData['quantity'] ?? 0);
            $unitPrice = floatval($itemData['unit_price'] ?? 0);
            $calculatedTotal += $quantity * $unitPrice;
        }

        return $calculatedTotal;
    }

    private function assertValidQuoteUploads(Request $request): void
    {
        if (! $request->hasFile('document_files')) {
            return;
        }
        $files = $request->file('document_files');
        if (! is_array($files)) {
            $files = [$files];
        }
        $validator = Validator::make(
            ['document_files' => $files],
            ['document_files' => 'array', 'document_files.*' => 'file|max:10240|mimes:pdf,jpeg,jpg,png,gif,webp,doc,docx']
        );
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * @return list<string>
     */
    private function mergeMarketRateDocumentFiles(Request $request, ?\App\Models\MarketRate $existing): array
    {
        $paths = [];
        if ($existing && is_array($existing->document_files)) {
            $paths = $existing->document_files;
        }
        foreach ((array) $request->input('clear_document_files', []) as $cleared) {
            if ($cleared === null || $cleared === '') {
                continue;
            }
            $paths = array_values(array_filter($paths, fn ($p) => $p !== $cleared));
            try {
                Storage::disk('public')->delete($cleared);
            } catch (\Throwable $e) {
                Log::warning('market_rate: no se pudo borrar archivo', ['path' => $cleared, 'message' => $e->getMessage()]);
            }
        }
        if ($request->hasFile('document_files')) {
            $uploaded = $request->file('document_files');
            $list = is_array($uploaded) ? $uploaded : [$uploaded];
            foreach ($list as $file) {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    $paths[] = $file->store('cotizaciones', 'public');
                }
            }
        }

        return array_values(array_unique(array_filter($paths)));
    }

    private function normalizeReferenceLinksField($raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $text = trim((string) $raw);
        if ($text === '') {
            return null;
        }
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $kept = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (! preg_match('#^https?://#i', $line)) {
                continue;
            }
            $kept[] = $line;
        }

        return $kept === [] ? null : implode("\n", $kept);
    }

    private static function supportingMaterialAdminHtml(\App\Models\MarketRate $entry): string
    {
        $parts = [];
        $paths = \App\Models\MarketRate::normalizeDocumentFilesToPathList($entry->document_files);
        if ($paths !== []) {
            $lis = [];
            foreach ($paths as $idx => $path) {
                $url = route('market-rate.uploaded-file', ['id' => $entry->id, 'index' => $idx]);
                $lis[] = '<li><a href="'.e($url).'" target="_blank" rel="noopener">'.e(basename($path)).'</a></li>';
            }
            if ($lis !== []) {
                $parts[] = '<strong>Archivos</strong><ul class="mb-0 small">'.implode('', $lis).'</ul>';
            }
        }
        if ($entry->reference_links) {
            $lis = [];
            foreach (\App\Models\MarketRate::referenceLinkUrlsList($entry->reference_links) as $line) {
                $lis[] = '<li><a href="'.e($line).'" target="_blank" rel="noopener">'.e(\Illuminate\Support\Str::limit($line, 72)).'</a></li>';
            }
            if ($lis !== []) {
                $parts[] = '<strong>Enlaces</strong><ul class="mb-0 small">'.implode('', $lis).'</ul>';
            }
        }
        if ($parts === []) {
            return '<span class="text-muted">—</span>';
        }

        return '<div>'.implode('</div><div class="mt-2">', $parts).'</div>';
    }
}
