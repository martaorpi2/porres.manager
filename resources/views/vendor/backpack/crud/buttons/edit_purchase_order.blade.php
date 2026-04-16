@php
    $blockedByOp = isset($entry->active_payment_orders_count)
        ? (int) $entry->active_payment_orders_count > 0
        : $entry->hasBlockingPaymentOrder();
    $canEdit = ! $blockedByOp && $crud->hasAccess('update', $entry);
@endphp

@if ($canEdit)
    <a href="{{ url($crud->route.'/'.$entry->getKey().'/edit') }}" bp-button="update" class="btn btn-sm btn-link">
        <i class="la la-edit"></i> <span>{{ trans('backpack::crud.edit') }}</span>
    </a>
@endif
