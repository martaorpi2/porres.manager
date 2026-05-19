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
        $block3PrimaryNames = [
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
    }

    $cols1 = [];
    $cols2 = [];
    $cols3Primary = [];
    $cols3AreaFollowUp = [];
    $colsAdminCompras = [];
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
        $b = $nameToBlock[$n] ?? 1;
        if ($b === 2) {
            $cols2[] = $col;
        } elseif ($b === 3) {
            $cols3Primary[] = $col;
        } elseif ($b === 4) {
            $colsAdminCompras[] = $col;
        } else {
            $cols1[] = $col;
        }
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
</style>

{{-- Bloque 1: encabezado, aprobación (nivel superior) y cotizaciones --}}
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
@endif

@include('admin.purchase-request.inc.show_collapsible_block', [
    'blockId' => 'purchase-request-show-info',
    'modifier' => 'info',
    'title' => 'Información de la solicitud',
    'icon' => 'la la-info-circle',
    'contentColumns' => $cols2,
])

@if (! $usesSuperiorAuthorityShowLayout)
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

