@php
    /** @var \Illuminate\Support\Collection|array $columns */
    $block1Names = [
        'purchase_request_show_actions_heading',
        'market_rates_table',
        'details_table',
        'supplier_suggestions_table',
        'direct_purchase_compras_suggest',
        'purchase_orders_table',
        'create_payment_orders_from_pr',
        'delivery_reception_actions',
    ];
    $block2Names = [
        'purchase_request_show_info_section_heading',
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
    $block3Names = [
        'purchase_request_show_superior_section_heading',
        'purchase_request_superior_quotation_actions',
        'approval_actions',
        'direct_purchase_superior_actions',
    ];
    $nameToBlock = [];
    foreach ($block1Names as $n) {
        $nameToBlock[$n] = 1;
    }
    foreach ($block2Names as $n) {
        $nameToBlock[$n] = 2;
    }
    foreach ($block3Names as $n) {
        $nameToBlock[$n] = 3;
    }
    $cols1 = [];
    $cols2 = [];
    $cols3 = [];
    foreach ($columns as $col) {
        $n = $col['name'] ?? '';
        $b = $nameToBlock[$n] ?? 1;
        if ($b === 2) {
            $cols2[] = $col;
        } elseif ($b === 3) {
            $cols3[] = $col;
        } else {
            $cols1[] = $col;
        }
    }
@endphp

{{-- Bloque 1: acciones, cotizaciones y seguimiento (mismo contenido; mismo envoltorio card que la vista show estándar) --}}
<div class="purchase-request-show-block purchase-request-show-block--actions card no-padding no-border mb-4">
    @if (count($cols1))
        <table class="table table-striped m-0 p-0">
            <tbody>
                @include('admin.purchase-request.inc.show_table_rows', ['columns' => $cols1])
            </tbody>
        </table>
    @endif
</div>

{{-- Bloque 2: información de la solicitud (títulos y textos introductorios vienen de las columnas) --}}
@if (count($cols2))
    <div class="purchase-request-show-block purchase-request-show-block--info card mb-4 border-secondary shadow-sm">
        <div class="card-body p-0">
            <table class="table table-striped m-0 p-0 mb-0">
                <tbody>
                    @include('admin.purchase-request.inc.show_table_rows', ['columns' => $cols2])
                </tbody>
            </table>
        </div>
    </div>
@endif

@php
    $hasLineButtons = $crud->buttons()->where('stack', 'line')->count() && ($displayActionsColumn ?? true);
@endphp

{{-- Bloque 3: acciones de nivel superior --}}
@if (count($cols3) || $hasLineButtons)
    <div class="purchase-request-show-block purchase-request-show-block--superior card mb-0 border-secondary shadow-sm">
        <div class="card-body p-0">
            @if (count($cols3))
                <table class="table table-striped m-0 p-0 mb-0">
                    <tbody>
                        @include('admin.purchase-request.inc.show_table_rows', ['columns' => $cols3])
                    </tbody>
                </table>
            @endif
            @if ($hasLineButtons)
                <table class="table table-striped m-0 p-0 @if (count($cols3)) border-top @endif mb-0">
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
            @endif
        </div>
    </div>
@endif
