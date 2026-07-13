@php
    /** @var \Illuminate\Support\Collection|array $columns */
    $prShowUser = backpack_user();
    $prShowEntry = $crud->getCurrentEntry();
    $isResponsableArea = $prShowUser && $prShowUser->hasResponsableAreaOrInstituteAuthorityRole();
    $usesSuperiorAuthorityShowLayout = $prShowUser && (
        $prShowUser->hasRole('role_representante_legal', 'backpack')
        || $prShowUser->hasRole('role_apoderado', 'backpack')
    );
    $isRepresentanteLegalViewer = $prShowUser && $prShowUser->hasRole('role_representante_legal', 'backpack');
    $usesAdminInstitucionDecisionLayout = $prShowUser && ! $usesSuperiorAuthorityShowLayout && ! $isResponsableArea && (
        $prShowUser->hasAdministradoraInstitucionRole()
        || $prShowUser->hasRole('role_admin_sistema', 'backpack')
    );
    $needsApprovalAboveRepresentante = false;
    $representanteIsPendingSuperiorTarget = false;
    if ($prShowEntry && $isRepresentanteLegalViewer) {
        $prShowEntry->loadMissing(['marketRates.quoteDetails', 'details.selectedMarketRate.quoteDetails', 'purchaseRequestEvents']);
        $needsApprovalAboveRepresentante = $prShowEntry->requiresApprovalAboveRepresentanteLegal();
        $representanteIsPendingSuperiorTarget = $prShowEntry->isRepresentanteLegalTargetForPendingSuperiorApproval();
    }

    $block3AreaFollowUpNames = [
        'direct_purchase_compras_suggest',
        'purchase_orders_table',
        'create_payment_orders_from_pr',
    ];

    $adminComprasCollapsedNames = [
        'supplier_suggestions_table',
        'direct_purchase_compras_suggest',
        'purchase_orders_table',
        'create_payment_orders_from_pr',
        'delivery_reception_actions',
        'purchase_request_superior_quotation_actions',
    ];

    $adminOperationalFollowUpNames = [
        'supplier_suggestions_table',
        'direct_purchase_compras_suggest',
        'purchase_orders_table',
        'create_payment_orders_from_pr',
        'delivery_reception_actions',
    ];

    $hiddenWhenSummaryPresent = [
        'request_number',
        'request_date',
        'status',
        'total_amount',
        'requestingUser.name',
    ];

    $hiddenInAdminDecisionLayout = array_merge($hiddenWhenSummaryPresent, [
        'priority',
        'justification',
        'observations',
        'responsibilityArea.name',
        'approval_status',
        'approvedBy.name',
        'approved_date',
    ]);

    $skipDetailsForApprovalForm = false;
    if ($usesAdminInstitucionDecisionLayout && $prShowEntry && $prShowUser) {
        $prShowEntry->loadMissing(['marketRates', 'details']);
        if (
            in_array((string) $prShowEntry->status, ['Pendiente', 'En Proceso'], true)
            && ! $prShowEntry->is_direct_purchase
            && $prShowEntry->hasQuotationSelectionResolved()
            && $prShowEntry->canBeApprovedBy($prShowUser)
        ) {
            $skipDetailsForApprovalForm = true;
        }
    }

    if ($usesSuperiorAuthorityShowLayout) {
        $block1Names = [
            'purchase_request_show_actions_heading',
        ];
        $showApprovalInMainBlock = ! $isRepresentanteLegalViewer
            || $representanteIsPendingSuperiorTarget
            || ! $needsApprovalAboveRepresentante
            || ($prShowEntry && in_array((string) $prShowEntry->status, ['Aprobada', 'Rechazada', 'Completada'], true));
        if ($showApprovalInMainBlock) {
            $block1Names[] = 'approval_actions';
        }
        if ($isRepresentanteLegalViewer && ! $needsApprovalAboveRepresentante) {
            $block1Names[] = 'direct_purchase_superior_actions';
        }
        $block1Names[] = 'market_rates_table';
        $block1Names[] = 'details_table';
    } elseif ($usesAdminInstitucionDecisionLayout) {
        $block1Names = [
            'purchase_request_show_actions_heading',
            'details_table',
            'market_rates_table',
            'approval_actions',
            'purchase_request_superior_quotation_actions',
            'direct_purchase_superior_actions',
        ];
    } else {
        $block1Names = [
            'purchase_request_show_actions_heading',
            'market_rates_table',
            'details_table',
            'supplier_suggestions_table',
        ];
        if (! $isResponsableArea) {
            $block1Names = array_merge($block1Names, $block3AreaFollowUpNames);
        }
        $block1Names[] = 'delivery_reception_actions';
    }

    $block2Names = [
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
    ];
    if ($usesAdminInstitucionDecisionLayout) {
        $block2Names = [
            'general_request_info',
        ];
    }
    if ($usesSuperiorAuthorityShowLayout) {
        $block3PrimaryNames = [];
        if ($isRepresentanteLegalViewer) {
            if ($needsApprovalAboveRepresentante && ! $representanteIsPendingSuperiorTarget) {
                $block3PrimaryNames[] = 'approval_actions';
            }
        } else {
            $block3PrimaryNames[] = 'direct_purchase_superior_actions';
        }
    } else {
        $block3PrimaryNames = $usesAdminInstitucionDecisionLayout
            ? []
            : [
                'purchase_request_superior_quotation_actions',
                'approval_actions',
                'direct_purchase_superior_actions',
            ];
    }

    $nameToBlock = [];
    foreach ($block1Names as $n) {
        $nameToBlock[$n] = 1;
    }
    foreach ($block2Names as $n) {
        $nameToBlock[$n] = 2;
    }
    foreach ($block3PrimaryNames as $n) {
        $nameToBlock[$n] = 3;
    }
    if ($usesSuperiorAuthorityShowLayout) {
        foreach ($adminComprasCollapsedNames as $n) {
            $nameToBlock[$n] = 4;
        }
    } elseif ($usesAdminInstitucionDecisionLayout) {
        foreach ($adminOperationalFollowUpNames as $n) {
            $nameToBlock[$n] = 4;
        }
    }

    $cols1 = [];
    $cols2 = [];
    $cols3Primary = [];
    $cols3AreaFollowUp = [];
    $colsAdminCompras = [];
    $colsAdminOperational = [];
    foreach ($columns as $col) {
        $n = $col['name'] ?? '';
        if (
            $n === 'direct_purchase_compras_suggest'
            && $prShowEntry
            && ! $prShowEntry->is_direct_purchase
        ) {
            $prShowEntry->loadMissing(['purchaseRequestEvents', 'details', 'purchaseOrders', 'marketRates']);
            if ($prShowEntry->isFrozenPendingSuperiorApproval()) {
                continue;
            }
        }
        if ($isResponsableArea && in_array($n, $block3AreaFollowUpNames, true)) {
            $cols3AreaFollowUp[] = $col;
            continue;
        }
        if ($usesAdminInstitucionDecisionLayout && in_array($n, $adminOperationalFollowUpNames, true)) {
            $colsAdminOperational[] = $col;
            continue;
        }
        if ($usesAdminInstitucionDecisionLayout && in_array($n, $hiddenInAdminDecisionLayout, true)) {
            continue;
        }
        if (! $usesAdminInstitucionDecisionLayout && in_array($n, $hiddenWhenSummaryPresent, true)) {
            continue;
        }
        if ($n === 'details_table' && $skipDetailsForApprovalForm) {
            continue;
        }
        if ($n === 'general_request_info' && $usesAdminInstitucionDecisionLayout && $prShowEntry && ! $prShowEntry->converted_from_general_request_id) {
            continue;
        }
        if ($n === 'observations' && $prShowEntry && trim((string) ($prShowEntry->observations ?? '')) === '') {
            continue;
        }
        $b = $nameToBlock[$n] ?? 1;
        if ($b === 2) {
            $cols2[] = $col;
        } elseif ($b === 3) {
            $cols3Primary[] = $col;
        } elseif ($b === 4) {
            if ($usesAdminInstitucionDecisionLayout) {
                $colsAdminOperational[] = $col;
            } else {
                $colsAdminCompras[] = $col;
            }
        } else {
            $cols1[] = $col;
        }
    }
    if ($usesAdminInstitucionDecisionLayout && $prShowEntry) {
        $cols2 = array_values(array_filter($cols2, function ($col) use ($prShowEntry) {
            $n = $col['name'] ?? '';
            if ($n === 'general_request_info' && ! $prShowEntry->converted_from_general_request_id) {
                return false;
            }

            return true;
        }));
    }
    $cols3 = array_merge($cols3Primary, $cols3AreaFollowUp);
    $hasLineButtons = $crud->buttons()->where('stack', 'line')->count() && ($displayActionsColumn ?? true);
    $openSuperiorBlock = $prShowUser && (
        $prShowUser->hasRole('role_admin_institucion', 'backpack')
        || $prShowUser->hasRole('role_admin_sistema', 'backpack')
    );
    $block1ColumnsWithoutLabel = [];
    if ($usesSuperiorAuthorityShowLayout) {
        $block1ColumnsWithoutLabel[] = 'purchase_request_show_actions_heading';
        if ($showApprovalInMainBlock ?? true) {
            $block1ColumnsWithoutLabel[] = 'approval_actions';
        }
    } elseif ($usesAdminInstitucionDecisionLayout) {
        $block1ColumnsWithoutLabel = [
            'purchase_request_show_actions_heading',
            'market_rates_table',
            'approval_actions',
            'purchase_request_superior_quotation_actions',
            'direct_purchase_superior_actions',
        ];
        if (! $skipDetailsForApprovalForm) {
            array_splice($block1ColumnsWithoutLabel, 1, 0, ['details_table']);
        }
    }
    $showSuperiorBlockForRepresentante = $isRepresentanteLegalViewer
        && $needsApprovalAboveRepresentante
        && ! $representanteIsPendingSuperiorTarget;
@endphp

<style>
    .purchase-request-show-block > summary {
        list-style: none;
        cursor: pointer;
    }
    .purchase-request-show-block > summary::-webkit-details-marker {
        display: none;
    }
    .purchase-request-show-block__chevron {
        transition: transform 0.2s ease;
    }
    .purchase-request-show-block[open] .purchase-request-show-block__chevron {
        transform: rotate(180deg);
    }
    .pr-decision-summary .pr-decision-kpi {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 0.45rem 0.65rem;
        height: 100%;
    }
    .pr-decision-summary .pr-decision-kpi-label {
        color: #6c757d;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        margin-bottom: 0.1rem;
    }
    .pr-decision-product-row {
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 0.55rem 0.75rem;
        background: #fff;
    }
</style>

{{-- Bloque 1: lo esencial para decidir --}}
<div class="purchase-request-show-block purchase-request-show-block--actions card no-padding no-border mb-4">
    @if (count($cols1))
        <table class="table table-striped m-0 p-0">
            <tbody>
                @include('admin.purchase-request.inc.show_table_rows', [
                    'columns' => $cols1,
                    'columnsWithoutLabel' => $block1ColumnsWithoutLabel,
                ])
            </tbody>
        </table>
    @endif
</div>

@if ($usesSuperiorAuthorityShowLayout)
    @include('admin.purchase-request.inc.show_collapsible_block', [
        'blockId' => 'purchase-request-show-admin-compras',
        'modifier' => 'admin-compras',
        'title' => 'Acciones de la administradora y/o sector de compras',
        'icon' => 'la la-shopping-cart',
        'contentColumns' => $colsAdminCompras,
        'open' => false,
    ])
@elseif ($usesAdminInstitucionDecisionLayout && count($colsAdminOperational))
    @include('admin.purchase-request.inc.show_collapsible_block', [
        'blockId' => 'purchase-request-show-operational',
        'modifier' => 'operational',
        'title' => 'Seguimiento operativo (órdenes, pagos, entregas)',
        'icon' => 'la la-truck',
        'contentColumns' => $colsAdminOperational,
        'open' => false,
    ])
@endif

@if (count($cols2))
@include('admin.purchase-request.inc.show_collapsible_block', [
    'blockId' => 'purchase-request-show-info',
    'modifier' => 'info',
    'title' => $usesAdminInstitucionDecisionLayout ? 'Más información de la solicitud' : 'Información de la solicitud',
    'icon' => 'la la-info-circle',
    'contentColumns' => $cols2,
    'open' => ! $usesAdminInstitucionDecisionLayout,
])
@endif

@if (! $usesSuperiorAuthorityShowLayout && ! $usesAdminInstitucionDecisionLayout)
    @include('admin.purchase-request.inc.show_collapsible_block', [
        'blockId' => 'purchase-request-show-superior',
        'modifier' => 'superior',
        'title' => 'Acciones de nivel superior',
        'icon' => 'la la-user-shield',
        'cardMarginClass' => $hasLineButtons ? 'mb-4' : 'mb-0',
        'contentColumns' => $cols3,
        'open' => $openSuperiorBlock,
    ])
@elseif ($isRepresentanteLegalViewer && $showSuperiorBlockForRepresentante && count($cols3))
    @include('admin.purchase-request.inc.show_collapsible_block', [
        'blockId' => 'purchase-request-show-superior',
        'modifier' => 'superior',
        'title' => 'Acciones de nivel superior',
        'icon' => 'la la-user-shield',
        'cardMarginClass' => $hasLineButtons ? 'mb-4' : 'mb-0',
        'contentColumns' => $cols3,
        'open' => true,
    ])
@elseif (! $isRepresentanteLegalViewer && count($cols3))
    @include('admin.purchase-request.inc.show_collapsible_block', [
        'blockId' => 'purchase-request-show-superior',
        'modifier' => 'superior',
        'title' => 'Acciones de nivel superior',
        'icon' => 'la la-user-shield',
        'cardMarginClass' => $hasLineButtons ? 'mb-4' : 'mb-0',
        'contentColumns' => $cols3,
        'open' => true,
    ])
@endif

@if ($hasLineButtons)
<div class="card no-padding no-border mb-0">
    <table class="table table-striped m-0 p-0 mb-0">
        <tbody>
            <tr>
                <td>
                    <strong>{{ trans('backpack::crud.actions') }}</strong>
                </td>
                <td>
                    @include('crud::inc.button_stack', ['stack' => 'line'])
                </td>
            </tr>
        </tbody>
    </table>
</div>
@endif

