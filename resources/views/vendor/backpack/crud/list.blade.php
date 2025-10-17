@extends(backpack_view('blank'))

@php
  $defaultBreadcrumbs = [
    trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
    $crud->entity_name_plural => url($crud->route),
    trans('backpack::crud.list') => false,
  ];

  // if breadcrumbs aren't defined in the CrudController, use the default breadcrumbs
  $breadcrumbs = $breadcrumbs ?? $defaultBreadcrumbs;
@endphp

@section('header')
  <div class="container-fluid">
    <h2>
      <span class="text-capitalize">{!! $crud->getHeading() ?? $crud->entity_name_plural !!}</span>
      <small id="datatable_info_stack">{!! $crud->getSubheading() ?? '' !!}</small>
    </h2>
  </div>
@endsection

@section('content')
<!-- Default box -->
  <div class="row">

    <!-- THE ACTUAL CONTENT -->
    <div class="{{ $crud->getListContentClass() }}">
      <div class="row mb-0">
        <div class="col-sm-6">
          @if ( $crud->buttons()->where('stack', 'top')->count() ||  $crud->exportButtons())
          <div class="d-print-none {{ $crud->hasAccess('create')?'with-border':'' }}">

            @include('crud::inc.button_stack', ['stack' => 'top'])

          </div>
          @endif
        </div>
        <div class="col-sm-6">
          <div id="datatable_search_stack" class="mt-sm-0 mt-2 d-print-none"></div>
        </div>
      </div>

      {{-- Backpack List Filters --}}
      @if ($crud->filtersEnabled())
        @include('crud::inc.filters_navbar')
      @endif

      <div class="overflow-hidden mt-2">

        @if($crud->route == 'admin/supplier')
          <!-- Filtro personalizado para proveedores -->
          <div class="card mb-3">
            <div class="card-header bg-primary text-white">
              <h6 class="card-title mb-0">
                <i class="fas fa-filter"></i> Filtros
              </h6>
            </div>
            <div class="card-body py-2">
              <form method="GET" action="{{ url($crud->route) }}">
                <div class="row">
                  <div class="col-md-4">
                    <label for="nombre" class="form-label">Nombre:</label>
                    <input type="text" name="nombre" id="nombre" class="form-control" placeholder="Buscar por nombre..." value="{{ request('nombre') }}">
                  </div>
                  <div class="col-md-4">
                    <label for="rubro" class="form-label">Rubro:</label>
                    <select name="rubro" id="rubro" class="form-control select2" onchange="this.form.submit()">
                      <option value="">Todos los rubros</option>
                      @foreach(\App\Models\SuppliersHeading::all() as $rubro)
                        <option value="{{ $rubro->id }}" {{ request('rubro') == $rubro->id ? 'selected' : '' }}>
                          {{ $rubro->name }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label for="sector" class="form-label">Sector:</label>
                    <select name="sector" id="sector" class="form-control select2" onchange="this.form.submit()">
                      <option value="">Todos los sectores</option>
                      @foreach(\App\Models\Sector::all() as $sector)
                        <option value="{{ $sector->id }}" {{ request('sector') == $sector->id ? 'selected' : '' }}>
                          {{ $sector->name }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
              </form>
            </div>
          </div>
        @endif

        <div class="table-responsive">
          <table id="crudTable" class="bg-white table table-striped table-hover nowrap rounded shadow-xs border-xs" cellspacing="0">
            <thead>
              <tr>
                {{-- Table columns --}}
                @foreach ($crud->columns() as $column)
                  <th
                    data-orderable="{{ var_export($column['orderable'], true) }}"
                    data-priority="{{ $column['priority'] ?? ($loop->iteration <= 2 ? 1 : 2) }}"
                    data-visible-in-table="{{ var_export($column['visibleInTable'] ?? true, true) }}"
                    data-visible="{{ var_export($column['visibleInTable'] ?? true, true) }}"
                    data-can-be-visible-in-table="true"
                    data-backpack-column="{{ var_export($column['backpack_column'] ?? false, true) }}"
                    class="{{ $column['class'] ?? '' }}"
                    >
                    {!! $column['label'] !!}
                  </th>
                @endforeach

                @if ( $crud->buttons()->where('stack', 'line')->count() )
                  <th data-orderable="false" data-priority="{{ $crud->getActionsColumnPriority() }}" data-visible-in-table="false" data-can-be-visible-in-table="false" class="text-center">{{ trans('backpack::crud.actions') }}</th>
                @endif
              </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
              <tr>
                {{-- Table columns --}}
                @foreach ($crud->columns() as $column)
                  <th>{!! $column['label'] !!}</th>
                @endforeach

                @if ( $crud->buttons()->where('stack', 'line')->count() )
                  <th class="text-center">{{ trans('backpack::crud.actions') }}</th>
                @endif
              </tr>
            </tfoot>
          </table>
        </div>

      </div><!-- /.box-body -->

    </div><!-- /.box -->
  </div>

@endsection

@section('after_styles')
  <!-- DATA TABLES -->
  <link rel="stylesheet" type="text/css" href="{{ asset('packages/datatables.net-bs4/css/dataTables.bootstrap4.min.css') }}">
  <link rel="stylesheet" type="text/css" href="{{ asset('packages/datatables.net-fixedheader-bs4/css/fixedHeader.bootstrap4.min.css') }}">
  <link rel="stylesheet" type="text/css" href="{{ asset('packages/datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css') }}">

  <link rel="stylesheet" href="{{ asset('packages/backpack/crud/css/crud.css') }}">
  <link rel="stylesheet" href="{{ asset('packages/backpack/crud/css/form.css') }}">
  <link rel="stylesheet" href="{{ asset('packages/backpack/crud/css/list.css') }}">

  <!-- FontAwesome CSS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  
  <!-- Select2 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-theme@0.1.0-beta.10/dist/select2-bootstrap.min.css" rel="stylesheet" />

  <!-- CRUD LIST CONTENT - crud_list_styles stack -->
  @stack('crud_list_styles')
@endsection

@section('after_scripts')
  @include('crud::inc.datatables_logic')
  <script src="{{ asset('packages/backpack/crud/js/crud.js') }}"></script>
  <script src="{{ asset('packages/backpack/crud/js/form.js') }}"></script>
  <script src="{{ asset('packages/backpack/crud/js/list.js') }}"></script>

  <!-- Select2 JS -->
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

  <!-- CRUD LIST CONTENT - crud_list_scripts stack -->
  @stack('crud_list_scripts')

  <script>
    $(document).ready(function() {
      // Inicializar Select2 para los filtros
      $('.select2').select2({
        theme: 'bootstrap',
        placeholder: 'Buscar...',
        allowClear: true,
        width: '100%'
      });

      // Manejar búsqueda por nombre con debounce
      let nombreTimeout;
      $('#nombre').on('input', function() {
        clearTimeout(nombreTimeout);
        nombreTimeout = setTimeout(() => {
          $(this).closest('form').submit();
        }, 500); // Espera 500ms después de que el usuario deje de escribir
      });
    });
  </script>
@endsection
