@if (count($contentColumns))
<details
    @if (! empty($blockId)) id="{{ $blockId }}" @endif
    @if (! empty($open)) open @endif
    class="purchase-request-show-block purchase-request-show-block--{{ $modifier }} card {{ $cardMarginClass ?? 'mb-4' }} border-secondary shadow-sm"
>
    <summary class="purchase-request-show-block__summary px-3 py-3 bg-white d-flex align-items-center gap-2">
        <h5 class="mb-0 text-dark flex-grow-1">
            <i class="{{ $icon }} text-primary"></i> {{ $title }}
        </h5>
        <i class="la la-angle-down purchase-request-show-block__chevron text-muted" aria-hidden="true"></i>
    </summary>
    <div class="purchase-request-show-block__body card-body p-0 border-top">
        @if (count($contentColumns))
            <table class="table table-striped m-0 p-0 mb-0">
                <tbody>
                    @include('admin.purchase-request.inc.show_table_rows', ['columns' => $contentColumns])
                </tbody>
            </table>
        @endif
    </div>
</details>
@endif
