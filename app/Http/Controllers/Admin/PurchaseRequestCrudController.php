<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\PurchaseRequestRequest;
use App\Models\MarketRate;
use App\Models\PurchaseRequestEvent;
use App\Services\PurchaseRequestNotificationService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Class PurchaseRequestCrudController
 *
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class PurchaseRequestCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\PurchaseRequest::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/purchase-request');
        CRUD::setEntityNameStrings('solicitud de compra', 'solicitudes de compra');

        // Usar FormRequest personalizado
        CRUD::setValidation(PurchaseRequestRequest::class);

        // El responsable de compras no puede editar solicitudes de compra.
        $user = backpack_user();
        if ($user && $user->hasRole('role_responsable_compras', 'backpack')) {
            CRUD::denyAccess('update');
        }
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     *
     * @return void
     */
    protected function setupListOperation()
    {
        // Habilitar tabla responsiva
        CRUD::enableResponsiveTable();

        CRUD::addClause('with', ['responsibilityArea', 'requestingUser', 'details', 'purchaseOrders', 'marketRates']);

        // Filtrar solicitudes según el rol del usuario
        $user = backpack_user();
        if ($user) {
            // Roles que pueden ver todas las solicitudes (administradores, apoderado y representante legal)
            $adminRoles = ['role_admin_sistema', 'role_admin_institucion', 'role_responsable_compras', 'role_apoderado', 'role_representante_legal'];
            $isAdmin = false;
            foreach ($adminRoles as $role) {
                if ($user->hasRole($role, 'backpack')) {
                    $isAdmin = true;
                    break;
                }
            }

            if (! $isAdmin) {
                // Solicitudes donde el usuario es solicitante nominal o quien registró la solicitud (created_by).
                CRUD::addClause(function ($query) use ($user) {
                    $query->where(function ($q) use ($user) {
                        $q->where('requesting_user_id', $user->id);
                        if (Schema::hasColumn('purchase_requests', 'created_by')) {
                            $q->orWhere('created_by', $user->id);
                        }
                    });
                });
            }
        }

        CRUD::column('request_number')->label('Número de Solicitud');
        CRUD::column('request_date')->label('Fecha');
        CRUD::column('responsibilityArea.name')->label('Área');
        CRUD::column('requestingUser.name')->label('Solicitante');
        CRUD::column('status')->label('Estado');
        CRUD::addColumn([
            'name' => 'purchase_type',
            'label' => 'Tipo de Compra',
            'type' => 'closure',
            'function' => function ($entry) {
                $type = $entry->purchase_type ?? 'normal';
                $badges = [
                    'normal' => '<span class="badge bg-secondary">Normal</span>',
                    'rapida' => '<span class="badge bg-success">Rápida</span>',
                    'directa' => '<span class="badge bg-info">Directa</span>',
                    'internet' => '<span class="badge bg-primary">Por internet</span>',
                ];

                return $badges[$type] ?? $badges['normal'];
            },
            'escaped' => false,
        ]);
        CRUD::column('priority')->label('Prioridad');
        CRUD::column('total_amount')->label('Monto Total')->type('number')->decimals(2)->prefix('$');

        // Agregar columna personalizada para mostrar cantidad de productos
        CRUD::column('details_count')->label('Productos')->type('custom_html')
            ->value(function ($entry) {
                $count = $entry->details->count();

                return '<span class="badge bg-info">'.$count.' productos</span>';
            });

        // Agregar columna personalizada para mostrar cantidad de cotizaciones
        CRUD::column('quotations_count')->label('Cotizaciones')->type('custom_html')
            ->value(function ($entry) {
                // Evitar consultas por fila: marketRates ya viene eager-loaded.
                $quotationsCount = $entry->marketRates->count();

                if ($quotationsCount > 0) {
                    return '<span class="badge bg-success">'.$quotationsCount.' cotizaciones</span>';
                } else {
                    return '<span class="badge bg-warning">Sin cotizaciones</span>';
                }
            });

        // Remover botón de edición por defecto y usar el personalizado
        CRUD::removeButton('update');

        // Ocultar botones de crear, editar y eliminar para roles sin edición manual de solicitudes.
        $rolesWithoutManualPurchaseRequestCrud = $user && (
            $user->hasRole('role_apoderado', 'backpack')
            || $user->hasRole('role_representante_legal', 'backpack')
            || $user->hasRole('role_responsable_compras', 'backpack')
        );

        if ($rolesWithoutManualPurchaseRequestCrud) {
            CRUD::removeButton('create');
            CRUD::removeButton('delete');
        } else {
            CRUD::addButton('line', 'edit_purchase_request', 'view', 'crud::buttons.edit_purchase_request', 'beginning');
        }

        if ($user && (
            $rolesWithoutManualPurchaseRequestCrud
            || $user->hasRole('role_admin_institucion', 'backpack')
        )) {
            CRUD::removeButton('delete');
        }

        // Botón para ver orden de compra (solo si existe y para usuarios que no sean role_responsable_area)
        if (! $user || ! $user->hasResponsableAreaOrInstituteAuthorityRole()) {
            CRUD::addButton('line', 'view_purchase_order', 'view', 'crud::buttons.view_purchase_order', 'end');
        }

        // Filtro personalizado para solicitudes pendientes
        // Solo aplicar si el parámetro está explícitamente en la URL actual
        // No aplicar si viene de una restauración automática de Backpack desde localStorage
        // Backpack agrega 'persistent-table=true' cuando restaura desde localStorage
        $hasPendientes = request()->query('pendientes') == '1';
        $hasEnProceso = request()->query('en_proceso') == '1';
        $hasAprobadasPorSuperior = request()->query('aprobadas_por_superior') == '1';
        $hasPendienteSeleccionCotizacion = request()->query('pendiente_seleccion_cotizacion') == '1';
        $isPersistentRestore = request()->query('persistent-table') == 'true';

        // Solo aplicar el filtro si:
        // 1. El parámetro pendientes está presente
        // 2. NO es una restauración automática desde localStorage (sin persistent-table)
        // Esto asegura que cuando el usuario accede desde el menú, se muestre todo
        if ($hasPendientes && ! $isPersistentRestore) {
            CRUD::addClause('where', 'status', 'Pendiente');
        }

        if ($hasEnProceso && ! $isPersistentRestore) {
            CRUD::addClause('where', 'status', 'En Proceso');
        }

        // Solicitudes aprobadas por nivel superior (desde aviso del dashboard de compras)
        if ($hasAprobadasPorSuperior && ! $isPersistentRestore && $user && $user->effectivelyHasResponsableComprasRole()) {
            CRUD::addClause(function ($query) use ($user) {
                $ids = \App\Models\PurchaseRequest::queryApprovedBySuperiorWithoutPurchaseOrder($user->id)->pluck('id');
                if ($ids->isEmpty()) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereIn($query->getModel()->getQualifiedKeyName(), $ids);
                }
            });
        }

        // Desde aviso del dashboard de compras: ≥3 cotizaciones y falta elegir cotización para continuar
        $canUseComprasWorkQueueFilters = $user && (
            $user->effectivelyHasResponsableComprasRole()
            || $user->hasRole('role_admin_institucion', 'backpack')
        );

        if ($hasPendienteSeleccionCotizacion && ! $isPersistentRestore && $canUseComprasWorkQueueFilters) {
            $ids = \App\Models\PurchaseRequest::purchaseRequestsNeedingQuotationAssignment()->pluck('id');
            CRUD::addClause(function ($query) use ($ids) {
                if ($ids->isEmpty()) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereIn($query->getModel()->getQualifiedKeyName(), $ids);
                }
            });
        }

        // Desde dashboard: filtrar por tipo de compra (normal / directa / rápida / internet)
        $compraTipo = request()->query('compra_tipo');
        if ($compraTipo && ! $isPersistentRestore && in_array($compraTipo, ['normal', 'directa', 'rapida', 'internet'], true)) {
            if ($compraTipo === 'normal') {
                CRUD::addClause(function ($query) {
                    $query->where(function ($q) {
                        $q->where('purchase_type', 'normal')->orWhereNull('purchase_type');
                    });
                });
            } else {
                CRUD::addClause('where', 'purchase_type', $compraTipo);
            }
        }
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     *
     * @return void
     */
    protected function setupCreateOperation()
    {
        $user = backpack_user();
        if (! $user || ! $user->hasPermissionTo('solicitud.crear', 'backpack')) {
            abort(403, 'No tienes permiso para crear solicitudes de compra.');
        }

        CRUD::setOperationSetting('defaultSaveAction', 'save_and_preview');

        // Verificar si viene de una solicitud general
        $convertedFrom = request()->get('converted_from');
        $generalRequest = null;
        $existingProducts = [];

        if ($convertedFrom) {
            // Cargar la solicitud general con detalles, productos (incluyendo stockLevels) y entregas
            $generalRequest = \App\Models\GeneralRequest::with(['details.product.stockLevels', 'deliveries.details'])->find($convertedFrom);

            // Cargar productos de la solicitud general para pre-cargarlos en el formulario
            // Solo mostrar productos con cantidades faltantes (no totalmente entregados)
            // Los precios se establecen en 0, ya que el sector de compras los asignará después
            if ($generalRequest && $generalRequest->details) {
                foreach ($generalRequest->details as $detail) {
                    if ($detail->product) {
                        // Calcular cantidad entregada
                        $deliveredQuantity = 0;
                        if ($generalRequest->deliveries) {
                            foreach ($generalRequest->deliveries as $delivery) {
                                $deliveryDetail = $delivery->details->where('product_id', $detail->product_id)->first();
                                if ($deliveryDetail) {
                                    $deliveredQuantity += $deliveryDetail->delivered_quantity ?? 0;
                                }
                            }
                        }

                        // Calcular cantidad faltante
                        $requestedQuantity = $detail->requested_quantity ?? 0;
                        $pendingQuantity = max(0, $requestedQuantity - $deliveredQuantity);

                        // Solo incluir productos con cantidad faltante > 0
                        if ($pendingQuantity > 0) {
                            // Calcular stock total del producto
                            $stockTotal = $detail->product->stockLevels->sum('quantity') ?? 0;

                            $existingProducts[] = [
                                'product_id' => $detail->product_id,
                                'product_name' => $detail->product->name ?? 'Producto no encontrado',
                                'name' => $detail->product->name ?? 'Producto no encontrado',
                                'unit' => $detail->product->unit_measurement ?? 'unidad',
                                'description' => $detail->product->description ?? '',
                                'quantity' => $pendingQuantity, // Usar cantidad faltante en lugar de solicitada
                                'price' => 0, // Precio inicial en 0, el sector de compras lo asignará
                                'specifications' => $detail->specifications ?? '',
                                'product_description' => $detail->product_description ?? '',
                                'minimum_stock' => $detail->product->minimum_stock ?? 0,
                                'stock_total' => $stockTotal,
                            ];
                        }
                    }
                }
            }
        }

        // Setup common fields
        $this->setupCreateFields();

        // Si hay productos existentes de la solicitud general, reemplazar el campo de productos
        if (! empty($existingProducts)) {
            CRUD::modifyField('products_selection', [
                'value' => $this->getProductsSelectionHtml($existingProducts, $generalRequest->area_id ?? null),
            ]);
        }

        // Override defaults if converting from general request
        if ($generalRequest) {
            $user = backpack_user();
            CRUD::modifyField('responsibility_area_id', ['default' => $generalRequest->area_id]);
            // El requesting_user_id debe ser el usuario logueado (responsable de área), no el creador de la solicitud general
            CRUD::modifyField('requesting_user_id', ['default' => $user ? $user->id : $generalRequest->created_by]);
            if ($user) {
                CRUD::modifyField('requesting_user_display', ['default' => $user->name, 'value' => $user->name]);
            }
            CRUD::modifyField('priority', ['default' => $generalRequest->priority]);
            CRUD::modifyField('justification', ['default' => $generalRequest->description]);

            // Asegurar que los valores por defecto se establezcan correctamente
            if ($generalRequest->area_id) {
                CRUD::modifyField('responsibility_area_id', ['value' => $generalRequest->area_id]);
            }
            // El requesting_user_id debe ser el usuario logueado
            if ($user) {
                CRUD::modifyField('requesting_user_id', ['value' => $user->id]);
                CRUD::modifyField('requesting_user_display', ['value' => $user->name]);
            }
        }

        // Campo oculto para la conversión
        if ($convertedFrom) {
            // Establecer el valor del campo oculto que ya está definido en setupCreateFields
            CRUD::modifyField('converted_from_general_request_id', ['value' => $convertedFrom]);

            // Mostrar información de la solicitud general
            $generalRequestInfo = '';
            if ($convertedFrom) {
                $generalRequest = \App\Models\GeneralRequest::with(['deliveries.details'])->find($convertedFrom);
                if ($generalRequest) {
                    $productsCount = count($existingProducts);
                    $hasDeliveries = $generalRequest->deliveries && $generalRequest->deliveries->isNotEmpty();
                    $deliveryNote = $hasDeliveries ? ' Solo se muestran los productos con cantidades faltantes (no totalmente entregados).' : '';
                    $generalRequestInfo = '<div class="alert alert-info">
                        <h5><i class="la la-info-circle"></i> Conversión desde Solicitud General</h5>
                        <p><strong>Número:</strong> '.($generalRequest->number ?? 'N/A').'</p>
                        <p><strong>Título:</strong> '.($generalRequest->title ?? 'N/A').'</p>
                        <p><strong>Descripción:</strong> '.($generalRequest->description ?? 'N/A').'</p>
                        <p><strong>Productos:</strong> '.$productsCount.' producto(s) con cantidades faltantes cargado(s) desde la solicitud general.'.$deliveryNote.' Puede editarlos o eliminarlos antes de guardar.</p>
                    </div>';
                }
            }

            CRUD::field('general_request_info')->label('Información de Solicitud General')->type('custom_html')
                ->value($generalRequestInfo.'
                <script>
                document.addEventListener("DOMContentLoaded", function() {
                    // Asegurar que el campo oculto tenga el valor correcto
                    var hiddenField = document.querySelector("input[name=\'converted_from_general_request_id\']");
                    if (hiddenField) {
                        hiddenField.value = "'.$convertedFrom.'";
                    } else {
                        // Si no existe, crearlo
                        hiddenField = document.createElement("input");
                        hiddenField.type = "hidden";
                        hiddenField.name = "converted_from_general_request_id";
                        hiddenField.value = "'.$convertedFrom.'";
                        document.querySelector("form").appendChild(hiddenField);
                    }
                });
                </script>');
        }
    }

    /**
     * Define what happens when the Update operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     *
     * @return void
     */
    protected function setupUpdateOperation()
    {
        // Verificar permisos y que el usuario solo pueda editar sus propias solicitudes
        $user = backpack_user();
        if (! $user) {
            abort(403, 'No tienes permiso para editar solicitudes de compra.');
        }

        $entry = $this->crud->getCurrentEntry();
        if (! $entry) {
            abort(404, 'Solicitud de compra no encontrada.');
        }

        $entry->loadMissing(['purchaseRequestEvents', 'details', 'purchaseOrders', 'marketRates']);
        if ($entry->isFrozenPendingSuperiorApproval()) {
            abort(403, 'No se puede modificar la solicitud mientras está pendiente la aprobación de nivel superior. Tras un rechazo parcial del nivel superior, podrá ajustar cotizaciones y reabrir el circuito desde «Acciones de nivel superior».');
        }

        // Verificar roles de administrador
        $isAdminSistema = $user->hasRole('role_admin_sistema', 'backpack');
        $isAdminInstitucion = $user->hasRole('role_admin_institucion', 'backpack');
        $isResponsableCompras = $user->effectivelyHasResponsableComprasRole();

        $isActingCreator = $entry->isActingAsCreatingUser((int) $user->id);

        // Verificar si el usuario es responsable de área o autoridad del instituto
        $isResponsableArea = $user && $user->hasResponsableAreaOrInstituteAuthorityRole();

        $entry->loadMissing(['marketRates', 'details']);
        $areaProductsLockedByQuotationSelection = $isResponsableArea && $entry->hasQuotationSelectionResolved();
        $productsLockedForComprasOrAdminInstitucion = $this->userCannotModifyPurchaseRequestProductDetails($user);

        // Si es administrador del sistema o responsable de compras, puede editar cualquier solicitud
        if ($isAdminSistema || $isResponsableCompras) {
            // Pueden editar cualquier solicitud
        } elseif ($isAdminInstitucion) {
            // El administrador del instituto solo puede editar solicitudes que él registró en el sistema
            if (! $isActingCreator) {
                abort(403, 'Solo puedes editar las solicitudes de compra que creaste.');
            }
        } else {
            // Los demás usuarios: solo solicitudes que registraron (created_by / legado requesting_user_id)
            if (! $isActingCreator) {
                abort(403, 'Solo puedes editar las solicitudes de compra que creaste.');
            }

            // Solo puede editar si el estado es "Pendiente"
            if ($entry->status !== 'Pendiente') {
                abort(403, 'Solo puedes editar solicitudes de compra con estado "Pendiente".');
            }
        }

        // Responsable de área / autoridad que no registró esta solicitud (y no es admin global): vista parcial heredada
        if ($isResponsableArea && ! $isActingCreator && ! $isAdminSistema && ! $isResponsableCompras) {
            // Campos de solo lectura (información) - se muestran como inputs bloqueados con readonly
            CRUD::field('request_number')->label('Número de Solicitud')
                ->type('text')
                ->default($entry->request_number)
                ->attributes(['readonly' => 'readonly'])
                ->wrapper(['class' => 'form-group col-sm-12 col-md-4 mb-3']);

            CRUD::field('request_date')->label('Fecha de Solicitud')
                ->type('date')
                ->default($entry->request_date ? $entry->request_date->format('Y-m-d') : '')
                ->attributes(['readonly' => 'readonly'])
                ->wrapper(['class' => 'form-group col-sm-12 col-md-4 mb-3']);

            CRUD::field('requesting_user_display')->label('Usuario Solicitante')
                ->type('text')
                ->default($entry->requestingUser ? $entry->requestingUser->name : '')
                ->attributes(['readonly' => 'readonly'])
                ->wrapper(['class' => 'form-group col-sm-12 col-md-4 mb-3']);
            CRUD::field('requesting_user_id')->type('hidden')->value($entry->requesting_user_id);

            // Para el área, mostrar el nombre pero mantener el ID
            CRUD::field('responsibility_area_display')->label('Área de Responsabilidad')
                ->type('text')
                ->default($entry->responsibilityArea ? $entry->responsibilityArea->name : '')
                ->attributes(['readonly' => 'readonly'])
                ->wrapper(['class' => 'form-group col-sm-12 col-md-4 mb-3']);
            CRUD::field('responsibility_area_id')->type('hidden')->value($entry->responsibility_area_id);

            // Campos ocultos que deben mantenerse con sus valores actuales
            CRUD::field('status')->type('hidden')->value($entry->status);
            CRUD::field('total_amount')->type('hidden')->value($entry->total_amount);
            CRUD::field('observations')->type('hidden')->value($entry->observations);

            // Campos editables para responsable de área
            CRUD::field('priority')->label('Prioridad')
                ->type('select_from_array')
                ->options([
                    'Baja' => 'Baja',
                    'Media' => 'Media',
                    'Alta' => 'Alta',
                    'Urgente' => 'Urgente',
                ])
                ->default($entry->priority ?? 'Media')
                ->wrapper(['class' => 'form-group col-sm-12 col-md-4 mb-3']);

            CRUD::field('justification')->label('Justificación')->type('textarea')->default($entry->justification)
                ->wrapper(['class' => 'form-group col-sm-12 col-md-6 mb-3']);

            // Campo para seleccionar productos - solo si no está aprobada ni bloqueada (cotización / compras / administradora)
            if ($entry->status !== 'Aprobada' && ! $areaProductsLockedByQuotationSelection && ! $productsLockedForComprasOrAdminInstitucion) {
                CRUD::addField([
                    'name' => 'selected_products',
                    'type' => 'hidden',
                    'value' => '[]',
                ]);
                $entry->load('details.product');
                if ($entry->details && $entry->details->count() > 0) {
                    $existingProducts = $entry->details->map(function ($detail) {
                        return [
                            'product_id' => $detail->product_id,
                            'product_name' => $detail->product ? $detail->product->name : 'N/A',
                            'unit' => $detail->product ? $detail->product->unit_measurement : '',
                            'description' => $detail->product ? $detail->product->description : '',
                            'quantity' => $detail->requested_quantity ?? 0,
                            'price' => $detail->estimated_unit_price ?? 0,
                            'specifications' => $detail->specifications ?? '',
                            'product_description' => $detail->product_description ?? '',
                        ];
                    })->toArray();

                    CRUD::field('products_selection')->label('Productos Solicitados')->type('custom_html')
                        ->value($this->getProductsSelectionHtml($existingProducts, $entry->responsibility_area_id));
                } else {
                    CRUD::field('products_selection')->label('Productos Solicitados')->type('custom_html')
                        ->value($this->getProductsSelectionHtml([], $entry->responsibility_area_id));
                }
            } else {
                $entry->load('details.product');
                CRUD::field('products_selection')->label('Productos Solicitados')->type('custom_html')
                    ->value($this->getProductsReadOnlyHtml($entry));
                if ($productsLockedForComprasOrAdminInstitucion && $entry->status !== 'Aprobada') {
                    CRUD::field('quotation_lock_notice')->label('')->type('custom_html')
                        ->value('<div class="alert alert-info mb-0"><i class="la la-info-circle"></i> El detalle de productos solo puede modificarlo quien registró la solicitud o el administrador del sistema.</div>');
                } elseif ($areaProductsLockedByQuotationSelection && $entry->status !== 'Aprobada') {
                    CRUD::field('quotation_lock_notice')->label('')->type('custom_html')
                        ->value('<div class="alert alert-info mb-0"><i class="la la-info-circle"></i> El sector de compras ya seleccionó cotización(es) en esta solicitud: no puede modificar los productos ni cargar nuevas cotizaciones.</div>');
                }
            }
        } else {
            // Para administradores, usar todos los campos
            $this->setupCreateFields();

            $entry = $this->crud->getCurrentEntry();
            if ($entry) {
                CRUD::modifyField('requesting_user_display', [
                    'value' => optional($entry->requestingUser)->name ?? '',
                ]);
                CRUD::modifyField('requesting_user_id', ['value' => $entry->requesting_user_id]);
                CRUD::modifyField('request_date', [
                    'value' => $entry->request_date ? $entry->request_date->format('Y-m-d') : '',
                ]);
                CRUD::modifyField('request_number', ['value' => $entry->request_number]);
            }

            // Productos editables solo si no está aprobada y el rol puede modificar el detalle
            if ($entry && $entry->status !== 'Aprobada' && ! $productsLockedForComprasOrAdminInstitucion) {
                $entry->load('details.product');

                if ($entry->details && $entry->details->count() > 0) {
                    $existingProducts = $entry->details->map(function ($detail) {
                        return [
                            'product_id' => $detail->product_id,
                            'product_name' => $detail->product ? $detail->product->name : 'N/A',
                            'unit' => $detail->product ? $detail->product->unit_measurement : '',
                            'description' => $detail->product ? $detail->product->description : '',
                            'quantity' => $detail->requested_quantity ?? 0,
                            'price' => $detail->estimated_unit_price ?? 0,
                            'specifications' => $detail->specifications ?? '',
                            'product_description' => $detail->product_description ?? '',
                        ];
                    })->toArray();

                    CRUD::modifyField('products_selection', [
                        'value' => $this->getProductsSelectionHtml($existingProducts),
                    ]);
                }
            } elseif ($entry) {
                $entry->load('details.product');
                CRUD::modifyField('products_selection', [
                    'value' => $this->getProductsReadOnlyHtml($entry),
                ]);
                if ($productsLockedForComprasOrAdminInstitucion && $entry->status !== 'Aprobada') {
                    CRUD::field('products_readonly_notice')->label('')->type('custom_html')
                        ->value('<div class="alert alert-info mb-0"><i class="la la-info-circle"></i> El detalle de productos solo puede modificarlo quien registró la solicitud o el administrador del sistema.</div>');
                }
            }

            // Agregar campos adicionales para actualización
            // Obtener la entrada actual para verificar el monto
            $entry = $this->crud->getCurrentEntry();
            $user = backpack_user();

            // Determinar las opciones de estado disponibles según el usuario y el monto
            $statusOptions = [
                'Pendiente' => 'Pendiente',
                'Rechazada' => 'Rechazada',
                'En Proceso' => 'En Proceso',
                'Completada' => 'Completada',
            ];

            // Verificar si el usuario puede aprobar esta solicitud
            $canApprove = false;
            if ($entry && $user) {
                $canApprove = $entry->canBeApprovedBy($user);
            }

            // Solo agregar la opción "Aprobada" si el usuario puede aprobar
            if ($canApprove) {
                $statusOptions['Aprobada'] = 'Aprobada';
            }

            CRUD::field('status')->label('Estado')
                ->type('select_from_array')
                ->options($statusOptions)
                ->hint($canApprove ? '' : ($entry && $entry->requires_admin_approval ? 'Esta solicitud requiere aprobación del administrador del instituto debido a que supera el límite de autorización.' : ''));

            // Solo mostrar campos de aprobación si el usuario puede aprobar
            if ($canApprove) {
                CRUD::field('approved_by')->label('Aprobado por')
                    ->type('select')
                    ->model('App\Models\User')
                    ->attribute('name');

                CRUD::field('approved_date')->label('Fecha de Aprobación')->type('date');
            }
        }
    }

    private function autofilledFieldWrapper(): array
    {
        return ['class' => 'form-group col-sm-12 col-md-4 mb-3 pr-autofield'];
    }

    private function autofilledFieldAttributes(): array
    {
        return [
            'readonly' => 'readonly',
            'class' => 'form-control pr-autofield-input',
            'tabindex' => '-1',
            'onfocus' => 'this.blur();',
        ];
    }

    private function autofilledFieldsStylesHtml(): string
    {
        return '<style>
            .pr-autofield label {
                color: #6c757d !important;
                font-size: 0.78rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                margin-bottom: 0.25rem;
            }
            .pr-autofield .help-block,
            .pr-autofield small.form-text {
                color: #868e96 !important;
                font-size: 0.72rem;
                font-style: italic;
            }
            .pr-autofield-input,
            .pr-autofield .form-control[readonly] {
                background-color: #e9ecef !important;
                border: 1px dashed #adb5bd !important;
                color: #495057 !important;
                cursor: not-allowed !important;
                box-shadow: none !important;
                opacity: 1;
            }
            .pr-autofield-input:focus,
            .pr-autofield .form-control[readonly]:focus {
                outline: none;
                box-shadow: none !important;
                border-color: #adb5bd !important;
            }
        </style>';
    }

    /**
     * Setup common fields for create and update operations
     */
    private function setupCreateFields()
    {
        $col3 = ['class' => 'form-group col-sm-12 col-md-4 mb-3'];
        $colHalf = ['class' => 'form-group col-sm-12 col-md-6 mb-3'];
        $colFull = ['class' => 'form-group col-sm-12 mb-3'];

        CRUD::addField([
            'name' => 'autofilled_fields_styles',
            'type' => 'custom_html',
            'value' => $this->autofilledFieldsStylesHtml(),
            'wrapper' => ['class' => 'form-group col-12 mb-2'],
        ]);

        $user = backpack_user();
        $requestingUserId = $user ? (int) $user->id : (int) (auth()->id() ?? 1);
        $requestingUserName = $user?->name ?? (\App\Models\User::find($requestingUserId)->name ?? '');

        CRUD::field('request_number')->label('Número de Solicitud')
            ->hint('Asignado automáticamente')
            ->default(\App\Models\PurchaseRequest::generateNextNumber())
            ->attributes($this->autofilledFieldAttributes())
            ->wrapper($this->autofilledFieldWrapper());

        CRUD::field('request_date')->label('Fecha de Solicitud')
            ->hint('Fecha actual del sistema')
            ->type('date')
            ->default(now()->format('Y-m-d'))
            ->attributes($this->autofilledFieldAttributes())
            ->wrapper($this->autofilledFieldWrapper());

        CRUD::field('requesting_user_display')->label('Usuario Solicitante')
            ->hint('Usuario que registra la solicitud')
            ->type('text')
            ->default($requestingUserName)
            ->attributes($this->autofilledFieldAttributes())
            ->wrapper($this->autofilledFieldWrapper());

        CRUD::field('requesting_user_id')->type('hidden')->default($requestingUserId);

        CRUD::field('responsibility_area_id')->label('Área de Responsabilidad')
            ->type('select')
            ->model('App\Models\ResponsibilityArea')
            ->attribute('name')
            ->validationRules('required|exists:responsibility_areas,id')
            ->wrapper($col3);

        CRUD::field('priority')->label('Prioridad')
            ->type('select_from_array')
            ->options([
                'Baja' => 'Baja',
                'Media' => 'Media',
                'Alta' => 'Alta',
                'Urgente' => 'Urgente',
            ])
            ->default('Media')
            ->wrapper($col3);

        CRUD::addField([
            'name' => 'purchase_type',
            'label' => 'Tipo de compra',
            'type' => 'select_from_array',
            'options' => [
                'normal' => 'Normal',
                'internet' => 'Por internet (Mercado Libre, etc.)',
            ],
            'default' => 'normal',
            'wrapper' => $col3,
        ]);

        CRUD::field('justification')->label('Justificación')->type('textarea')
            ->wrapper($colHalf);
        CRUD::field('observations')->label('Observaciones')->type('textarea')
            ->wrapper($colHalf);

        // Campos ocultos con valores por defecto
        CRUD::field('status')->type('hidden')->default('Pendiente');
        CRUD::field('total_amount')->type('hidden')->default(0);

        // Campo oculto para conversión desde solicitud general (se establecerá dinámicamente)
        CRUD::field('converted_from_general_request_id')->type('hidden')->attributes(['name' => 'converted_from_general_request_id']);

        // Mismo patrón que GeneralRequest: el input debe existir en el formulario para que el POST siempre incluya la clave
        CRUD::addField([
            'name' => 'selected_products',
            'type' => 'hidden',
            'value' => '[]',
        ]);

        // Campo para seleccionar productos
        CRUD::field('products_selection')->label('Productos Solicitados')->type('custom_html')
            ->wrapper($colFull)
            ->value('
            <div id="products-container">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="product-select" class="form-label">Seleccionar Producto</label>
                        <select id="product-select" class="form-control">
                            <option value="">Seleccionar un producto...</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="product-quantity" class="form-label">Cantidad</label>
                        <input type="number" id="product-quantity" class="form-control" min="1" value="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <button type="button" id="add-product-btn" class="btn btn-primary btn-block">
                            <i class="la la-plus"></i> Agregar
                        </button>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-12">
                        <button type="button" id="add-new-product-btn" class="btn btn-success">
                            <i class="la la-plus-circle"></i> Agregar Nuevo Producto
                        </button>
                    </div>
                </div>
                <div id="selected-products-list"></div>
            </div>
            
            <script>
            (function purchaseRequestProductsInitCreate() {
                function init() {
                // Cargar productos existentes
                loadProducts();
                
                // Event listeners
                document.getElementById("add-product-btn").addEventListener("click", addProduct);
                document.getElementById("add-new-product-btn").addEventListener("click", showNewProductModal);
                
                const selectedListEl = document.getElementById("selected-products-list");
                if (selectedListEl) {
                    selectedListEl.addEventListener("click", function(e) {
                        const btn = e.target.closest("button.remove-product");
                        if (!btn) return;
                        e.preventDefault();
                        if (!confirm("¿Eliminar esta línea de productos de la solicitud?")) return;
                        const row = btn.closest(".selected-product-item");
                        if (row) {
                            row.remove();
                            updateHiddenFields();
                        }
                    });
                }
                
                // Función para cargar productos
                function loadProducts() {
                    fetch("'.backpack_url('api/productos').'")
                        .then(response => response.json())
                        .then(data => {
                            const select = document.getElementById("product-select");
                            select.innerHTML = \'<option value="">Seleccionar un producto...</option>\';
                            data.forEach(product => {
                                const option = document.createElement("option");
                                option.value = product.id;
                                option.textContent = product.name + " (" + product.unit_measurement + ")";
                                option.setAttribute("data-unit", product.unit_measurement);
                                option.setAttribute("data-description", product.description || "");
                                select.appendChild(option);
                            });
                            
                            // Después de cargar productos, verificar si hay productos pre-seleccionados en la URL
                            loadPreSelectedProducts(data);
                        })
                        .catch(error => console.error("Error loading products:", error));
                }
                
                // Función para cargar productos pre-seleccionados desde la URL
                function loadPreSelectedProducts(productsData) {
                    const urlParams = new URLSearchParams(window.location.search);
                    const selectedProductsParam = urlParams.get(\'selected_products\');
                    
                    if (selectedProductsParam) {
                        try {
                            const selectedProducts = JSON.parse(decodeURIComponent(selectedProductsParam));
                            
                            if (Array.isArray(selectedProducts) && selectedProducts.length > 0) {
                                // Crear un mapa de productos por ID para búsqueda rápida
                                const productsMap = {};
                                productsData.forEach(product => {
                                    productsMap[product.id] = product;
                                });
                                
                                // Agregar cada producto pre-seleccionado a la lista
                                selectedProducts.forEach(productData => {
                                    const product = productsMap[productData.product_id];
                                    if (product) {
                                        const quantity = productData.quantity || 1;
                                        const price = productData.price || productData.unit_price || 0;
                                        const specs = productData.specifications || productData.product_description || "";
                                        
                                        addProductToList(
                                            product.id,
                                            product.name + " (" + product.unit_measurement + ")",
                                            product.unit_measurement,
                                            product.description || "",
                                            quantity,
                                            price,
                                            specs
                                        );
                                    }
                                });
                            }
                        } catch (error) {
                            console.error("Error parsing pre-selected products:", error);
                        }
                    }
                }
                
                // Función para agregar producto
                function addProduct() {
                    const select = document.getElementById("product-select");
                    const quantity = document.getElementById("product-quantity");
                    
                    if (!select.value) {
                        alert("Por favor seleccione un producto");
                        return;
                    }
                    
                    if (!quantity.value || quantity.value < 1) {
                        alert("Por favor ingrese una cantidad válida");
                        return;
                    }
                    
                    const selectedOption = select.options[select.selectedIndex];
                    const productId = select.value;
                    const productName = selectedOption.textContent;
                    const unit = selectedOption.getAttribute("data-unit");
                    const description = selectedOption.getAttribute("data-description");
                    
                    addProductToList(productId, productName, unit, description, quantity.value, 0, "");
                    
                    // Limpiar campos
                    select.value = "";
                    quantity.value = 1;
                }
                
                // Función para agregar producto a la lista
                function getProductsForm() {
                    const c = document.getElementById("products-container");
                    if (c) {
                        const f = c.closest("form");
                        if (f) return f;
                    }
                    const opUpdate = document.querySelector("[bp-section=crud-operation-update] form");
                    if (opUpdate) return opUpdate;
                    const opCreate = document.querySelector("[bp-section=crud-operation-create] form");
                    if (opCreate) return opCreate;
                    const mainForm = document.querySelector("main form");
                    if (mainForm) return mainForm;
                    return document.querySelector("form[method=post]");
                }
                
                function escapeHtml(s) {
                    if (s == null || s === undefined) return "";
                    return String(s)
                        .replace(/&/g, "&amp;")
                        .replace(/</g, "&lt;")
                        .replace(/>/g, "&gt;")
                        .replace(/"/g, "&quot;");
                }
                
                function addProductToList(productId, productName, unit, description, quantity, price = 0, specifications = "") {
                    const container = document.getElementById("selected-products-list");
                    const productDiv = document.createElement("div");
                    productDiv.className = "selected-product-item border p-3 mb-2";
                    productDiv.setAttribute("data-product-id", productId);
                    if (typeof productId === "string" && productId.indexOf("new_") === 0) {
                        productDiv.setAttribute("data-new-name", productName || "");
                        productDiv.setAttribute("data-new-unit", unit || "unidad");
                        productDiv.setAttribute("data-new-description", description || "");
                    }
                    
                    const safeName = escapeHtml(productName);
                    const safeDesc = escapeHtml(description);
                    const safeSpecs = escapeHtml(specifications);
                    
                    productDiv.innerHTML = `
                        <div class="row">
                            <div class="col-md-4">
                                <strong>${safeName}</strong>
                                ${description ? `<br><small class="text-muted">${safeDesc}</small>` : ""}
                            </div>
                            <div class="col-md-2">
                                <label>Cantidad:</label>
                                <input type="number" class="form-control product-quantity" value="${quantity}" min="1">
                            </div>
                            <div class="col-md-2">
                                <label>Precio Unit. Est.:</label>
                                <input type="number" class="form-control product-price" step="0.01" min="0" value="${price}">
                            </div>
                            <div class="col-md-3">
                                <label>Descripción / Especificaciones:</label>
                                <textarea class="form-control product-specs" rows="2" placeholder="Describa el producto o indique especificaciones...">${safeSpecs}</textarea>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-danger btn-sm remove-product">
                                    <i class="la la-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    
                    container.appendChild(productDiv);
                    
                    // Event listeners para actualizar totales
                    productDiv.querySelector(".product-quantity").addEventListener("input", updateTotals);
                    productDiv.querySelector(".product-price").addEventListener("input", updateTotals);
                    productDiv.querySelector(".product-specs").addEventListener("input", updateTotals);
                    
                    updateHiddenFields();
                }
                
                // Función para actualizar campos ocultos
                function updateHiddenFields() {
                    const form = getProductsForm();
                    if (!form) {
                        console.error("No se encontró el formulario de solicitud de compra (products-container).");
                        return;
                    }
                    const pc = document.getElementById("products-container");
                    const products = [];
                    (pc ? pc.querySelectorAll(".selected-product-item") : []).forEach(item => {
                        const productId = item.getAttribute("data-product-id");
                        const quantity = item.querySelector(".product-quantity").value;
                        const price = item.querySelector(".product-price").value;
                        const specs = item.querySelector(".product-specs").value;
                        
                        const row = {
                            product_id: productId,
                            quantity: quantity,
                            price: price,
                            specifications: specs
                        };
                        if (typeof productId === "string" && productId.indexOf("new_") === 0) {
                            row.name = item.getAttribute("data-new-name") || "";
                            row.unit = item.getAttribute("data-new-unit") || "unidad";
                            row.description = item.getAttribute("data-new-description") || "";
                            row.product_description = specs || item.getAttribute("data-new-description") || "";
                        }
                        products.push(row);
                    });
                    
                    const json = JSON.stringify(products);
                    const hiddens = form.querySelectorAll("input[name=\'selected_products\']");
                    if (hiddens.length === 0) {
                        const h = document.createElement("input");
                        h.type = "hidden";
                        h.name = "selected_products";
                        h.value = json;
                        form.appendChild(h);
                    } else {
                        const arr = Array.from(hiddens);
                        arr.forEach(el => { el.value = json; });
                        arr.slice(1).forEach(el => el.remove());
                    }
                }
                
                // Función para actualizar totales
                function updateTotals() {
                    updateHiddenFields();
                }
                
                // Función para mostrar modal de nuevo producto
                function showNewProductModal() {
                    const productName = prompt("Nombre del nuevo producto:");
                    if (!productName) return;
                    
                    const productUnit = prompt("Unidad del producto (ej: kg, litros, unidades):");
                    if (!productUnit) return;
                    
                    const productDescription = prompt("Descripción / Especificaciones (opcional):") || "";
                    
                    const tempId = "new_" + Date.now();
                    addProductToList(tempId, productName, productUnit, productDescription, 1, 0, productDescription);
                }

                const __prForm = getProductsForm();
                if (__prForm && !__prForm.dataset.prProductsSyncBound) {
                    __prForm.dataset.prProductsSyncBound = "1";
                    __prForm.addEventListener("submit", function() { updateHiddenFields(); }, true);
                }
                }
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", init, { once: true });
                } else {
                    init();
                }
            })();
            </script>
            ');
    }

    /**
     * Get products filtered by responsibility area
     * Muestra solo productos de las categorías relacionadas con el área
     */
    public function getProductosByArea()
    {
        $areaId = request()->get('area_id');
        $query = \App\Models\Product::with(['category', 'stockLevels']);

        if ($areaId) {
            // Obtener el área
            $area = \App\Models\ResponsibilityArea::find($areaId);

            if ($area) {
                // Mapeo de áreas a categorías permitidas
                $areaCategoryMap = [
                    'Informática' => ['Equipos Informáticos', 'Software'],
                    'Salud' => ['Material Médico', 'Reactivos'],
                    'Insumos de Salud' => ['Material Médico', 'Reactivos'],
                    'Mantenimiento' => ['Herramientas', 'Repuestos', 'Limpieza'],
                    'Insumos Generales' => ['Material de Oficina', 'Limpieza', 'Insumos Generales'],
                ];

                // Obtener las categorías permitidas para esta área
                $areaName = $area->name;
                if (isset($areaCategoryMap[$areaName])) {
                    $allowedCategoryNames = $areaCategoryMap[$areaName];

                    // Obtener los IDs de las categorías permitidas
                    $categoryIds = \App\Models\Category::whereIn('name', $allowedCategoryNames)
                        ->pluck('id');

                    if ($categoryIds->isNotEmpty()) {
                        // Filtrar productos por las categorías permitidas
                        $query->whereIn('category_id', $categoryIds);
                    } else {
                        // Si no hay categorías relacionadas, no mostrar ningún producto
                        $query->where('id', 0);
                    }
                } else {
                    // Si el área no está en el mapeo, no mostrar ningún producto
                    $query->where('id', 0);
                }
            } else {
                // Si no existe el área, no mostrar ningún producto
                $query->where('id', 0);
            }
        }

        // Obtener productos filtrados
        $productos = $query->get()
            ->map(function ($producto) {
                $stockTotal = $producto->stockLevels->sum('quantity');

                return [
                    'id' => $producto->id,
                    'name' => $producto->name,
                    'description' => $producto->description,
                    'category_name' => $producto->category->name ?? 'Sin categoría',
                    'unit_measurement' => $producto->unit_measurement,
                    'stock_total' => $stockTotal,
                    'minimum_stock' => $producto->minimum_stock,
                ];
            })
            ->values();

        return response()->json($productos);
    }

    /**
     * Generate HTML for products selection with existing products
     */
    private function getProductsSelectionHtml($existingProducts = [], $areaId = null)
    {
        $existingProductsJson = json_encode($existingProducts);

        return '
        <div id="products-container">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="product-select" class="form-label">Seleccionar Producto</label>
                    <select id="product-select" class="form-control">
                        <option value="">Seleccionar un producto...</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="product-quantity" class="form-label">Cantidad</label>
                    <input type="number" id="product-quantity" class="form-control" min="1" value="1">
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <button type="button" id="add-product-btn" class="btn btn-primary btn-block">
                        <i class="la la-plus"></i> Agregar
                    </button>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-12">
                    <button type="button" id="add-new-product-btn" class="btn btn-success">
                        <i class="la la-plus-circle"></i> Agregar Nuevo Producto
                    </button>
                </div>
            </div>
            <div id="selected-products-list"></div>
        </div>
        
        <script>
        (function purchaseRequestProductsInitEdit() {
            function init() {
            const existingProducts = '.$existingProductsJson.';
            
            // Cargar productos existentes
            loadProducts();
            
            // Cargar productos existentes en la lista
            if (existingProducts && existingProducts.length > 0) {
                existingProducts.forEach(product => {
                    const specs = product.specifications || product.product_description || "";
                    addProductToList(
                        product.product_id, 
                        product.product_name + " (" + product.unit + ")", 
                        product.unit, 
                        product.description, 
                        product.quantity,
                        product.price,
                        specs,
                        product.minimum_stock || 0,
                        product.stock_total || 0
                    );
                });
            } else {
                updateHiddenFields();
            }
            
            // Event listeners
            document.getElementById("add-product-btn").addEventListener("click", addProduct);
            document.getElementById("add-new-product-btn").addEventListener("click", showNewProductModal);
            
            const selectedListEl = document.getElementById("selected-products-list");
            if (selectedListEl) {
                selectedListEl.addEventListener("click", function(e) {
                    const btn = e.target.closest("button.remove-product");
                    if (!btn) return;
                    e.preventDefault();
                    if (!confirm("¿Eliminar esta línea de productos de la solicitud?")) return;
                    const row = btn.closest(".selected-product-item");
                    if (row) {
                        row.remove();
                        updateHiddenFields();
                    }
                });
            }
            
            // Función para cargar productos
            function loadProducts() {
                const areaId = '.($areaId ? $areaId : 'null').';
                const url = areaId ? "'.backpack_url('api/productos-por-area').'?area_id=" + areaId : "'.backpack_url('api/productos').'";
                
                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        const select = document.getElementById("product-select");
                        select.innerHTML = \'<option value="">Seleccionar un producto...</option>\';
                        data.forEach(product => {
                            const option = document.createElement("option");
                            option.value = product.id;
                            option.textContent = product.name + " (" + product.unit_measurement + ")";
                            option.setAttribute("data-unit", product.unit_measurement);
                            option.setAttribute("data-description", product.description || "");
                            option.setAttribute("data-minimum-stock", product.minimum_stock || 0);
                            option.setAttribute("data-stock-total", product.stock_total || 0);
                            select.appendChild(option);
                        });
                    })
                    .catch(error => console.error("Error loading products:", error));
            }
            
            // Función para agregar producto
            function addProduct() {
                const select = document.getElementById("product-select");
                const quantity = document.getElementById("product-quantity");
                
                if (!select.value) {
                    alert("Por favor seleccione un producto");
                    return;
                }
                
                if (!quantity.value || quantity.value < 1) {
                    alert("Por favor ingrese una cantidad válida");
                    return;
                }
                
                const selectedOption = select.options[select.selectedIndex];
                const productId = select.value;
                const productName = selectedOption.textContent;
                const unit = selectedOption.getAttribute("data-unit");
                const description = selectedOption.getAttribute("data-description");
                const minimumStock = parseFloat(selectedOption.getAttribute("data-minimum-stock")) || 0;
                const stockTotal = parseFloat(selectedOption.getAttribute("data-stock-total")) || 0;
                
                addProductToList(productId, productName, unit, description, quantity.value, 0, "", minimumStock, stockTotal);
                
                // Limpiar campos
                select.value = "";
                quantity.value = 1;
            }
            
            function getProductsForm() {
                const c = document.getElementById("products-container");
                if (c) {
                    const f = c.closest("form");
                    if (f) return f;
                }
                const opUpdate = document.querySelector("[bp-section=crud-operation-update] form");
                if (opUpdate) return opUpdate;
                const opCreate = document.querySelector("[bp-section=crud-operation-create] form");
                if (opCreate) return opCreate;
                const mainForm = document.querySelector("main form");
                if (mainForm) return mainForm;
                return document.querySelector("form[method=post]");
            }
            
            function escapeHtml(s) {
                if (s == null || s === undefined) return "";
                return String(s)
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;");
            }
            
            // Función para agregar producto a la lista
            function addProductToList(productId, productName, unit, description, quantity, price = 0, specifications = "", minimumStock = 0, stockTotal = 0) {
                const container = document.getElementById("selected-products-list");
                const productDiv = document.createElement("div");
                productDiv.className = "selected-product-item border p-3 mb-2";
                productDiv.setAttribute("data-product-id", productId);
                if (typeof productId === "string" && productId.indexOf("new_") === 0) {
                    productDiv.setAttribute("data-new-name", productName || "");
                    productDiv.setAttribute("data-new-unit", unit || "unidad");
                    productDiv.setAttribute("data-new-description", description || "");
                }
                
                // Calcular cantidad sugerida (cantidad solicitada + stock mínimo)
                const suggestedQuantity = parseFloat(quantity) + (minimumStock > 0 ? minimumStock : 0);
                const showStockMinSuggestion = minimumStock > 0;
                
                const safeName = escapeHtml(productName);
                const safeDesc = escapeHtml(description);
                const safeSpecs = escapeHtml(specifications);
                const safeUnit = escapeHtml(unit);
                
                productDiv.innerHTML = `
                    <div class="row">
                        <div class="col-md-4">
                            <strong>${safeName}</strong>
                            ${description ? `<br><small class="text-muted">${safeDesc}</small>` : ""}
                            ${showStockMinSuggestion ? `<br><small class="text-info"><i class="la la-info-circle"></i> Stock actual: ${stockTotal} | Stock mínimo: ${minimumStock}</small>` : ""}
                        </div>
                        <div class="col-md-2">
                            <label>Cantidad:</label>
                            <input type="number" class="form-control product-quantity" value="${quantity}" min="1">
                            ${showStockMinSuggestion ? `
                                <small class="text-muted d-block mt-1">
                                    <a href="#" class="add-stock-min-link" style="color: #17a2b8; text-decoration: none;">
                                        <i class="la la-plus-circle"></i> Incluir stock mínimo (+${minimumStock})
                                    </a>
                                </small>
                                <small class="text-success d-block mt-1" style="display: none;" id="suggested-${productId}">
                                    Sugerido: ${suggestedQuantity} ${safeUnit}
                                </small>
                            ` : ""}
                        </div>
                        <div class="col-md-2">
                            <label>Precio Unit. Est.:</label>
                            <input type="number" class="form-control product-price" step="0.01" min="0" value="${price}">
                        </div>
                        <div class="col-md-3">
                            <label>Descripción / Especificaciones:</label>
                            <textarea class="form-control product-specs" rows="2" placeholder="Describa el producto o indique especificaciones...">${safeSpecs}</textarea>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-danger btn-sm remove-product">
                                <i class="la la-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
                
                container.appendChild(productDiv);
                
                // Event listener para agregar stock mínimo
                const addStockMinLink = productDiv.querySelector(".add-stock-min-link");
                if (addStockMinLink && showStockMinSuggestion) {
                    addStockMinLink.addEventListener("click", function(e) {
                        e.preventDefault();
                        const quantityInput = productDiv.querySelector(".product-quantity");
                        const currentQuantity = parseFloat(quantityInput.value) || 0;
                        const newQuantity = currentQuantity + minimumStock;
                        quantityInput.value = newQuantity;
                        
                        // Mostrar mensaje de sugerencia
                        const suggestedMsg = productDiv.querySelector(`#suggested-${productId}`);
                        if (suggestedMsg) {
                            suggestedMsg.textContent = `Total: ${newQuantity} ${unit}`;
                            suggestedMsg.style.display = "block";
                        }
                        
                        // Ocultar el enlace después de usarlo
                        addStockMinLink.style.display = "none";
                        
                        updateTotals();
                    });
                }
                
                // Event listeners para actualizar totales
                productDiv.querySelector(".product-quantity").addEventListener("input", updateTotals);
                productDiv.querySelector(".product-price").addEventListener("input", updateTotals);
                productDiv.querySelector(".product-specs").addEventListener("input", updateTotals);
                
                updateHiddenFields();
            }
            
            // Función para actualizar campos ocultos
            function updateHiddenFields() {
                const form = getProductsForm();
                if (!form) {
                    console.error("No se encontró el formulario de solicitud de compra (products-container).");
                    return;
                }
                const pc = document.getElementById("products-container");
                const products = [];
                (pc ? pc.querySelectorAll(".selected-product-item") : []).forEach(item => {
                    const productId = item.getAttribute("data-product-id");
                    const quantity = item.querySelector(".product-quantity").value;
                    const price = item.querySelector(".product-price").value;
                    const specs = item.querySelector(".product-specs").value;
                    
                    const row = {
                        product_id: productId,
                        quantity: quantity,
                        price: price,
                        specifications: specs
                    };
                    if (typeof productId === "string" && productId.indexOf("new_") === 0) {
                        row.name = item.getAttribute("data-new-name") || "";
                        row.unit = item.getAttribute("data-new-unit") || "unidad";
                        row.description = item.getAttribute("data-new-description") || "";
                        row.product_description = specs || item.getAttribute("data-new-description") || "";
                    }
                    products.push(row);
                });
                
                const json = JSON.stringify(products);
                const hiddens = form.querySelectorAll("input[name=\'selected_products\']");
                if (hiddens.length === 0) {
                    const h = document.createElement("input");
                    h.type = "hidden";
                    h.name = "selected_products";
                    h.value = json;
                    form.appendChild(h);
                } else {
                    const arr = Array.from(hiddens);
                    arr.forEach(el => { el.value = json; });
                    arr.slice(1).forEach(el => el.remove());
                }
            }
            
            // Función para actualizar totales
            function updateTotals() {
                updateHiddenFields();
            }
            
            // Función para mostrar modal de nuevo producto
            function showNewProductModal() {
                const productName = prompt("Nombre del nuevo producto:");
                if (!productName) return;
                
                const productUnit = prompt("Unidad del producto (ej: kg, litros, unidades):");
                if (!productUnit) return;
                
                const productDescription = prompt("Descripción / Especificaciones (opcional):") || "";
                
                const tempId = "new_" + Date.now();
                addProductToList(tempId, productName, productUnit, productDescription, 1, 0, productDescription, 0, 0);
            }

            const __prFormEdit = getProductsForm();
            if (__prFormEdit && !__prFormEdit.dataset.prProductsSyncBound) {
                __prFormEdit.dataset.prProductsSyncBound = "1";
                __prFormEdit.addEventListener("submit", function() { updateHiddenFields(); }, true);
            }
            }
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", init, { once: true });
            } else {
                init();
            }
        })();
        </script>
        ';
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store()
    {
        $this->crud->hasAccessOrFail('create');

        // execute the FormRequest authorization and validation, if one is required
        $request = $this->crud->validateRequest();

        // register any Model Events defined on fields
        $this->crud->registerFieldEvents();

        // Obtener datos para guardar
        $dataToSave = $this->crud->getStrippedSaveRequest($request);

        // Verificar si viene de una solicitud general desde el parámetro URL o del request
        $convertedFrom = request()->get('converted_from') ?? $request->input('converted_from_general_request_id');

        // Validar permisos para convertir solicitudes generales
        $user = backpack_user();
        if ($convertedFrom && $user) {
            // Validar que el usuario personal no pueda convertir
            if ($user->hasRole('role_personal', 'backpack')) {
                \Alert::error('No tienes permisos para convertir solicitudes generales a solicitudes de compra.')->flash();

                return redirect()->back();
            }

            // Validar que el responsable de área solo pueda convertir solicitudes de su área
            if ($user->hasResponsableAreaOrInstituteAuthorityRole()) {
                $generalRequest = \App\Models\GeneralRequest::find($convertedFrom);
                if ($generalRequest) {
                    $userAreas = \App\Models\ResponsibilityArea::where('responsible_user_id', $user->id)->pluck('id');

                    // Solo puede convertir si la solicitud pertenece a su área
                    // NO puede convertir solicitudes que él creó para otras áreas
                    if (! $generalRequest->area_id || ! $userAreas->contains($generalRequest->area_id)) {
                        \Alert::error('Solo puedes convertir a compra las solicitudes que pertenecen a tu depósito/área.')->flash();

                        return redirect()->back();
                    }
                }
            }
        }

        \Log::info('Parámetro converted_from desde URL:', ['converted_from' => request()->get('converted_from')]);
        \Log::info('Campo converted_from_general_request_id en request:', ['field' => $request->input('converted_from_general_request_id')]);
        \Log::info('Campo converted_from_general_request_id en datos:', ['field' => $dataToSave['converted_from_general_request_id'] ?? 'no existe']);

        // Si viene de una conversión, asegurar que se guarde el ID
        if ($convertedFrom) {
            $dataToSave['converted_from_general_request_id'] = $convertedFrom;
            \Log::info('Agregado converted_from_general_request_id a datos:', ['id' => $convertedFrom]);
        }

        // Solicitante de la solicitud de compra: si viene de una general, usar el solicitante efectivo de esa solicitud
        if ($convertedFrom) {
            $generalRequest = \App\Models\GeneralRequest::query()->find($convertedFrom);
            if ($generalRequest) {
                $dataToSave['requesting_user_id'] = $generalRequest->solicitingUserId();
                \Log::info('Establecido requesting_user_id desde solicitud general:', ['user_id' => $dataToSave['requesting_user_id']]);
            } elseif ($user) {
                $dataToSave['requesting_user_id'] = $user->id;
            }
        } elseif ($user) {
            $dataToSave['requesting_user_id'] = $user->id;
            \Log::info('Establecido requesting_user_id al usuario logueado:', ['user_id' => $user->id, 'email' => $user->email]);
        }

        // Asegurar que los campos requeridos tengan valores por defecto
        if (! isset($dataToSave['status'])) {
            $dataToSave['status'] = 'Pendiente';
        }
        if (! isset($dataToSave['priority'])) {
            $dataToSave['priority'] = 'Media';
        }
        if (! isset($dataToSave['total_amount'])) {
            $dataToSave['total_amount'] = 0;
        }

        if ($user) {
            $dataToSave['created_by'] = $user->id;
        }

        // Debug: Log los datos que se van a guardar
        \Log::info('Datos a guardar en PurchaseRequest:', $dataToSave);

        try {
            // insert item in the db
            $item = $this->crud->create($dataToSave);
            $this->data['entry'] = $this->crud->entry = $item;

            // Recargar el item para asegurar que tiene el converted_from_general_request_id
            $item->refresh();
            \Log::info('Item creado:', ['id' => $item->id, 'converted_from_general_request_id' => $item->converted_from_general_request_id]);

            // Verificar si el usuario seleccionó productos manualmente
            $selectedProducts = $request->input('selected_products');
            $hasManualProducts = ! empty($selectedProducts) && $selectedProducts !== '[]';

            // Si viene de una solicitud general y NO hay productos seleccionados manualmente,
            // replicar automáticamente los productos de la solicitud general
            if ($item->converted_from_general_request_id && ! $hasManualProducts) {
                \Log::info('Replicando productos desde solicitud general');
                $this->replicateProductsFromGeneralRequest($item);
            }

            // Procesar productos seleccionados manualmente (si el usuario los seleccionó)
            if ($hasManualProducts) {
                \Log::info('Procesando productos seleccionados manualmente');
                $this->processSelectedProducts($item, $request);
            }

            // Verificar si requiere aprobación de administrador después de calcular el total
            $item->refresh();
            if ($item->total_amount > 0) {
                $comprasLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_responsable_compras');
                $requiresAdminApproval = $item->total_amount > $comprasLimit;
                $item->update(['requires_admin_approval' => $requiresAdminApproval]);
            }

            // show a success message
            \Alert::success(trans('backpack::crud.insert_success'))->flash();

            // save the redirect choice for next time
            $this->crud->setSaveAction();

            // Actualizar estado de la solicitud general si viene de una conversión
            if ($item->converted_from_general_request_id) {
                \Log::info('Intentando actualizar solicitud general:', ['id' => $item->converted_from_general_request_id]);
                $generalRequest = \App\Models\GeneralRequest::with('details.product', 'deliveries.details')
                    ->find($item->converted_from_general_request_id);
                if ($generalRequest) {
                    // Verificar si tiene entregas para determinar el estado correcto
                    $hasAnyDelivery = false;
                    $allDelivered = true;
                    $hasDetails = false;

                    // Verificar el estado de entrega de cada producto
                    foreach ($generalRequest->details as $detail) {
                        $requestedQty = $detail->requested_quantity ?? 0;

                        if ($requestedQty <= 0) {
                            continue;
                        }

                        $hasDetails = true;

                        // Calcular cantidad entregada
                        $deliveredQty = 0;
                        foreach ($generalRequest->deliveries as $delivery) {
                            $deliveryDetail = $delivery->details->where('product_id', $detail->product_id)->first();
                            if ($deliveryDetail) {
                                $deliveredQty += $deliveryDetail->delivered_quantity ?? 0;
                            }
                        }

                        if ($deliveredQty > 0) {
                            $hasAnyDelivery = true;
                        }

                        // Si este producto no está completamente entregado, entonces no todos están entregados
                        if ($deliveredQty < $requestedQty) {
                            $allDelivered = false;
                        }
                    }

                    // Determinar el estado según las entregas
                    $newStatus = 'revisada_area'; // Por defecto, si no hay entregas

                    if ($hasDetails && $hasAnyDelivery) {
                        // Si hay entregas, determinar si es parcial o total
                        if ($allDelivered) {
                            $newStatus = 'entregada_totalmente';
                        } else {
                            $newStatus = 'entregada_parcialmente';
                        }
                    }

                    // No cambiar el estado si está archivada
                    if ($generalRequest->status === 'archivada') {
                        $newStatus = 'archivada';
                    }

                    // Actualizar la solicitud general
                    $generalRequest->update([
                        'is_converted' => true,
                        'status' => $newStatus,
                    ]);

                    \Log::info('Solicitud general actualizada exitosamente:', [
                        'id' => $generalRequest->id,
                        'is_converted' => $generalRequest->is_converted,
                        'status' => $newStatus,
                        'has_deliveries' => $hasAnyDelivery,
                    ]);

                    \Alert::info('La solicitud general '.$generalRequest->number.' ha sido marcada como convertida a compra y su estado ha sido actualizado a: '.ucfirst(str_replace('_', ' ', $newStatus)).'.')->flash();
                } else {
                    \Log::error('No se encontró la solicitud general con ID:', ['id' => $item->converted_from_general_request_id]);
                }
            } else {
                \Log::info('No hay converted_from_general_request_id en el item guardado');
            }

            return $this->crud->performSaveAction($item->getKey());
        } catch (\Exception $e) {
            \Log::error('Error al guardar PurchaseRequest: '.$e->getMessage());
            \Alert::error('Error al guardar la solicitud de compra: '.$e->getMessage())->flash();

            return redirect()->back()->withInput();
        }
    }

    /**
     * Replicar productos desde la solicitud general a la solicitud de compra
     */
    private function replicateProductsFromGeneralRequest($purchaseRequest)
    {
        try {
            // Cargar la solicitud general con detalles, productos y entregas
            $generalRequest = \App\Models\GeneralRequest::with(['details.product', 'deliveries.details'])
                ->find($purchaseRequest->converted_from_general_request_id);

            if (! $generalRequest) {
                \Log::warning('Solicitud general no encontrada para replicar productos', [
                    'general_request_id' => $purchaseRequest->converted_from_general_request_id,
                ]);

                return;
            }

            // Verificar si ya hay detalles en la solicitud de compra
            $existingDetailsCount = \App\Models\PurchaseRequestDetail::where('purchase_request_id', $purchaseRequest->id)->count();

            if ($existingDetailsCount > 0) {
                \Log::info('La solicitud de compra ya tiene productos. No se replicarán automáticamente.', [
                    'purchase_request_id' => $purchaseRequest->id,
                    'existing_details_count' => $existingDetailsCount,
                ]);

                return;
            }

            $totalAmount = 0;
            $replicatedCount = 0;

            // Replicar cada detalle de la solicitud general
            // Solo incluir productos con cantidades faltantes (no totalmente entregados)
            foreach ($generalRequest->details as $generalDetail) {
                if (! $generalDetail->product) {
                    \Log::warning('Producto no encontrado en detalle de solicitud general', [
                        'general_request_detail_id' => $generalDetail->id,
                    ]);

                    continue;
                }

                // Calcular cantidad entregada
                $deliveredQuantity = 0;
                if ($generalRequest->deliveries) {
                    foreach ($generalRequest->deliveries as $delivery) {
                        $deliveryDetail = $delivery->details->where('product_id', $generalDetail->product_id)->first();
                        if ($deliveryDetail) {
                            $deliveredQuantity += $deliveryDetail->delivered_quantity ?? 0;
                        }
                    }
                }

                // Calcular cantidad faltante
                $requestedQuantity = (float) ($generalDetail->requested_quantity ?? 0);
                $pendingQuantity = max(0, $requestedQuantity - $deliveredQuantity);

                // Solo replicar productos con cantidad faltante > 0
                if ($pendingQuantity <= 0) {
                    \Log::info('Producto omitido porque ya está totalmente entregado', [
                        'general_request_detail_id' => $generalDetail->id,
                        'product_id' => $generalDetail->product_id,
                        'requested_quantity' => $requestedQuantity,
                        'delivered_quantity' => $deliveredQuantity,
                    ]);

                    continue;
                }

                // Crear el detalle en la solicitud de compra con la cantidad faltante
                // Los precios se establecen en 0, ya que el sector de compras los asignará después
                $purchaseRequestDetail = \App\Models\PurchaseRequestDetail::create([
                    'purchase_request_id' => $purchaseRequest->id,
                    'product_id' => $generalDetail->product_id,
                    'requested_quantity' => $pendingQuantity, // Usar cantidad faltante en lugar de solicitada
                    'specifications' => $generalDetail->specifications,
                    'justification' => $generalDetail->justification,
                    'estimated_unit_price' => 0, // Precio inicial en 0, el sector de compras lo asignará
                    'estimated_total' => 0, // Total inicial en 0
                    'status' => 'Pendiente',
                ]);

                $totalAmount += $purchaseRequestDetail->estimated_total;
                $replicatedCount++;

                \Log::info('Producto replicado desde solicitud general (solo cantidad faltante)', [
                    'general_request_detail_id' => $generalDetail->id,
                    'purchase_request_detail_id' => $purchaseRequestDetail->id,
                    'product_id' => $generalDetail->product_id,
                    'product_name' => $generalDetail->product->name ?? 'N/A',
                    'requested_quantity' => $requestedQuantity,
                    'delivered_quantity' => $deliveredQuantity,
                    'pending_quantity' => $pendingQuantity,
                ]);
            }

            // Actualizar el monto total de la solicitud de compra (incluso si es 0)
            $purchaseRequest->update(['total_amount' => $totalAmount]);

            \Log::info('Productos replicados exitosamente desde solicitud general', [
                'general_request_id' => $generalRequest->id,
                'purchase_request_id' => $purchaseRequest->id,
                'products_replicated' => $replicatedCount,
                'total_amount' => $totalAmount,
            ]);

            \Alert::info($replicatedCount.' producto(s) replicado(s) desde la solicitud general '.$generalRequest->number)->flash();

        } catch (\Exception $e) {
            \Log::error('Error al replicar productos desde solicitud general', [
                'purchase_request_id' => $purchaseRequest->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update()
    {
        $this->crud->hasAccessOrFail('update');

        // execute the FormRequest authorization and validation, if one is required
        $request = $this->crud->validateRequest();

        // register any Model Events defined on fields
        $this->crud->registerFieldEvents();

        $user = backpack_user();
        $entry = $this->crud->getCurrentEntry();

        if (! $entry) {
            abort(404, 'Solicitud de compra no encontrada.');
        }

        $entry->loadMissing(['purchaseRequestEvents', 'details', 'purchaseOrders', 'marketRates']);
        if ($entry->isFrozenPendingSuperiorApproval()) {
            abort(403, 'No se puede modificar la solicitud mientras está pendiente la aprobación de nivel superior.');
        }

        // Validar que el usuario solo pueda editar sus propias solicitudes (para role_admin_institucion)
        $isAdminSistema = $user->hasRole('role_admin_sistema', 'backpack');
        $isAdminInstitucion = $user->hasRole('role_admin_institucion', 'backpack');
        $isResponsableCompras = $user->effectivelyHasResponsableComprasRole();
        $isActingCreator = $entry->isActingAsCreatingUser((int) $user->id);

        // Si no es administrador del sistema ni responsable de compras, verificar restricciones
        if (! $isAdminSistema && ! $isResponsableCompras) {
            if ($isAdminInstitucion) {
                // El administrador del instituto solo puede editar solicitudes que él registró
                if (! $isActingCreator) {
                    abort(403, 'Solo puedes editar las solicitudes de compra que creaste.');
                }
            } else {
                if (! $isActingCreator) {
                    abort(403, 'Solo puedes editar las solicitudes de compra que creaste.');
                }

                // Solo puede editar si el estado es "Pendiente"
                if ($entry->status !== 'Pendiente') {
                    abort(403, 'Solo puedes editar solicitudes de compra con estado "Pendiente".');
                }
            }
        }

        // Obtener datos para guardar
        $dataToSave = $this->crud->getStrippedSaveRequest($request);
        unset($dataToSave['created_by']);

        $entry->loadMissing(['marketRates', 'details']);
        $areaCannotChangeProducts = $user->hasResponsableAreaOrInstituteAuthorityRole()
            && $entry->hasQuotationSelectionResolved();
        $comprasOrAdminInstitucionCannotChangeProducts = $this->userCannotModifyPurchaseRequestProductDetails($user);
        $cannotChangeProducts = $areaCannotChangeProducts || $comprasOrAdminInstitucionCannotChangeProducts;

        try {
            // update item in the db
            $item = $this->crud->update($this->crud->getCurrentEntryId(), $dataToSave);
            $this->data['entry'] = $this->crud->entry = $item;

            // Procesar productos seleccionados (eliminar existentes y crear nuevos)
            if ($entry->status !== 'Aprobada' && ! $cannotChangeProducts && $request->has('selected_products')) {
                \Log::info('Procesando productos en actualización:', ['selected_products' => $request->input('selected_products')]);
                $item->details()->delete();
                $this->processSelectedProducts($item, $request, true);
                $item->refresh();
                $this->pruneOrphanQuoteDetailsForPurchaseRequest($item);
            } elseif ($entry->status === 'Aprobada') {
                \Alert::warning('No se pueden modificar los productos de una solicitud aprobada.')->flash();
            } elseif ($cannotChangeProducts && $request->has('selected_products')) {
                if ($comprasOrAdminInstitucionCannotChangeProducts) {
                    \Alert::warning('No tiene permiso para modificar el detalle de productos de esta solicitud.')->flash();
                } else {
                    \Alert::warning('No se pueden modificar los productos: el sector de compras ya seleccionó cotización(es) en esta solicitud.')->flash();
                }
            }

            // Verificar si requiere aprobación de administrador después de actualizar el total
            $item->refresh();
            if ($item->total_amount > 0 && $item->status === 'Pendiente') {
                $comprasLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_responsable_compras');
                $requiresAdminApproval = $item->total_amount > $comprasLimit;
                $item->update(['requires_admin_approval' => $requiresAdminApproval]);
            }

            // show a success message
            \Alert::success(trans('backpack::crud.update_success'))->flash();

            // save the redirect choice for next time
            $this->crud->setSaveAction();

            return $this->crud->performSaveAction($item->getKey());
        } catch (\Exception $e) {
            \Log::error('Error al actualizar PurchaseRequest: '.$e->getMessage());
            \Log::error('Stack trace: '.$e->getTraceAsString());
            \Alert::error('Error al actualizar la solicitud de compra: '.$e->getMessage())->flash();

            return redirect()->back()->withInput();
        }
    }

    /**
     * Define what happens when the Delete operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-delete
     *
     * @return void
     */
    protected function setupDeleteOperation()
    {
        // Bloquear eliminación para role_admin_institucion
        $user = backpack_user();
        if ($user && $user->hasRole('role_admin_institucion', 'backpack')) {
            abort(403, 'No tienes permiso para eliminar solicitudes de compra.');
        }

        $entry = $this->crud->getCurrentEntry();
        if ($entry instanceof \App\Models\PurchaseRequest && $entry->deletionIsForbidden()) {
            abort(403, 'No se puede eliminar una solicitud de compra que ya fue aprobada, está en proceso o está completada.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $this->crud->hasAccessOrFail('delete');

        // Bloquear eliminación para role_admin_institucion
        $user = backpack_user();
        if ($user && $user->hasRole('role_admin_institucion', 'backpack')) {
            abort(403, 'No tienes permiso para eliminar solicitudes de compra.');
        }

        $entry = \App\Models\PurchaseRequest::find($id);
        if ($entry && $entry->deletionIsForbidden()) {
            $message = 'No se puede eliminar una solicitud de compra que ya fue aprobada, está en proceso o está completada.';
            if (request()->ajax()) {
                return response()->json(['error' => [$message]]);
            }
            \Alert::error($message)->flash();

            return redirect()->back();
        }

        return $this->crud->delete($id);
    }

    /**
     * Get HTML for displaying products as read-only
     */
    private function getProductsReadOnlyHtml($entry)
    {
        $entry->load('details.product');

        if (! $entry->details || $entry->details->count() === 0) {
            return '<div class="alert alert-info">No hay productos seleccionados.</div>';
        }

        $html = '<div class="table-responsive">';
        $html .= '<table class="table table-bordered table-striped">';
        $html .= '<thead class="thead-dark">';
        $html .= '<tr>';
        $html .= '<th>Producto</th>';
        $html .= '<th>Unidad</th>';
        $html .= '<th>Cantidad</th>';
        $html .= '<th>Precio Unitario</th>';
        $html .= '<th>Subtotal</th>';
        $html .= '<th>Descripción / Especificaciones</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        $total = 0;
        foreach ($entry->details as $detail) {
            $productName = $detail->product ? $detail->product->name : 'N/A';
            $unit = $detail->product ? $detail->product->unit_measurement : '';
            $quantity = $detail->requested_quantity ?? 0;
            $price = $detail->estimated_unit_price ?? 0;
            $subtotal = $quantity * $price;
            $total += $subtotal;
            $descSpecs = $detail->specifications ?? $detail->product_description ?? '';

            $html .= '<tr>';
            $html .= '<td>'.e($productName).'</td>';
            $html .= '<td>'.e($unit).'</td>';
            $html .= '<td class="text-right">'.number_format($quantity, 2).'</td>';
            $html .= '<td class="text-right">$'.number_format($price, 2).'</td>';
            $html .= '<td class="text-right">$'.number_format($subtotal, 2).'</td>';
            $html .= '<td><small>'.($descSpecs ? nl2br(e($descSpecs)) : '-').'</small></td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '<tfoot>';
        $html .= '<tr class="font-weight-bold">';
        $html .= '<td colspan="4" class="text-right">Total:</td>';
        $html .= '<td class="text-right">$'.number_format($total, 2).'</td>';
        $html .= '<td></td>';
        $html .= '</tr>';
        $html .= '</tfoot>';
        $html .= '</table>';
        $html .= '</div>';
        $html .= '<div class="alert alert-warning mt-2">';
        $html .= '<i class="la la-lock"></i> <strong>Nota:</strong> Los productos no pueden ser modificados porque la solicitud está aprobada.';
        $html .= '</div>';

        return $html;
    }

    /**
     * Process selected products and create purchase request details
     */
    private function processSelectedProducts($purchaseRequest, $request, $isUpdate = false)
    {
        $selectedProducts = $request->input('selected_products');

        if (! $selectedProducts || $selectedProducts === '[]' || $selectedProducts === '') {
            \Log::info('No hay productos seleccionados');
            $this->resetPurchaseRequestTotalsAfterProductSync($purchaseRequest);

            return;
        }

        // Si ya es un array, usarlo directamente, sino decodificar JSON
        if (is_array($selectedProducts)) {
            $products = $selectedProducts;
        } else {
            $products = json_decode($selectedProducts, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                \Log::error('Error al decodificar JSON de productos:', [
                    'json_error' => json_last_error_msg(),
                    'raw_value' => $selectedProducts,
                ]);
                $this->resetPurchaseRequestTotalsAfterProductSync($purchaseRequest);

                return;
            }
        }

        if (! $products || ! is_array($products) || empty($products)) {
            \Log::warning('Productos seleccionados está vacío o no es un array válido');
            $this->resetPurchaseRequestTotalsAfterProductSync($purchaseRequest);

            return;
        }

        \Log::info('Productos a procesar:', ['count' => count($products), 'products' => $products]);

        $totalAmount = 0;

        foreach ($products as $productData) {
            if (! isset($productData['product_id'])) {
                \Log::warning('Producto sin product_id', ['data' => $productData]);

                continue;
            }

            $productIdRaw = $productData['product_id'] ?? null;
            // Convertir a números para evitar errores de multiplicación
            $quantity = (float) ($productData['quantity'] ?? 0);
            $price = (float) ($productData['price'] ?? 0);
            $specifications = $productData['specifications'] ?? '';
            // Unificado: descripción/especificaciones se guarda en ambos campos para compatibilidad
            $productDescription = $productData['product_description'] ?? $specifications;

            $isNewProduct = is_string($productIdRaw) && str_starts_with($productIdRaw, 'new_');

            if ($isNewProduct) {
                $name = trim((string) ($productData['name'] ?? ''));
                $specTrim = trim((string) $specifications);
                if ($name === '' || preg_match('/^producto\s+nuevo$/iu', $name)) {
                    $name = $specTrim !== '' ? Str::limit($specTrim, 255, '') : 'Producto Nuevo';
                }
                $description = trim((string) ($productData['description'] ?? $productData['product_description'] ?? $specTrim));
                $unit = trim((string) ($productData['unit'] ?? 'unidad')) ?: 'unidad';

                $defaultCategoryId = \App\Models\Category::query()->orderBy('id')->value('id') ?? 1;

                $newProduct = \App\Models\Product::create([
                    'name' => $name,
                    'description' => $description,
                    'unit_measurement' => $unit,
                    'category_id' => $defaultCategoryId,
                    'minimum_stock' => 0,
                ]);
                $productId = $newProduct->id;
                \Log::info('Nuevo producto creado:', ['id' => $newProduct->id, 'name' => $newProduct->name]);
            } else {
                // Validar que el producto existe
                $productId = (int) $productIdRaw;
                $product = \App\Models\Product::find($productId);
                if (! $product) {
                    \Log::warning('Producto no encontrado:', ['product_id' => $productId]);

                    continue;
                }
            }

            // Crear el detalle de la solicitud de compra
            $detail = \App\Models\PurchaseRequestDetail::create([
                'purchase_request_id' => $purchaseRequest->id,
                'product_id' => $productId,
                'product_description' => $productDescription,
                'requested_quantity' => $quantity,
                'specifications' => $specifications,
                'estimated_unit_price' => $price,
                'estimated_total' => $price * $quantity,
                'status' => 'Pendiente',
            ]);

            $totalAmount += $price * $quantity;
            \Log::info('Detalle creado:', ['detail_id' => $detail->id, 'product_id' => $productId]);
        }

        // Actualizar el monto total de la solicitud y verificar si requiere aprobación de administrador
        $comprasLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_responsable_compras');
        $requiresAdminApproval = $totalAmount > $comprasLimit;

        $purchaseRequest->update([
            'total_amount' => $totalAmount,
            'requires_admin_approval' => $requiresAdminApproval,
        ]);
        \Log::info('Monto total actualizado:', ['total' => $totalAmount, 'requires_admin_approval' => $requiresAdminApproval]);
    }

    /**
     * Cuando no quedan líneas de producto, el total de la solicitud debe reflejarlo (detalles ya borrados antes).
     */
    private function resetPurchaseRequestTotalsAfterProductSync(\App\Models\PurchaseRequest $purchaseRequest): void
    {
        $purchaseRequest->update([
            'total_amount' => 0,
            'requires_admin_approval' => false,
        ]);
    }

    /**
     * Elimina líneas de cotización (quote_details) de productos que ya no están en la solicitud.
     */
    private function pruneOrphanQuoteDetailsForPurchaseRequest(\App\Models\PurchaseRequest $purchaseRequest): void
    {
        $purchaseRequest->loadMissing('details');
        $keptProductIds = $purchaseRequest->details->pluck('product_id')->unique()->filter()->values()->all();
        $marketRateIds = \App\Models\MarketRate::query()
            ->where('purchase_request_id', $purchaseRequest->id)
            ->pluck('id');

        if ($marketRateIds->isEmpty()) {
            return;
        }

        $baseQuery = \App\Models\QuoteDetail::query()->whereIn('market_rate_id', $marketRateIds);
        if ($keptProductIds === []) {
            $baseQuery->delete();
        } else {
            (clone $baseQuery)->whereNotIn('product_id', $keptProductIds)->delete();
        }

        foreach ($marketRateIds as $mrId) {
            $mr = \App\Models\MarketRate::query()->find($mrId);
            if (! $mr) {
                continue;
            }
            $total = (float) \App\Models\QuoteDetail::query()
                ->where('market_rate_id', $mrId)
                ->get()
                ->sum(fn (\App\Models\QuoteDetail $d) => (float) $d->quantity * (float) $d->unit_price);
            $mr->update(['total_amount' => $total]);
        }
    }

    /**
     * Generate comparative Excel file for purchase request quotes (sin guardar archivos)
     */
    public function generateComparativeExcel($id)
    {
        $purchaseRequest = \App\Models\PurchaseRequest::with([
            'details.product',
            'responsibilityArea',
        ])->findOrFail($id);

        // Get all market rates for this purchase request (incluye cotizaciones globales sin detalle por producto)
        $productIds = $purchaseRequest->details->pluck('product_id')->toArray();
        $marketRates = \App\Models\MarketRate::with([
            'supplier',
            'quoteDetails.product',
        ])->where('purchase_request_id', $purchaseRequest->id)->get();

        // Group market rates by supplier
        $suppliers = $marketRates->groupBy('supplier_id');

        // Generate Excel in memory
        $filename = 'Planilla_Comparativa_'.$purchaseRequest->request_number.'_'.date('Y-m-d').'.xlsx';

        // Create Excel file in memory
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers
        $sheet->setCellValue('A1', 'Planilla Comparativa de Cotizaciones');
        $sheet->setCellValue('A2', 'Solicitud: '.$purchaseRequest->request_number);
        $sheet->setCellValue('A3', 'Fecha: '.date('d/m/Y'));
        $sheet->setCellValue('A4', 'Área: '.($purchaseRequest->responsibilityArea->name ?? 'N/A'));

        $row = 6;
        // Resumen de monto total por proveedor para facilitar comparación.
        $sheet->setCellValue('A'.$row, 'Resumen total por proveedor');
        $sheet->getStyle('A'.$row)->getFont()->setBold(true);
        $row++;

        $sheet->setCellValue('A'.$row, 'Proveedor');
        $sheet->setCellValue('B'.$row, 'Subtotal');
        $sheet->setCellValue('C'.$row, 'IVA');
        $sheet->setCellValue('D'.$row, 'Total + IVA');
        $sheet->setCellValue('E'.$row, 'Productos incluidos');
        $sheet->getStyle('A'.$row.':E'.$row)->getFont()->setBold(true);
        $row++;

        foreach ($suppliers as $supplierId => $supplierRates) {
            $supplier = $supplierRates->first()->supplier;
            $supplierName = $supplier->company_name ?? ('Proveedor '.$supplierId);
            $effectiveTotal = 0.0;
            $vatAmount = 0.0;
            $totalWithVat = 0.0;
            $totalQty = 0.0;
            $productNames = [];
            $hasGlobalWithoutDetails = false;

            foreach ($supplierRates as $rate) {
                foreach ($rate->quoteDetails as $qd) {
                    $name = $qd->product->name ?? ('Producto #'.($qd->product_id ?? 'N/A'));
                    if (is_string($name) && trim($name) !== '') {
                        $productNames[] = trim($name);
                    }
                }

                $rateSubtotalFromDetails = (float) $rate->quoteDetails->sum(function ($d) {
                    return ((float) ($d->quantity ?? 0)) * ((float) ($d->unit_price ?? 0));
                });
                $rateTotalQty = (float) $rate->quoteDetails->sum(function ($d) {
                    return (float) ($d->quantity ?? 0);
                });
                $rateSubtotal = $rateSubtotalFromDetails > 0
                    ? $rateSubtotalFromDetails
                    : (float) ($rate->total_amount ?? 0);
                if ($rate->quoteDetails->isEmpty() && $rateSubtotal > 0) {
                    $hasGlobalWithoutDetails = true;
                }

                $rateVat = (float) ($rate->vat_amount ?? 0);
                $rateTotalWithVat = (float) ($rate->total_amount_with_vat ?? 0);

                if ($rateVat <= 0 && $rateTotalWithVat > 0 && $rateSubtotal > 0) {
                    $rateVat = max(0, $rateTotalWithVat - $rateSubtotal);
                }
                if ($rateTotalWithVat <= 0 && $rateSubtotal > 0) {
                    $rateTotalWithVat = $rateSubtotal + max(0, $rateVat);
                }

                $effectiveTotal += max(0, $rateSubtotal);
                $vatAmount += max(0, $rateVat);
                $totalWithVat += max(0, $rateTotalWithVat);
                $totalQty += max(0, $rateTotalQty);
            }
            $sheet->setCellValue('A'.$row, $supplierName);
            $sheet->setCellValue('B'.$row, $effectiveTotal > 0 ? '$'.number_format($effectiveTotal, 2) : 'Sin monto informado');
            $sheet->setCellValue('C'.$row, $vatAmount > 0 ? '$'.number_format($vatAmount, 2) : '$0.00');
            $sheet->setCellValue('D'.$row, $totalWithVat > 0 ? '$'.number_format($totalWithVat, 2) : 'Sin monto informado');
            $productNames = array_values(array_unique($productNames));
            $productsLabel = empty($productNames) ? 'Sin detalle de productos' : implode(', ', $productNames);
            if ($hasGlobalWithoutDetails) {
                $productsLabel .= empty($productNames) ? 'Cotización global (sin detalle)' : ' + Cotización global sin detalle';
            }
            $sheet->setCellValue('E'.$row, $productsLabel);
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'E') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Create writer and output to memory
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        // Create temporary file in memory
        $tempFile = tempnam(sys_get_temp_dir(), 'excel_');
        $writer->save($tempFile);

        // Return download response without saving to project
        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Generate Excel file with PhpSpreadsheet
     */
    private function generateExcelFile($purchaseRequest, $suppliers, $filePath)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        // Set title
        $sheet->setTitle('Planilla Comparativa');

        // Header information
        $sheet->setCellValue('A1', 'PLANILLA COMPARATIVA DE COTIZACIONES');
        $sheet->setCellValue('A2', 'Solicitud de Compra: '.$purchaseRequest->request_number);
        $sheet->setCellValue('A3', 'Área: '.$purchaseRequest->responsibilityArea->name);
        $sheet->setCellValue('A4', 'Fecha: '.date('d/m/Y'));

        // Merge title cells
        $sheet->mergeCells('A1:Z1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Start data from row 6
        $currentRow = 6;

        // Create header row
        $header = ['Producto', 'Cantidad', 'Unidad'];
        $supplierColumns = [];
        $col = 'D'; // Start from column D

        foreach ($suppliers as $supplierId => $marketRates) {
            $supplier = $marketRates->first()->supplier;
            $supplierColumns[$supplierId] = [
                'name' => $supplier->company_name,
                'price_col' => $col,
                'subtotal_col' => chr(ord($col) + 1),
                'delivery_col' => chr(ord($col) + 2),
            ];

            $header[] = $supplier->company_name.' - Precio Unit.';
            $header[] = $supplier->company_name.' - Subtotal';
            $header[] = $supplier->company_name.' - Plazo';

            $col = chr(ord($col) + 3);
        }

        $header[] = 'Recomendación';
        $header[] = 'Observaciones';

        // Write header
        $col = 'A';
        foreach ($header as $headerText) {
            $sheet->setCellValue($col.$currentRow, $headerText);
            $sheet->getStyle($col.$currentRow)->getFont()->setBold(true);
            $sheet->getStyle($col.$currentRow)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('4472C4');
            $sheet->getStyle($col.$currentRow)->getFont()->getColor()->setRGB('FFFFFF');
            $col++;
        }

        $currentRow++;

        // Data rows
        foreach ($purchaseRequest->details as $detail) {
            $row = [
                $detail->product->name ?? 'Producto no encontrado',
                $detail->requested_quantity,
                $detail->product->unit ?? 'Unidad',
            ];

            $bestOption = null;
            $bestPrice = null;
            $bestScore = 0;
            $observations = [];
            $supplierData = [];
            $recommendations = [];

            // Collect data for each supplier
            foreach ($suppliers as $supplierId => $marketRates) {
                $supplier = $marketRates->first()->supplier;
                $quoteDetail = null;

                // Find quote detail for this product
                foreach ($marketRates as $marketRate) {
                    $quoteDetail = $marketRate->quoteDetails->where('product_id', $detail->product_id)->first();
                    if ($quoteDetail) {
                        break;
                    }
                }

                if ($quoteDetail) {
                    $unitPrice = $quoteDetail->unit_price;
                    $subtotal = $quoteDetail->quantity * $unitPrice;
                    $deliveryTime = 15; // Default days

                    $supplierData[$supplierId] = [
                        'name' => $supplier->company_name,
                        'unit_price' => $unitPrice,
                        'subtotal' => $subtotal,
                        'delivery_time' => $deliveryTime,
                        'score' => $this->calculateSupplierScore($subtotal, $deliveryTime),
                    ];

                    $observations[] = $supplier->company_name.': $'.number_format($subtotal, 2).' ('.$deliveryTime.' días)';

                    // Add to recommendations
                    $recommendations[] = [
                        'name' => $supplier->company_name,
                        'price' => $subtotal,
                        'delivery' => $deliveryTime,
                        'score' => $this->calculateSupplierScore($subtotal, $deliveryTime),
                    ];
                } else {
                    $supplierData[$supplierId] = [
                        'name' => $supplier->company_name,
                        'unit_price' => null,
                        'subtotal' => null,
                        'delivery_time' => null,
                        'score' => 0,
                    ];
                }
            }

            // Determine recommendation based on score (but don't auto-select)
            $recommendation = 'Sin recomendación';
            if (! empty($recommendations)) {
                // Sort by score (highest first)
                usort($recommendations, function ($a, $b) {
                    return $b['score'] <=> $a['score'];
                });

                $topRecommendation = $recommendations[0];
                $recommendation = $topRecommendation['name'].' (Puntuación: '.number_format($topRecommendation['score'], 1).')';

                // Add additional recommendations if there are multiple good options
                if (count($recommendations) > 1) {
                    $secondBest = $recommendations[1];
                    if ($secondBest['score'] > $topRecommendation['score'] * 0.8) { // Within 80% of best
                        $recommendation .= ' | '.$secondBest['name'].' (Alt.)';
                    }
                }
            }

            // Write product data
            $col = 'A';
            $sheet->setCellValue($col.$currentRow, $row[0]);
            $col++;
            $sheet->setCellValue($col.$currentRow, $row[1]);
            $col++;
            $sheet->setCellValue($col.$currentRow, $row[2]);
            $col++;

            // Write supplier data
            foreach ($supplierColumns as $supplierId => $columns) {
                $data = $supplierData[$supplierId] ?? null;

                if ($data && $data['unit_price'] !== null) {
                    $sheet->setCellValue($columns['price_col'].$currentRow, $data['unit_price']);
                    $sheet->setCellValue($columns['subtotal_col'].$currentRow, $data['subtotal']);
                    $sheet->setCellValue($columns['delivery_col'].$currentRow, $data['delivery_time'].' días');

                    // Format currency
                    $sheet->getStyle($columns['price_col'].$currentRow)->getNumberFormat()
                        ->setFormatCode('$#,##0.00');
                    $sheet->getStyle($columns['subtotal_col'].$currentRow)->getNumberFormat()
                        ->setFormatCode('$#,##0.00');
                } else {
                    $sheet->setCellValue($columns['price_col'].$currentRow, 'Sin cotización');
                    $sheet->setCellValue($columns['subtotal_col'].$currentRow, 'Sin cotización');
                    $sheet->setCellValue($columns['delivery_col'].$currentRow, 'Sin cotización');
                }
            }

            // Write recommendation and observations
            $sheet->setCellValue($col.$currentRow, $recommendation);
            $col++;
            $sheet->setCellValue($col.$currentRow, implode(' | ', $observations));

            // Highlight recommended supplier row (if any)
            if (! empty($recommendations)) {
                $sheet->getStyle('A'.$currentRow.':'.$col.$currentRow)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFF2CC'); // Light yellow for recommendation
            }

            $currentRow++;
        }

        // Auto-size columns
        foreach (range('A', $col) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Add borders
        $sheet->getStyle('A6:'.$col.($currentRow - 1))->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // Save file
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save(storage_path('app/public/'.$filePath));
    }

    /**
     * Calculate supplier score based on price and delivery time
     */
    private function calculateSupplierScore($subtotal, $deliveryTime)
    {
        // Score based on price (lower is better) and delivery time (shorter is better)
        // Price weight: 70%, Delivery time weight: 30%
        $priceScore = max(0, 100 - ($subtotal / 100)); // Normalize price
        $deliveryScore = max(0, 100 - ($deliveryTime * 2)); // Normalize delivery time

        return ($priceScore * 0.7) + ($deliveryScore * 0.3);
    }

    /**
     * Select winning market rate for purchase request
     */
    public function selectMarketRate($id, $marketRateId)
    {
        $purchaseRequest = \App\Models\PurchaseRequest::findOrFail($id);
        $this->assertComprasCanMutateQuotationSelection($purchaseRequest);
        $marketRate = \App\Models\MarketRate::with('quoteDetails')->findOrFail($marketRateId);

        // Calcular el monto total de la cotización desde los detalles si no está disponible
        $newTotalAmount = $marketRate->total_amount;
        if (! $newTotalAmount || $newTotalAmount == 0) {
            // Recalcular desde los detalles de la cotización
            $newTotalAmount = $marketRate->quoteDetails->sum(function ($detail) {
                return ($detail->quantity ?? 0) * ($detail->unit_price ?? 0);
            });

            // Si se calculó un monto, actualizar la cotización
            if ($newTotalAmount > 0) {
                $marketRate->update(['total_amount' => $newTotalAmount]);
            }
        }

        // Si aún no hay monto, mantener el de la solicitud de compra
        if (! $newTotalAmount || $newTotalAmount == 0) {
            $newTotalAmount = $purchaseRequest->total_amount ?? 0;
        }

        $comprasLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_responsable_compras');
        $requiresAdminApproval = $newTotalAmount > $comprasLimit;

        $purchaseRequest->update([
            'selected_market_rate_id' => $marketRateId,
            'total_amount' => $newTotalAmount,
            'requires_admin_approval' => $requiresAdminApproval,
            'status' => 'Aprobada',
        ]);

        \Alert::success('Cotización seleccionada exitosamente. El monto total de la solicitud se ha actualizado a $'.number_format($newTotalAmount, 2).'.')->flash();

        return redirect()->back();
    }

    /**
     * Estados en los que compras/administración pueden sugerir compra directa.
     */
    private function statusAllowsComprasDirectPurchaseSuggestion(string $status): bool
    {
        return in_array($status, ['Pendiente', 'En Proceso'], true);
    }

    /**
     * Puede marcar o sugerir compra directa en nombre del sector de compras.
     */
    private function userCanUseComprasSectorDirectPurchase(?\App\Models\User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('role_admin_institucion', 'backpack') || $user->hasRole('role_admin_sistema', 'backpack')) {
            return true;
        }

        return $user->effectivelyHasResponsableComprasRole();
    }

    /**
     * Administradora del instituto y sector de compras no deben modificar productos/ítems de la solicitud.
     */
    private function userCannotModifyPurchaseRequestProductDetails(?\App\Models\User $user): bool
    {
        if (! $user) {
            return true;
        }

        if ($user->hasRole('role_admin_institucion', 'backpack')) {
            return true;
        }

        return $user->effectivelyHasResponsableComprasRole();
    }

    /**
     * Responsable de compras sin rol de administración del sistema o del instituto.
     */
    private function userIsResponsableComprasSinAdmin(?\App\Models\User $user): bool
    {
        if (! $user || ! $user->hasRole('role_responsable_compras', 'backpack')) {
            return false;
        }
        if ($user->hasRole('role_admin_sistema', 'backpack') || $user->hasRole('role_admin_institucion', 'backpack')) {
            return false;
        }

        return true;
    }

    /**
     * Estados en los que compras (sin admin institucional/sistema) puede seleccionar o alternar cotizaciones.
     * Incluye «En Proceso»: el área puede pasar a ese estado al notificar a compras.
     */
    private function statusAllowsComprasSinAdminQuotationSelection(string $status): bool
    {
        return in_array($status, ['Pendiente', 'En Proceso'], true);
    }

    /**
     * Solicitud congelada tras pedir aprobación de nivel superior (salvo rechazo parcial / reapertura).
     */
    private function purchaseRequestFrozenPendingSuperiorApproval(\App\Models\PurchaseRequest $purchaseRequest): bool
    {
        $purchaseRequest->loadMissing(['purchaseRequestEvents', 'details', 'purchaseOrders', 'marketRates']);

        return $purchaseRequest->isFrozenPendingSuperiorApproval();
    }

    /**
     * Compras (sin admin) solo puede alterar cotizaciones / asignación por producto en Pendiente o En proceso.
     */
    private function assertComprasCanMutateQuotationSelection(\App\Models\PurchaseRequest $purchaseRequest): void
    {
        $purchaseRequest->loadMissing(['purchaseRequestEvents', 'details', 'purchaseOrders', 'marketRates', 'approvedBy']);

        if ($purchaseRequest->isFrozenPendingSuperiorApproval()) {
            abort(403, 'No se puede modificar la solicitud (cotizaciones, productos ni asignaciones) mientras está pendiente la aprobación de nivel superior.');
        }

        if ($purchaseRequest->locksQuotationAndAssignmentChanges()) {
            if ($purchaseRequest->wasApprovedBySuperiorAuthority()) {
                abort(403, 'No se pueden modificar cotizaciones ni asignaciones: la solicitud ya fue aprobada por el nivel superior (representante legal o apoderado).');
            }

            abort(403, 'No se pueden modificar cotizaciones ni asignaciones: la solicitud ya está aprobada.');
        }

        $user = backpack_user();
        if ($this->userIsResponsableComprasSinAdmin($user) && ! $this->statusAllowsComprasSinAdminQuotationSelection((string) $purchaseRequest->status)) {
            abort(403, 'No se puede modificar cotizaciones ni la asignación por producto: la solicitud debe estar Pendiente o En proceso.');
        }
    }

    /**
     * Show form to select market rate with justification
     */
    public function showSelectMarketRateForm($id, $marketRateId)
    {
        $purchaseRequest = \App\Models\PurchaseRequest::with([
            'responsibilityArea',
            'details.product',
        ])->findOrFail($id);

        $this->assertComprasCanMutateQuotationSelection($purchaseRequest);

        $marketRate = \App\Models\MarketRate::with([
            'supplier',
            'quoteDetails.product',
        ])->findOrFail($marketRateId);

        return view('admin.purchase-request.select-market-rate', compact('purchaseRequest', 'marketRate'));
    }

    /**
     * Store market rate selection with justification
     */
    public function storeMarketRateSelection($id, $marketRateId)
    {
        // Verificar que solo el responsable de compras pueda seleccionar cotizaciones
        $user = backpack_user();
        $adminRoles = ['role_admin_sistema', 'role_admin_institucion', 'role_responsable_compras'];
        $isAdmin = false;
        foreach ($adminRoles as $role) {
            if ($user && $user->hasRole($role, 'backpack')) {
                $isAdmin = true;
                break;
            }
        }

        if (! $isAdmin) {
            abort(403, 'Solo el responsable de compras puede seleccionar cotizaciones.');
        }

        $purchaseRequest = \App\Models\PurchaseRequest::findOrFail($id);
        $this->assertComprasCanMutateQuotationSelection($purchaseRequest);
        $marketRate = \App\Models\MarketRate::with('quoteDetails')->findOrFail($marketRateId);

        $request = request();

        // Calcular monto efectivo de la cotización (prioriza total con IVA).
        $newTotalAmount = $this->getMarketRateEffectiveTotal($marketRate);
        if (! $newTotalAmount || $newTotalAmount == 0) {
            $newTotalAmount = (float) ($purchaseRequest->total_amount ?? 0);
        }

        $comprasLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_responsable_compras');
        $requiresAdminApproval = $newTotalAmount > $comprasLimit;

        // Seleccionar cotización sin autoaprobar: la revisión inicial debe pasar por administradora.
        $purchaseRequest->update([
            'selected_market_rate_id' => $marketRateId,
            'selection_justification' => $request->input('justification'),
            'selected_by' => auth()->id(),
            'selected_at' => now(),
            'total_amount' => $newTotalAmount,
            'requires_admin_approval' => $requiresAdminApproval,
        ]);

        $selectionMessage = $user && $user->hasAdministradoraInstitucionRole()
            ? 'Cotización seleccionada. Puede registrar su revisión y decisión en la sección «Aprobación».'
            : 'Cotización seleccionada. Ahora debe solicitarse revisión a la administradora del instituto.';
        \Alert::success($selectionMessage)->flash();

        // Marcar la cotización como seleccionada para permitir selección múltiple en la vista.
        $marketRate->update(['is_selected' => true]);

        // Recalcular total de solicitud según cotizaciones seleccionadas (incluyendo IVA)
        $recalculatedTotal = $this->recalculateSelectedQuotationsTotalForPurchaseRequest($purchaseRequest);
        $purchaseRequest->update([
            'total_amount' => $recalculatedTotal,
            'requires_admin_approval' => $recalculatedTotal > $comprasLimit,
        ]);

        return redirect()->route('purchase-request.show', $id);
    }

    /**
     * Toggle selección múltiple de cotizaciones desde la vista de solicitud.
     */
    public function toggleMarketRateSelection($id, $marketRateId)
    {
        // Verificar que solo el responsable de compras/admin pueda seleccionar cotizaciones
        $user = backpack_user();
        $adminRoles = ['role_admin_sistema', 'role_admin_institucion', 'role_responsable_compras'];
        $isAdmin = false;
        foreach ($adminRoles as $role) {
            if ($user && $user->hasRole($role, 'backpack')) {
                $isAdmin = true;
                break;
            }
        }

        if (! $isAdmin) {
            abort(403, 'Solo el responsable de compras puede seleccionar cotizaciones.');
        }

        $purchaseRequest = \App\Models\PurchaseRequest::with('marketRates')->findOrFail($id);
        $this->assertComprasCanMutateQuotationSelection($purchaseRequest);
        $marketRate = \App\Models\MarketRate::where('purchase_request_id', $id)->findOrFail($marketRateId);

        $newValue = ! (bool) $marketRate->is_selected;
        $marketRate->update(['is_selected' => $newValue]);

        // Mantener compatibilidad con lógica existente basada en selected_market_rate_id.
        if ($newValue && empty($purchaseRequest->selected_market_rate_id)) {
            $purchaseRequest->update([
                'selected_market_rate_id' => $marketRate->id,
                'selected_by' => auth()->id(),
                'selected_at' => now(),
            ]);
        }

        if (! $newValue && (int) $purchaseRequest->selected_market_rate_id === (int) $marketRate->id) {
            $anotherSelectedId = $purchaseRequest->marketRates()
                ->where('is_selected', true)
                ->where('id', '!=', $marketRate->id)
                ->value('id');

            $purchaseRequest->update([
                'selected_market_rate_id' => $anotherSelectedId,
            ]);
        }

        // Recalcular total y requisito de aprobación usando cotizaciones seleccionadas.
        $comprasLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_responsable_compras');
        $recalculatedTotal = $this->recalculateSelectedQuotationsTotalForPurchaseRequest($purchaseRequest);
        $purchaseRequest->update([
            'total_amount' => $recalculatedTotal,
            'requires_admin_approval' => $recalculatedTotal > $comprasLimit,
        ]);

        \Alert::success($newValue ? 'Cotización seleccionada.' : 'Cotización deseleccionada.')->flash();

        return redirect()->route('purchase-request.show', $id);
    }

    /**
     * Compras solicita aprobación inicial de cotizaciones a administradora.
     */
    public function requestQuotationSuperiorAuthorization($id)
    {
        $user = backpack_user();
        $adminRoles = ['role_admin_sistema', 'role_responsable_compras'];
        $allowed = false;
        foreach ($adminRoles as $role) {
            if ($user && $user->hasRole($role, 'backpack')) {
                $allowed = true;
                break;
            }
        }
        if (! $allowed) {
            abort(403, 'Solo el sector de compras o administración puede solicitar esta autorización.');
        }

        $purchaseRequest = \App\Models\PurchaseRequest::findOrFail($id);
        $purchaseRequest->load(['marketRates', 'purchaseRequestEvents', 'details', 'purchaseOrders']);

        if ($purchaseRequest->isFrozenPendingSuperiorApproval()) {
            \Alert::error('No se puede modificar la solicitud mientras está pendiente la aprobación de nivel superior.')->flash();

            return redirect()->route('purchase-request.show', $id);
        }

        if (! PurchaseRequestNotificationService::isAwaitingAdministratorQuotationApproval($purchaseRequest)) {
            \Alert::error('No se puede enviar la solicitud: la solicitud debe estar pendiente o en proceso, con cotización(es) seleccionada(s).')->flash();

            return redirect()->route('purchase-request.show', $id);
        }

        if (! $purchaseRequest->hasQuotationsAssignedToAllRequestProducts()) {
            \Alert::warning('Antes de solicitar la revisión de la administradora, debe asignar una cotización a cada producto (sección «Asignar cotización por producto» cuando hay más de una cotización, o seleccionar una cotización que incluya todos los productos).')->flash();

            return redirect()->route('purchase-request.show', $id);
        }

        PurchaseRequestEvent::record(
            $purchaseRequest,
            PurchaseRequestEvent::EVENT_COMPRAS_ADMINISTRATOR_REVIEW_REQUESTED,
            $user?->id,
            [
                'request_number' => $purchaseRequest->request_number,
                'status' => $purchaseRequest->status,
            ]
        );

        PurchaseRequestNotificationService::notifyAdministratorQuotationApprovalNeeded($purchaseRequest);
        \Alert::success('Se envió el correo solicitando revisión a la administradora del instituto.')->flash();

        return redirect()->route('purchase-request.show', $id);
    }

    /**
     * Administradora solicita aprobación al nivel superior según monto.
     */
    public function requestQuotationHigherLevelAuthorization($id)
    {
        $user = backpack_user();
        $allowed = $user && (
            $user->hasRole('role_admin_institucion', 'backpack')
            || $user->hasRole('role_admin_sistema', 'backpack')
        );
        if (! $allowed) {
            abort(403, 'Solo la administradora del instituto puede solicitar aprobación al nivel superior.');
        }

        $purchaseRequest = \App\Models\PurchaseRequest::findOrFail($id);
        $purchaseRequest->load('marketRates');

        $effectiveTotal = $purchaseRequest->effectiveTotalForAuthorizationLimits();
        if (! PurchaseRequestNotificationService::shouldAdministratorEscalateQuotationApproval($purchaseRequest, $effectiveTotal)) {
            \Alert::error('No corresponde escalar esta solicitud: el monto no supera el límite de la administradora o faltan cotizaciones seleccionadas/asignadas.')->flash();

            return redirect()->route('purchase-request.show', $id);
        }

        $isResend = $purchaseRequest->isAwaitingSuperiorApprovalAfterAdministratorEscalation();

        PurchaseRequestNotificationService::notifySuperiorQuotationApprovalNeededFromAdministrator($purchaseRequest);

        PurchaseRequestEvent::record(
            $purchaseRequest,
            PurchaseRequestEvent::EVENT_ADMINISTRATOR_SUPERIOR_APPROVAL_REQUESTED,
            $user->id,
            [
                'request_number' => $purchaseRequest->request_number,
                'effective_total' => $effectiveTotal,
                'status' => $purchaseRequest->status,
            ]
        );

        $purchaseRequest->markSuperiorQuotationEscalationPending();

        \Alert::success(
            $isResend
                ? 'Se reenvió la solicitud de aprobación al nivel superior correspondiente.'
                : 'Se envió el correo solicitando aprobación al nivel superior correspondiente.'
        )->flash();

        return redirect()->route('purchase-request.show', $id);
    }

    /**
     * Administración: tras observaciones del nivel superior (ítems no autorizados), reabrir la solicitud
     * para ajustar cotización/asignación y exigir de nuevo autorización por ítem. No aplica si ya hay OC.
     */
    public function requestSuperiorReapprovalAfterRevision($id)
    {
        $user = backpack_user();
        $allowed = $user && (
            $user->hasRole('role_admin_institucion', 'backpack')
            || $user->hasRole('role_admin_sistema', 'backpack')
        );
        if (! $allowed) {
            abort(403, 'Solo la administradora del instituto o administración de sistema pueden reabrir la solicitud para un nuevo circuito de autorización.');
        }

        $purchaseRequest = \App\Models\PurchaseRequest::with(['details', 'marketRates', 'purchaseOrders'])->findOrFail($id);

        if (! $purchaseRequest->canReopenForSuperiorAuthorizationAfterRevision()) {
            \Alert::error('No corresponde reabrir esta solicitud: debe estar aprobada, con cotización definida, al menos un ítem no autorizado para compra, sin orden de compra generada y no ser compra directa.')->flash();

            return redirect()->route('purchase-request.show', $id);
        }

        $httpRequest = request();
        $httpRequest->validate([
            'reopen_justification' => 'nullable|string|max:1000',
        ]);

        $effectiveTotal = $this->recalculateSelectedQuotationsTotalForPurchaseRequest($purchaseRequest);
        $comprasLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_responsable_compras');
        $requiresAdminApproval = $effectiveTotal > $comprasLimit;

        \Illuminate\Support\Facades\DB::transaction(function () use ($purchaseRequest, $effectiveTotal, $requiresAdminApproval) {
            $purchaseRequest->details()->update([
                'line_authorization_status' => \App\Models\PurchaseRequestDetail::LINE_AUTH_PENDING,
                'line_authorization_rejection_reason' => null,
                'line_authorized_by' => null,
                'line_authorized_at' => null,
            ]);
            $purchaseRequest->update([
                'status' => 'En Proceso',
                'approved_by' => null,
                'approved_date' => null,
                'approval_justification' => null,
                'admin_quotation_reviewed_at' => null,
                'admin_quotation_reviewed_by' => null,
                'admin_quotation_review_justification' => null,
                'superior_quotation_escalation_pending_at' => null,
                'total_amount' => $effectiveTotal,
                'requires_admin_approval' => $requiresAdminApproval,
            ]);
        });

        PurchaseRequestEvent::record(
            $purchaseRequest->fresh(),
            PurchaseRequestEvent::EVENT_REOPENED_AFTER_SUPERIOR_REVISION,
            $user->id,
            [
                'request_number' => $purchaseRequest->request_number,
                'justification' => $httpRequest->input('reopen_justification'),
            ]
        );

        $purchaseRequest = $purchaseRequest->fresh(['marketRates']);

        if (PurchaseRequestNotificationService::shouldAdministratorEscalateQuotationApproval($purchaseRequest)) {
            PurchaseRequestNotificationService::notifySuperiorReapprovalAfterAdministrativeRevision($purchaseRequest);
        } elseif ($purchaseRequest->requires_admin_approval) {
            PurchaseRequestNotificationService::notifyAdministratorPurchaseRequestReopenedAfterSuperiorObservations($purchaseRequest);
        } else {
            PurchaseRequestNotificationService::notifyComprasPurchaseRequestReopenedAfterAdministrativeRevision($purchaseRequest);
        }

        \Log::info('purchase_request.reopen_after_superior_revision', [
            'purchase_request_id' => $purchaseRequest->id,
            'user_id' => $user->id,
            'justification' => $httpRequest->input('reopen_justification'),
        ]);

        \Alert::success('La solicitud quedó en curso para una nueva autorización por ítem. Se envió el correo correspondiente.')->flash();

        return redirect()->route('purchase-request.show', $id);
    }

    /**
     * Compra directa autorizada: el flujo no exige dos cotizaciones para invocar al circuito de compras.
     */
    private function isDirectPurchaseAuthorizedForWorkflow(\App\Models\PurchaseRequest $purchaseRequest): bool
    {
        return (bool) $purchaseRequest->is_direct_purchase
            && ! empty($purchaseRequest->direct_purchase_authorized_by)
            && ! empty($purchaseRequest->direct_purchase_supplier_id)
            && ! $purchaseRequest->direct_purchase_authorization_rejected;
    }

    /**
     * Impide notificar a compras si hay menos de dos cotizaciones (salvo compra directa autorizada).
     */
    private function blocksNotifyComprasForInsufficientQuotations(\App\Models\PurchaseRequest $purchaseRequest): bool
    {
        if ($this->isDirectPurchaseAuthorizedForWorkflow($purchaseRequest)) {
            return false;
        }
        $purchaseRequest->loadMissing('marketRates');

        return $purchaseRequest->marketRates->count() < 2;
    }

    /**
     * Responsable de área: notificar por correo a compras que la solicitud requiere su intervención.
     */
    public function notifyComprasIntervention($id)
    {
        $user = backpack_user();
        if (! $user || ! $user->hasResponsableAreaOrInstituteAuthorityRole()) {
            abort(403, 'Solo los responsables de área o la autoridad del instituto pueden enviar esta notificación.');
        }

        $purchaseRequest = \App\Models\PurchaseRequest::query()->findOrFail($id);
        $purchaseRequest->loadMissing(['purchaseRequestEvents', 'details', 'purchaseOrders', 'marketRates']);

        if ($purchaseRequest->isFrozenPendingSuperiorApproval()) {
            \Alert::error('No se puede modificar la solicitud mientras está pendiente la aprobación de nivel superior.')->flash();

            return redirect()->route('purchase-request.show', $id);
        }

        $mayNotify = $purchaseRequest->isActingAsCreatingUser((int) $user->id)
            || (int) $purchaseRequest->requesting_user_id === (int) $user->id
            || \App\Models\ResponsibilityArea::query()
                ->where('id', $purchaseRequest->responsibility_area_id)
                ->where('responsible_user_id', $user->id)
                ->exists();

        if (! $mayNotify) {
            abort(403, 'No tiene permiso para notificar sobre esta solicitud.');
        }

        if (in_array($purchaseRequest->status, ['Completada', 'Rechazada'], true)) {
            \Alert::warning('No se puede enviar la notificación en el estado actual de la solicitud.')->flash();

            return redirect()->route('purchase-request.show', $id);
        }

        if ($this->blocksNotifyComprasForInsufficientQuotations($purchaseRequest)) {
            $n = $purchaseRequest->marketRates->count();
            \Alert::warning(
                'Para notificar al circuito de compras se requieren al menos dos cotizaciones (comparación de ofertas). '
                .'Actualmente '.($n === 0 ? 'no hay cotizaciones cargadas' : 'solo hay una cotización cargada').'. '
                .'Cargue al menos una cotización más antes de continuar.'
            )->flash();

            return redirect()->route('purchase-request.show', $id);
        }

        $previousStatus = $purchaseRequest->status;
        if ($purchaseRequest->status === 'Pendiente') {
            $purchaseRequest->update(['status' => 'En Proceso']);
        }

        PurchaseRequestNotificationService::notifyComprasManualInterventionFromArea($purchaseRequest->fresh(), $user);

        $hayCompras = \App\Models\User::backpackHasAnyUserWithRole('role_responsable_compras');
        $destinoTxt = $hayCompras ? 'responsable(s) de compras' : 'administración del instituto (no hay usuarios con rol responsable de compras)';

        if ($previousStatus === 'Pendiente') {
            \Alert::success('Se envió el correo a '.$destinoTxt.' y la solicitud pasó a estado En proceso.')->flash();
        } else {
            \Alert::success('Se envió el correo a '.$destinoTxt.'.')->flash();
        }

        return redirect()->route('purchase-request.show', $id);
    }

    /**
     * Show form to suggest a supplier
     */
    public function showSuggestSupplierForm($id)
    {
        $purchaseRequest = \App\Models\PurchaseRequest::with([
            'responsibilityArea',
            'details.product',
        ])->findOrFail($id);

        // Verificar que el usuario sea responsable de área
        $user = backpack_user();
        if (! $user || ! $user->hasResponsableAreaOrInstituteAuthorityRole()) {
            abort(403, 'Solo los responsables de área pueden sugerir proveedores.');
        }

        $suppliers = \App\Models\Supplier::all();

        return view('admin.purchase-request.suggest-supplier', compact('purchaseRequest', 'suppliers'));
    }

    /**
     * Store supplier suggestion
     */
    public function storeSupplierSuggestion($id)
    {
        // Verificar que el usuario sea responsable de área
        $user = backpack_user();
        if (! $user || ! $user->hasResponsableAreaOrInstituteAuthorityRole()) {
            abort(403, 'Solo los responsables de área pueden sugerir proveedores.');
        }

        $purchaseRequest = \App\Models\PurchaseRequest::findOrFail($id);
        $request = request();

        // Validar que no exista ya una sugerencia del mismo usuario para el mismo proveedor
        $existingSuggestion = \App\Models\SupplierSuggestion::where('purchase_request_id', $id)
            ->where('supplier_id', $request->input('supplier_id'))
            ->where('suggested_by', $user->id)
            ->first();

        if ($existingSuggestion) {
            \Alert::error('Ya has sugerido este proveedor para esta solicitud.')->flash();

            return redirect()->back();
        }

        \App\Models\SupplierSuggestion::create([
            'purchase_request_id' => $id,
            'supplier_id' => $request->input('supplier_id'),
            'suggested_by' => $user->id,
            'justification' => $request->input('justification'),
        ]);

        \Alert::success('Proveedor sugerido exitosamente.')->flash();

        return redirect()->route('purchase-request.show', $id);
    }

    /**
     * Approve a purchase request
     */
    public function approvePurchaseRequest($id)
    {
        $purchaseRequest = \App\Models\PurchaseRequest::with(['marketRates', 'details.product'])->findOrFail($id);
        $user = backpack_user();

        if (! $user) {
            abort(403, 'No tienes permiso para aprobar solicitudes de compra.');
        }

        if (! in_array($purchaseRequest->status, ['Pendiente', 'En Proceso'], true)) {
            abort(403, 'Solo se pueden aprobar solicitudes con estado "Pendiente" o "En proceso".');
        }

        // No permitir aprobar sin cotización seleccionada (salvo compra directa).
        $hasAnySelectedQuotation = ! empty($purchaseRequest->selected_market_rate_id)
            || $purchaseRequest->marketRates->contains(function ($mr) {
                return (bool) ($mr->is_selected ?? false);
            });
        if (! $purchaseRequest->is_direct_purchase && ! $hasAnySelectedQuotation) {
            \Alert::error('Debe seleccionar al menos una cotización antes de aprobar la solicitud.')->flash();

            return redirect()->route('purchase-request.show', $id);
        }

        $httpRequest = request();
        $httpRequest->validate([
            'approval_justification' => 'required|string|max:1000',
        ]);

        $detailDecisions = [];
        if ($purchaseRequest->is_direct_purchase) {
            foreach ($purchaseRequest->details as $detail) {
                $detailDecisions[$detail->id] = \App\Models\PurchaseRequestDetail::LINE_AUTH_APPROVED;
            }
        } else {
            $lineDecision = $httpRequest->input('line_decision', []);
            if (! is_array($lineDecision)) {
                \Alert::error('Debe indicar para cada ítem si autoriza o no la compra.')->flash();

                return redirect()->route('purchase-request.show', $id);
            }
            $expectedIds = $purchaseRequest->details->pluck('id')->map(fn ($i) => (int) $i)->sort()->values()->all();
            $submittedIds = collect(array_keys($lineDecision))->map(fn ($i) => (int) $i)->sort()->values()->all();
            if ($expectedIds !== $submittedIds) {
                \Alert::error('Debe indicar para cada ítem si autoriza o no la compra.')->flash();

                return redirect()->route('purchase-request.show', $id);
            }
            foreach ($purchaseRequest->details as $detail) {
                $decision = $lineDecision[$detail->id] ?? null;
                if (! in_array($decision, [\App\Models\PurchaseRequestDetail::LINE_AUTH_APPROVED, \App\Models\PurchaseRequestDetail::LINE_AUTH_REJECTED], true)) {
                    \Alert::error('Decisión inválida en uno o más ítems.')->flash();

                    return redirect()->route('purchase-request.show', $id);
                }
                $detailDecisions[$detail->id] = $decision;
                if ($decision === \App\Models\PurchaseRequestDetail::LINE_AUTH_REJECTED) {
                    $reason = trim((string) ($httpRequest->input('line_rejection_reason')[$detail->id] ?? ''));
                    if ($reason === '') {
                        \Alert::error('Debe indicar el motivo del rechazo para cada ítem no autorizado.')->flash();

                        return redirect()->route('purchase-request.show', $id);
                    }
                    if (mb_strlen($reason) > 1000) {
                        \Alert::error('El motivo de rechazo por ítem no puede superar 1000 caracteres.')->flash();

                        return redirect()->route('purchase-request.show', $id);
                    }
                }
            }
        }

        $approvedSubtotal = 0.0;
        $rejectedItemsForMail = [];
        foreach ($purchaseRequest->details as $detail) {
            $decision = $detailDecisions[$detail->id] ?? \App\Models\PurchaseRequestDetail::LINE_AUTH_REJECTED;
            if ($decision === \App\Models\PurchaseRequestDetail::LINE_AUTH_APPROVED) {
                $approvedSubtotal += $detail->quotationSubtotalForPurchase($purchaseRequest);
            } else {
                $label = $detail->product ? $detail->product->name : ($detail->product_description ?? 'Producto #'.$detail->product_id);
                $rejectedItemsForMail[] = [
                    'label' => $label,
                    'reason' => trim((string) ($httpRequest->input('line_rejection_reason')[$detail->id] ?? '')),
                ];
            }
        }

        $anyApproved = collect($detailDecisions)->contains(\App\Models\PurchaseRequestDetail::LINE_AUTH_APPROVED);
        $anyRejected = collect($detailDecisions)->contains(\App\Models\PurchaseRequestDetail::LINE_AUTH_REJECTED);

        if (! $anyApproved) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($purchaseRequest, $user, $httpRequest, $detailDecisions) {
                foreach ($purchaseRequest->details as $detail) {
                    $detail->update([
                        'line_authorization_status' => \App\Models\PurchaseRequestDetail::LINE_AUTH_REJECTED,
                        'line_authorization_rejection_reason' => trim((string) ($httpRequest->input('line_rejection_reason')[$detail->id] ?? '')),
                        'line_authorized_by' => $user->id,
                        'line_authorized_at' => now(),
                    ]);
                }
                $purchaseRequest->update([
                    'status' => 'Rechazada',
                    'approved_by' => $user->id,
                    'approved_date' => now(),
                    'approval_justification' => $httpRequest->input('approval_justification'),
                    'requires_admin_approval' => false,
                    'total_amount' => 0,
                ]);
            });

            $this->dispatchPurchaseLineRejectionNotifications($purchaseRequest->fresh(['details.product', 'responsibilityArea.responsibleUser']), $user, $rejectedItemsForMail);

            \Alert::warning('Ningún ítem fue autorizado para compra. La solicitud quedó rechazada.')->flash();

            return redirect()->route('purchase-request.show', $id);
        }

        // Monto a validar por topes: solo ítems autorizados (compra directa sigue el total de cotización / flujo existente).
        $amountForLimits = $purchaseRequest->is_direct_purchase
            ? $this->recalculateSelectedQuotationsTotalForPurchaseRequest($purchaseRequest)
            : $approvedSubtotal;

        $comprasLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_responsable_compras');
        $requiresAdminApproval = $amountForLimits > $comprasLimit;

        $entryForApproval = clone $purchaseRequest;
        $entryForApproval->total_amount = $amountForLimits;
        $entryForApproval->requires_admin_approval = $requiresAdminApproval;

        $purchaseRequestForEscalateCheck = clone $purchaseRequest;
        $purchaseRequestForEscalateCheck->total_amount = $amountForLimits;

        if (! $entryForApproval->canBeApprovedBy($user)) {
            if (
                $user->hasRole('role_admin_institucion', 'backpack')
                && PurchaseRequestNotificationService::shouldAdministratorEscalateQuotationApproval($purchaseRequestForEscalateCheck)
            ) {
                \Illuminate\Support\Facades\DB::transaction(function () use ($purchaseRequest, $user, $httpRequest, $detailDecisions, $requiresAdminApproval, $amountForLimits, $anyRejected) {
                    foreach ($purchaseRequest->details as $detail) {
                        $decision = $detailDecisions[$detail->id];
                        $update = [
                            'line_authorization_status' => $decision,
                            'line_authorized_by' => $user->id,
                            'line_authorized_at' => now(),
                        ];
                        if ($decision === \App\Models\PurchaseRequestDetail::LINE_AUTH_REJECTED) {
                            $update['line_authorization_rejection_reason'] = trim((string) ($httpRequest->input('line_rejection_reason')[$detail->id] ?? ''));
                        } else {
                            $update['line_authorization_rejection_reason'] = null;
                        }
                        $detail->update($update);
                    }

                    $purchaseRequest->update([
                        'total_amount' => $amountForLimits,
                        'requires_admin_approval' => $requiresAdminApproval,
                        'admin_quotation_review_justification' => $httpRequest->input('approval_justification'),
                        'admin_quotation_reviewed_at' => now(),
                        'admin_quotation_reviewed_by' => $user->id,
                    ]);

                    PurchaseRequestEvent::record(
                        $purchaseRequest,
                        PurchaseRequestEvent::EVENT_ADMINISTRATION_INITIAL_REVIEW_PENDING_SUPERIOR,
                        $user->id,
                        [
                            'total_amount_for_limits' => $amountForLimits,
                            'requires_admin_approval' => $requiresAdminApproval,
                            'any_line_rejected' => $anyRejected,
                            'status' => $purchaseRequest->status,
                        ]
                    );
                });

                $purchaseRequest = $purchaseRequest->fresh(['details.product', 'responsibilityArea.responsibleUser']);

                if ($anyRejected) {
                    $this->dispatchPurchaseLineRejectionNotifications($purchaseRequest, $user, $rejectedItemsForMail);
                }

                \Alert::warning('Se guardaron en el sistema la decisión por ítem y la justificación. El monto supera el tope de la administradora del instituto: use «Solicitar aprobación de nivel superior» o espere la decisión de quien corresponda. La solicitud no quedará «Aprobada» hasta esa autorización por monto.')->flash();

                return redirect()->route('purchase-request.show', $id);
            }
            if ($user->hasRole('role_admin_institucion', 'backpack')) {
                $adminLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_admin_institucion');
                abort(403, 'No puedes aprobar esta solicitud de compra porque supera tu límite de autorización de $'.number_format($adminLimit, 2).'. El monto autorizado de los ítems seleccionados es $'.number_format($amountForLimits, 2).'.');
            }
            if ($user->hasRole('role_apoderado', 'backpack')) {
                $apoderadoLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_apoderado');
                abort(403, 'No puedes aprobar esta solicitud de compra porque supera tu límite de autorización de $'.number_format($apoderadoLimit, 2).'. El monto autorizado de los ítems seleccionados es $'.number_format($amountForLimits, 2).'.');
            }
            if ($user->hasRole('role_representante_legal', 'backpack')) {
                $representanteLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_representante_legal');
                abort(403, 'No puedes aprobar esta solicitud de compra porque supera tu límite de autorización de $'.number_format($representanteLimit, 2).'. El monto autorizado de los ítems seleccionados es $'.number_format($amountForLimits, 2).'.');
            }
            abort(403, 'No tienes permiso para aprobar esta solicitud de compra.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($purchaseRequest, $user, $httpRequest, $detailDecisions, $requiresAdminApproval, $amountForLimits) {
            foreach ($purchaseRequest->details as $detail) {
                $decision = $detailDecisions[$detail->id];
                $update = [
                    'line_authorization_status' => $decision,
                    'line_authorized_by' => $user->id,
                    'line_authorized_at' => now(),
                ];
                if ($decision === \App\Models\PurchaseRequestDetail::LINE_AUTH_REJECTED) {
                    $update['line_authorization_rejection_reason'] = trim((string) ($httpRequest->input('line_rejection_reason')[$detail->id] ?? ''));
                } else {
                    $update['line_authorization_rejection_reason'] = null;
                }
                $detail->update($update);
            }

            $purchaseRequest->update([
                'status' => 'Aprobada',
                'approved_by' => $user->id,
                'approved_date' => now(),
                'approval_justification' => $httpRequest->input('approval_justification'),
                'requires_admin_approval' => false,
                'superior_quotation_escalation_pending_at' => null,
                'total_amount' => $amountForLimits,
            ]);
        });

        $purchaseRequest = $purchaseRequest->fresh(['details.product', 'responsibilityArea.responsibleUser']);

        if ($anyRejected) {
            $this->dispatchPurchaseLineRejectionNotifications($purchaseRequest, $user, $rejectedItemsForMail);
        }

        $approverIsSuperior = $user->hasRole('role_admin_institucion', 'backpack')
            || $user->hasRole('role_apoderado', 'backpack')
            || $user->hasRole('role_representante_legal', 'backpack');
        if ($approverIsSuperior) {
            PurchaseRequestNotificationService::notifyComprasRequestApprovedBySuperior($purchaseRequest);
        }

        $msg = $anyRejected
            ? 'Solicitud procesada: hay ítems autorizados para compra y otros no autorizados (se notificó por correo al responsable del área).'
            : 'Solicitud de compra aprobada exitosamente.';
        \Alert::success($msg)->flash();

        return redirect()->route('purchase-request.show', $id);
    }

    /**
     * Correo por ítems no autorizados: siempre al responsable del área (no al usuario solicitante nominado).
     *
     * @param  list<array{label: string, reason: string}>  $rejectedItems
     */
    private function dispatchPurchaseLineRejectionNotifications(\App\Models\PurchaseRequest $purchaseRequest, $user, array $rejectedItems): void
    {
        if ($rejectedItems === []) {
            return;
        }

        $actorName = (string) ($user->name ?? 'Usuario');
        PurchaseRequestNotificationService::notifyAreaResponsiblePurchaseLinesRejected(
            $purchaseRequest,
            $actorName,
            $rejectedItems,
            $user instanceof \App\Models\User ? $user : null
        );
    }

    /**
     * Total cotizado a usar al generar OC (solo ítems autorizados si hubo rechazos parciales).
     */
    private function recalculatePurchaseOrderGenerationTotal(\App\Models\PurchaseRequest $purchaseRequest): float
    {
        $purchaseRequest->loadMissing('details.product', 'details.selectedMarketRate.quoteDetails');

        if (! $purchaseRequest->hasRejectedLineAuthorizations()) {
            return $this->recalculateSelectedQuotationsTotalForPurchaseRequest($purchaseRequest);
        }

        return (float) $purchaseRequest->details
            ->filter(fn ($d) => $d->line_authorization_status === \App\Models\PurchaseRequestDetail::LINE_AUTH_APPROVED)
            ->sum(fn ($d) => $d->quotationSubtotalForPurchase($purchaseRequest));
    }

    /**
     * Revoca la aprobación de la solicitud (solo representante legal, sin OC generada).
     */
    public function cancelPurchaseRequestApproval($id)
    {
        $user = backpack_user();
        $isRepresentanteLegal = $user && (
            $user->hasRole('role_representante_legal', 'backpack')
            || $user->hasRole('role_representante_legal', 'web')
            || $user->getRoleNames()->contains('role_representante_legal')
        );
        if (! $isRepresentanteLegal) {
            abort(403, 'Solo el representante legal puede cancelar una aprobación.');
        }

        $purchaseRequest = \App\Models\PurchaseRequest::with(['marketRates', 'purchaseOrders'])->findOrFail($id);

        if ($purchaseRequest->status !== 'Aprobada') {
            \Alert::error('Solo se puede cancelar la aprobación cuando la solicitud está en estado «Aprobada».')->flash();

            return redirect()->route('purchase-request.show', $id);
        }

        if ($purchaseRequest->purchaseOrders->isNotEmpty()) {
            \Alert::error('No se puede cancelar la aprobación: ya existen órdenes de compra asociadas a esta solicitud.')->flash();

            return redirect()->route('purchase-request.show', $id);
        }

        $request = request();
        $request->validate([
            'cancellation_reason' => 'required|string|max:1000',
        ]);

        $effectiveTotal = $this->recalculateSelectedQuotationsTotalForPurchaseRequest($purchaseRequest);
        $comprasLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_responsable_compras');
        $requiresAdminApproval = $effectiveTotal > $comprasLimit;

        $update = [
            'status' => 'Pendiente',
            'approved_by' => null,
            'approved_date' => null,
            'approval_justification' => null,
            'admin_quotation_reviewed_at' => null,
            'admin_quotation_reviewed_by' => null,
            'admin_quotation_review_justification' => null,
            'total_amount' => $effectiveTotal,
            'requires_admin_approval' => $requiresAdminApproval,
        ];

        if ($purchaseRequest->is_direct_purchase) {
            $update['direct_purchase_authorized_by'] = null;
            $update['direct_purchase_authorized_at'] = null;
            $update['direct_purchase_authorization_rejected'] = false;
            $update['direct_purchase_authorization_rejection_reason'] = null;
            $update['purchase_type'] = 'normal';
        }

        $purchaseRequest->update($update);

        $purchaseRequest->details()->update([
            'line_authorization_status' => \App\Models\PurchaseRequestDetail::LINE_AUTH_PENDING,
            'line_authorization_rejection_reason' => null,
            'line_authorized_by' => null,
            'line_authorized_at' => null,
        ]);

        \Log::info('Aprobación de solicitud de compra cancelada por representante legal', [
            'purchase_request_id' => $purchaseRequest->id,
            'user_id' => $user->id,
            'reason' => $request->input('cancellation_reason'),
        ]);

        \Alert::success('La aprobación fue anulada. La solicitud volvió a estado pendiente.')->flash();

        return redirect()->route('purchase-request.show', $id);
    }

    /**
     * Obtiene total efectivo de una cotización (priorizando total con IVA).
     */
    private function getMarketRateEffectiveTotal(\App\Models\MarketRate $marketRate): float
    {
        $totalWithVat = (float) ($marketRate->total_amount_with_vat ?? 0);
        if ($totalWithVat > 0) {
            return $totalWithVat;
        }

        $subtotal = (float) ($marketRate->total_amount ?? 0);
        $vat = (float) ($marketRate->vat_amount ?? 0);
        if ($subtotal > 0 || $vat > 0) {
            return $subtotal + max(0, $vat);
        }

        return (float) $marketRate->quoteDetails->sum(function ($detail) {
            return ((float) ($detail->quantity ?? 0)) * ((float) ($detail->unit_price ?? 0));
        });
    }

    /**
     * Cotizaciones que determinan el monto efectivo de la solicitud (is_selected o selected_market_rate_id).
     */
    private function marketRatesContributingToPurchaseRequestTotal(\App\Models\PurchaseRequest $purchaseRequest): \Illuminate\Support\Collection
    {
        $selectedRates = \App\Models\MarketRate::query()
            ->where('purchase_request_id', $purchaseRequest->id)
            ->where('is_selected', true)
            ->get();
        if ($selectedRates->isNotEmpty()) {
            return $selectedRates;
        }
        if (! empty($purchaseRequest->selected_market_rate_id)) {
            $single = \App\Models\MarketRate::query()->find($purchaseRequest->selected_market_rate_id);

            return $single ? collect([$single]) : collect();
        }

        return collect();
    }

    private function renderAdministratorEscalateSuperiorButtonHtml(
        \App\Models\PurchaseRequest $entry,
        ?float $effectiveTotal = null
    ): string {
        $user = backpack_user();
        if (! $user) {
            return '';
        }
        $canRequestHigherApproval = $user->hasRole('role_admin_institucion', 'backpack')
            || $user->hasRole('role_admin_sistema', 'backpack');
        if (! $canRequestHigherApproval) {
            return '';
        }

        $effectiveTotal ??= $entry->effectiveTotalForAuthorizationLimits();
        if (! PurchaseRequestNotificationService::shouldAdministratorEscalateQuotationApproval($entry, $effectiveTotal)) {
            return '';
        }

        $adminLimit = (float) \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_admin_institucion');
        $entry->loadMissing(['purchaseRequestEvents']);

        if ($entry->isAwaitingSuperiorApprovalAfterAdministratorEscalation()) {
            $lastEscalation = $entry->latestSuperiorEscalationEvent();
            $requestedAt = $lastEscalation && $lastEscalation->created_at
                ? ($lastEscalation->created_at instanceof \Carbon\Carbon
                    ? $lastEscalation->created_at->format('d/m/Y H:i')
                    : \Carbon\Carbon::parse($lastEscalation->created_at)->format('d/m/Y H:i'))
                : '—';
            $requestedBy = $lastEscalation?->user ? e($lastEscalation->user->name) : null;

            return '<div class="alert alert-success mb-3 purchase-request-superior-escalation-alert">'
                .'<p class="mb-2"><strong>Solicitud enviada al nivel superior</strong></p>'
                .'<p class="mb-2 small">Ya se notificó al nivel superior (apoderado o representante legal, según monto) el <strong>'.e($requestedAt).'</strong>'
                .($requestedBy ? ' por '.$requestedBy : '')
                .'. La solicitud permanece pendiente de esa aprobación antes de quedar en estado «Aprobada».</p>'
                .'<p class="mb-2 small">Si necesita reiterar el pedido, puede enviar el recordatorio nuevamente.</p>'
                .'<form method="POST" action="'.e(route('purchase-request.request-quotation-higher-level-authorization', $entry->id)).'" class="d-inline mt-1">'
                .csrf_field()
                .'<button type="submit" class="btn btn-primary purchase-request-superior-escalation-alert__btn"><i class="la la-envelope"></i> Solicitar nuevamente aprobación de nivel superior</button>'
                .'</form>'
                .'</div>';
        }

        return '<div class="alert alert-info mb-3">'
            .'<p class="mb-2"><strong>Escalamiento de aprobación</strong></p>'
            .'<p class="mb-2 small">El monto efectivo de la solicitud (<strong>$'.number_format($effectiveTotal, 2).'</strong>) supera su límite de autorización ($'.number_format($adminLimit, 2).'). Puede solicitar la intervención del nivel superior correspondiente (apoderado o representante legal, según monto).</p>'
            .'<form method="POST" action="'.e(route('purchase-request.request-quotation-higher-level-authorization', $entry->id)).'" class="d-inline">'
            .csrf_field()
            .'<button type="submit" class="btn btn-info"><i class="la la-level-up"></i> Solicitar aprobación de nivel superior</button>'
            .'</form>'
            .'</div>';
    }

    /**
     * Escalamiento ante nivel superior y reapertura tras observaciones (sección aparte en la vista show).
     */
    private function renderSuperiorQuotationEscalationHtml(\App\Models\PurchaseRequest $entry): string
    {
        $user = backpack_user();
        if (! $user) {
            return '';
        }
        $effectiveTotal = $entry->effectiveTotalForAuthorizationLimits();
        $html = $this->renderAdministratorEscalateSuperiorButtonHtml($entry, $effectiveTotal);
        $entry->loadMissing(['purchaseOrders', 'details']);
        $canReopenAfterSuperiorRevision = (
            $user->hasRole('role_admin_institucion', 'backpack')
            || $user->hasRole('role_admin_sistema', 'backpack')
        ) && $entry->canReopenForSuperiorAuthorizationAfterRevision();
        if ($canReopenAfterSuperiorRevision) {
            $reopenFieldId = 'reopen_justification_superior_'.$entry->id;
            $html .= '<div class="alert alert-secondary mb-3">';
            $html .= '<p class="mb-2"><strong>Revisión tras observaciones del nivel superior</strong></p>';
            $html .= '<p class="mb-2 small">Si ajustó cotizaciones o la asignación por producto, puede reabrir la solicitud: las autorizaciones por ítem volverán a <strong>pendiente</strong> y se notificará al nivel correspondiente para una nueva decisión. No use esta acción si ya generó orden de compra.</p>';
            $html .= '<form method="POST" action="'.e(route('purchase-request.request-superior-reapproval-after-revision', $entry->id)).'" class="mt-2">';
            $html .= csrf_field();
            $html .= '<div class="mb-2"><label for="'.$reopenFieldId.'" class="form-label small mb-0">Notas internas (opcional, queda en el registro del sistema):</label>';
            $html .= '<textarea name="reopen_justification" id="'.$reopenFieldId.'" class="form-control form-control-sm" rows="2" maxlength="1000"></textarea></div>';
            $html .= '<button type="submit" class="btn btn-outline-secondary" onclick="return confirm(\'¿Confirma reabrir la solicitud para nueva autorización por ítem? Se anularán las decisiones por línea actuales.\')">';
            $html .= '<i class="la la-refresh"></i> Reabrir para nueva autorización (post-observaciones)</button>';
            $html .= '</form>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Recalcula total de la solicitud según cotizaciones seleccionadas (incluye IVA).
     */
    private function recalculateSelectedQuotationsTotalForPurchaseRequest(\App\Models\PurchaseRequest $purchaseRequest): float
    {
        return $purchaseRequest->effectiveTotalForAuthorizationLimits();
    }

    /**
     * Reject a purchase request
     */
    public function rejectPurchaseRequest($id)
    {
        $purchaseRequest = \App\Models\PurchaseRequest::findOrFail($id);
        $user = backpack_user();

        if (! $user) {
            abort(403, 'No tienes permiso para rechazar solicitudes de compra.');
        }

        // Verificar si el usuario puede aprobar/rechazar esta solicitud
        if (! $purchaseRequest->canBeApprovedBy($user)) {
            abort(403, 'No tienes permiso para rechazar esta solicitud de compra.');
        }

        if (! in_array($purchaseRequest->status, ['Pendiente', 'En Proceso'], true)) {
            abort(403, 'Solo se pueden rechazar solicitudes con estado "Pendiente" o "En proceso".');
        }

        // Actualizar la solicitud como rechazada
        $purchaseRequest->update([
            'status' => 'Rechazada',
            'approved_by' => $user->id,
            'approved_date' => now(),
        ]);

        $purchaseRequest->details()->update([
            'line_authorization_status' => \App\Models\PurchaseRequestDetail::LINE_AUTH_REJECTED,
            'line_authorization_rejection_reason' => null,
            'line_authorized_by' => $user->id,
            'line_authorized_at' => now(),
        ]);

        PurchaseRequestNotificationService::notifyAreaResponsiblePurchaseRequestFullyRejected(
            $purchaseRequest->fresh(['responsibilityArea.responsibleUser']),
            (string) ($user->name ?? 'Usuario'),
            $user instanceof \App\Models\User ? $user : null
        );

        \Alert::warning('Solicitud de compra rechazada.')->flash();

        return redirect()->route('purchase-request.show', $id);
    }

    /**
     * Mark purchase request as direct purchase (solo sector de compras)
     */
    public function markAsDirectPurchase($id)
    {
        $purchaseRequest = \App\Models\PurchaseRequest::findOrFail($id);
        $user = backpack_user();

        if (! $user) {
            abort(403, 'No tienes permiso para marcar compras directas.');
        }

        if (! $this->userCanUseComprasSectorDirectPurchase($user)) {
            abort(403, 'Solo el sector de compras o la administración del instituto pueden marcar compras directas.');
        }

        if (! $this->statusAllowsComprasDirectPurchaseSuggestion((string) $purchaseRequest->status)) {
            abort(403, 'Solo se pueden marcar como compra directa las solicitudes con estado "Pendiente" o "En Proceso".');
        }

        $purchaseRequest->loadMissing(['purchaseRequestEvents']);
        if ($purchaseRequest->isFrozenPendingSuperiorApproval()) {
            abort(403, 'No se puede sugerir compra directa mientras está pendiente la aprobación de nivel superior.');
        }

        $request = request();
        $request->validate([
            'direct_purchase_supplier_id' => 'required|exists:suppliers,id',
            'direct_purchase_justification' => 'required|string|max:1000',
        ]);

        // Marcar como compra directa y solicitar autorización automáticamente
        $purchaseRequest->update([
            'is_direct_purchase' => true,
            'direct_purchase_supplier_id' => $request->input('direct_purchase_supplier_id'),
            'direct_purchase_justification' => $request->input('direct_purchase_justification'),
            'direct_purchase_authorization_requested' => true,
            'direct_purchase_authorization_requested_by' => $user->id,
            'direct_purchase_authorization_requested_at' => now(),
        ]);

        PurchaseRequestNotificationService::notifySuperiorsDirectPurchaseAuthorizationRequested($purchaseRequest->fresh());

        \Alert::success('Solicitud marcada como compra directa y autorización solicitada exitosamente. La solicitud está pendiente de aprobación por parte del administrador, apoderado o representante legal.')->flash();

        return redirect()->route('purchase-request.show', $id);
    }

    /**
     * Approve direct purchase authorization
     */
    public function approveDirectPurchase($id)
    {
        $purchaseRequest = \App\Models\PurchaseRequest::findOrFail($id);
        $user = backpack_user();

        if (! $user) {
            abort(403, 'No tienes permiso para aprobar compras directas.');
        }

        // Solo administrador, apoderado o representante legal pueden aprobar
        $canApprove = $user->hasRole('role_admin_institucion', 'backpack')
                   || $user->hasRole('role_apoderado', 'backpack')
                   || $user->hasRole('role_representante_legal', 'backpack');

        if (! $canApprove) {
            abort(403, 'Solo el administrador del instituto, apoderado o representante legal pueden aprobar compras directas.');
        }

        // Validar que sea una compra directa y que se haya solicitado autorización
        if (! $purchaseRequest->is_direct_purchase) {
            abort(403, 'Esta solicitud no está marcada como compra directa.');
        }

        if (! $purchaseRequest->direct_purchase_authorization_requested) {
            abort(403, 'No se ha solicitado autorización para esta compra directa.');
        }

        // Validar que no esté ya aprobada o rechazada
        if ($purchaseRequest->direct_purchase_authorized_by || $purchaseRequest->direct_purchase_authorization_rejected) {
            abort(403, 'Esta compra directa ya ha sido procesada.');
        }

        // Validar que el usuario tenga límite suficiente para aprobar
        $userLimit = 0;
        $userRoleName = '';

        if ($user->hasRole('role_admin_institucion', 'backpack')) {
            $userLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_admin_institucion');
            $userRoleName = 'administrador del instituto';
        } elseif ($user->hasRole('role_apoderado', 'backpack')) {
            $userLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_apoderado');
            $userRoleName = 'apoderado';
        } elseif ($user->hasRole('role_representante_legal', 'backpack')) {
            $userLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_representante_legal');
            $userRoleName = 'representante legal';
        }

        if ($userLimit > 0 && $purchaseRequest->total_amount > $userLimit) {
            abort(403, 'No puedes aprobar esta compra directa porque supera tu límite de autorización de $'.number_format($userLimit, 2).'. El monto de la compra directa es $'.number_format($purchaseRequest->total_amount, 2).'.');
        }

        // Aprobar la compra directa y la solicitud, y cambiar el tipo de compra
        $purchaseRequest->update([
            'direct_purchase_authorized_by' => $user->id,
            'direct_purchase_authorized_at' => now(),
            'direct_purchase_authorization_rejected' => false,
            'status' => 'Aprobada',
            'approved_by' => $user->id,
            'approved_date' => now(),
            'requires_admin_approval' => false,
            'purchase_type' => 'directa',
        ]);

        $purchaseRequest->details()->update([
            'line_authorization_status' => \App\Models\PurchaseRequestDetail::LINE_AUTH_APPROVED,
            'line_authorization_rejection_reason' => null,
            'line_authorized_by' => $user->id,
            'line_authorized_at' => now(),
        ]);

        PurchaseRequestNotificationService::notifyComprasRequestApprovedBySuperior($purchaseRequest->fresh());

        \Alert::success('Compra directa aprobada exitosamente. La solicitud de compra ha sido aprobada.')->flash();

        return redirect()->route('purchase-request.show', $id);
    }

    /**
     * Reject direct purchase authorization
     */
    public function rejectDirectPurchaseAuthorization($id)
    {
        $purchaseRequest = \App\Models\PurchaseRequest::findOrFail($id);
        $user = backpack_user();

        if (! $user) {
            abort(403, 'No tienes permiso para rechazar compras directas.');
        }

        // Solo administrador, apoderado o representante legal pueden rechazar
        $canReject = $user->hasRole('role_admin_institucion', 'backpack')
                  || $user->hasRole('role_apoderado', 'backpack')
                  || $user->hasRole('role_representante_legal', 'backpack');

        if (! $canReject) {
            abort(403, 'Solo el administrador del instituto, apoderado o representante legal pueden rechazar compras directas.');
        }

        // Validar que sea una compra directa y que se haya solicitado autorización
        if (! $purchaseRequest->is_direct_purchase) {
            abort(403, 'Esta solicitud no está marcada como compra directa.');
        }

        if (! $purchaseRequest->direct_purchase_authorization_requested) {
            abort(403, 'No se ha solicitado autorización para esta compra directa.');
        }

        // Validar que no esté ya aprobada o rechazada
        if ($purchaseRequest->direct_purchase_authorized_by || $purchaseRequest->direct_purchase_authorization_rejected) {
            abort(403, 'Esta compra directa ya ha sido procesada.');
        }

        $request = request();
        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        // Rechazar la autorización
        $purchaseRequest->update([
            'direct_purchase_authorization_rejected' => true,
            'direct_purchase_authorization_rejection_reason' => $request->input('rejection_reason'),
        ]);

        $purchaseRequest->details()->update([
            'line_authorization_status' => \App\Models\PurchaseRequestDetail::LINE_AUTH_REJECTED,
            'line_authorization_rejection_reason' => null,
            'line_authorized_by' => $user->id,
            'line_authorized_at' => now(),
        ]);

        PurchaseRequestNotificationService::notifyAreaResponsibleDirectPurchaseAuthorizationRejected(
            $purchaseRequest->fresh(['responsibilityArea.responsibleUser']),
            (string) ($user->name ?? 'Usuario'),
            (string) $request->input('rejection_reason'),
            $user instanceof \App\Models\User ? $user : null
        );

        \Alert::warning('Autorización de compra directa rechazada.')->flash();

        return redirect()->route('purchase-request.show', $id);
    }

    /**
     * Asignar cotización por producto (para generar una OC con varios proveedores)
     */
    public function assignQuotations($id)
    {
        $user = backpack_user();
        if ($user && $user->hasResponsableAreaOrInstituteAuthorityRole()) {
            abort(403, 'Los responsables de área no pueden asignar cotizaciones.');
        }
        if ($user && $user->hasRole('role_representante_legal', 'backpack')) {
            abort(403, 'El representante legal no puede asignar cotización por producto.');
        }

        $purchaseRequest = \App\Models\PurchaseRequest::with(['details.product', 'marketRates.quoteDetails'])->findOrFail($id);
        if ($purchaseRequest->status === 'Completada') {
            \Alert::error('No se puede modificar la asignación: la solicitud ya está completada.')->flash();

            return redirect()->back();
        }

        if ($purchaseRequest->isFrozenPendingSuperiorApproval()) {
            \Alert::error('No se puede modificar la asignación de cotizaciones mientras está pendiente la aprobación de nivel superior.')->flash();

            return redirect()->back();
        }

        if ($purchaseRequest->locksQuotationAndAssignmentChanges()) {
            \Alert::error(
                $purchaseRequest->wasApprovedBySuperiorAuthority()
                    ? 'No se puede modificar la asignación: la solicitud ya fue aprobada por el nivel superior.'
                    : 'No se puede modificar la asignación: la solicitud ya está aprobada.'
            )->flash();

            return redirect()->back();
        }

        if ($this->userIsResponsableComprasSinAdmin($user) && ! $this->statusAllowsComprasSinAdminQuotationSelection((string) $purchaseRequest->status)) {
            \Alert::error('No se puede modificar la asignación por producto: la solicitud debe estar Pendiente o En proceso.')->flash();

            return redirect()->back();
        }

        try {
            \App\Models\PurchaseRequestDetail::ensureSelectedMarketRateIdColumnExists();
        } catch (\Throwable $e) {
            \Log::error('ensureSelectedMarketRateIdColumnExists failed', ['exception' => $e]);
            \Alert::error('No se pudo preparar la base de datos para asignar cotizaciones. Ejecute: php artisan migrate --path=database/migrations/2026_04_15_130000_ensure_selected_market_rate_id_on_purchase_request_details_table.php')->flash();

            return redirect()->back();
        }

        $detailQuotes = request()->input('detail_quote', []);
        $marketRateIds = $purchaseRequest->marketRates->pluck('id')->toArray();
        $quoteDetailsByRate = $purchaseRequest->marketRates->keyBy('id')->map->quoteDetails->keyBy('product_id');

        foreach ($purchaseRequest->details as $detail) {
            $marketRateId = isset($detailQuotes[$detail->id]) ? (int) $detailQuotes[$detail->id] : null;
            if ($marketRateId === null) {
                \App\Models\PurchaseRequestDetail::where('id', $detail->id)->update(['selected_market_rate_id' => null]);

                continue;
            }
            if (! in_array($marketRateId, $marketRateIds)) {
                \Alert::error('Cotización inválida para el producto '.($detail->product->name ?? '').'.')->flash();

                return redirect()->back();
            }
            $quoteDetail = $purchaseRequest->marketRates->firstWhere('id', $marketRateId)
                ->quoteDetails->firstWhere('product_id', $detail->product_id);
            if (! $quoteDetail) {
                \Alert::error('La cotización seleccionada no incluye el producto: '.($detail->product->name ?? '').'.')->flash();

                return redirect()->back();
            }
            \App\Models\PurchaseRequestDetail::where('id', $detail->id)->update(['selected_market_rate_id' => $marketRateId]);
        }

        \Alert::success('Asignación por producto guardada correctamente.')->flash();

        return redirect()->back();
    }

    private function renderPurchaseOrderIssueDateFieldHtml(): string
    {
        return '<div class="row mb-3"><div class="col-md-4">'
            .'<label for="purchase_order_issue_date" class="form-label">Fecha de Emisión:</label>'
            .'<input type="date" name="issue_date" id="purchase_order_issue_date" class="form-control" value="'.e(date('Y-m-d')).'" required>'
            .'</div></div>';
    }

    /**
     * Formulario y avisos para generar orden de compra (sección Órdenes de Compra Asociadas).
     */
    private function renderGeneratePurchaseOrderFormHtml(\App\Models\PurchaseRequest $entry): string
    {
        $user = backpack_user();
        if (! $user instanceof \App\Models\User || ! $user->canGeneratePurchaseOrders()) {
            return '';
        }
        if ($entry->status === 'Completada') {
            return '';
        }

        $entry->loadMissing(['marketRates.quoteDetails', 'details', 'details.product']);
        $representanteLegalSinAsignarPorProducto = $user->hasRole('role_representante_legal', 'backpack');
        $totalAmount = $this->recalculateSelectedQuotationsTotalForPurchaseRequest($entry);
        $threshold = \App\Models\PurchaseRequest::quotationCoverageThresholdAmount();
        $minQuotations = \App\Models\PurchaseRequest::minimumQuotationsRequiredAboveThreshold();
        $quotationsCount = $entry->marketRates->count();

        $html = '';
        $isDirectPurchaseAuthorized = $entry->is_direct_purchase
            && $entry->direct_purchase_authorized_by
            && $entry->direct_purchase_supplier_id
            && ! $entry->direct_purchase_authorization_rejected;

        if ($isDirectPurchaseAuthorized) {
            if ($entry->status !== 'Aprobada') {
                $html .= '<div class="mt-3 alert alert-warning"><i class="la la-exclamation-triangle"></i> <strong>Atención:</strong> La solicitud debe estar aprobada antes de generar la orden de compra.</div>';
            } else {
                $html .= '<div class="mt-3"><div class="alert alert-success"><i class="la la-check-circle"></i> <strong>Compra Directa Autorizada:</strong> Puede proceder a generar la orden de compra.</div></div>';
                $html .= '<form method="POST" action="'.route('purchase-request.generate-purchase-order', $entry->id).'">'.csrf_field();
                $html .= $this->renderPurchaseOrderIssueDateFieldHtml();
                $html .= '<button type="submit" class="btn btn-primary" onclick="return confirm(\'¿Está seguro de generar la orden de compra?\')"><i class="la la-shopping-cart"></i> Generar Orden de Compra</button></form></div>';
            }
        } elseif ($totalAmount > $threshold) {
            if ($entry->status !== 'Aprobada') {
                $html .= '<div class="mt-3 alert alert-warning"><i class="la la-exclamation-triangle"></i> <strong>Atención:</strong> La solicitud debe estar aprobada antes de generar la orden de compra.</div>';
            } elseif ($quotationsCount < $minQuotations) {
                $missingQuotes = max(0, $minQuotations - $quotationsCount);
                $html .= '<div class="mt-3 alert alert-danger"><i class="la la-exclamation-triangle"></i> <strong>No se puede generar la orden de compra:</strong> Para solicitudes mayores a $'.number_format($threshold, 2).' se requieren <strong>al menos '.$minQuotations.' cotizaciones</strong>. Actualmente hay '.$quotationsCount.' cotización(es). Debe agregar '.$missingQuotes.' cotización(es) más.</div>';
            } elseif ($entry->getProductsWithFewerThanThreeQuotations()->isNotEmpty()) {
                $productNames = $entry->getProductsWithFewerThanThreeQuotations()->pluck('name')->implode(', ');
                $html .= '<div class="mt-3 alert alert-danger"><i class="la la-exclamation-triangle"></i> <strong>No se puede generar la orden de compra:</strong> Los siguientes productos deben estar cotizados en <strong>al menos '.$minQuotations.' cotizaciones distintas</strong>: '.e($productNames).'.</div>';
            } else {
                $allDetailsAssigned = $entry->details->isNotEmpty() && $entry->details->every(fn ($d) => ! empty($d->selected_market_rate_id));
                $canGenerateWithQuote = $entry->selected_market_rate_id || $allDetailsAssigned;
                if (! $canGenerateWithQuote) {
                    $html .= '<div class="mt-3 alert alert-warning"><i class="la la-exclamation-triangle"></i> <strong>Atención:</strong> Debe seleccionar una cotización o asignar una por producto en la sección de cotizaciones.</div>';
                } else {
                    $html .= '<div class="mt-3"><div class="alert alert-success"><i class="la la-check-circle"></i> <strong>Listo para generar orden.</strong> Puede proceder a generar la orden de compra.</div>';
                    $html .= '<form method="POST" action="'.route('purchase-request.generate-purchase-order', $entry->id).'">'.csrf_field();
                    $html .= $this->renderPurchaseOrderIssueDateFieldHtml();
                    $html .= '<button type="submit" class="btn btn-primary" onclick="return confirm(\'¿Está seguro de generar la orden de compra?\')"><i class="la la-shopping-cart"></i> Generar Orden de Compra</button></form></div>';
                }
            }
        } else {
            $html .= '<div class="mt-3">';
            if ($totalAmount == 0) {
                $html .= '<div class="alert alert-warning"><i class="la la-exclamation-triangle"></i> Debe asignar precios a los productos antes de generar la orden de compra.</div>';
            } elseif ($quotationsCount == 0) {
                $html .= '<div class="alert alert-info"><i class="la la-info-circle"></i> Requiere al menos una cotización cargada.</div>';
            } elseif ($quotationsCount == 1 && ! $entry->selected_market_rate_id) {
                $html .= '<div class="alert alert-warning"><i class="la la-exclamation-triangle"></i> Debe seleccionar la cotización cargada.</div>';
            } elseif ($quotationsCount == 1 && $entry->selected_market_rate_id) {
                if ($entry->status !== 'Aprobada') {
                    $html .= '<div class="alert alert-warning"><i class="la la-exclamation-triangle"></i> La solicitud debe estar aprobada antes de generar la orden de compra.</div>';
                } else {
                    $html .= '<div class="alert alert-success"><i class="la la-check-circle"></i> <strong>Listo para generar orden.</strong></div>';
                    $html .= '<form method="POST" action="'.route('purchase-request.generate-purchase-order', $entry->id).'">'.csrf_field();
                    $html .= $this->renderPurchaseOrderIssueDateFieldHtml();
                    $html .= '<div class="text-end"><button type="submit" class="btn btn-primary" onclick="return confirm(\'¿Está seguro de generar la orden de compra?\')"><i class="la la-shopping-cart"></i> Generar Orden de Compra</button></div></form>';
                }
            } else {
                $allDetailsAssignedLow = $entry->details->isNotEmpty() && $entry->details->every(fn ($d) => ! empty($d->selected_market_rate_id));
                if ($entry->selected_market_rate_id || $allDetailsAssignedLow) {
                    $html .= '<div class="alert alert-success"><i class="la la-check-circle"></i> <strong>Listo para generar orden.</strong></div>';
                    $html .= '<form method="POST" action="'.route('purchase-request.generate-purchase-order', $entry->id).'">'.csrf_field();
                    $html .= $this->renderPurchaseOrderIssueDateFieldHtml();
                    $html .= '<button type="submit" class="btn btn-primary" onclick="return confirm(\'¿Está seguro de generar la orden de compra?\')"><i class="la la-shopping-cart"></i> Generar Orden de Compra</button></form>';
                } else {
                    $html .= '<div class="alert alert-warning"><i class="la la-exclamation-triangle"></i> Seleccione o asigne cotización por producto en la sección de cotizaciones.</div>';
                }
            }
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Generate purchase order from selected market rate or directly if amount <= 60000
     * Si todos los detalles tienen asignada una cotización (selected_market_rate_id), genera una OC con varios proveedores.
     */
    public function generatePurchaseOrder($id)
    {
        $user = backpack_user();
        if (! $user instanceof \App\Models\User || ! $user->canGeneratePurchaseOrders()) {
            abort(403, 'No tiene permiso para generar órdenes de compra.');
        }

        $purchaseRequest = \App\Models\PurchaseRequest::with([
            'selectedMarketRate.supplier',
            'selectedMarketRate.quoteDetails.product',
            'details.product',
            'details.selectedMarketRate.supplier',
            'details.selectedMarketRate.quoteDetails.product',
            'responsibilityArea',
        ])->findOrFail($id);

        try {
            \App\Models\PurchaseRequestDetail::ensureSelectedMarketRateIdColumnExists();
            \App\Models\PurchaseOrderDetail::ensureSupplierIdColumnExists();
            $purchaseRequest->load([
                'details.product',
                'details.selectedMarketRate.supplier',
                'details.selectedMarketRate.quoteDetails.product',
            ]);
        } catch (\Throwable $e) {
            \Log::error('ensure purchase request / OC detail columns failed', ['exception' => $e]);
        }

        // Verificar que la solicitud esté aprobada antes de generar la orden
        if ($purchaseRequest->status !== 'Aprobada') {
            if ($purchaseRequest->requires_admin_approval) {
                \Alert::error('No se puede generar la orden de compra. La solicitud requiere aprobación del administrador del instituto debido a que supera el límite de autorización.')->flash();
            } else {
                \Alert::error('No se puede generar la orden de compra. La solicitud debe estar aprobada primero.')->flash();
            }

            return redirect()->back();
        }

        // Si es compra directa autorizada, generar orden directamente sin cotizaciones
        if ($purchaseRequest->is_direct_purchase
            && $purchaseRequest->direct_purchase_authorized_by
            && $purchaseRequest->direct_purchase_supplier_id
            && ! $purchaseRequest->direct_purchase_authorization_rejected) {
            return $this->generatePurchaseOrderWithoutQuote($purchaseRequest, $purchaseRequest->direct_purchase_supplier_id);
        }

        $totalAmount = $this->recalculatePurchaseOrderGenerationTotal($purchaseRequest);
        $purchaseRequest->update([
            'total_amount' => $totalAmount,
        ]);
        $threshold = \App\Models\PurchaseRequest::quotationCoverageThresholdAmount();
        $minQuotations = \App\Models\PurchaseRequest::minimumQuotationsRequiredAboveThreshold();
        $quotationsCount = $this->countQuotationsForPurchaseRequest($purchaseRequest);
        $detailsForAssignment = in_array($purchaseRequest->status, ['Aprobada'], true)
            ? $purchaseRequest->details->filter(fn ($d) => $d->line_authorization_status === \App\Models\PurchaseRequestDetail::LINE_AUTH_APPROVED)
            : $purchaseRequest->details;
        $allDetailsHaveAssignment = $detailsForAssignment->isNotEmpty()
            && $detailsForAssignment->every(fn ($d) => ! empty($d->selected_market_rate_id));

        // Flujo: asignación por producto (una OC con varios proveedores)
        if ($allDetailsHaveAssignment) {
            if ($totalAmount > $threshold && $quotationsCount < $minQuotations) {
                \Alert::error('Para solicitudes mayores a $'.number_format($threshold, 2).' se requieren al menos '.$minQuotations.' cotizaciones.')->flash();

                return redirect()->back();
            }
            $productsWithFewer = $purchaseRequest->getProductsWithFewerThanThreeQuotations();
            if ($totalAmount > $threshold && $productsWithFewer->isNotEmpty()) {
                \Alert::error('Cada producto debe estar en al menos '.$minQuotations.' cotizaciones. Productos faltantes: '.$productsWithFewer->pluck('name')->implode(', ').'.')->flash();

                return redirect()->back();
            }

            return $this->generatePurchaseOrderFromPerProductAssignment($purchaseRequest);
        }

        // Flujo clásico: una cotización para toda la solicitud
        if ($totalAmount > $threshold) {
            if ($quotationsCount < $minQuotations) {
                $missingQuotes = max(0, $minQuotations - $quotationsCount);
                \Alert::error('Para solicitudes de compra mayores a $'.number_format($threshold, 2).' se requieren al menos '.$minQuotations.' cotizaciones. Actualmente hay '.$quotationsCount.' cotización(es). Debe agregar '.$missingQuotes.' cotización(es) más antes de generar la orden de compra.')->flash();

                return redirect()->back();
            }
            $productsWithFewerQuotations = $purchaseRequest->getProductsWithFewerThanThreeQuotations();
            if ($productsWithFewerQuotations->isNotEmpty()) {
                $names = $productsWithFewerQuotations->pluck('name')->implode(', ');
                \Alert::error('No se puede generar la orden: los siguientes productos deben estar cotizados en al menos '.$minQuotations.' cotizaciones distintas: '.$names.'. Agregue estos productos a más cotizaciones antes de generar la orden de compra.')->flash();

                return redirect()->back();
            }
            if (! $purchaseRequest->selected_market_rate_id) {
                \Alert::error('Debe seleccionar una cotización antes de generar la orden de compra, o asignar una cotización por producto en la sección inferior.')->flash();

                return redirect()->back();
            }

            return $this->generatePurchaseOrderFromQuote($purchaseRequest);
        }
        if (! $purchaseRequest->selected_market_rate_id) {
            \Alert::error('Debe seleccionar una cotización antes de generar la orden de compra, o asignar una cotización por producto en la sección inferior.')->flash();

            return redirect()->back();
        }

        return $this->generatePurchaseOrderFromQuote($purchaseRequest);
    }

    /**
     * Generar una sola OC con líneas de distintas cotizaciones (varios proveedores)
     */
    private function generatePurchaseOrderFromPerProductAssignment($purchaseRequest)
    {
        $request = request();
        $issueDate = $request->input('issue_date') ? \Carbon\Carbon::parse($request->input('issue_date')) : now();

        $linesBySupplier = [];
        $paymentConditionsBySupplierId = [];
        foreach ($purchaseRequest->details as $requestDetail) {
            if (! $requestDetail->isAuthorizedForPurchaseOrder()) {
                continue;
            }
            $marketRate = $requestDetail->selectedMarketRate;
            if (! $marketRate || ! $requestDetail->product) {
                continue;
            }
            $quoteDetail = $marketRate->quoteDetails->firstWhere('product_id', $requestDetail->product_id);
            if (! $quoteDetail) {
                continue;
            }
            $input = $this->findOrCreateInputFromProduct($quoteDetail->product);
            if (! $input) {
                continue;
            }
            $unitPrice = $this->parseMonetaryValue($quoteDetail->unit_price);
            $sid = $marketRate->supplier_id;
            if (! isset($linesBySupplier[$sid])) {
                $linesBySupplier[$sid] = [];
            }
            $pm = $marketRate->payment_method;
            if ($pm !== null && trim((string) $pm) !== '' && ! isset($paymentConditionsBySupplierId[$sid])) {
                $paymentConditionsBySupplierId[$sid] = trim((string) $pm);
            }
            $linesBySupplier[$sid][] = [
                'input_id' => $input->id,
                'quantity' => $quoteDetail->quantity,
                'unit_price' => $unitPrice,
            ];
        }

        if ($linesBySupplier === []) {
            \Alert::error('No se pudo generar la orden: no hay líneas válidas con cotización asignada.')->flash();

            return redirect()->back();
        }

        ksort($linesBySupplier, SORT_NUMERIC);

        $area = $purchaseRequest->responsibilityArea;
        $letter = $area ? $area->purchaseOrderLetter() : 'X';
        $year = (int) now()->year;

        $createdOrders = [];
        \Illuminate\Support\Facades\DB::transaction(function () use (
            $purchaseRequest,
            $issueDate,
            $linesBySupplier,
            $paymentConditionsBySupplierId,
            $letter,
            $year,
            &$createdOrders
        ) {
            $correlative = \App\Models\PurchaseOrder::nextCorrelativeForAreaAndYear($letter, $year);
            $supplierIndex = 1;
            foreach ($linesBySupplier as $supplierId => $lines) {
                $orderNumber = \App\Models\PurchaseOrder::formatPurchaseOrderNumber($letter, $year, $correlative, $supplierIndex);
                $purchaseOrder = \App\Models\PurchaseOrder::create([
                    'number' => $orderNumber,
                    'date' => now(),
                    'issue_date' => $issueDate,
                    'supplier_id' => $supplierId,
                    'authorizing_user_id' => auth()->id(),
                    'status' => 'Pendiente',
                    'purchase_request_id' => $purchaseRequest->id,
                    'payment_conditions' => $paymentConditionsBySupplierId[$supplierId] ?? null,
                ]);
                foreach ($lines as $line) {
                    \App\Models\PurchaseOrderDetail::create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'supplier_id' => $supplierId,
                        'input_id' => $line['input_id'],
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                    ]);
                }
                $createdOrders[] = $purchaseOrder;
                $supplierIndex++;
            }

            $newType = ($purchaseRequest->purchase_type === 'internet') ? 'internet' : 'normal';
            $purchaseRequest->update(['status' => 'Completada', 'purchase_type' => $newType]);
        });

        $numbers = collect($createdOrders)->pluck('number')->implode(', ');
        $msg = count($createdOrders) > 1
            ? 'Órdenes de compra generadas ('.count($createdOrders).' proveedores): '.$numbers
            : 'Orden de compra generada exitosamente: '.$numbers;
        \Alert::success($msg)->flash();

        PurchaseRequestNotificationService::notifyAdministratorPurchaseOrdersCreated($purchaseRequest, $createdOrders);

        return redirect()->route('purchase-order.show', $createdOrders[0]->id);
    }

    /**
     * Generate purchase order from selected quote
     */
    private function generatePurchaseOrderFromQuote($purchaseRequest)
    {
        $request = request();

        $orderNumber = \App\Models\PurchaseOrder::allocateNextFormattedNumber($purchaseRequest->responsibilityArea, 1);

        // Obtener fecha de emisión del request o usar la fecha actual
        $issueDate = $request->input('issue_date') ? \Carbon\Carbon::parse($request->input('issue_date')) : now();

        $quoteDetails = $purchaseRequest->selectedMarketRate->quoteDetails;

        $supplierId = $purchaseRequest->selectedMarketRate->supplier_id;
        // Create purchase order (supplier_id opcional a nivel orden cuando hay varios proveedores por línea)
        $purchaseOrder = \App\Models\PurchaseOrder::create([
            'number' => $orderNumber,
            'date' => now(),
            'issue_date' => $issueDate,
            'supplier_id' => $supplierId,
            'authorizing_user_id' => auth()->id(),
            'status' => 'Pendiente',
            'purchase_request_id' => $purchaseRequest->id,
            'payment_conditions' => $purchaseRequest->selectedMarketRate?->payment_method,
        ]);

        $approvedProductIds = $purchaseRequest->details
            ->filter(fn ($d) => $d->isAuthorizedForPurchaseOrder())
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->all();

        $createdLines = 0;
        // Create purchase order details from quote (cada línea con su proveedor)
        foreach ($quoteDetails as $quoteDetail) {
            if ($approvedProductIds !== [] && ! in_array($quoteDetail->product_id, $approvedProductIds, true)) {
                continue;
            }
            // Buscar o crear el Input correspondiente al Product
            $input = $this->findOrCreateInputFromProduct($quoteDetail->product);

            if ($input) {
                $unitPrice = $this->parseMonetaryValue($quoteDetail->unit_price);
                \App\Models\PurchaseOrderDetail::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'supplier_id' => $supplierId,
                    'input_id' => $input->id,
                    'quantity' => $quoteDetail->quantity,
                    'unit_price' => $unitPrice,
                ]);
                $createdLines++;
            }
        }

        if ($createdLines === 0) {
            $purchaseOrder->delete();
            \Alert::error('No se pudo generar la orden: no hay ítems autorizados para compra en esta cotización.')->flash();

            return redirect()->back();
        }

        // Update purchase request status and type (preservar 'internet' si aplica)
        $newType = ($purchaseRequest->purchase_type === 'internet') ? 'internet' : 'normal';
        $purchaseRequest->update([
            'status' => 'Completada',
            'purchase_type' => $newType,
        ]);
        \Alert::success('Orden de compra generada exitosamente: '.$purchaseOrder->number)->flash();

        PurchaseRequestNotificationService::notifyAdministratorPurchaseOrdersCreated($purchaseRequest, $purchaseOrder);

        return redirect()->route('purchase-order.show', $purchaseOrder->id);
    }

    /**
     * Generate purchase order without quote (for amounts <= 60000)
     */
    private function generatePurchaseOrderWithoutQuote($purchaseRequest, $supplierId)
    {
        $request = request();

        // Validar que el proveedor existe
        $supplier = \App\Models\Supplier::findOrFail($supplierId);

        $orderNumber = \App\Models\PurchaseOrder::allocateNextFormattedNumber($purchaseRequest->responsibilityArea, 1);

        // Obtener fecha de emisión del request o usar la fecha actual
        $issueDate = $request->input('issue_date') ? \Carbon\Carbon::parse($request->input('issue_date')) : now();

        // Obtener precios del request
        $prices = $request->input('prices', []);
        $lines = [];

        foreach ($purchaseRequest->details as $requestDetail) {
            if (! $requestDetail->isAuthorizedForPurchaseOrder()) {
                continue;
            }
            if (! $requestDetail->product) {
                continue;
            }

            $input = $this->findOrCreateInputFromProduct($requestDetail->product);
            if (! $input) {
                continue;
            }

            $rawPrice = $prices[$requestDetail->id] ?? $requestDetail->estimated_unit_price ?? 0;
            $unitPrice = $this->parseMonetaryValue($rawPrice);

            $lines[] = [
                'input_id' => $input->id,
                'quantity' => $requestDetail->requested_quantity,
                'unit_price' => $unitPrice,
            ];
        }

        if (empty($lines)) {
            \Alert::error('No se pudo generar la orden: no hay líneas válidas para crear la OC.')->flash();

            return redirect()->back();
        }

        // Create purchase order
        $purchaseOrder = \App\Models\PurchaseOrder::create([
            'number' => $orderNumber,
            'date' => now(),
            'issue_date' => $issueDate,
            'supplier_id' => $supplierId,
            'authorizing_user_id' => auth()->id(),
            'status' => 'Pendiente',
            'purchase_request_id' => $purchaseRequest->id,
            'payment_conditions' => $purchaseRequest->selectedMarketRate?->payment_method,
        ]);

        // Create purchase order details from purchase request details (cada línea con el mismo proveedor)
        foreach ($lines as $line) {
            \App\Models\PurchaseOrderDetail::create([
                'purchase_order_id' => $purchaseOrder->id,
                'supplier_id' => $supplierId,
                'input_id' => $line['input_id'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
            ]);
        }

        // Determinar el tipo de compra (preservar 'internet' si ya estaba marcada)
        $purchaseType = ($purchaseRequest->purchase_type === 'internet') ? 'internet' : 'normal';
        $totalAmount = $this->recalculatePurchaseOrderGenerationTotal($purchaseRequest);
        $threshold = 60000;
        if ($purchaseType !== 'internet') {
            if ($purchaseRequest->is_direct_purchase && $purchaseRequest->direct_purchase_authorized_by) {
                $purchaseType = 'directa';
            } elseif ($totalAmount <= $threshold) {
                $purchaseType = 'rapida';
            }
        }

        // Update purchase request status and type
        $purchaseRequest->update([
            'status' => 'Completada',
            'purchase_type' => $purchaseType,
        ]);
        \Alert::success('Orden de compra generada exitosamente: '.$purchaseOrder->number)->flash();

        PurchaseRequestNotificationService::notifyAdministratorPurchaseOrdersCreated($purchaseRequest, $purchaseOrder);

        return redirect()->route('purchase-order.show', $purchaseOrder->id);
    }

    /**
     * Count quotations for a purchase request
     */
    private function countQuotationsForPurchaseRequest($purchaseRequest)
    {
        // Usar la relación del modelo en lugar de consulta directa
        return $purchaseRequest->marketRates()->count();
    }

    private function parseMonetaryValue($raw): float
    {
        if ($raw === null || $raw === '') {
            return 0.0;
        }
        if (is_numeric($raw)) {
            return (float) $raw;
        }
        $value = trim((string) $raw);
        $value = str_replace(['$', ' '], '', $value);
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }
        $value = preg_replace('/[^0-9.\-]/', '', $value);
        if ($value === '' || ! is_numeric($value)) {
            return 0.0;
        }

        return (float) $value;
    }

    /**
     * Find or create Input from Product
     */
    protected function findOrCreateInputFromProduct($product)
    {
        // Intentar encontrar un input con el mismo nombre
        $input = \App\Models\Input::where('name', $product->name)->first();

        if ($input) {
            return $input;
        }

        // Si no existe, crear uno nuevo
        try {
            $input = \App\Models\Input::create([
                'name' => $product->name,
                'description' => $product->description ?? '',
                'unit' => $product->unit_measurement ?? 'unidad',
                'price' => 0, // El precio se establecerá en el detalle de la orden
            ]);

            \Log::info('Input creado desde Product', [
                'product_id' => $product->id,
                'input_id' => $input->id,
                'name' => $input->name,
            ]);

            return $input;
        } catch (\Exception $e) {
            \Log::error('Error al crear input desde Product', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Define what happens when the Show operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-show
     *
     * @return void
     */
    protected function setupShowOperation()
    {
        CRUD::addClause('with', ['responsibilityArea', 'requestingUser', 'approvedBy', 'details.product', 'details.selectedMarketRate.supplier', 'selectedMarketRate.supplier', 'selectedBy', 'convertedFromGeneralRequest', 'deliveries.details', 'purchaseOrders.paymentOrders', 'purchaseOrders.receptions', 'directPurchaseSupplier', 'directPurchaseAuthorizationRequestedBy', 'directPurchaseAuthorizedBy', 'marketRates']);

        // Ocultar botón de eliminar para role_admin_institucion, role_apoderado y role_representante_legal
        $user = backpack_user();
        if ($user && ($user->hasRole('role_admin_institucion', 'backpack') || $user->hasRole('role_apoderado', 'backpack') || $user->hasRole('role_representante_legal', 'backpack'))) {
            CRUD::removeButton('delete');
        }

        CRUD::column('request_number')->label('Número de Solicitud');
        CRUD::column('request_date')->label('Fecha');
        CRUD::column('status')->label('Estado');
        CRUD::column('priority')->label('Prioridad');
        CRUD::column('justification')->label('Motivo')->type('custom_html')
            ->value(function ($entry) {
                $text = $entry->justification ?? '';

                return '<div class="text-break" style="white-space: pre-wrap; word-break: break-word;">'.e($text).'</div>';
            });
        CRUD::column('observations')->label('Observaciones');
        CRUD::column('responsibilityArea.name')->label('Área');
        CRUD::column('requestingUser.name')->label('Solicitante');
        CRUD::column('approvedBy.name')->label('Aprobada por');
        CRUD::column('approved_date')->label('Fecha Aprobación')->type('date');

        // Columna para mostrar si requiere aprobación de administrador
        CRUD::column('approval_status')->label('Estado de Aprobación')->type('custom_html')
            ->value(function ($entry) {
                if ($entry->status === 'Rechazada') {
                    return '<span class="badge bg-danger">Rechazada</span>';
                }
                if ($entry->admin_quotation_reviewed_at && ! in_array($entry->status, ['Aprobada', 'Completada'], true)) {
                    return '<span class="badge bg-info text-dark">Revisión administración registrada — pendiente nivel superior</span>';
                }
                if ($entry->hasAdministrativeApprovalRecorded()) {
                    return '<span class="badge bg-success">Aprobada</span>';
                }
                if ($entry->requires_admin_approval) {
                    $comprasLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_responsable_compras');
                    $total = (float) ($entry->total_amount ?? 0);
                    $adminLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_admin_institucion');
                    $apoderadoLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_apoderado');
                    $representanteLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_representante_legal');

                    $requiredRole = null;
                    if ($adminLimit > 0 && $total <= $adminLimit) {
                        $requiredRole = 'Administrador del Instituto';
                    } elseif ($apoderadoLimit > 0 && $total <= $apoderadoLimit) {
                        $requiredRole = 'Apoderado';
                    } elseif ($representanteLimit > 0 && $total <= $representanteLimit) {
                        $requiredRole = 'Representante Legal';
                    }

                    if ($requiredRole) {
                        return '<span class="badge bg-warning">Requiere aprobación de '.e($requiredRole).' (Monto: $'.number_format($total, 2).')</span>';
                    }

                    return '<span class="badge bg-danger">Monto supera todos los límites de aprobación (Monto: $'.number_format($total, 2).')</span>';
                } else {
                    return '<span class="badge bg-secondary">Pendiente</span>';
                }
            });

        CRUD::column('total_amount')->label('Monto total e IVA')->type('custom_html')
            ->value(function ($entry) {
                $rates = $this->marketRatesContributingToPurchaseRequestTotal($entry);
                $totalEfectivo = $this->recalculateSelectedQuotationsTotalForPurchaseRequest($entry);
                $ivaSum = (float) $rates->sum(fn ($mr) => max(0, (float) ($mr->vat_amount ?? 0)));
                $stored = (float) ($entry->total_amount ?? 0);
                $fmt = static fn (float $v): string => '$'.number_format($v, 2, ',', '.');

                $html = '<div class="mb-1"><span class="text-muted small">Monto registrado en solicitud</span><br><strong class="fs-5">'.$fmt($stored).'</strong></div>';
                if ($rates->isNotEmpty()) {
                    $html .= '<div class="mt-2 p-2 rounded border bg-light">';
                    $html .= '<span class="badge bg-warning text-dark me-2 fs-6">IVA</span>';
                    $html .= '<strong class="fs-6">'.$fmt($ivaSum).'</strong>';
                    $html .= '<div class="mt-2"><span class="text-muted small">Total con IVA (cotización seleccionada)</span><br><strong class="text-primary fs-5">'.$fmt($totalEfectivo).'</strong></div>';
                    $html .= '</div>';
                } else {
                    $html .= '<div class="mt-2"><span class="badge bg-secondary">IVA</span> <span class="text-muted small">Se mostrará al seleccionar una cotización en «Cotizaciones disponibles».</span></div>';
                }

                return $html;
            });

        // Eliminar completamente la sección de adjuntos
        CRUD::removeColumn('attachments');

        // Agregar campo personalizado para mostrar información de la solicitud general de origen
        CRUD::column('general_request_info')->label('Solicitud General de Origen')->type('custom_html')
            ->value(function ($entry) {
                if (! $entry->convertedFromGeneralRequest) {
                    return '<p class="mb-0" style="color:#000;">Esta solicitud de compra no fue convertida desde una solicitud general.</p>';
                }

                $generalRequest = $entry->convertedFromGeneralRequest;
                // Cargar los detalles de productos con entregas
                $generalRequest->load('details.product', 'deliveries.details');
                $generalDetails = $generalRequest->details;

                $html = '<div class="card border-info">';
                $html .= '<div class="card-header bg-info text-white">';
                $html .= '<h6 class="mb-0"><i class="la la-file-alt"></i> Solicitud General de Origen</h6>';
                $html .= '</div>';
                $html .= '<div class="card-body">';
                $html .= '<div class="row">';
                $html .= '<div class="col-md-6">';
                $html .= '<p class="mb-1"><strong>Número:</strong> '.e($generalRequest->number ?? 'N/A').'</p>';
                $html .= '<p class="mb-1"><strong>Título:</strong> '.e($generalRequest->title ?? 'N/A').'</p>';
                $html .= '<p class="mb-1"><strong>Estado:</strong> ';
                $status = $generalRequest->status ?? 'N/A';
                $statusClass = strtolower(str_replace([' ', '_'], '-', $status));
                $statusColors = [
                    'creada' => 'secondary',
                    'revisada-area' => 'info',
                    'archivada' => 'dark',
                    'convertida-a-compra' => 'warning',
                    'entregada-parcialmente' => 'warning',
                    'entregada-totalmente' => 'success',
                ];
                $badgeColor = $statusColors[$statusClass] ?? 'secondary';
                $html .= '<span class="badge bg-'.$badgeColor.'">'.ucfirst(str_replace('_', ' ', $status)).'</span>';
                $html .= '</p>';
                $html .= '</div>';
                $html .= '<div class="col-md-6">';
                $html .= '<p class="mb-1"><strong>Área:</strong> '.e($generalRequest->area->name ?? 'N/A').'</p>';
                $html .= '<p class="mb-1"><strong>Creada por:</strong> '.e($generalRequest->createdBy->name ?? 'N/A').'</p>';
                $html .= '<p class="mb-1"><strong>Fecha de creación:</strong> '.($generalRequest->created_at ? $generalRequest->created_at->format('d/m/Y H:i') : 'N/A').'</p>';
                $html .= '</div>';
                $html .= '</div>';
                if ($generalRequest->description) {
                    $html .= '<div class="row mt-2">';
                    $html .= '<div class="col-12">';
                    $html .= '<p class="mb-1"><strong>Descripción:</strong></p>';
                    $html .= '<p class="text-muted small mb-2">'.nl2br(e($generalRequest->description)).'</p>';
                    $html .= '</div>';
                    $html .= '</div>';
                }

                // Mostrar productos de la solicitud general
                if ($generalDetails->isNotEmpty()) {
                    $html .= '<div class="row mt-3">';
                    $html .= '<div class="col-12">';
                    $html .= '<p class="mb-2"><strong>Productos Solicitados ('.$generalDetails->count().'):</strong></p>';
                    $html .= '<div class="table-responsive" style="max-height: 300px; overflow-y: auto;">';
                    $html .= '<table class="table table-sm table-bordered table-striped mb-0" style="font-size: 0.95rem;">';
                    $html .= '<thead class="table-light" style="position: sticky; top: 0; z-index: 10;">';
                    $html .= '<tr>';
                    $html .= '<th style="width: 35%;">Producto</th>';
                    $html .= '<th style="width: 15%;" class="text-center">Solicitado</th>';
                    $html .= '<th style="width: 15%;" class="text-center">Entregado</th>';
                    $html .= '<th style="width: 15%;" class="text-center">Pendiente</th>';
                    $html .= '<th style="width: 10%;" class="text-center">Estado</th>';
                    $html .= '<th style="width: 10%;">Especificaciones</th>';
                    $html .= '</tr>';
                    $html .= '</thead>';
                    $html .= '<tbody>';

                    foreach ($generalDetails as $detail) {
                        $productName = $detail->product->name ?? 'Producto no encontrado';
                        if (is_array($productName)) {
                            $productName = 'Producto no encontrado';
                        }
                        $unit = $detail->product->unit_measurement ?? '';
                        if (is_array($unit)) {
                            $unit = '';
                        }
                        $requestedQuantity = $detail->requested_quantity ?? 0;

                        // Calcular cantidad entregada
                        $deliveredQuantity = 0;
                        if ($generalRequest->deliveries) {
                            foreach ($generalRequest->deliveries as $delivery) {
                                $deliveryDetail = $delivery->details->where('product_id', $detail->product_id)->first();
                                if ($deliveryDetail) {
                                    $deliveredQuantity += $deliveryDetail->delivered_quantity ?? 0;
                                }
                            }
                        }

                        $pendingQuantity = max(0, $requestedQuantity - $deliveredQuantity);

                        // Determinar estado de entrega
                        $deliveryStatus = 'Pendiente';
                        $deliveryStatusColor = 'secondary';
                        $deliveryStatusIcon = 'clock';
                        if ($deliveredQuantity == 0) {
                            $deliveryStatus = 'Pendiente';
                            $deliveryStatusColor = 'secondary';
                            $deliveryStatusIcon = 'clock';
                        } elseif ($deliveredQuantity >= $requestedQuantity) {
                            $deliveryStatus = 'Completo';
                            $deliveryStatusColor = 'success';
                            $deliveryStatusIcon = 'check-circle';
                        } else {
                            $deliveryStatus = 'Parcial';
                            $deliveryStatusColor = 'warning';
                            $deliveryStatusIcon = 'exclamation-triangle';
                        }

                        $specifications = $detail->specifications ?? '';
                        if (is_array($specifications)) {
                            $specifications = '';
                        }

                        $html .= '<tr>';
                        $html .= '<td><small><strong>'.e($productName).'</strong>';
                        if ($unit) {
                            $html .= '<br><span class="text-muted">('.e($unit).')</span>';
                        }
                        $html .= '</small></td>';
                        $html .= '<td class="text-center"><small><strong>'.number_format($requestedQuantity).'</strong></small></td>';
                        $html .= '<td class="text-center"><small>'.number_format($deliveredQuantity).'</small></td>';
                        $html .= '<td class="text-center"><small>'.number_format($pendingQuantity).'</small></td>';
                        $html .= '<td class="text-center"><small><span class="badge bg-'.$deliveryStatusColor.'" title="'.$deliveryStatus.'"><i class="la la-'.$deliveryStatusIcon.'"></i> '.$deliveryStatus.'</span></small></td>';
                        $html .= '<td><small class="text-muted">'.($specifications ? e(substr($specifications, 0, 40)).(strlen($specifications) > 40 ? '...' : '') : '-').'</small></td>';
                        $html .= '</tr>';
                    }

                    $html .= '</tbody>';
                    $html .= '</table>';
                    $html .= '</div>';
                    $html .= '</div>';
                    $html .= '</div>';
                } else {
                    $html .= '<div class="row mt-2">';
                    $html .= '<div class="col-12">';
                    $html .= '<p class="text-muted small mb-0"><em>No hay productos en esta solicitud general.</em></p>';
                    $html .= '</div>';
                    $html .= '</div>';
                }

                $html .= '</div>';
                $html .= '</div>';

                return $html;
            });

        // Agregar campo personalizado para mostrar detalles de productos
        CRUD::column('details_table')->label('Detalles de Productos')->type('custom_html')
            ->value(function ($entry) {
                $entry->loadMissing(['details.product']);
                $details = $entry->details;

                if ($details->isEmpty()) {
                    return '<div class="alert alert-info">No hay productos solicitados.</div>';
                }

                $user = backpack_user();
                $decisionFocusView = $user && ! $user->hasResponsableAreaOrInstituteAuthorityRole() && (
                    $user->hasAdministradoraInstitucionRole()
                    || $user->hasRole('role_admin_sistema', 'backpack')
                ) && in_array((string) $entry->status, ['Pendiente', 'En Proceso'], true);

                if ($decisionFocusView) {
                    $html = '<div class="mb-3">';
                    $html .= '<div class="d-flex align-items-center gap-2 mb-2">';
                    $html .= '<h6 class="mb-0 text-primary"><i class="la la-shopping-cart"></i> Productos solicitados</h6>';
                    $html .= '<span class="badge bg-primary">'.$details->count().'</span>';
                    $html .= '</div>';
                    $html .= '<div class="row g-2">';

                    foreach ($details as $index => $detail) {
                        $lineNo = $index + 1;
                        $requestedQuantity = $detail->requested_quantity ?? 0;
                        $rawCatalogName = null;
                        if ($detail->product) {
                            $n = $detail->product->name;
                            $rawCatalogName = is_array($n) ? null : $n;
                        }
                        $specLine = trim((string) ($detail->product_description ?? $detail->specifications ?? ''));
                        $isGenericCatalog = is_string($rawCatalogName) && preg_match('/^producto\s+nuevo$/iu', $rawCatalogName);
                        if ($isGenericCatalog && $specLine !== '') {
                            $productLabel = $specLine;
                        } elseif ($isGenericCatalog) {
                            $productLabel = 'Ítem #'.$lineNo;
                        } else {
                            $productLabel = ($rawCatalogName !== null && $rawCatalogName !== '') ? (string) $rawCatalogName : 'Sin catálogo';
                        }

                        $unit = ($detail->product && $detail->product->unit_measurement && ! is_array($detail->product->unit_measurement))
                            ? (string) $detail->product->unit_measurement
                            : 'un.';

                        $html .= '<div class="col-12">';
                        $html .= '<div class="pr-decision-product-row d-flex flex-wrap align-items-center gap-2">';
                        $html .= '<span class="badge bg-secondary">#'.$lineNo.'</span>';
                        $html .= '<strong class="text-nowrap">'.e($productLabel).'</strong>';
                        $html .= '<span class="badge bg-primary">'.number_format($requestedQuantity).' '.e($unit).'</span>';
                        if ($specLine !== '' && ! $isGenericCatalog) {
                            $html .= '<span class="text-muted small flex-grow-1">'.e(\Illuminate\Support\Str::limit($specLine, 120)).'</span>';
                        }
                        $html .= '</div></div>';
                    }

                    $html .= '</div></div>';

                    return $html;
                }

                $html = '<div class="card border-primary">';
                $html .= '<div class="card-header bg-primary text-white">';
                $html .= '<h6 class="mb-0"><i class="la la-shopping-cart"></i> Productos Solicitados <span class="badge bg-light text-primary ms-1">'.$details->count().'</span></h6>';
                $html .= '</div>';
                $html .= '<div class="card-body p-0">';
                $html .= '<div class="table-responsive">';
                $html .= '<table class="table table-sm table-bordered mb-0">';
                $html .= '<thead class="table-light">';
                $html .= '<tr>';
                $html .= '<th style="width: '.($decisionFocusView ? '40' : '28').'%;">Producto</th>';
                $html .= '<th style="width: 12%;" class="text-center">Cantidad</th>';
                if (! $decisionFocusView) {
                    $html .= '<th style="width: 12%;" class="text-center">Cantidad Recibida</th>';
                    $html .= '<th style="width: 12%;" class="text-center">Estado Recepción</th>';
                }
                $html .= '<th style="width: '.($decisionFocusView ? '30' : '20').'%;">Descripción / Especificaciones</th>';
                if (! $decisionFocusView) {
                    $html .= '<th style="width: 10%;" class="text-center">Estado</th>';
                }
                $html .= '<th style="width: '.($decisionFocusView ? '18' : '14').'%;" class="text-center">Autorización compra</th>';
                $html .= '</tr>';
                $html .= '</thead>';
                $html .= '<tbody>';

                foreach ($details as $index => $detail) {
                    $deliveredQuantity = $detail->delivered_quantity ?? 0;
                    $requestedQuantity = $detail->requested_quantity ?? 0;
                    $deliveryStatus = $detail->delivery_status ?? 'Pendiente';
                    $isFullyDelivered = $detail->is_fully_delivered ?? false;

                    // Determinar estado de recepción
                    $deliveryStatusColor = 'secondary';
                    $deliveryStatusIcon = 'clock';
                    if ($deliveryStatus == 'Completo') {
                        $deliveryStatusColor = 'success';
                        $deliveryStatusIcon = 'check-circle';
                    } elseif ($deliveryStatus == 'Parcial') {
                        $deliveryStatusColor = 'warning';
                        $deliveryStatusIcon = 'exclamation-triangle';
                    } else {
                        $deliveryStatusColor = 'secondary';
                        $deliveryStatusIcon = 'clock';
                    }

                    $html .= '<tr>';
                    $lineNo = $index + 1;
                    $rawCatalogName = null;
                    if ($detail->product) {
                        $n = $detail->product->name;
                        $rawCatalogName = is_array($n) ? null : $n;
                    }
                    $specLine = trim((string) ($detail->product_description ?? $detail->specifications ?? ''));
                    $isGenericCatalog = is_string($rawCatalogName) && preg_match('/^producto\s+nuevo$/iu', $rawCatalogName);

                    $html .= '<td>';
                    $html .= '<span class="badge bg-secondary me-1">#'.$lineNo.'</span>';
                    if ($isGenericCatalog && $specLine !== '') {
                        $html .= '<strong>'.e($specLine).'</strong>';
                        $html .= '<br><small class="text-muted">Catálogo: '.e((string) $rawCatalogName).' · ID '.(int) $detail->product_id.'</small>';
                    } elseif ($isGenericCatalog) {
                        $html .= '<strong>'.e('Ítem #'.$lineNo.' (sin descripción en la línea)').'</strong>';
                        $html .= '<br><small class="text-muted">Nombre en catálogo: '.e((string) $rawCatalogName).' · ID '.(int) $detail->product_id.'</small>';
                    } else {
                        $label = ($rawCatalogName !== null && $rawCatalogName !== '') ? (string) $rawCatalogName : 'Sin catálogo';
                        $html .= '<strong>'.e($label).'</strong>';
                        if ($detail->product_id) {
                            $html .= '<br><small class="text-muted">ID producto '.(int) $detail->product_id.'</small>';
                        }
                    }
                    if ($detail->product && $detail->product->description && ! is_array($detail->product->description)) {
                        $html .= '<br><small class="text-muted">'.e($detail->product->description).'</small>';
                    }
                    $html .= '</td>';
                    $html .= '<td class="text-center"><span class="badge bg-primary">'.number_format($requestedQuantity).'</span>';
                    if ($detail->product && $detail->product->unit_measurement && ! is_array($detail->product->unit_measurement)) {
                        $html .= '<br><small class="text-muted">'.e($detail->product->unit_measurement).'</small>';
                    }
                    $html .= '</td>';
                    if (! $decisionFocusView) {
                        $html .= '<td class="text-center">';
                        $html .= '<span class="badge bg-'.($deliveredQuantity > 0 ? ($isFullyDelivered ? 'success' : 'warning') : 'secondary').'" title="Cantidad recibida: '.number_format($deliveredQuantity).' de '.number_format($requestedQuantity).'">';
                        $html .= number_format($deliveredQuantity).' / '.number_format($requestedQuantity);
                        $html .= '</span>';
                        $html .= '</td>';
                        $html .= '<td class="text-center">';
                        $html .= '<span class="badge bg-'.$deliveryStatusColor.'" title="Estado de recepción: '.e($deliveryStatus).'">';
                        $html .= '<i class="la la-'.$deliveryStatusIcon.'"></i> '.e($deliveryStatus);
                        $html .= '</span>';
                        $html .= '</td>';
                    }
                    $descSpecs = $detail->specifications ?? $detail->product_description ?? '';
                    if (is_array($descSpecs)) {
                        $descSpecs = '';
                    }
                    $html .= '<td><small>'.($descSpecs ? e($descSpecs) : '-').'</small></td>';
                    if (! $decisionFocusView) {
                        $status = $detail->status ?? 'Pendiente';
                        if (is_array($status)) {
                            $status = 'Pendiente';
                        }
                        $html .= '<td class="text-center"><span class="badge bg-'.($detail->status == 'Aprobada' ? 'success' : ($detail->status == 'Rechazada' ? 'danger' : 'warning')).'">'.e((string) $status).'</span></td>';
                    }
                    $las = $detail->line_authorization_status ?? \App\Models\PurchaseRequestDetail::LINE_AUTH_PENDING;
                    $authLabel = match ($las) {
                        \App\Models\PurchaseRequestDetail::LINE_AUTH_APPROVED => 'Autorizada',
                        \App\Models\PurchaseRequestDetail::LINE_AUTH_REJECTED => 'No autorizada',
                        default => 'Pendiente',
                    };
                    $authColor = match ($las) {
                        \App\Models\PurchaseRequestDetail::LINE_AUTH_APPROVED => 'success',
                        \App\Models\PurchaseRequestDetail::LINE_AUTH_REJECTED => 'danger',
                        default => 'secondary',
                    };
                    $html .= '<td class="text-center"><span class="badge bg-'.$authColor.'">'.e($authLabel).'</span>';
                    if ($las === \App\Models\PurchaseRequestDetail::LINE_AUTH_REJECTED && $detail->line_authorization_rejection_reason) {
                        $html .= '<br><small class="text-muted">'.e(\Illuminate\Support\Str::limit((string) $detail->line_authorization_rejection_reason, 120)).'</small>';
                    }
                    $html .= '</td>';
                    $html .= '</tr>';
                }

                $html .= '</tbody>';
                $html .= '</table>';
                $html .= '</div>';
                $html .= '</div>';

                // Agregar botón para editar/agregar productos si el usuario tiene permisos
                if ($user) {
                    $isAdminSistema = $user->hasRole('role_admin_sistema', 'backpack');
                    $isActingCreator = $entry->isActingAsCreatingUser((int) $user->id);
                    $isResponsableArea = $user->hasResponsableAreaOrInstituteAuthorityRole();
                    $entry->loadMissing(['marketRates', 'details', 'purchaseRequestEvents', 'purchaseOrders']);
                    $areaProductsLockedByQuotation = $isResponsableArea && $entry->hasQuotationSelectionResolved();
                    $productsLockedForComprasOrAdminInstitucion = $this->userCannotModifyPurchaseRequestProductDetails($user);
                    $frozenPendingSuperiorProducts = $entry->isFrozenPendingSuperiorApproval();

                    // Verificar si puede editar productos (solicitud completada: no editar desde aquí)
                    $canEdit = false;
                    if ($entry->status !== 'Completada' && ! $productsLockedForComprasOrAdminInstitucion && ! $frozenPendingSuperiorProducts) {
                        if ($isAdminSistema) {
                            $canEdit = true;
                        } elseif ($isActingCreator && $entry->status === 'Pendiente' && ! $areaProductsLockedByQuotation) {
                            $canEdit = true;
                        }
                    }

                    if ($canEdit) {
                        $html .= '<div class="card-footer bg-light text-end">';
                        $html .= '<a href="'.backpack_url('purchase-request/'.$entry->id.'/edit').'" class="btn btn-primary">';
                        $html .= '<i class="la la-edit"></i> Editar Productos';
                        $html .= '</a>';
                        $html .= '</div>';
                    }
                }

                $html .= '</div>';

                return $html;
            });

        // Compra directa: sugerencia del sector de compras (sigue con cotizaciones / detalle operativo)
        CRUD::column('direct_purchase_compras_suggest')->label('Compra directa (sector de compras)')->type('custom_html')
            ->value(function ($entry) {
                $user = backpack_user();
                if (! $user || ! $this->userCanUseComprasSectorDirectPurchase($user)) {
                    return '';
                }

                if ($entry->is_direct_purchase) {
                    return '<p class="text-muted small mb-0"><i class="la la-check-circle"></i> Esta solicitud ya está marcada como <strong>compra directa</strong>. Revise el bloque «Compra directa — autorización superior» para aprobar o rechazar.</p>';
                }

                if (! $this->statusAllowsComprasDirectPurchaseSuggestion((string) $entry->status)) {
                    return '<p class="text-muted small mb-0"><i class="la la-info-circle"></i> La sugerencia de compra directa solo aplica en estado <strong>Pendiente</strong> o <strong>En proceso</strong> (actual: <strong>'.e((string) $entry->status).'</strong>).</p>';
                }

                $entry->loadMissing(['purchaseRequestEvents']);
                if ($entry->isFrozenPendingSuperiorApproval()) {
                    return '';
                }

                $html = '<div class="card border-secondary mt-3">';
                $html .= '<div class="card-header bg-secondary text-white">';
                $html .= '<h6 class="mb-0"><i class="la la-hand-pointer"></i> Sugerencia de Compra Directa</h6>';
                $html .= '</div>';
                $html .= '<div class="card-body">';
                $html .= '<p class="mb-3">Si existe un único proveedor para los productos solicitados (por especialidad), puede marcar esta solicitud como compra directa. Al marcarla, se solicitará automáticamente la autorización a nivel superior.</p>';
                $html .= '<p class="mb-3 text-muted"><small><i class="la la-info-circle"></i> El responsable de área puede sugerir proveedores desde la sección de sugerencias de proveedores.</small></p>';

                $modalId = 'markDirectPurchaseModal'.$entry->id;
                $supplierFieldId = 'direct_purchase_supplier_id_'.$entry->id;
                $justificationFieldId = 'direct_purchase_justification_'.$entry->id;
                $suppliers = \App\Models\Supplier::orderBy('company_name')->get();

                $html .= '<button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#'.$modalId.'">';
                $html .= '<i class="la la-hand-pointer"></i> Sugerir Compra Directa';
                $html .= '</button>';

                $html .= '<div class="modal fade" id="'.$modalId.'" tabindex="-1" role="dialog" aria-labelledby="'.$modalId.'Label" aria-hidden="true">';
                $html .= '<div class="modal-dialog modal-lg" role="document">';
                $html .= '<div class="modal-content">';
                $html .= '<div class="modal-header bg-info text-white">';
                $html .= '<h5 class="modal-title" id="'.$modalId.'Label">Sugerir Compra Directa</h5>';
                $html .= '<button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">';
                $html .= '<span aria-hidden="true">&times;</span>';
                $html .= '</button>';
                $html .= '</div>';
                $html .= '<form method="POST" action="'.route('purchase-request.mark-direct-purchase', $entry->id).'">';
                $html .= csrf_field();
                $html .= '<div class="modal-body">';
                $html .= '<div class="form-group">';
                $html .= '<label for="'.$supplierFieldId.'">Proveedor <span class="text-danger">*</span></label>';
                $html .= '<select name="direct_purchase_supplier_id" id="'.$supplierFieldId.'" class="form-control" required>';
                $html .= '<option value="">Seleccione un proveedor</option>';
                foreach ($suppliers as $supplier) {
                    $html .= '<option value="'.$supplier->id.'">'.e($supplier->company_name).'</option>';
                }
                $html .= '</select>';
                $html .= '</div>';
                $html .= '<div class="form-group">';
                $html .= '<label for="'.$justificationFieldId.'">Justificación <span class="text-danger">*</span></label>';
                $html .= '<textarea name="direct_purchase_justification" id="'.$justificationFieldId.'" class="form-control" rows="4" required placeholder="Explique por qué este proveedor es el único disponible para estos productos (especialidad, exclusividad, etc.)"></textarea>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '<div class="modal-footer">';
                $html .= '<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>';
                $html .= '<button type="submit" class="btn btn-info">Sugerir Compra Directa</button>';
                $html .= '</div>';
                $html .= '</form>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';

                $html .= '<script>
                    (function() {
                        function initDirectPurchaseModal() {
                            var modal = document.getElementById("'.$modalId.'");
                            if (!modal) return;
                            if (modal.parentElement && modal.parentElement.tagName !== "BODY") {
                                document.body.appendChild(modal);
                            }
                            if (typeof jQuery !== "undefined" && jQuery.fn.modal) {
                                jQuery("button[data-target=\'#'.$modalId.'\']").off("click").on("click", function(e) {
                                    e.preventDefault();
                                    jQuery("#'.$modalId.'").appendTo("body").modal("show");
                                });
                            }
                        }
                        if (document.readyState === "loading") {
                            document.addEventListener("DOMContentLoaded", initDirectPurchaseModal);
                        } else {
                            initDirectPurchaseModal();
                        }
                        setTimeout(initDirectPurchaseModal, 100);
                    })();
                    </script>';

                $html .= '</div>';
                $html .= '</div>';

                return $html;
            });

        // Compra directa: seguimiento y decisión de nivel superior (debajo de la parte informativa)
        CRUD::column('direct_purchase_superior_actions')->label('Compra directa — autorización superior')->type('custom_html')
            ->value(function ($entry) {
                $user = backpack_user();
                if (! $user || ! $entry->is_direct_purchase) {
                    return '';
                }

                $html = '<div class="card border-info mt-0">';
                $html .= '<div class="card-header bg-info text-white">';
                $html .= '<h6 class="mb-0"><i class="la la-hand-pointer"></i> Compra directa</h6>';
                $html .= '</div>';
                $html .= '<div class="card-body">';

                if ($entry->directPurchaseSupplier) {
                    $html .= '<p class="mb-2"><strong>Proveedor:</strong> '.e($entry->directPurchaseSupplier->company_name).'</p>';
                }

                if ($entry->direct_purchase_justification) {
                    $html .= '<p class="mb-2"><strong>Justificación:</strong> '.nl2br(e($entry->direct_purchase_justification)).'</p>';
                }

                if ($entry->direct_purchase_authorization_requested) {
                    if ($entry->directPurchaseAuthorizationRequestedBy) {
                        $html .= '<p class="mb-2"><strong>Solicitud de autorización por:</strong> '.e($entry->directPurchaseAuthorizationRequestedBy->name).'</p>';
                    }
                    if ($entry->direct_purchase_authorization_requested_at) {
                        $requestedAt = $entry->direct_purchase_authorization_requested_at instanceof \Carbon\Carbon
                            ? $entry->direct_purchase_authorization_requested_at->format('d/m/Y H:i')
                            : \Carbon\Carbon::parse($entry->direct_purchase_authorization_requested_at)->format('d/m/Y H:i');
                        $html .= '<p class="mb-2"><strong>Fecha de solicitud:</strong> '.$requestedAt.'</p>';
                    }

                    if ($entry->direct_purchase_authorized_by) {
                        $html .= '<div class="alert alert-success mt-2">';
                        $html .= '<i class="la la-check-circle"></i> <strong>Autorizada</strong>';
                        if ($entry->directPurchaseAuthorizedBy) {
                            $html .= ' por '.e($entry->directPurchaseAuthorizedBy->name);
                        }
                        if ($entry->direct_purchase_authorized_at) {
                            $authorizedAt = $entry->direct_purchase_authorized_at instanceof \Carbon\Carbon
                                ? $entry->direct_purchase_authorized_at->format('d/m/Y H:i')
                                : \Carbon\Carbon::parse($entry->direct_purchase_authorized_at)->format('d/m/Y H:i');
                            $html .= ' el '.$authorizedAt;
                        }
                        $html .= '</div>';
                    } elseif ($entry->direct_purchase_authorization_rejected) {
                        $html .= '<div class="alert alert-danger mt-2">';
                        $html .= '<i class="la la-times-circle"></i> <strong>Autorización Rechazada</strong>';
                        if ($entry->direct_purchase_authorization_rejection_reason) {
                            $html .= '<br><strong>Razón:</strong> '.nl2br(e($entry->direct_purchase_authorization_rejection_reason));
                        }
                        $html .= '</div>';
                    } else {
                        $html .= '<div class="alert alert-warning mt-2">';
                        $html .= '<i class="la la-clock"></i> <strong>Pendiente de autorización</strong>';
                        $html .= '</div>';

                        $canApproveByLimit = false;
                        $userLimit = 0;

                        if ($user->hasRole('role_admin_institucion', 'backpack')) {
                            $userLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_admin_institucion');
                            $canApproveByLimit = $entry->total_amount <= $userLimit;
                        } elseif ($user->hasRole('role_apoderado', 'backpack')) {
                            $userLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_apoderado');
                            $canApproveByLimit = $entry->total_amount <= $userLimit;
                        } elseif ($user->hasRole('role_representante_legal', 'backpack')) {
                            $userLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_representante_legal');
                            $canApproveByLimit = $entry->total_amount <= $userLimit;
                        }

                        if (($user->hasRole('role_admin_institucion', 'backpack') || $user->hasRole('role_apoderado', 'backpack') || $user->hasRole('role_representante_legal', 'backpack')) && ! $canApproveByLimit) {
                            $adminLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_admin_institucion');
                            $apoderadoLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_apoderado');
                            $representanteLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_representante_legal');

                            $html .= '<div class="alert alert-danger mt-2">';
                            $html .= '<i class="la la-exclamation-triangle"></i> ';
                            $html .= '<strong>Límite excedido:</strong> Esta compra directa ($'.number_format($entry->total_amount, 2).') supera tu límite de autorización de $'.number_format($userLimit, 2).'. ';
                            $html .= 'No puedes aprobar esta compra directa. ';

                            $canApproveList = [];
                            if ($entry->total_amount <= $adminLimit) {
                                $canApproveList[] = 'administrador del instituto (límite: $'.number_format($adminLimit, 2).')';
                            }
                            if ($entry->total_amount <= $apoderadoLimit) {
                                $canApproveList[] = 'apoderado (límite: $'.number_format($apoderadoLimit, 2).')';
                            }
                            if ($entry->total_amount <= $representanteLimit) {
                                $canApproveList[] = 'representante legal (límite: $'.number_format($representanteLimit, 2).')';
                            }

                            if (! empty($canApproveList)) {
                                $html .= 'Puede ser aprobada por: '.implode(', ', $canApproveList).'.';
                            } else {
                                $html .= 'Ningún usuario tiene límite suficiente para aprobar esta compra directa.';
                            }

                            $html .= '</div>';
                        } elseif ($canApproveByLimit) {
                            $html .= '<div class="mt-3">';
                            $html .= '<form method="POST" action="'.route('purchase-request.approve-direct-purchase', $entry->id).'" class="d-inline">';
                            $html .= csrf_field();
                            $html .= '<button type="submit" class="btn btn-success btn-sm" onclick="return confirm(\'¿Está seguro de aprobar esta compra directa?\')">';
                            $html .= '<i class="la la-check"></i> Aprobar Compra Directa';
                            $html .= '</button>';
                            $html .= '</form>';
                            $html .= '<button type="button" class="btn btn-danger btn-sm ms-2" data-toggle="modal" data-target="#rejectDirectPurchaseModal'.$entry->id.'">';
                            $html .= '<i class="la la-times"></i> Rechazar Autorización';
                            $html .= '</button>';
                            $html .= '</div>';

                            $html .= '<div class="modal fade" id="rejectDirectPurchaseModal'.$entry->id.'" tabindex="-1" role="dialog">';
                            $html .= '<div class="modal-dialog" role="document">';
                            $html .= '<div class="modal-content">';
                            $html .= '<div class="modal-header bg-danger text-white">';
                            $html .= '<h5 class="modal-title">Rechazar Autorización de Compra Directa</h5>';
                            $html .= '<button type="button" class="close text-white" data-dismiss="modal">&times;</button>';
                            $html .= '</div>';
                            $html .= '<form method="POST" action="'.route('purchase-request.reject-direct-purchase-authorization', $entry->id).'">';
                            $html .= csrf_field();
                            $html .= '<div class="modal-body">';
                            $html .= '<div class="form-group">';
                            $html .= '<label for="rejection_reason_dp_'.$entry->id.'">Razón del rechazo:</label>';
                            $html .= '<textarea name="rejection_reason" id="rejection_reason_dp_'.$entry->id.'" class="form-control" rows="3" required></textarea>';
                            $html .= '</div>';
                            $html .= '</div>';
                            $html .= '<div class="modal-footer">';
                            $html .= '<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>';
                            $html .= '<button type="submit" class="btn btn-danger">Rechazar</button>';
                            $html .= '</div>';
                            $html .= '</form>';
                            $html .= '</div>';
                            $html .= '</div>';
                            $html .= '</div>';
                        }
                    }
                }

                $html .= '</div>';
                $html .= '</div>';

                return $html;
            });

        // Agregar campo para mostrar cotizaciones disponibles.
        CRUD::column('market_rates_table')->label('Cotizaciones Disponibles')->type('custom_html')
            ->value(function ($entry) {
                // Usar la relación del modelo en lugar de consulta directa
                $entry->load(['marketRates.supplier', 'marketRates.quoteDetails.product', 'purchaseRequestEvents']);
                $marketRates = $entry->marketRates;

                $quotationsViewer = backpack_user();
                $representanteLegalSinAsignarPorProducto = $quotationsViewer && $quotationsViewer->hasRole('role_representante_legal', 'backpack');

                $canSelectQuotations = $quotationsViewer && (
                    $quotationsViewer->hasRole('role_admin_sistema', 'backpack')
                    || $quotationsViewer->hasRole('role_admin_institucion', 'backpack')
                    || $quotationsViewer->effectivelyHasResponsableComprasRole()
                );

                $comprasSinAdmin = $quotationsViewer
                    && $quotationsViewer->hasRole('role_responsable_compras', 'backpack')
                    && ! $quotationsViewer->hasRole('role_admin_sistema', 'backpack')
                    && ! $quotationsViewer->hasRole('role_admin_institucion', 'backpack');
                $frozenPendingSuperior = $this->purchaseRequestFrozenPendingSuperiorApproval($entry);
                $quotationsLockedAfterApproval = $entry->locksQuotationAndAssignmentChanges();
                $viewerIsSuperiorAuthority = $quotationsViewer && (
                    $quotationsViewer->hasRole('role_representante_legal', 'backpack')
                    || $quotationsViewer->hasRole('role_apoderado', 'backpack')
                );
                $allowsChangesAfterPartialRejection = $entry->canReopenForSuperiorAuthorizationAfterRevision();
                $comprasPuedeEditarSeleccionCotizaciones = (! $comprasSinAdmin || $this->statusAllowsComprasSinAdminQuotationSelection((string) $entry->status))
                    && ! $frozenPendingSuperior
                    && ! $quotationsLockedAfterApproval;

                $html = '';

                if ($quotationsLockedAfterApproval && ! $entry->wasApprovedBySuperiorAuthority()) {
                    $html .= '<div class="alert alert-secondary mb-3"><i class="la la-lock"></i> ';
                    $html .= 'La solicitud está <strong>aprobada</strong>. Las cotizaciones y la asignación por producto no pueden modificarse.';
                    $html .= '</div>';
                } elseif ($frozenPendingSuperior && ! $viewerIsSuperiorAuthority) {
                    $html .= '<div class="alert alert-info mb-3"><i class="la la-lock"></i> '
                        .'La solicitud está bloqueada mientras está pendiente la <strong>aprobación de nivel superior</strong>. '
                        .'No puede agregar, editar ni eliminar cotizaciones, ni modificar productos o asignaciones. '
                        .'Si el nivel superior rechaza ítems de forma parcial, podrá ajustar la solicitud y reabrir el circuito desde «Acciones de nivel superior».</div>';
                } elseif ($allowsChangesAfterPartialRejection) {
                    $html .= '<div class="alert alert-warning mb-3"><i class="la la-edit"></i> '
                        .'<strong>Revisión tras observaciones del nivel superior:</strong> puede ajustar cotizaciones y asignaciones antes de reabrir la solicitud para una nueva autorización.</div>';
                }

                if ($marketRates->isEmpty()) {
                    $html .= '<div class="alert alert-warning">No hay cotizaciones disponibles para los productos de esta solicitud.</div>';
                } else {
                    $html .= '<div class="table-responsive">';
                    $html .= '<table class="table table-striped table-bordered">';
                    $html .= '<thead class="thead-dark">';
                    $html .= '<tr>';
                    $html .= '<th>Proveedor</th>';
                    $html .= '<th>Fecha</th>';
                    $html .= '<th>Total</th>';
                    $html .= '<th>Productos</th>';
                    $html .= '<th>Acciones</th>';
                    $html .= '</tr>';
                    $html .= '</thead>';
                    $html .= '<tbody>';

                    foreach ($marketRates as $marketRate) {
                        $isSelected = (bool) ($marketRate->is_selected || $entry->selected_market_rate_id == $marketRate->id);
                        $rowClass = $isSelected ? 'table-success' : '';

                        $html .= '<tr class="'.$rowClass.'">';
                        $supplierName = $marketRate->supplier->company_name ?? 'Proveedor no encontrado';
                        if (is_array($supplierName)) {
                            $supplierName = 'Proveedor no encontrado';
                        }
                        $html .= '<td><strong>'.$supplierName.'</strong></td>';
                        $date = $marketRate->date;
                        if (is_string($date)) {
                            $date = \Carbon\Carbon::parse($date);
                        }
                        $html .= '<td>'.($date ? $date->format('d/m/Y') : 'N/A').'</td>';
                        $subtotal = (float) ($marketRate->total_amount ?? 0);
                        $vatAmount = (float) ($marketRate->vat_amount ?? 0);
                        $totalWithVat = (float) ($marketRate->total_amount_with_vat ?? 0);
                        if ($totalWithVat <= 0 && ($subtotal > 0 || $vatAmount > 0)) {
                            $totalWithVat = $subtotal + $vatAmount;
                        }
                        $html .= '<td class="text-end"><strong>$'.number_format($totalWithVat > 0 ? $totalWithVat : $subtotal, 2).'</strong>';
                        if ($vatAmount > 0) {
                            $html .= '<br><small class="text-muted">Subtotal: $'.number_format($subtotal, 2).' + IVA: $'.number_format($vatAmount, 2).'</small>';
                        }
                        $html .= '</td>';
                        $documentFiles = MarketRate::normalizeDocumentFilesToPathList($marketRate->document_files);

                        if ($marketRate->quoteDetails->isEmpty()) {
                            $productsHtml = '<span class="text-muted">Sin productos</span>';
                        } else {
                            $productsHtml = '<div><span class="badge bg-info mb-1">'.$marketRate->quoteDetails->count().' productos</span></div>';
                            $productsHtml .= '<ul class="mb-0 ps-3">';
                            foreach ($marketRate->quoteDetails as $detail) {
                                $productName = $detail->product->name ?? ('Producto #'.$detail->product_id);
                                if (is_array($productName)) {
                                    $productName = 'Producto no encontrado';
                                }
                                $productsHtml .= '<li>'.e($productName);
                                $detailDescription = $detail->product_description ?? ($detail->product->description ?? null);
                                if ($detailDescription && ! is_array($detailDescription)) {
                                    $productsHtml .= '<br><small class="text-muted">'.e($detailDescription).'</small>';
                                }
                                $productsHtml .= ' - Cant: '.(float) $detail->quantity.' - $'.number_format((float) $detail->unit_price, 2).'/u</li>';
                            }
                            $productsHtml .= '</ul>';
                        }
                        $html .= '<td>'.$productsHtml.'</td>';
                        $html .= '<td>';

                        $html .= '<a href="'.route('market-rate.pdf', $marketRate->id).'" class="btn btn-sm btn-outline-primary me-1" target="_blank">';
                        $html .= '<i class="la la-file-pdf-o"></i> PDF';
                        $html .= '</a>';

                        if ($documentFiles !== []) {
                            foreach ($documentFiles as $idx => $filePath) {
                                $label = $idx === 0 ? 'Archivo subido' : ('Archivo '.($idx + 1));
                                $fileUrl = route('market-rate.uploaded-file', ['id' => $marketRate->id, 'index' => $idx]);
                                $html .= '<a href="'.e($fileUrl).'" class="btn btn-sm btn-outline-secondary me-1" target="_blank" rel="noopener">';
                                $html .= '<i class="la la-paperclip"></i> '.e($label);
                                $html .= '</a>';
                            }
                        }

                        $referenceUrls = MarketRate::referenceLinkUrlsList($marketRate->reference_links);
                        if ($referenceUrls !== []) {
                            foreach ($referenceUrls as $idx => $linkUrl) {
                                $linkLabel = count($referenceUrls) === 1
                                    ? 'Enlace (Mercado Libre u otros)'
                                    : ('Enlace '.($idx + 1));
                                $html .= '<a href="'.e($linkUrl).'" class="btn btn-sm btn-outline-info me-1" target="_blank" rel="noopener" title="'.e($linkUrl).'">';
                                $html .= '<i class="la la-external-link"></i> '.e($linkLabel);
                                $html .= '</a>';
                            }
                        }

                        if ($entry->status != 'Completada' && $canSelectQuotations && $comprasPuedeEditarSeleccionCotizaciones) {
                            $html .= '<form method="POST" action="'.e(backpack_url('purchase-request/'.$entry->id.'/toggle-market-rate/'.$marketRate->id)).'" style="display:inline-block;" class="me-1">';
                            $html .= csrf_field();
                            if ($isSelected) {
                                $html .= '<button type="submit" class="btn btn-sm btn-warning"><i class="la la-minus-circle"></i> Deseleccionar</button>';
                            } else {
                                $html .= '<button type="submit" class="btn btn-sm btn-success"><i class="la la-check"></i> Seleccionar</button>';
                            }
                            $html .= '</form>';
                        }

                        $html .= '</td>';
                        $html .= '</tr>';
                    }

                    $html .= '</tbody>';
                    $html .= '</table>';
                    $html .= '</div>';

                    $canRequestAdministratorApproval = $quotationsViewer
                        && ! $quotationsViewer->hasAdministradoraInstitucionRole()
                        && (
                            $quotationsViewer->effectivelyHasResponsableComprasRole()
                            || $quotationsViewer->hasRole('role_admin_sistema', 'backpack')
                        );
                    $entry->loadMissing(['details', 'marketRates.quoteDetails']);
                    $adminReviewBase = $canSelectQuotations && $comprasPuedeEditarSeleccionCotizaciones && $canRequestAdministratorApproval && PurchaseRequestNotificationService::isAwaitingAdministratorQuotationApproval($entry);
                    $quotationsAssignedToAllProducts = $entry->hasQuotationsAssignedToAllRequestProducts();
                    if ($adminReviewBase && $quotationsAssignedToAllProducts) {
                        $html .= '<div class="alert alert-warning mt-3 mb-0">';
                        $html .= '<p class="mb-2"><strong>Revisión de administradora</strong></p>';
                        $html .= '<p class="mb-2 small">Cuando tenga definida(s) la(s) cotización(es) para <strong>cada producto</strong>, solicite la revisión de la administradora del instituto.</p>';
                        $html .= '<form method="POST" action="'.e(route('purchase-request.request-quotation-superior-authorization', $entry->id)).'" class="d-inline">';
                        $html .= csrf_field();
                        $html .= '<button type="submit" class="btn btn-primary"><i class="la la-envelope"></i> Solicitar revisión de administradora</button>';
                        $html .= '</form>';
                        $html .= '</div>';
                    } elseif ($adminReviewBase && ! $quotationsAssignedToAllProducts) {
                        $html .= '<div class="alert alert-info mt-3 mb-0">';
                        $html .= '<p class="mb-2"><strong>Revisión de administradora</strong></p>';
                        $html .= '<p class="small mb-0">Para solicitar la revisión debe primero <strong>asignar cotización a cada producto</strong> de la solicitud. Use la sección «Asignar cotización por producto» (cuando hay más de una cotización) o seleccione una cotización que cotice <strong>todos</strong> los productos. El botón de envío a la administradora se habilitará al completar esa asignación.</p>';
                        $html .= '</div>';
                    }

                    // Botón para descargar planilla comparativa (solo si hay más de una cotización)
                    $quotationsCount = $marketRates->count();
                    if ($quotationsCount > 1) {
                        $user = backpack_user();
                        // Solo mostrar si el usuario no es role_responsable_area
                        if (! $user || ! $user->hasResponsableAreaOrInstituteAuthorityRole()) {
                            $html .= '<div class="mt-3">';
                            $html .= '<a href="'.route('purchase-request.comparative-excel', $entry->id).'" class="btn btn-success">';
                            $html .= '<i class="la la-file-excel"></i> Descargar Planilla Comparativa';
                            $html .= '</a>';
                            $html .= '</div>';
                        }
                    }

                    // Asignar cotización por producto (para OC con varios proveedores) — solo quien puede seleccionar cotizaciones (compras/admins); no responsable de área ni representante legal; compras sin admin solo con solicitud pendiente o en proceso
                    $entry->load(['details.product', 'details.selectedMarketRate.supplier']);
                    if ($quotationsCount >= 2 && $entry->status !== 'Completada' && ! $representanteLegalSinAsignarPorProducto && $comprasPuedeEditarSeleccionCotizaciones && $canSelectQuotations) {
                        $html .= '<div class="card mt-3 border-info">';
                        $html .= '<div class="card-header bg-info text-white"><strong><i class="la la-link"></i> Asignar cotización por producto</strong></div>';
                        $html .= '<div class="card-body">';
                        $html .= '<p class="text-muted small">Asigne para cada producto qué cotización usar. Luego puede generar <strong>una sola orden de compra</strong> con ítems de distintos proveedores.</p>';
                        $html .= '<form method="POST" action="'.route('purchase-request.assign-quotations', $entry->id).'">';
                        $html .= csrf_field();
                        $html .= '<div class="table-responsive"><table class="table table-sm table-bordered">';
                        $html .= '<thead><tr><th>Producto</th><th>Cantidad</th><th>Cotización a usar</th></tr></thead><tbody>';
                        foreach ($entry->details as $detail) {
                            $productName = $detail->product ? $detail->product->name : 'Producto #'.$detail->id;
                            $ratesWithProduct = $marketRates->filter(function ($mr) use ($detail) {
                                return $mr->quoteDetails->contains('product_id', $detail->product_id);
                            });
                            $html .= '<tr><td>'.e($productName).'</td><td>'.(int) $detail->requested_quantity.'</td><td>';
                            $html .= '<select name="detail_quote['.$detail->id.']" class="form-control form-control-sm">';
                            $html .= '<option value="">— Sin asignar —</option>';
                            foreach ($ratesWithProduct as $mr) {
                                $qd = $mr->quoteDetails->firstWhere('product_id', $detail->product_id);
                                $price = $qd ? number_format($qd->unit_price, 2) : '—';
                                $supplierName = $mr->supplier ? $mr->supplier->company_name : 'Proveedor';
                                $selected = $detail->selected_market_rate_id == $mr->id ? ' selected' : '';
                                $html .= '<option value="'.$mr->id.'"'.$selected.'>'.e($supplierName).' — $'.$price.'/u</option>';
                            }
                            $html .= '</select></td></tr>';
                        }
                        $html .= '</tbody></table></div>';
                        $html .= '<button type="submit" class="btn btn-info btn-sm"><i class="la la-save"></i> Guardar asignación</button>';
                        $html .= '</form></div></div>';
                    }
                }

                // Botón para agregar nueva cotización (compras, admin y responsable de área; solo si no está aprobada/completada)
                $user = backpack_user();
                $adminRoles = ['role_admin_sistema', 'role_admin_institucion', 'role_responsable_compras', 'role_responsable_area', 'role_autoridad_instituto'];
                $canCreateQuotation = false;
                foreach ($adminRoles as $role) {
                    if ($user && $user->hasRole($role, 'backpack')) {
                        $canCreateQuotation = true;
                        break;
                    }
                }

                $entry->loadMissing(['marketRates', 'details']);
                $areaBlockedFromNewQuotations = $user && $user->hasResponsableAreaOrInstituteAuthorityRole() && $entry->hasQuotationSelectionResolved();

                $allowsQuotationChangesDespiteStatus = $entry->allowsModificationDuringSuperiorRevisionWorkflow();

                // Solo mostrar el botón si tiene permiso, no está congelada por nivel superior y (no aprobada o revisión tras rechazo parcial)
                if (
                    $canCreateQuotation
                    && ! $frozenPendingSuperior
                    && ! $quotationsLockedAfterApproval
                    && ! $areaBlockedFromNewQuotations
                    && ($allowsQuotationChangesDespiteStatus || ($entry->status !== 'Aprobada' && $entry->status !== 'Completada'))
                ) {
                    $html .= '<div class="mt-3">';
                    $html .= '<a href="'.backpack_url('market-rate/create?purchase_request_id='.$entry->id).'" class="btn btn-success">';
                    $html .= '<i class="la la-plus"></i> Agregar Nueva Cotización';
                    $html .= '</a>';
                    $html .= '</div>';
                }

                if ($entry->purchaseOrders()->doesntExist()) {
                    $userOc = backpack_user();
                    if ($userOc instanceof \App\Models\User && $userOc->canGeneratePurchaseOrders()) {
                        $html .= '<p class="small text-muted mt-3 mb-0"><i class="la la-arrow-down"></i> Para indicar la <strong>fecha de emisión</strong> y generar la orden de compra, use la sección <strong>Órdenes de Compra Asociadas</strong> más abajo.</p>';
                    }
                }


                return $html;
            });

        // Agregar campo para mostrar sugerencias de proveedores
        CRUD::column('supplier_suggestions_table')->label('Sugerencias de Proveedores')->type('custom_html')
            ->value(function ($entry) {
                try {
                    $entry->load(['supplierSuggestions.supplier', 'supplierSuggestions.suggestedBy']);
                    $suggestions = $entry->supplierSuggestions;
                } catch (\Exception $e) {
                    // Si hay un error al cargar las sugerencias (por ejemplo, tabla no existe), usar colección vacía
                    $suggestions = collect([]);
                }

                $html = '';

                $user = backpack_user();
                $isResponsableArea = $user && $user->hasResponsableAreaOrInstituteAuthorityRole();

                $entry->loadMissing(['purchaseRequestEvents', 'details', 'purchaseOrders', 'marketRates']);
                $frozenPendingSuperior = $entry->isFrozenPendingSuperiorApproval();

                // Botón para sugerir proveedor (solo responsables de área)
                if ($isResponsableArea && $entry->status != 'Completada' && ! $frozenPendingSuperior) {
                    $alreadyNotifiedCompras = $entry->status === 'En Proceso';
                    $notifyComprasLabel = $alreadyNotifiedCompras ? 'Volver a notificar al circuito de compras' : 'Notificar al circuito de compras';
                    $entry->loadMissing('marketRates');
                    $blockNotifyComprasQuotes = $this->blocksNotifyComprasForInsufficientQuotations($entry);
                    $quotationsCountForNotify = $entry->marketRates->count();
                    if ($blockNotifyComprasQuotes) {
                        $html .= '<div class="alert alert-warning mb-2">';
                        $html .= '<i class="la la-exclamation-triangle"></i> <strong>No puede notificar al circuito de compras todavía:</strong> ';
                        $html .= 'se requieren al menos <strong>dos cotizaciones</strong> (comparación de ofertas). ';
                        if ($quotationsCountForNotify === 0) {
                            $html .= 'Actualmente no hay cotizaciones cargadas en esta solicitud.';
                        } else {
                            $html .= 'Actualmente solo hay <strong>una</strong> cotización cargada; cargue al menos una más.';
                        }
                        $html .= '</div>';
                    }
                    $html .= '<div class="mb-3 d-flex flex-wrap gap-2 align-items-start">';
                    $html .= '<a href="'.route('purchase-request.suggest-supplier', $entry->id).'" class="btn btn-info">';
                    $html .= '<i class="la la-lightbulb"></i> Sugerir Proveedor';
                    $html .= '</a>';
                    if ($entry->status !== 'Rechazada') {
                        $notifyDisabledAttr = $blockNotifyComprasQuotes ? ' disabled title="Cargue al menos dos cotizaciones antes de notificar al circuito de compras."' : '';
                        $html .= '<form method="POST" action="'.e(route('purchase-request.notify-compras-intervention', $entry->id)).'" class="d-inline">';
                        $html .= csrf_field();
                        $html .= '<button type="submit" class="btn btn-primary"'.$notifyDisabledAttr;
                        if ($blockNotifyComprasQuotes) {
                            $html .= ' aria-disabled="true"';
                        }
                        $html .= '>';
                        $html .= '<i class="la la-envelope"></i> '.e($notifyComprasLabel);
                        $html .= '</button>';
                        $html .= '</form>';
                    }
                    $html .= '</div>';
                }

                if ($suggestions->isEmpty()) {
                    $html .= '-';
                } else {
                    $html .= '<div class="table-responsive">';
                    $html .= '<table class="table table-striped table-bordered">';
                    $html .= '<thead class="thead-dark">';
                    $html .= '<tr>';
                    $html .= '<th>Proveedor</th>';
                    $html .= '<th>Sugerido por</th>';
                    $html .= '<th>Justificación</th>';
                    $html .= '<th>Fecha</th>';
                    $html .= '</tr>';
                    $html .= '</thead>';
                    $html .= '<tbody>';

                    foreach ($suggestions as $suggestion) {
                        $html .= '<tr>';
                        $supplierName = $suggestion->supplier->company_name ?? 'Proveedor no encontrado';
                        if (is_array($supplierName)) {
                            $supplierName = 'Proveedor no encontrado';
                        }
                        $html .= '<td><strong>'.$supplierName.'</strong></td>';
                        $suggestedByName = $suggestion->suggestedBy->name ?? 'Usuario no encontrado';
                        if (is_array($suggestedByName)) {
                            $suggestedByName = 'Usuario no encontrado';
                        }
                        $html .= '<td>'.$suggestedByName.'</td>';
                        $justification = $suggestion->justification ?? 'Sin justificación';
                        if (is_array($justification)) {
                            $justification = 'Sin justificación';
                        }
                        $html .= '<td>'.$justification.'</td>';
                        $createdAt = $suggestion->created_at;
                        if (is_string($createdAt)) {
                            $createdAt = \Carbon\Carbon::parse($createdAt);
                        }
                        $html .= '<td>'.($createdAt ? $createdAt->format('d/m/Y H:i') : 'N/A').'</td>';
                        $html .= '</tr>';
                    }

                    $html .= '</tbody>';
                    $html .= '</table>';
                    $html .= '</div>';
                }

                return $html;
            });

        // Agregar campo para mostrar órdenes de compra asociadas (debajo de sugerencias de proveedores)
        CRUD::column('purchase_orders_table')->label('Órdenes de Compra Asociadas')->type('custom_html')
            ->value(function ($entry) {
                $entry->load('purchaseOrders.supplier', 'purchaseOrders.details');
                $purchaseOrders = $entry->purchaseOrders;

                if ($purchaseOrders->isEmpty()) {
                    $generateHtml = $this->renderGeneratePurchaseOrderFormHtml($entry);

                    return $generateHtml !== ''
                        ? $generateHtml
                        : '<p class="mb-0" style="color:#000;">No hay órdenes de compra asociadas a esta solicitud.</p>';
                }

                $html = '<div class="table-responsive">';
                $html .= '<table class="table table-striped table-bordered">';
                $html .= '<thead class="thead-dark">';
                $html .= '<tr>';
                $html .= '<th>Número</th>';
                $html .= '<th>Fecha de emisión</th>';
                $html .= '<th>Proveedor</th>';
                $html .= '<th>Estado</th>';
                $html .= '<th>Total</th>';
                $html .= '<th>Acciones</th>';
                $html .= '</tr>';
                $html .= '</thead>';
                $html .= '<tbody>';

                foreach ($purchaseOrders as $purchaseOrder) {
                    $statusBadge = match ($purchaseOrder->status) {
                        'Pendiente' => 'bg-warning',
                        'Aprobada' => 'bg-success',
                        'Recibida' => 'bg-info',
                        default => 'bg-secondary'
                    };

                    $html .= '<tr>';
                    $html .= '<td><strong>'.e($purchaseOrder->number ?? 'N/A').'</strong></td>';
                    $issueAt = $purchaseOrder->issue_date ?? $purchaseOrder->date;
                    $issueFormatted = 'N/A';
                    if ($issueAt) {
                        $issueFormatted = $issueAt instanceof \Carbon\Carbon
                            ? $issueAt->format('d/m/Y')
                            : \Carbon\Carbon::parse($issueAt)->format('d/m/Y');
                    }
                    $html .= '<td>'.$issueFormatted.'</td>';
                    $html .= '<td>'.e($purchaseOrder->supplier_display_name).'</td>';
                    $html .= '<td><span class="badge '.$statusBadge.'">'.e($purchaseOrder->status ?? 'N/A').'</span></td>';
                    $html .= '<td><strong>$'.number_format($purchaseOrder->total ?? 0, 2).'</strong></td>';
                    $html .= '<td>';
                    $html .= '<a href="'.backpack_url('purchase-order/'.$purchaseOrder->id.'/show').'" class="btn btn-sm btn-info">';
                    $html .= '<i class="la la-eye"></i> Ver';
                    $html .= '</a>';
                    $html .= '</td>';
                    $html .= '</tr>';
                }

                $html .= '</tbody>';
                $html .= '</table>';
                $html .= '</div>';

                return $html;
            });

        // Órdenes de pago desde la solicitud (misma regla que en el detalle de la OC: solo administradora del instituto)
        CRUD::column('create_payment_orders_from_pr')->label('Órdenes de Pago')->type('custom_html')
            ->value(function ($entry) {
                $user = backpack_user();
                if ($user && $user->hasResponsableAreaOrInstituteAuthorityRole()) {
                    return '';
                }
                if (! $user instanceof \App\Models\User) {
                    return '';
                }
                if (! $user->hasAdministradoraInstitucionRole() && ! $user->hasResponsableComprasRole()) {
                    return '';
                }

                $entry->load(['purchaseOrders.paymentOrders']);
                $purchaseOrders = $entry->purchaseOrders;
                if ($purchaseOrders->isEmpty()) {
                    return '<p class="mb-0" style="color:#000;">No hay orden de compra asociada. Cuando exista una OC, la administradora del instituto podrá generar la orden de pago.</p>';
                }

                $isAdmin = $user instanceof \App\Models\User && $user->hasAdministradoraInstitucionRole();

                $html = '<div class="card border-success mt-1">';
                $html .= '<div class="card-header bg-success text-white py-2"><h6 class="mb-0"><i class="la la-money-bill-wave"></i> Orden de pago desde esta solicitud</h6></div>';
                $html .= '<div class="card-body">';

                foreach ($purchaseOrders as $purchaseOrder) {
                    $purchaseOrder->loadMissing(['paymentOrders']);
                    $hasPaymentOrders = $purchaseOrder->paymentOrders->isNotEmpty();

                    $html .= '<div class="mb-3 pb-3 border-bottom">';
                    $html .= '<p class="mb-2"><strong>OC '.e($purchaseOrder->number ?? 'N/A').'</strong>';

                    if ($hasPaymentOrders) {
                        $html .= '</p>';
                        foreach ($purchaseOrder->paymentOrders as $paymentOrder) {
                            $opLabel = e($paymentOrder->payment_number ?? ('OP #'.$paymentOrder->id));
                            $html .= '<a href="'.backpack_url('payment-order/'.$paymentOrder->id.'/show').'" class="btn btn-sm btn-primary me-1 mb-1">';
                            $html .= '<i class="la la-eye"></i> Ver orden de pago: '.$opLabel;
                            $html .= '</a>';
                        }
                        $html .= '<p class="text-muted small mb-0 mt-2"><i class="la la-check"></i> Orden(es) de pago asociada(s) a esta OC.</p>';
                    } else {
                        $html .= ' <a href="'.backpack_url('purchase-order/'.$purchaseOrder->id.'/show').'" class="btn btn-sm btn-outline-info ms-1">Ver OC</a></p>';
                        if ($isAdmin) {
                            $html .= '<a href="'.backpack_url('payment-order/create?purchase_order_id='.$purchaseOrder->id).'" class="btn btn-success">';
                            $html .= '<i class="la la-money-bill-wave"></i> Crear Orden de Pago';
                            $html .= '</a>';
                        } else {
                            $html .= '<p class="text-muted small mb-0"><i class="la la-info-circle"></i> La orden de pago la genera la <strong>administradora del instituto</strong> luego de la orden de compra; no requiere recepción conforme.</p>';
                        }
                    }
                    $html .= '</div>';
                }

                $html .= '</div></div>';

                return $html;
            });

        // Agregar información de selección si existe
        CRUD::field('selection_info')->label('Información de Selección')->type('custom_html')
            ->value(function ($entry) {
                if (! $entry->selected_market_rate_id) {
                    return '';
                }

                // Verificar que las relaciones estén cargadas
                if (! $entry->selectedMarketRate || ! $entry->selectedMarketRate->supplier) {
                    return '<div class="alert alert-warning">Información de cotización seleccionada no disponible.</div>';
                }

                $html = '<div class="alert alert-success">';
                $html .= '<h5><i class="la la-check-circle"></i> Cotización Seleccionada</h5>';
                $supplierName = $entry->selectedMarketRate->supplier->company_name ?? 'Proveedor no encontrado';
                if (is_array($supplierName)) {
                    $supplierName = 'Proveedor no encontrado';
                }
                $html .= '<p><strong>Proveedor:</strong> '.$supplierName.'</p>';
                $html .= '<p><strong>Total:</strong> $'.number_format($entry->selectedMarketRate->total_amount ?? 0, 2).'</p>';
                $selectedByName = $entry->selectedBy->name ?? 'Usuario no encontrado';
                if (is_array($selectedByName)) {
                    $selectedByName = 'Usuario no encontrado';
                }
                $html .= '<p><strong>Seleccionado por:</strong> '.$selectedByName.'</p>';
                $selectedAt = $entry->selected_at;
                if ($selectedAt) {
                    if (is_string($selectedAt)) {
                        $selectedAt = \Carbon\Carbon::parse($selectedAt);
                    }
                    $selectedAtFormatted = $selectedAt ? $selectedAt->format('d/m/Y H:i') : 'No disponible';
                } else {
                    $selectedAtFormatted = 'No disponible';
                }
                $html .= '<p><strong>Fecha de selección:</strong> '.$selectedAtFormatted.'</p>';
                if ($entry->selection_justification && ! is_array($entry->selection_justification)) {
                    $html .= '<p><strong>Justificación:</strong> '.$entry->selection_justification.'</p>';
                }
                $html .= '</div>';

                return $html;
            });

        // Agregar botones para crear entregas y recepciones (solo para role_responsable_area) y estado de recepción (todos los perfiles)
        CRUD::column('delivery_reception_actions')->label('Acciones de Entrega y Recepción')->type('custom_html')
            ->value(function ($entry) {
                $user = backpack_user();
                $isResponsableArea = $user && $user->hasResponsableAreaOrInstituteAuthorityRole();

                $entry->load(['purchaseOrders.receptions', 'purchaseOrders.paymentOrders']);

                $receptionsLines = [];
                foreach ($entry->purchaseOrders as $purchaseOrder) {
                    foreach ($purchaseOrder->receptions as $reception) {
                        $recLabel = e($reception->number ?? 'REC-'.$reception->id);
                        $ocLabel = e($purchaseOrder->number ?? 'N/A');
                        $isConforme = ($reception->according ?? '') === 'Si';
                        $badge = $isConforme
                            ? '<span class="badge bg-success ms-1">Conforme</span>'
                            : '<span class="badge bg-secondary ms-1">No conforme</span>';
                        $receptionsLines[] = '<li class="mb-1">'
                            .'<a href="'.backpack_url('reception/'.$reception->id.'/show').'">'.$recLabel.'</a>'
                            .' <span class="text-muted">(OC '.$ocLabel.')</span> '.$badge.'</li>';
                    }
                }

                $receptionsBlock = '';
                if ($receptionsLines !== []) {
                    $receptionsBlock .= '<div class="mb-3"><strong><i class="la la-truck-loading"></i> Recepciones registradas</strong>';
                    $receptionsBlock .= '<ul class="mb-0 ps-3">'.implode('', $receptionsLines).'</ul></div>';
                }

                if (! $isResponsableArea) {
                    if ($receptionsBlock === '') {
                        return '';
                    }

                    return '<div class="card border-info mt-3">'
                        .'<div class="card-header bg-info text-white">'
                        .'<h6 class="mb-0"><i class="la la-truck-loading"></i> Entrega y recepción</h6>'
                        .'</div>'
                        .'<div class="card-body">'.$receptionsBlock.'</div>'
                        .'</div>';
                }

                // Verificar condiciones para acciones (responsable de área):
                // 1. La solicitud debe estar aprobada
                $isApproved = $entry->status === 'Aprobada';

                // 2. Debe existir al menos una orden de compra relacionada
                $hasPurchaseOrder = $entry->purchaseOrders->isNotEmpty();

                // 3. Debe existir al menos una orden de pago relacionada con alguna orden de compra
                $hasPaymentOrder = false;
                if ($hasPurchaseOrder) {
                    foreach ($entry->purchaseOrders as $purchaseOrder) {
                        if ($purchaseOrder->paymentOrders->isNotEmpty()) {
                            $hasPaymentOrder = true;
                            break;
                        }
                    }
                }

                $canShowActionButtons = $isApproved && $hasPurchaseOrder && $hasPaymentOrder;

                if (! $canShowActionButtons && $receptionsBlock === '') {
                    return '';
                }

                $html = '<div class="card border-success mt-3">';
                $html .= '<div class="card-header bg-success text-white">';
                $html .= '<h6 class="mb-0"><i class="la la-tasks"></i> '.($canShowActionButtons ? 'Acciones disponibles' : 'Entrega y recepción').'</h6>';
                $html .= '</div>';
                $html .= '<div class="card-body">';
                if ($receptionsBlock !== '') {
                    $html .= $receptionsBlock;
                }

                if ($canShowActionButtons) {
                    $html .= '<div class="row">';
                    // Botón para crear entrega
                    $html .= '<div class="col-md-6 mb-2">';
                    $html .= '<a href="'.backpack_url('delivery/create?purchase_request_id='.$entry->id).'" class="btn btn-primary btn-block">';
                    $html .= '<i class="la la-people-carry"></i> Crear Entrega';
                    $html .= '</a>';
                    $html .= '</div>';

                    // Botón para crear recepción solo si la primera OC aún no tiene recepción
                    $firstPurchaseOrder = $entry->purchaseOrders->first();
                    if ($firstPurchaseOrder && $firstPurchaseOrder->receptions->isEmpty()) {
                        $html .= '<div class="col-md-6 mb-2">';
                        $html .= '<a href="'.backpack_url('reception/create?purchase_order_id='.$firstPurchaseOrder->id).'" class="btn btn-success btn-block">';
                        $html .= '<i class="la la-truck-loading"></i> Crear Recepción';
                        $html .= '</a>';
                        $html .= '</div>';
                    }
                    $html .= '</div>';
                }

                $html .= '</div>';
                $html .= '</div>';

                return $html;
            });

        // Agregar botones de acción en la vista previa
        // Botón para generar planilla comparativa (solo para usuarios que no sean role_responsable_area)
        $user = backpack_user();
        if (! $user || ! $user->hasResponsableAreaOrInstituteAuthorityRole()) {
            CRUD::addButton('top', 'comparative_excel', 'view', 'crud::buttons.comparative_excel', 'end');

            // Botón para generar/ver orden de compra (solo para usuarios que no sean role_responsable_area)
            CRUD::addButton('top', 'purchase_order_action', 'view', 'crud::buttons.purchase_order_action', 'end');
        }

        // Agregar columna para botones de aprobación o información de aprobación
        CRUD::column('approval_actions')->label('Aprobación')->type('custom_html')
            ->value(function ($entry) {
                $user = backpack_user();
                if (! $user) {
                    return '';
                }

                $adminReviewSummaryHtml = '';
                if ($entry->admin_quotation_reviewed_at && ! in_array($entry->status, ['Aprobada', 'Completada', 'Rechazada'], true)) {
                    $entry->loadMissing(['adminQuotationReviewedBy', 'details.product']);
                    $reviewAt = $entry->admin_quotation_reviewed_at instanceof \Carbon\Carbon
                        ? $entry->admin_quotation_reviewed_at->format('d/m/Y H:i')
                        : \Carbon\Carbon::parse($entry->admin_quotation_reviewed_at)->format('d/m/Y H:i');
                    $adminReviewSummaryHtml = '<div class="card border-info mt-3 mb-3">';
                    $adminReviewSummaryHtml .= '<div class="card-header bg-info text-dark"><h6 class="mb-0"><i class="la la-clipboard-check"></i> Revisión de la administración del instituto (registrada en el sistema)</h6></div>';
                    $adminReviewSummaryHtml .= '<div class="card-body">';
                    if ($entry->adminQuotationReviewedBy) {
                        $adminReviewSummaryHtml .= '<p class="mb-2"><strong>Registrada por:</strong> '.e($entry->adminQuotationReviewedBy->name).'</p>';
                    }
                    $adminReviewSummaryHtml .= '<p class="mb-2"><strong>Fecha:</strong> '.e($reviewAt).'</p>';
                    if ($entry->admin_quotation_review_justification) {
                        $adminReviewSummaryHtml .= '<p class="mb-2"><strong>Justificación:</strong> '.nl2br(e($entry->admin_quotation_review_justification)).'</p>';
                    }
                    $adminReviewSummaryHtml .= '<p class="mb-2 small text-muted">La solicitud <strong>no</strong> figura como «Aprobada» hasta la autorización por monto del nivel correspondiente.</p>';
                    $adminReviewSummaryHtml .= '</div></div>';
                }

                // Aprobada, o completada pero conservando datos de aprobación (antes «Completada» devolvía vacío → «-» en la vista)
                $mostrarResumenAprobacion = $entry->status === 'Aprobada'
                    || ($entry->status === 'Completada' && ($entry->approved_by || $entry->approved_date));

                if ($mostrarResumenAprobacion) {
                    $entry->loadMissing(['approvedBy', 'directPurchaseAuthorizedBy', 'selectedBy']);

                    $html = '<div class="card border-success mt-3">';
                    $html .= '<div class="card-header bg-success text-white">';
                    $tituloTarjeta = $entry->status === 'Completada' ? 'Registro de aprobación' : 'Solicitud aprobada';
                    $html .= '<h6 class="mb-0"><i class="la la-check-circle"></i> '.e($tituloTarjeta).'</h6>';
                    $html .= '</div>';
                    $html .= '<div class="card-body">';

                    if ($entry->status === 'Completada') {
                        $html .= '<p class="mb-2"><span class="badge bg-secondary">Solicitud completada</span></p>';
                    }

                    $aprobador = $entry->approvedBy;
                    if (! $aprobador && $entry->approved_by) {
                        $aprobador = \App\Models\User::find($entry->approved_by);
                    }
                    if (! $aprobador && $entry->is_direct_purchase && $entry->directPurchaseAuthorizedBy) {
                        $aprobador = $entry->directPurchaseAuthorizedBy;
                    }

                    if ($aprobador) {
                        $html .= '<p class="mb-2"><strong>Aprobada por:</strong> '.e($aprobador->name);
                        if (method_exists($aprobador, 'hasRole')) {
                            if ($aprobador->hasRole('role_representante_legal', 'backpack')) {
                                $html .= ' <span class="badge bg-info text-white">Representante legal</span>';
                            } elseif ($aprobador->hasRole('role_apoderado', 'backpack')) {
                                $html .= ' <span class="badge bg-info text-white">Apoderado</span>';
                            } elseif ($aprobador->hasRole('role_admin_institucion', 'backpack')) {
                                $html .= ' <span class="badge bg-primary">Administrador del instituto</span>';
                            } elseif ($aprobador->hasRole('role_responsable_compras', 'backpack')) {
                                $html .= ' <span class="badge bg-secondary">Responsable de compras</span>';
                            }
                        }
                        $html .= '</p>';
                    } elseif ($entry->selectedBy) {
                        $html .= '<p class="mb-2"><strong>Consta aprobación vía selección de cotización por:</strong> '.e($entry->selectedBy->name).'</p>';
                    } else {
                        $html .= '<p class="mb-2 text-muted"><strong>Aprobación:</strong> no hay usuario asociado a la firma en base de datos.</p>';
                    }

                    if ($entry->approved_date) {
                        $approvedDate = $entry->approved_date instanceof \Carbon\Carbon
                            ? $entry->approved_date->format('d/m/Y')
                            : \Carbon\Carbon::parse($entry->approved_date)->format('d/m/Y');
                        $html .= '<p class="mb-2"><strong>Fecha de aprobación:</strong> '.$approvedDate.'</p>';
                    }

                    if ($entry->approval_justification) {
                        $html .= '<p class="mb-0"><strong>Justificación:</strong> '.nl2br(e($entry->approval_justification)).'</p>';
                    }

                    $entry->loadMissing(['details.product']);
                    if ($entry->hasRejectedLineAuthorizations()) {
                        $html .= '<div class="alert alert-warning mt-3 mb-0"><strong>Ítems no autorizados para compra:</strong><ul class="mb-0 mt-2">';
                        foreach ($entry->details as $d) {
                            if ($d->line_authorization_status !== \App\Models\PurchaseRequestDetail::LINE_AUTH_REJECTED) {
                                continue;
                            }
                            $lbl = $d->product ? $d->product->name : ($d->product_description ?? 'Producto #'.$d->product_id);
                            $html .= '<li><strong>'.e($lbl).':</strong> '.e((string) ($d->line_authorization_rejection_reason ?? '—')).'</li>';
                        }
                        $html .= '</ul></div>';
                    }

                    $viewerIsRepresentanteLegal = $user->hasRole('role_representante_legal', 'backpack')
                        || $user->hasRole('role_representante_legal', 'web')
                        || $user->getRoleNames()->contains('role_representante_legal');

                    if ($viewerIsRepresentanteLegal
                        && $entry->status === 'Aprobada'
                        && ! $entry->purchaseOrders()->exists()) {
                        $html .= '<div class="mt-3 pt-3 border-top">';
                        $html .= '<p class="mb-2 text-dark"><strong><i class="la la-undo"></i> Cancelar aprobación</strong></p>';
                        $html .= '<p class="small text-muted mb-2">La solicitud volverá a <strong>pendiente</strong>. Solo puede anularse si aún no existe ninguna orden de compra asociada.</p>';
                        $html .= '<form method="POST" action="'.e(route('purchase-request.cancel-approval', $entry->id)).'" class="border rounded p-3 bg-light" onsubmit="return confirm(\'¿Confirmar la anulación de la aprobación? La solicitud quedará pendiente.\');">';
                        $html .= csrf_field();
                        $html .= '<div class="mb-3">';
                        $html .= '<label for="cancellation_reason_'.$entry->id.'" class="form-label">Motivo de la anulación <span class="text-danger">*</span></label>';
                        $html .= '<textarea name="cancellation_reason" id="cancellation_reason_'.$entry->id.'" class="form-control" rows="3" required maxlength="1000" placeholder="Indique el motivo"></textarea>';
                        $html .= '</div>';
                        $html .= '<button type="submit" class="btn btn-warning"><i class="la la-undo"></i> Confirmar anulación de aprobación</button>';
                        $html .= '</form>';
                        $html .= '</div>';
                    }

                    $html .= '</div>';
                    $html .= '</div>';

                    return $html;
                }

                // Si la solicitud está rechazada, mostrar información
                if ($entry->status === 'Rechazada') {
                    $html = '<div class="card border-danger mt-3">';
                    $html .= '<div class="card-header bg-danger text-white">';
                    $html .= '<h6 class="mb-0"><i class="la la-times-circle"></i> Solicitud Rechazada</h6>';
                    $html .= '</div>';
                    $html .= '<div class="card-body">';

                    if ($entry->approvedBy) {
                        $html .= '<p class="mb-2"><strong>Rechazada por:</strong> '.e($entry->approvedBy->name).'</p>';
                    }

                    if ($entry->approved_date) {
                        $rejectedDate = $entry->approved_date instanceof \Carbon\Carbon
                            ? $entry->approved_date->format('d/m/Y')
                            : \Carbon\Carbon::parse($entry->approved_date)->format('d/m/Y');
                        $html .= '<p class="mb-0"><strong>Fecha de rechazo:</strong> '.$rejectedDate.'</p>';
                    }

                    $entry->loadMissing(['details.product']);
                    if ($entry->details->contains(fn ($d) => $d->line_authorization_status === \App\Models\PurchaseRequestDetail::LINE_AUTH_REJECTED)) {
                        $html .= '<div class="alert alert-light border mt-3 mb-0"><strong>Motivos por ítem:</strong><ul class="mb-0 mt-2 small">';
                        foreach ($entry->details as $d) {
                            if ($d->line_authorization_status !== \App\Models\PurchaseRequestDetail::LINE_AUTH_REJECTED) {
                                continue;
                            }
                            $lbl = $d->product ? $d->product->name : ($d->product_description ?? 'Producto #'.$d->product_id);
                            $html .= '<li><strong>'.e($lbl).':</strong> '.e((string) ($d->line_authorization_rejection_reason ?? '—')).'</li>';
                        }
                        $html .= '</ul></div>';
                    }

                    $html .= '</div>';
                    $html .= '</div>';

                    return $html;
                }

                // Completada sin approved_by / approved_date: sin bloque de aprobación (el resumen ya se mostró arriba si existía)
                if ($entry->status === 'Completada') {
                    return '';
                }

                // Si es una compra directa pendiente de autorización, no mostrar nada aquí
                // (se maneja en la columna direct_purchase_superior_actions)
                if ($entry->is_direct_purchase && $entry->direct_purchase_authorization_requested && ! $entry->direct_purchase_authorized_by && ! $entry->direct_purchase_authorization_rejected) {
                    return '';
                }

                // Recalcular monto efectivo (cotizaciones seleccionadas, incluyendo IVA) para validar límites correctamente en UI.
                $effectiveTotal = $this->recalculateSelectedQuotationsTotalForPurchaseRequest($entry);
                $comprasLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_responsable_compras');
                $entryForApproval = clone $entry;
                $entryForApproval->total_amount = $effectiveTotal;
                $entryForApproval->requires_admin_approval = $effectiveTotal > $comprasLimit;

                // Verificar si el usuario puede aprobar esta solicitud
                if (! $entryForApproval->canBeApprovedBy($user)) {
                    // Si es responsable de compras y supera su límite
                    if ($user->hasRole('role_responsable_compras', 'backpack')) {
                        $adminLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_admin_institucion');
                        $apoderadoLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_apoderado');
                        $representanteLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_representante_legal');

                        return $adminReviewSummaryHtml.'<div class="alert alert-warning mt-3">
                            <i class="la la-exclamation-triangle"></i> 
                            <strong>Límite excedido:</strong> Esta solicitud ($'.number_format($effectiveTotal, 2).') supera tu límite de autorización de $'.number_format($comprasLimit, 2).'. No puedes aprobar esta solicitud. Requiere aprobación del administrador del instituto (límite: $'.number_format($adminLimit, 2).'), apoderado (límite: $'.number_format($apoderadoLimit, 2).') o representante legal (límite: $'.number_format($representanteLimit, 2).').
                        </div>';
                    }

                    // Si es administrador del instituto y supera su límite
                    if ($user->hasRole('role_admin_institucion', 'backpack')) {
                        $adminLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_admin_institucion');

                        return $adminReviewSummaryHtml.'<div class="alert alert-danger mt-3">
                            <i class="la la-exclamation-triangle"></i> 
                            <strong>Límite excedido:</strong> Esta solicitud ($'.number_format($effectiveTotal, 2).') supera su límite de autorización de $'.number_format($adminLimit, 2).'. No puede aprobar esta solicitud; use el bloque <strong>Acciones de nivel superior</strong> para solicitar intervención del nivel correspondiente.
                        </div>';
                    }

                    // Si es apoderado y supera su límite
                    if ($user->hasRole('role_apoderado', 'backpack')) {
                        $apoderadoLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_apoderado');

                        return $adminReviewSummaryHtml.'<div class="alert alert-danger mt-3">
                            <i class="la la-exclamation-triangle"></i> 
                            <strong>Límite excedido:</strong> Esta solicitud ($'.number_format($effectiveTotal, 2).') supera tu límite de autorización de $'.number_format($apoderadoLimit, 2).'. No puedes aprobar esta solicitud.
                        </div>';
                    }

                    // Si es representante legal y supera su límite
                    if ($user->hasRole('role_representante_legal', 'backpack')) {
                        $representanteLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_representante_legal');

                        return $adminReviewSummaryHtml.'<div class="alert alert-danger mt-3">
                            <i class="la la-exclamation-triangle"></i> 
                            <strong>Límite excedido:</strong> Esta solicitud ($'.number_format($effectiveTotal, 2).') supera tu límite de autorización de $'.number_format($representanteLimit, 2).'. No puedes aprobar esta solicitud.
                        </div>';
                    }

                    // Si requiere aprobación de administrador y el usuario no es admin, apoderado ni representante legal
                    if ($entryForApproval->requires_admin_approval) {
                        $adminLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_admin_institucion');
                        $apoderadoLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_apoderado');
                        $representanteLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_representante_legal');

                        return $adminReviewSummaryHtml.'<div class="alert alert-warning mt-3">
                            <i class="la la-exclamation-triangle"></i> 
                            <strong>Requiere aprobación:</strong> Esta solicitud ($'.number_format($effectiveTotal, 2).') supera el límite de autorización del responsable de compras ($'.number_format($comprasLimit, 2).'). Requiere aprobación del administrador del instituto (límite: $'.number_format($adminLimit, 2).'), apoderado (límite: $'.number_format($apoderadoLimit, 2).') o representante legal (límite: $'.number_format($representanteLimit, 2).').
                        </div>';
                    }

                    return $adminReviewSummaryHtml;
                }

                // Mostrar formulario de aprobación/rechazo
                $html = $adminReviewSummaryHtml.'<div class="card border-primary mt-3">';
                $html .= '<div class="card-header bg-primary text-white">';
                $html .= '<h6 class="mb-0"><i class="la la-check-circle"></i> Acciones de Aprobación</h6>';
                $html .= '</div>';
                $html .= '<div class="card-body">';

                $hasSelectedQuotation = ! empty($entry->selected_market_rate_id)
                    || $entry->marketRates()->where('is_selected', true)->exists();
                if (! $entry->is_direct_purchase && ! $hasSelectedQuotation) {
                    $html .= '<div class="alert alert-warning mb-3">';
                    $html .= '<i class="la la-exclamation-triangle"></i> Debe seleccionar al menos una cotización en "Cotizaciones Disponibles" antes de aprobar.';
                    $html .= '</div>';
                }

                // Formulario para aprobar
                if ($entry->is_direct_purchase || $hasSelectedQuotation) {
                    $entry->loadMissing(['details.product']);
                    $html .= '<form method="POST" action="'.route('purchase-request.approve', $entry->id).'" class="mb-3">';
                    $html .= csrf_field();
                    if (! $entry->is_direct_purchase && $entry->details->isNotEmpty()) {
                        $html .= '<p class="text-muted small">Indique para cada producto si <strong>autoriza</strong> o <strong>no autoriza</strong> la compra. Si no autoriza, el motivo es obligatorio. Se notificará por correo al <strong>responsable del área</strong>.</p>';
                        if ($entry->admin_quotation_reviewed_at && $entry->admin_quotation_review_justification) {
                            $html .= '<div class="alert alert-light border mb-3 small"><strong>Justificación ya registrada por la administración del instituto</strong> (referencia):<div class="mt-1 text-break" style="white-space: pre-wrap;">'.e($entry->admin_quotation_review_justification).'</div></div>';
                        }
                        $html .= '<div class="table-responsive mb-3"><table class="table table-sm table-bordered align-middle">';
                        $html .= '<thead class="table-light"><tr><th>Producto</th><th class="text-center" style="width:220px;">Autorizar compra</th><th style="min-width:200px;">Motivo (si no autoriza)</th></tr></thead><tbody>';
                        foreach ($entry->details as $d) {
                            $label = $d->product ? $d->product->name : ($d->product_description ?? 'Ítem #'.$d->id);
                            $las = $d->line_authorization_status ?? \App\Models\PurchaseRequestDetail::LINE_AUTH_PENDING;
                            $approvedChecked = $las === \App\Models\PurchaseRequestDetail::LINE_AUTH_REJECTED ? '' : ' checked';
                            $rejectedChecked = $las === \App\Models\PurchaseRequestDetail::LINE_AUTH_REJECTED ? ' checked' : '';
                            $rejReason = e((string) ($d->line_authorization_rejection_reason ?? ''));
                            $html .= '<tr>';
                            $html .= '<td>'.e($label).'</td>';
                            $html .= '<td class="text-center">';
                            $html .= '<div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="line_decision['.(int) $d->id.']" id="line_ap_'.(int) $d->id.'" value="'.\App\Models\PurchaseRequestDetail::LINE_AUTH_APPROVED.'"'.$approvedChecked.'><label class="form-check-label" for="line_ap_'.(int) $d->id.'">Sí</label></div> ';
                            $html .= '<div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="line_decision['.(int) $d->id.']" id="line_rj_'.(int) $d->id.'" value="'.\App\Models\PurchaseRequestDetail::LINE_AUTH_REJECTED.'"'.$rejectedChecked.'><label class="form-check-label" for="line_rj_'.(int) $d->id.'">No</label></div>';
                            $html .= '</td>';
                            $html .= '<td><textarea name="line_rejection_reason['.(int) $d->id.']" class="form-control form-control-sm" rows="2" maxlength="1000" placeholder="Obligatorio si marca «No»">'.$rejReason.'</textarea></td>';
                            $html .= '</tr>';
                        }
                        $html .= '</tbody></table></div>';
                    }
                    $html .= '<div class="mb-3">';
                    $html .= '<label for="approval_justification" class="form-label">Justificación de la decisión:</label>';
                    $html .= '<textarea name="approval_justification" id="approval_justification" class="form-control" rows="3" required></textarea>';
                    $html .= '</div>';
                    $html .= '<button type="submit" class="btn btn-success" onclick="return confirm(\'¿Confirma registrar la decisión de autorización por ítem?\')">';
                    $html .= '<i class="la la-check"></i> Confirmar decisión';
                    $html .= '</button>';
                    $html .= '</form>';
                }

                // Botón para rechazar
                $html .= '<form method="POST" action="'.route('purchase-request.reject', $entry->id).'" class="d-inline ms-2">';
                $html .= csrf_field();
                $html .= '<button type="submit" class="btn btn-danger" onclick="return confirm(\'¿Está seguro de rechazar esta solicitud de compra?\')">';
                $html .= '<i class="la la-times"></i> Rechazar Solicitud';
                $html .= '</button>';
                $html .= '</form>';

                $html .= '</div>';
                $html .= '</div>';

                return $html;
            });

        CRUD::column('purchase_request_show_actions_heading')
            ->label(' ')
            ->type('custom_html')
            ->value(function ($entry) {
                $entry->loadMissing(['requestingUser', 'createdBy', 'responsibilityArea']);
                $num = e((string) ($entry->request_number ?? '#'.$entry->id));
                $fecha = $entry->request_date
                    ? ($entry->request_date instanceof \Carbon\Carbon
                        ? $entry->request_date->format('d/m/Y')
                        : e((string) $entry->request_date))
                    : '—';
                $estado = e((string) ($entry->status ?? ''));
                $prioridad = e((string) ($entry->priority ?? '—'));
                $area = e((string) ($entry->responsibilityArea->name ?? '—'));
                $solicitante = e(
                    $entry->createdBy?->name
                    ?? $entry->requestingUser?->name
                    ?? '—'
                );
                $motivo = trim((string) ($entry->justification ?? ''));
                $motivoHtml = $motivo !== '' ? nl2br(e($motivo)) : '—';
                $observaciones = trim((string) ($entry->observations ?? ''));
                $observacionesHtml = $observaciones !== '' ? nl2br(e($observaciones)) : '';

                $user = backpack_user();
                $isAdminDecisionLayout = $user && ! $user->hasResponsableAreaOrInstituteAuthorityRole() && (
                    $user->hasAdministradoraInstitucionRole()
                    || $user->hasRole('role_admin_sistema', 'backpack')
                ) && ! ($user->hasRole('role_representante_legal', 'backpack') || $user->hasRole('role_apoderado', 'backpack'));

                $rates = $this->marketRatesContributingToPurchaseRequestTotal($entry);
                $totalEfectivo = $this->recalculateSelectedQuotationsTotalForPurchaseRequest($entry);
                $ivaSum = (float) $rates->sum(fn ($mr) => max(0, (float) ($mr->vat_amount ?? 0)));
                $subFromFields = (float) $rates->sum(fn ($mr) => (float) ($mr->total_amount ?? 0));
                $subMostrar = $rates->isNotEmpty()
                    ? (max(0, $totalEfectivo - $ivaSum) > 0.005 ? max(0, $totalEfectivo - $ivaSum) : max(0, $subFromFields))
                    : (float) ($entry->total_amount ?? 0);

                $fmtMoney = static fn (float $v): string => '$'.number_format($v, 2, ',', '.');

                if ($isAdminDecisionLayout) {
                    $montosRow = $rates->isNotEmpty()
                        ? '<div class="col-6 col-md-4"><div class="pr-decision-kpi"><div class="pr-decision-kpi-label">Subtotal</div><strong>'.$fmtMoney($subMostrar).'</strong></div></div>'
                            .'<div class="col-6 col-md-4"><div class="pr-decision-kpi"><div class="pr-decision-kpi-label">IVA</div><strong>'.$fmtMoney($ivaSum).'</strong></div></div>'
                            .'<div class="col-12 col-md-4"><div class="pr-decision-kpi"><div class="pr-decision-kpi-label">Total con IVA</div><strong class="text-primary">'.$fmtMoney($totalEfectivo).'</strong></div></div>'
                        : '<div class="col-12"><div class="pr-decision-kpi"><div class="pr-decision-kpi-label">Monto total</div><strong class="text-primary">'.$fmtMoney((float) ($entry->total_amount ?? 0)).'</strong> <span class="text-muted small">(IVA al seleccionar cotización)</span></div></div>';

                    $comprasLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_responsable_compras');
                    $effectiveForHint = $rates->isNotEmpty() ? $totalEfectivo : (float) ($entry->total_amount ?? 0);
                    $requiresAdminApproval = $effectiveForHint > $comprasLimit || (bool) $entry->requires_admin_approval;
                    $approvalHint = '';
                    if ($requiresAdminApproval && in_array((string) $entry->status, ['Pendiente', 'En Proceso'], true)) {
                        $approvalHint = '<div class="col-12"><div class="alert alert-warning py-2 px-3 mb-0 small"><i class="la la-exclamation-triangle"></i> Supera el tope de compras; requiere autorización superior según monto.</div></div>';
                    }

                    $observacionesRow = $observacionesHtml !== ''
                        ? '<div class="col-12"><div class="pr-decision-kpi"><div class="pr-decision-kpi-label">Observaciones</div><div class="small text-break" style="white-space: pre-wrap;">'.$observacionesHtml.'</div></div></div>'
                        : '';

                    $html = '<div class="card border-primary bg-light mb-3 pr-decision-summary">'
                        .'<div class="card-body py-3 px-3">'
                        .'<div class="row g-2">'
                        .'<div class="col-6 col-md-3"><div class="pr-decision-kpi"><div class="pr-decision-kpi-label">Número</div><strong class="text-primary">'.$num.'</strong></div></div>'
                        .'<div class="col-6 col-md-3"><div class="pr-decision-kpi"><div class="pr-decision-kpi-label">Fecha</div><strong>'.$fecha.'</strong></div></div>'
                        .'<div class="col-6 col-md-3"><div class="pr-decision-kpi"><div class="pr-decision-kpi-label">Estado</div><span class="badge bg-secondary">'.$estado.'</span></div></div>'
                        .'<div class="col-6 col-md-3"><div class="pr-decision-kpi"><div class="pr-decision-kpi-label">Prioridad</div><strong>'.$prioridad.'</strong></div></div>'
                        .'<div class="col-12 col-md-6"><div class="pr-decision-kpi"><div class="pr-decision-kpi-label">Área</div><strong>'.$area.'</strong></div></div>'
                        .'<div class="col-12 col-md-6"><div class="pr-decision-kpi"><div class="pr-decision-kpi-label">Solicitante</div><strong>'.$solicitante.'</strong></div></div>'
                        .'<div class="col-12"><div class="pr-decision-kpi"><div class="pr-decision-kpi-label">Motivo</div><div class="small text-break" style="white-space: pre-wrap;">'.$motivoHtml.'</div></div></div>'
                        .$observacionesRow
                        .$montosRow
                        .$approvalHint
                        .'</div></div></div>'
                        .'<h5 class="mb-3 text-dark"><i class="la la-gavel text-primary"></i> Para decidir</h5>';

                    return $html;
                }

                $montosHtml = '';
                if ($rates->isNotEmpty()) {
                    $montosHtml = '<div class="mt-2 pt-2 border-top w-100">'
                        .'<div class="d-flex flex-wrap align-items-center gap-2 gap-md-3">'
                        .'<span class="text-nowrap"><span class="text-muted small d-block">Subtotal</span><strong class="fs-6">'.$fmtMoney($subMostrar).'</strong></span>'
                        .'<span class="badge bg-warning text-dark px-3 py-2 fs-6 text-nowrap shadow-sm"><i class="la la-balance-scale"></i> IVA '.$fmtMoney($ivaSum).'</span>'
                        .'<span class="text-nowrap ms-md-auto"><span class="text-muted small d-block">Total con IVA</span><strong class="fs-5 text-primary">'.$fmtMoney($totalEfectivo).'</strong></span>'
                        .'</div></div>';
                } else {
                    $montosHtml = '<div class="mt-2 pt-2 border-top w-100 d-flex flex-wrap align-items-center gap-2">'
                        .'<span><span class="text-muted small d-block">Monto total (solicitud)</span><strong class="fs-5 text-primary">'.$fmtMoney((float) ($entry->total_amount ?? 0)).'</strong></span>'
                        .'<span class="badge bg-secondary fs-6">IVA: al seleccionar cotización</span>'
                        .'</div>';
                }

                $html = '<div class="card border-primary bg-light mb-3">'
                    .'<div class="card-body py-2 px-3">'
                    .'<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">'
                    .'<span><strong class="text-primary">'.$num.'</strong>'
                    .'<span class="text-muted ms-2"><i class="la la-calendar"></i> '.$fecha.'</span>'
                    .'<span class="text-muted ms-2"><i class="la la-user"></i> <span class="small">Solicitante:</span> '.$solicitante.'</span></span>'
                    .'<span class="badge bg-secondary fs-6">'.$estado.'</span>'
                    .'</div>';

                $html .= $montosHtml
                    .'</div></div>';

                $isSuperiorAuthorityShowLayout = $user && (
                    $user->hasRole('role_representante_legal', 'backpack')
                    || $user->hasRole('role_apoderado', 'backpack')
                );

                if (! $isSuperiorAuthorityShowLayout && ! $isAdminDecisionLayout) {
                    $html .= '<h5 class="mb-3 text-dark"><i class="la la-bolt text-primary"></i> Acciones, cotizaciones y seguimiento</h5>';
                }

                return $html;
            });

        CRUD::column('purchase_request_superior_quotation_actions')
            ->label('Cotizaciones — escalamiento superior')
            ->type('custom_html')
            ->value(fn (\App\Models\PurchaseRequest $entry) => $this->renderSuperiorQuotationEscalationHtml($entry));

        CRUD::orderColumns([
            'purchase_request_show_actions_heading',
            'market_rates_table',
            'details_table',
            'supplier_suggestions_table',
            'direct_purchase_compras_suggest',
            'purchase_orders_table',
            'create_payment_orders_from_pr',
            'delivery_reception_actions',
            'request_number',
            'request_date',
            'status',
            'priority',
            'justification',
            'observations',
            'responsibilityArea.name',
            'requestingUser.name',
            'approvedBy.name',
            'approved_date',
            'total_amount',
            'approval_status',
            'general_request_info',
            'purchase_request_superior_quotation_actions',
            'approval_actions',
            'direct_purchase_superior_actions',
        ]);
    }

    /**
     * API endpoint to get purchase request data for quick purchase
     */
    public function getPurchaseRequestData($id)
    {
        $purchaseRequest = \App\Models\PurchaseRequest::with(['details.product'])
            ->findOrFail($id);

        return response()->json([
            'id' => $purchaseRequest->id,
            'request_number' => $purchaseRequest->request_number,
            'total_amount' => $purchaseRequest->total_amount,
            'details' => $purchaseRequest->details->map(function ($detail) {
                return [
                    'id' => $detail->id,
                    'product_id' => $detail->product_id,
                    'product_description' => $detail->product_description,
                    'product' => $detail->product ? [
                        'id' => $detail->product->id,
                        'name' => $detail->product->name,
                    ] : null,
                    'requested_quantity' => $detail->requested_quantity,
                    'estimated_unit_price' => $detail->estimated_unit_price,
                ];
            }),
        ]);
    }

    /**
     * API endpoint to get suppliers list
     */
    public function getSuppliers()
    {
        $suppliers = \App\Models\Supplier::select('id', 'company_name')->get();

        return response()->json($suppliers);
    }
}
