@if ($crud->hasAccess('list'))
  <a href="{{ url($crud->route.'/export/pdf') }}" class="btn btn-sm btn-link" target="_blank">
    <i class="la la-file-pdf-o"></i> Exportar PDF
  </a>
@endif
