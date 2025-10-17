@if ($crud->hasAccess('list'))
  <a href="{{ url($crud->route.'/export/excel') }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}" class="btn btn-sm btn-link">
    <i class="la la-file-excel-o"></i> Exportar Excel
  </a>
@endif
