@if ($crud->hasAccess('list'))
  <a href="{{ url($crud->route.'/export/pdf') }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}" class="btn btn-sm btn-link">
    <i class="la la-file-pdf-o"></i> Exportar PDF
  </a>
@endif
