@if ($crud->hasAccess('show', $entry))
    <a href="{{ route('internal-voucher.pdf', $entry->getKey()) }}"
       class="btn btn-sm btn-info"
       data-toggle="tooltip"
       title="Ver PDF del comprobante interno">
        <i class="la la-file-pdf"></i> <span>PDF</span>
    </a>
@endif
