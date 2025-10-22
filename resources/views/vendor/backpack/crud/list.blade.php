@extends(backpack_view('blank'))

@push('after_styles')
<style>
/* Custom CSS for ePorres Manager - Color Override */
:root {
    --bs-primary: #871f1f !important;
    --bs-primary-rgb: 135, 31, 31 !important;
}

/* Responsive table controls styling */
.dtr-control {
    cursor: pointer !important;
    color: #871f1f !important;
    font-weight: bold !important;
}

.dtr-control:hover {
    color: #a02a2a !important;
}

.dtr-control:before {
    content: "⋮" !important;
    font-size: 18px !important;
    line-height: 1 !important;
}

/* Ensure responsive controls are visible on mobile */
@media (max-width: 768px) {
    .dtr-control {
        display: inline-block !important;
        visibility: visible !important;
    }
    
    .has-hidden-columns .dtr-control {
        display: inline-block !important;
    }
}

/* Override any existing primary color definitions */
.bg-primary {
    background-color: #6c757d !important;
    color: #871f1f !important;
}

.text-primary {
    color: #871f1f !important;
}

.btn-primary {
    background-color: #871f1f !important;
    border-color: #871f1f !important;
    color: white !important;
}

.btn-primary:hover {
    background-color: #a02a2a !important;
    border-color: #a02a2a !important;
    color: white !important;
}

.btn-primary:focus {
    background-color: #a02a2a !important;
    border-color: #a02a2a !important;
    color: white !important;
    box-shadow: 0 0 0 0.2rem rgba(135, 31, 31, 0.25) !important;
}

.btn-primary:active {
    background-color: #6b1818 !important;
    border-color: #6b1818 !important;
    color: white !important;
}

/* Links */
a {
    color: #871f1f !important;
}

a:hover {
    color: #a02a2a !important;
}

/* Form controls */
.form-control:focus {
    border-color: #871f1f !important;
    box-shadow: 0 0 0 0.2rem rgba(135, 31, 31, 0.25) !important;
}

.form-check-input:checked {
    background-color: #871f1f !important;
    border-color: #871f1f !important;
}

/* Select2 styling */
.select2-container--default .select2-selection--single:focus {
    border-color: #871f1f !important;
}

.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #871f1f !important;
}

/* Pagination */
.page-link {
    color: #871f1f !important;
}

.page-link:hover {
    color: #a02a2a !important;
}

.page-item.active .page-link {
    background-color: #871f1f !important;
    border-color: #871f1f !important;
    color: white !important;
}

/* Badges */
.badge-primary {
    background-color: #871f1f !important;
    color: white !important;
}

/* Alerts */
.alert-primary {
    background-color: rgba(135, 31, 31, 0.1) !important;
    border-color: #871f1f !important;
    color: #6b1818 !important;
}

/* Progress bars */
.progress-bar {
    background-color: #871f1f !important;
    color: white !important;
}

/* Table hover effects */
.table-hover tbody tr:hover {
    background-color: rgba(135, 31, 31, 0.1) !important;
}

/* Breadcrumb active */
.breadcrumb-item.active {
    color: #871f1f !important;
}

/* Dropdown */
.dropdown-item:hover {
    background-color: rgba(135, 31, 31, 0.1) !important;
}

/* Modal */
.modal-header {
    background-color: #871f1f !important;
    color: white !important;
}

.modal-header .close {
    color: white !important;
}

/* Card borders */
.card {
    border-left: 4px solid #871f1f !important;
}

/* Navbar */
.navbar-brand {
    color: #871f1f !important;
}

.navbar-brand:hover {
    color: #a02a2a !important;
}

.navbar-nav .nav-link {
    color: rgba(255, 255, 255, 0.9) !important;
}

.navbar-nav .nav-link:hover {
    color: white !important;
}

  /* Project Logo specific styling */
  .project_logo,
  .project-logo,
  .navbar-brand b,
  .navbar-brand strong {
      color: #871f1f !important;
  }

  .project_logo:hover,
  .project-logo:hover,
  .navbar-brand:hover b,
  .navbar-brand:hover strong {
      color: #a02a2a !important;
  }

  /* Convert button styling */
  .btn-convert,
  .btn[class*="convert"],
  .btn[data-action="convert"],
  .btn[title*="convert"],
  .btn[title*="Convert"],
  .btn[aria-label*="convert"],
  .btn[aria-label*="Convert"] {
      background-color: #871f1f !important;
      border-color: #871f1f !important;
      color: white !important;
  }

  .btn-convert:hover,
  .btn[class*="convert"]:hover,
  .btn[data-action="convert"]:hover,
  .btn[title*="convert"]:hover,
  .btn[title*="Convert"]:hover,
  .btn[aria-label*="convert"]:hover,
  .btn[aria-label*="Convert"]:hover {
      background-color: #a02a2a !important;
      border-color: #a02a2a !important;
      color: white !important;
  }

  /* PDF button styling */
  .btn-pdf,
  .btn[class*="pdf"],
  .btn[data-action="pdf"],
  .btn[title*="pdf"],
  .btn[title*="PDF"],
  .btn[aria-label*="pdf"],
  .btn[aria-label*="PDF"] {
      background-color: #871f1f !important;
      border-color: #871f1f !important;
      color: white !important;
  }

  .btn-pdf:hover,
  .btn[class*="pdf"]:hover,
  .btn[data-action="pdf"]:hover,
  .btn[title*="pdf"]:hover,
  .btn[title*="PDF"]:hover,
  .btn[aria-label*="pdf"]:hover,
  .btn[aria-label*="PDF"]:hover {
      background-color: #a02a2a !important;
      border-color: #a02a2a !important;
      color: white !important;
  }

  /* Account/Profile menu button styling */
  .btn-account,
  .btn-profile,
  .btn[class*="account"],
  .btn[class*="profile"],
  .btn[class*="user"],
  .btn[data-action="account"],
  .btn[data-action="profile"],
  .btn[title*="account"],
  .btn[title*="Account"],
  .btn[title*="profile"],
  .btn[title*="Profile"],
  .btn[title*="mi cuenta"],
  .btn[title*="Mi cuenta"],
  .btn[aria-label*="account"],
  .btn[aria-label*="Account"],
  .btn[aria-label*="profile"],
  .btn[aria-label*="Profile"],
  .btn[aria-label*="mi cuenta"],
  .btn[aria-label*="Mi cuenta"],
  .dropdown-toggle[data-toggle="dropdown"],
  .navbar-nav .dropdown-toggle {
      background-color: #871f1f !important;
      border-color: #871f1f !important;
      color: white !important;
  }

  /* Override green colors specifically */
  .btn-success,
  .btn[class*="success"],
  .btn[style*="background-color: green"],
  .btn[style*="background-color: #28a745"],
  .btn[style*="background-color: #198754"],
  .btn[style*="background: green"],
  .btn[style*="background: #28a745"],
  .btn[style*="background: #198754"],
  .navbar-nav .btn-success,
  .navbar-nav .btn[class*="success"],
  .navbar-nav .dropdown-toggle.btn-success,
  .navbar-nav .dropdown-toggle.btn[class*="success"] {
      background-color: #871f1f !important;
      border-color: #871f1f !important;
      color: white !important;
  }

  .btn-account:hover,
  .btn-profile:hover,
  .btn[class*="account"]:hover,
  .btn[class*="profile"]:hover,
  .btn[class*="user"]:hover,
  .btn[data-action="account"]:hover,
  .btn[data-action="profile"]:hover,
  .btn[title*="account"]:hover,
  .btn[title*="Account"]:hover,
  .btn[title*="profile"]:hover,
  .btn[title*="Profile"]:hover,
  .btn[title*="mi cuenta"]:hover,
  .btn[title*="Mi cuenta"]:hover,
  .btn[aria-label*="account"]:hover,
  .btn[aria-label*="Account"]:hover,
  .btn[aria-label*="profile"]:hover,
  .btn[aria-label*="Profile"]:hover,
  .btn[aria-label*="mi cuenta"]:hover,
  .btn[aria-label*="Mi cuenta"]:hover,
  .dropdown-toggle[data-toggle="dropdown"]:hover,
  .navbar-nav .dropdown-toggle:hover {
      background-color: #a02a2a !important;
      border-color: #a02a2a !important;
      color: white !important;
  }

  /* Sidebar */
  .sidebar .nav-link.active {
      background-color: #871f1f !important;
      color: white !important;
  }

  .sidebar .nav-link:hover {
      background-color: #a02a2a !important;
      color: white !important;
  }

  /* Menu/Sidebar specific rules */
  .sidebar {
      color: black !important;
  }

  .sidebar .nav-link {
      color: black !important;
  }

  .sidebar .nav-link i {
      color: black !important;
  }

  .sidebar .nav-link:hover {
      background-color: #a02a2a !important;
      color: white !important;
  }

  .sidebar .nav-link:hover i {
      color: white !important;
  }

  /* Force white icon on hover - more specific selectors */
  .sidebar .nav-link:hover i,
  .sidebar .nav-link:hover .fa,
  .sidebar .nav-link:hover .fas,
  .sidebar .nav-link:hover .far,
  .sidebar .nav-link:hover .fab,
  .sidebar .nav-link:hover .fal,
  .sidebar .nav-link:hover .fad,
  .sidebar .nav-link:hover .icon,
  .sidebar .nav-link:hover [class*="fa-"],
  .sidebar .nav-link:hover [class*="icon-"] {
      color: white !important;
  }

  /* Force white text on hover - more specific selectors */
  .sidebar .nav-link:hover span,
  .sidebar .nav-link:hover .nav-link-text,
  .sidebar .nav-link:hover .menu-text,
  .sidebar .nav-link:hover .text,
  .sidebar .nav-link:hover div,
  .sidebar .nav-link:hover p,
  .sidebar .nav-link:hover a {
      color: white !important;
  }

  /* Override any text color in hover menu items */
  .sidebar .nav-link:hover *:not(i),
  .sidebar .nav-link:hover > * {
      color: white !important;
  }

  /* Force white text for any text element in hover menu items */
  .sidebar .nav-link:hover,
  .sidebar .nav-link:hover > *,
  .sidebar .nav-link:hover span,
  .sidebar .nav-link:hover div,
  .sidebar .nav-link:hover p,
  .sidebar .nav-link:hover a {
      color: white !important;
  }

  /* Specific rules for dashboard/home button - only when actually active */
  .sidebar .nav-link.active[href*="dashboard"],
  .sidebar .nav-link.active[href*="home"],
  .sidebar .nav-link.active[href*="inicio"],
  .sidebar .nav-link.active[href="/admin"],
  .sidebar .nav-link.active[href="/admin/"] {
      background-color: #871f1f !important;
      color: white !important;
  }

  /* Ensure dashboard button is NOT active when other pages are selected */
  .sidebar .nav-link[href*="dashboard"]:not(.active),
  .sidebar .nav-link[href*="home"]:not(.active),
  .sidebar .nav-link[href*="inicio"]:not(.active),
  .sidebar .nav-link[href="/admin"]:not(.active),
  .sidebar .nav-link[href="/admin/"]:not(.active) {
      background-color: transparent !important;
      color: black !important;
  }

  .sidebar .nav-link[href*="dashboard"]:hover,
  .sidebar .nav-link[href*="home"]:hover,
  .sidebar .nav-link[href*="inicio"]:hover,
  .sidebar .nav-link[href="/admin"]:hover,
  .sidebar .nav-link[href="/admin/"]:hover {
      background-color: #a02a2a !important;
      color: white !important;
  }

  .sidebar .nav-link[href*="dashboard"] i,
  .sidebar .nav-link[href*="home"] i,
  .sidebar .nav-link[href*="inicio"] i,
  .sidebar .nav-link[href="/admin"] i,
  .sidebar .nav-link[href="/admin/"] i,
  .sidebar .nav-link[href*="dashboard"]:hover i,
  .sidebar .nav-link[href*="home"]:hover i,
  .sidebar .nav-link[href*="inicio"]:hover i,
  .sidebar .nav-link[href="/admin"]:hover i,
  .sidebar .nav-link[href="/admin/"]:hover i {
      color: white !important;
  }

  .sidebar .nav-link.active {
      background-color: #871f1f !important;
      color: white !important;
  }

  .sidebar .nav-link.active i {
      color: white !important;
  }

  /* Ensure only one menu item is active at a time */
  .sidebar .nav-link:not(.active) {
      background-color: transparent !important;
      color: black !important;
  }

  .sidebar .nav-link:not(.active) i {
      color: black !important;
  }

  /* Force hover to work on all menu items */
  .sidebar .nav-link:not(.active):hover {
      background-color: #a02a2a !important;
      color: white !important;
  }

  .sidebar .nav-link:not(.active):hover i {
      color: white !important;
  }

  .sidebar .nav-link:not(.active):hover span,
  .sidebar .nav-link:not(.active):hover div,
  .sidebar .nav-link:not(.active):hover p,
  .sidebar .nav-link:not(.active):hover a {
      color: white !important;
  }

  /* Additional specific rules for active menu items */
  .sidebar .nav-link[style*="background-color: #871f1f"],
  .sidebar .nav-link[style*="background: #871f1f"],
  .sidebar .nav-link.bg-primary,
  .sidebar .nav-link.current {
      background-color: #871f1f !important;
      color: white !important;
  }

  .sidebar .nav-link[style*="background-color: #871f1f"] i,
  .sidebar .nav-link[style*="background: #871f1f"] i,
  .sidebar .nav-link.bg-primary i,
  .sidebar .nav-link.current i {
      color: white !important;
  }

  /* Force white text for any element with red background in sidebar */
  .sidebar [style*="background-color: #871f1f"],
  .sidebar [style*="background: #871f1f"],
  .sidebar .bg-primary {
      color: white !important;
  }

  .sidebar [style*="background-color: #871f1f"] *,
  .sidebar [style*="background: #871f1f"] *,
  .sidebar .bg-primary * {
      color: white !important;
  }

  /* Override specific purple color in sidebar */
  .sidebar [style*="color: #9563c7"],
  .sidebar [style*="color:#9563c7"],
  .sidebar .nav-link[style*="color: #9563c7"],
  .sidebar .nav-link[style*="color:#9563c7"] {
      color: white !important;
  }

  /* Force override for any purple text in active menu items */
  .sidebar .nav-link.active,
  .sidebar .nav-link.active *,
  .sidebar .nav-link[style*="background-color: #871f1f"],
  .sidebar .nav-link[style*="background-color: #871f1f"] *,
  .sidebar .nav-link.bg-primary,
  .sidebar .nav-link.bg-primary * {
      color: white !important;
  }

  /* Universal override for sidebar text colors */
  .sidebar .nav-link {
      color: black !important;
  }

  .sidebar .nav-link.active,
  .sidebar .nav-link[style*="background-color: #871f1f"],
  .sidebar .nav-link.bg-primary {
      color: white !important;
  }

  /* Force white text for active menu items - more specific selectors */
  .sidebar .nav-link.active span,
  .sidebar .nav-link.active .nav-link-text,
  .sidebar .nav-link.active .menu-text,
  .sidebar .nav-link.active .text,
  .sidebar .nav-link[style*="background-color: #871f1f"] span,
  .sidebar .nav-link[style*="background-color: #871f1f"] .nav-link-text,
  .sidebar .nav-link[style*="background-color: #871f1f"] .menu-text,
  .sidebar .nav-link[style*="background-color: #871f1f"] .text,
  .sidebar .nav-link.bg-primary span,
  .sidebar .nav-link.bg-primary .nav-link-text,
  .sidebar .nav-link.bg-primary .menu-text,
  .sidebar .nav-link.bg-primary .text {
      color: white !important;
  }

  /* Override any text color in active menu items */
  .sidebar .nav-link.active *:not(i),
  .sidebar .nav-link[style*="background-color: #871f1f"] *:not(i),
  .sidebar .nav-link.bg-primary *:not(i) {
      color: white !important;
  }

  /* Menu dropdown items */
  .sidebar .dropdown-menu {
      background-color: white !important;
      border-color: #871f1f !important;
  }

  .sidebar .dropdown-item {
      color: black !important;
  }

  .sidebar .dropdown-item:hover {
      background-color: #871f1f !important;
      color: white !important;
  }

  /* Specific rules for dropdown items active state */
  .sidebar .dropdown-item.active {
      background-color: #871f1f !important;
      color: white !important;
  }

  .sidebar .dropdown-item.active i {
      color: white !important;
  }

  /* Ensure only the current dropdown item is active */
  .sidebar .dropdown-item:not(.active) {
      background-color: transparent !important;
      color: black !important;
  }

  .sidebar .dropdown-item:not(.active) i {
      color: black !important;
  }

  /* Force override for any conflicting active states */
  .sidebar .dropdown-item.active[href*="supplier"]:not([href*="suppliers-heading"]) {
      background-color: transparent !important;
      color: black !important;
  }

  .sidebar .dropdown-item.active[href*="suppliers-heading"] {
      background-color: #871f1f !important;
      color: white !important;
  }

  .sidebar .dropdown-item.active[href*="suppliers-heading"] i {
      color: white !important;
  }

  /* Specific override for supplier list when suppliers-heading is active */
  .sidebar .dropdown-item[href*="supplier"]:not([href*="suppliers-heading"]) {
      background-color: transparent !important;
      color: black !important;
  }

  .sidebar .dropdown-item[href*="supplier"]:not([href*="suppliers-heading"]) i {
      color: black !important;
  }

  /* Override any dark text in sidebar - only for specific cases */
  .sidebar .text-dark {
      color: black !important;
  }

  .sidebar .text-muted {
      color: #6c757d !important;
  }

  .sidebar .text-secondary {
      color: #6c757d !important;
  }

/* Custom scrollbar */
::-webkit-scrollbar-thumb {
    background-color: #871f1f !important;
}

/* Loading spinner */
.spinner-border-primary {
    color: #871f1f !important;
}

/* Override any existing purple/violet colors */
[style*="#7d69ef"] {
    background-color: #871f1f !important;
}

[style*="color: #7d69ef"] {
    color: #871f1f !important;
}

/* Force override for any CSS variables that might be using the old color */
* {
    --primary-color: #871f1f !important;
    --primary: #871f1f !important;
    --success-color: #871f1f !important;
    --success: #871f1f !important;
    --green: #871f1f !important;
}

/* Override any purple/violet colors specifically */
[style*="#9563c7"],
[style*="color: #9563c7"],
[style*="color:#9563c7"] {
    color: white !important;
}

/* Override purple color in sidebar specifically */
.sidebar [style*="#9563c7"],
.sidebar [style*="color: #9563c7"],
.sidebar [style*="color:#9563c7"] {
    color: white !important;
}

/* Force white text for any text element in active menu items */
.sidebar .nav-link.active,
.sidebar .nav-link.active > *,
.sidebar .nav-link.active span,
.sidebar .nav-link.active div,
.sidebar .nav-link.active p,
.sidebar .nav-link.active a,
.sidebar .nav-link[style*="background-color: #871f1f"],
.sidebar .nav-link[style*="background-color: #871f1f"] > *,
.sidebar .nav-link[style*="background-color: #871f1f"] span,
.sidebar .nav-link[style*="background-color: #871f1f"] div,
.sidebar .nav-link[style*="background-color: #871f1f"] p,
.sidebar .nav-link[style*="background-color: #871f1f"] a,
.sidebar .nav-link.bg-primary,
.sidebar .nav-link.bg-primary > *,
.sidebar .nav-link.bg-primary span,
.sidebar .nav-link.bg-primary div,
.sidebar .nav-link.bg-primary p,
.sidebar .nav-link.bg-primary a {
    color: white !important;
}

/* Additional rules to ensure white text on red backgrounds */
.card-header.bg-primary,
.card-header[style*="background-color: #871f1f"],
.card-header[style*="background: #871f1f"] {
    color: white !important;
}

/* Ensure all elements with primary background have white text */
[class*="bg-primary"],
[style*="background-color: #871f1f"],
[style*="background: #871f1f"] {
    color: white !important;
}

/* Specific overrides for common elements */
.navbar.bg-primary,
.header.bg-primary,
.footer.bg-primary {
    color: white !important;
}

.navbar.bg-primary a,
.header.bg-primary a,
.footer.bg-primary a {
    color: white !important;
}

.navbar.bg-primary a:hover,
.header.bg-primary a:hover,
.footer.bg-primary a:hover {
    color: rgba(255, 255, 255, 0.8) !important;
}
</style>
@endpush

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
        @elseif($crud->route == 'admin/devolution')
          <!-- Filtro personalizado para devoluciones -->
          <div class="card mb-3">
            <div class="card-header bg-primary text-white">
              <h6 class="card-title mb-0">
                <i class="fas fa-filter"></i> Filtros
              </h6>
            </div>
            <div class="card-body py-2">
              <form method="GET" action="{{ url($crud->route) }}">
                <div class="row">
                  <div class="col-md-6">
                    <label for="recepcion" class="form-label">Recepción:</label>
                    <input type="text" name="recepcion" id="recepcion" class="form-control" placeholder="ID de recepción..." value="{{ request('recepcion') }}">
                  </div>
                  <div class="col-md-6">
                    <label for="fecha" class="form-label">Fecha:</label>
                    <input type="date" name="fecha" id="fecha" class="form-control" value="{{ request('fecha') }}" onchange="this.form.submit()">
                  </div>
                </div>
              </form>
            </div>
          </div>
        @elseif($crud->route == 'admin/product')
          <!-- Filtro personalizado para productos -->
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
                    <label for="categoria" class="form-label">Categoría:</label>
                    <select name="categoria" id="categoria" class="form-control select2" onchange="this.form.submit()">
                      <option value="">Todas las categorías</option>
                      @foreach(\App\Models\Category::all() as $categoria)
                        <option value="{{ $categoria->id }}" {{ request('categoria') == $categoria->id ? 'selected' : '' }}>
                          {{ $categoria->name }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label for="fecha_vencimiento" class="form-label">Vence antes de:</label>
                    <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" class="form-control" value="{{ request('fecha_vencimiento') }}" onchange="this.form.submit()">
                  </div>
                </div>
              </form>
            </div>
          </div>
        @elseif($crud->route == 'admin/category')
          <!-- Filtro personalizado para categorías -->
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
                </div>
              </form>
            </div>
          </div>
        @elseif($crud->route == 'admin/general-request')
          <!-- Filtro personalizado para solicitudes generales -->
          <div class="card mb-3">
            <div class="card-header bg-primary text-white">
              <h6 class="card-title mb-0">
                <i class="fas fa-filter"></i> Filtros
              </h6>
            </div>
            <div class="card-body py-2">
              <form method="GET" action="{{ url($crud->route) }}">
                <div class="row">
                  <div class="col-md-3">
                    <label for="numero" class="form-label">Número:</label>
                    <input type="text" name="numero" id="numero" class="form-control" placeholder="Buscar por número..." value="{{ request('numero') }}" onchange="this.form.submit()">
                  </div>
                  <div class="col-md-3">
                    <label for="creado_por" class="form-label">Solicitante:</label>
                    <select name="creado_por" id="creado_por" class="form-control select2" onchange="this.form.submit()">
                      <option value="">Todos los solicitantes</option>
                      @foreach(\App\Models\User::all() as $usuario)
                        <option value="{{ $usuario->id }}" {{ request('creado_por') == $usuario->id ? 'selected' : '' }}>
                          {{ $usuario->name }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="col-md-3">
                    <label for="area" class="form-label">Área:</label>
                    <select name="area" id="area" class="form-control select2" onchange="this.form.submit()">
                      <option value="">Todas las áreas</option>
                      <option value="Insumos Generales" {{ request('area') == 'Insumos Generales' ? 'selected' : '' }}>Insumos Generales</option>
                      <option value="Mantenimiento" {{ request('area') == 'Mantenimiento' ? 'selected' : '' }}>Mantenimiento</option>
                      <option value="Insumos de Salud" {{ request('area') == 'Insumos de Salud' ? 'selected' : '' }}>Insumos de Salud</option>
                      <option value="Informática" {{ request('area') == 'Informática' ? 'selected' : '' }}>Informática</option>
                    </select>
                  </div>
                  <div class="col-md-3">
                    <label for="prioridad" class="form-label">Prioridad:</label>
                    <select name="prioridad" id="prioridad" class="form-control select2" onchange="this.form.submit()">
                      <option value="">Todas las prioridades</option>
                      <option value="Baja" {{ request('prioridad') == 'Baja' ? 'selected' : '' }}>Baja</option>
                      <option value="Media" {{ request('prioridad') == 'Media' ? 'selected' : '' }}>Media</option>
                      <option value="Alta" {{ request('prioridad') == 'Alta' ? 'selected' : '' }}>Alta</option>
                      <option value="Urgente" {{ request('prioridad') == 'Urgente' ? 'selected' : '' }}>Urgente</option>
                    </select>
                  </div>
                </div>
                <div class="row mt-2">
                  <div class="col-md-3">
                    <label for="estado" class="form-label">Estado:</label>
                    <select name="estado" id="estado" class="form-control select2" onchange="this.form.submit()">
                      <option value="">Todos los estados</option>
                      <option value="Creada" {{ request('estado') == 'Creada' ? 'selected' : '' }}>Creada</option>
                      <option value="En Revisión" {{ request('estado') == 'En Revisión' ? 'selected' : '' }}>En Revisión</option>
                      <option value="Aprobada" {{ request('estado') == 'Aprobada' ? 'selected' : '' }}>Aprobada</option>
                      <option value="Rechazada" {{ request('estado') == 'Rechazada' ? 'selected' : '' }}>Rechazada</option>
                      <option value="Convertida a Compra" {{ request('estado') == 'Convertida a Compra' ? 'selected' : '' }}>Convertida a Compra</option>
                    </select>
                  </div>
                </div>
              </form>
            </div>
          </div>
        @elseif($crud->route == 'admin/purchase-order')
          <!-- Filtro personalizado para órdenes de compra -->
          <div class="card mb-3">
            <div class="card-header bg-primary text-white">
              <h6 class="card-title mb-0">
                <i class="fas fa-filter"></i> Filtros
              </h6>
            </div>
            <div class="card-body py-2">
              <form method="GET" action="{{ url($crud->route) }}">
                <div class="row">
                  <div class="col-md-3">
                    <label for="numero" class="form-label">Número:</label>
                    <input type="text" name="numero" id="numero" class="form-control" placeholder="Buscar por número..." value="{{ request('numero') }}">
                  </div>
                  <div class="col-md-3">
                    <label for="fecha" class="form-label">Fecha:</label>
                    <input type="date" name="fecha" id="fecha" class="form-control" value="{{ request('fecha') }}" onchange="this.form.submit()">
                  </div>
                  <div class="col-md-3">
                    <label for="estado" class="form-label">Estado:</label>
                    <select name="estado" id="estado" class="form-control select2" onchange="this.form.submit()">
                      <option value="">Todos los estados</option>
                      <option value="pendiente" {{ request('estado') == 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                      <option value="aprobada" {{ request('estado') == 'aprobada' ? 'selected' : '' }}>Aprobada</option>
                      <option value="rechazada" {{ request('estado') == 'rechazada' ? 'selected' : '' }}>Rechazada</option>
                      <option value="enviada" {{ request('estado') == 'enviada' ? 'selected' : '' }}>Enviada</option>
                      <option value="recibida" {{ request('estado') == 'recibida' ? 'selected' : '' }}>Recibida</option>
                    </select>
                  </div>
                  <div class="col-md-3">
                    <label for="proveedor" class="form-label">Proveedor:</label>
                    <select name="proveedor" id="proveedor" class="form-control select2" onchange="this.form.submit()">
                      <option value="">Todos los proveedores</option>
                      @foreach(\App\Models\Supplier::all() as $proveedor)
                        <option value="{{ $proveedor->id }}" {{ request('proveedor') == $proveedor->id ? 'selected' : '' }}>
                          {{ $proveedor->company_name }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
              </form>
            </div>
          </div>
        @elseif($crud->route == 'admin/payment-order')
          <!-- Filtro personalizado para órdenes de pago -->
          <div class="card mb-3">
            <div class="card-header bg-primary text-white">
              <h6 class="card-title mb-0">
                <i class="fas fa-filter"></i> Filtros
              </h6>
            </div>
            <div class="card-body py-2">
              <form method="GET" action="{{ url($crud->route) }}">
                <div class="row">
                  <div class="col-md-3">
                    <label for="numero" class="form-label">Número:</label>
                    <input type="text" name="numero" id="numero" class="form-control" placeholder="Buscar por número..." value="{{ request('numero') }}">
                  </div>
                  <div class="col-md-3">
                    <label for="fecha" class="form-label">Fecha:</label>
                    <input type="date" name="fecha" id="fecha" class="form-control" value="{{ request('fecha') }}" onchange="this.form.submit()">
                  </div>
                  <div class="col-md-3">
                    <label for="estado" class="form-label">Estado:</label>
                    <select name="estado" id="estado" class="form-control select2" onchange="this.form.submit()">
                      <option value="">Todos los estados</option>
                      <option value="pendiente" {{ request('estado') == 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                      <option value="aprobada" {{ request('estado') == 'aprobada' ? 'selected' : '' }}>Aprobada</option>
                      <option value="rechazada" {{ request('estado') == 'rechazada' ? 'selected' : '' }}>Rechazada</option>
                      <option value="pagada" {{ request('estado') == 'pagada' ? 'selected' : '' }}>Pagada</option>
                      <option value="cancelada" {{ request('estado') == 'cancelada' ? 'selected' : '' }}>Cancelada</option>
                    </select>
                  </div>
                  <div class="col-md-3">
                    <label for="orden_compra" class="form-label">Orden de Compra:</label>
                    <select name="orden_compra" id="orden_compra" class="form-control select2" onchange="this.form.submit()">
                      <option value="">Todas las órdenes</option>
                      @foreach(\App\Models\PurchaseOrder::all() as $orden)
                        <option value="{{ $orden->id }}" {{ request('orden_compra') == $orden->id ? 'selected' : '' }}>
                          {{ $orden->number }} - {{ $orden->supplier->company_name ?? 'Sin proveedor' }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
              </form>
            </div>
          </div>
        @elseif($crud->route == 'admin/purchase-request')
          <!-- Filtro personalizado para solicitudes de compra -->
          <div class="card mb-3">
            <div class="card-header bg-primary text-white">
              <h6 class="card-title mb-0">
                <i class="fas fa-filter"></i> Filtros
              </h6>
            </div>
            <div class="card-body py-2">
              <form method="GET" action="{{ url($crud->route) }}">
                <div class="row">
                  <div class="col-md-3">
                    <label for="numero" class="form-label">Número:</label>
                    <input type="text" name="numero" id="numero" class="form-control" placeholder="Buscar por número..." value="{{ request('numero') }}" onchange="this.form.submit()">
                  </div>
                  <div class="col-md-3">
                    <label for="fecha" class="form-label">Fecha:</label>
                    <input type="date" name="fecha" id="fecha" class="form-control" value="{{ request('fecha') }}" onchange="this.form.submit()">
                  </div>
                  <div class="col-md-3">
                    <label for="area" class="form-label">Área:</label>
                    <select name="area" id="area" class="form-control select2" onchange="this.form.submit()">
                      <option value="">Todas las áreas</option>
                      <option value="Insumos Generales" {{ request('area') == 'Insumos Generales' ? 'selected' : '' }}>Insumos Generales</option>
                      <option value="Mantenimiento" {{ request('area') == 'Mantenimiento' ? 'selected' : '' }}>Mantenimiento</option>
                      <option value="Insumos de Salud" {{ request('area') == 'Insumos de Salud' ? 'selected' : '' }}>Insumos de Salud</option>
                      <option value="Informática" {{ request('area') == 'Informática' ? 'selected' : '' }}>Informática</option>
                    </select>
                  </div>
                </div>
              </form>
            </div>
          </div>
        @elseif($crud->route == 'admin/reception')
          <!-- Filtro personalizado para recepciones -->
          <div class="card mb-3">
            <div class="card-header bg-primary text-white">
              <h6 class="card-title mb-0">
                <i class="fas fa-filter"></i> Filtros
              </h6>
            </div>
            <div class="card-body py-2">
              <form method="GET" action="{{ url($crud->route) }}">
                <div class="row">
                  <div class="col-md-3">
                    <label for="orden_compra" class="form-label">Orden de Compra:</label>
                    <select name="orden_compra" id="orden_compra" class="form-control select2" onchange="this.form.submit()">
                      <option value="">Todas las órdenes</option>
                      @foreach(\App\Models\PurchaseOrder::all() as $orden)
                        <option value="{{ $orden->id }}" {{ request('orden_compra') == $orden->id ? 'selected' : '' }}>
                          {{ $orden->number }} - {{ $orden->supplier->company_name ?? 'Sin proveedor' }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="col-md-3">
                    <label for="fecha" class="form-label">Fecha:</label>
                    <input type="date" name="fecha" id="fecha" class="form-control" value="{{ request('fecha') }}" onchange="this.form.submit()">
                  </div>
                  <div class="col-md-3">
                    <label for="conformidad" class="form-label">Conformidad:</label>
                    <select name="conformidad" id="conformidad" class="form-control" onchange="this.form.submit()">
                      <option value="">Todas</option>
                      <option value="Si" {{ request('conformidad') == 'Si' ? 'selected' : '' }}>Sí</option>
                      <option value="No" {{ request('conformidad') == 'No' ? 'selected' : '' }}>No</option>
                    </select>
                  </div>
                  <div class="col-md-3">
                    <label for="responsable" class="form-label">Responsable:</label>
                    <select name="responsable" id="responsable" class="form-control select2" onchange="this.form.submit()">
                      <option value="">Todos los responsables</option>
                      @foreach(\App\Models\User::all() as $usuario)
                        <option value="{{ $usuario->id }}" {{ request('responsable') == $usuario->id ? 'selected' : '' }}>
                          {{ $usuario->name }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
              </form>
            </div>
          </div>
        @elseif($crud->route == 'admin/suppliers-heading')
          <!-- Filtro personalizado para rubros de proveedores -->
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
                </div>
              </form>
            </div>
          </div>
        @elseif($crud->route == 'admin/location')
          <!-- Filtro personalizado para ubicaciones -->
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
                </div>
              </form>
            </div>
          </div>
        @elseif($crud->route == 'admin/stock-level')
          <!-- Filtro personalizado para stock levels -->
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
                    <label for="producto" class="form-label">Producto:</label>
                    <input type="text" name="producto" id="producto" class="form-control" placeholder="Buscar por producto..." value="{{ request('producto') }}">
                  </div>
                  <div class="col-md-4">
                    <label for="deposito" class="form-label">Depósito:</label>
                    <select name="deposito" id="deposito" class="form-control select2" onchange="this.form.submit()">
                      <option value="">Todos los depósitos</option>
                      @foreach(\App\Models\Location::whereIn('name', ['Insumos Generales', 'Mantenimiento', 'Insumos de Salud', 'Informática'])->get() as $deposito)
                        <option value="{{ $deposito->id }}" {{ request('deposito') == $deposito->id ? 'selected' : '' }}>
                          {{ $deposito->name }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
              </form>
            </div>
          </div>
        @elseif($crud->route == 'admin/inventory-movement')
          <!-- Filtro personalizado para movimientos de inventario -->
          <div class="card mb-3">
            <div class="card-header bg-primary text-white">
              <h6 class="card-title mb-0">
                <i class="fas fa-filter"></i> Filtros
              </h6>
            </div>
            <div class="card-body py-2">
              <form method="GET" action="{{ url($crud->route) }}">
                <div class="row">
                  <div class="col-md-3">
                    <label for="producto" class="form-label">Producto:</label>
                    <input type="text" name="producto" id="producto" class="form-control" placeholder="Buscar por producto..." value="{{ request('producto') }}">
                  </div>
                  <div class="col-md-3">
                    <label for="ubicacion" class="form-label">Ubicación:</label>
                    <select name="ubicacion" id="ubicacion" class="form-control select2" onchange="this.form.submit()">
                      <option value="">Todas las ubicaciones</option>
                      @foreach(\App\Models\Location::all() as $ubicacion)
                        <option value="{{ $ubicacion->id }}" {{ request('ubicacion') == $ubicacion->id ? 'selected' : '' }}>
                          {{ $ubicacion->name }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="col-md-3">
                    <label for="tipo" class="form-label">Tipo:</label>
                    <select name="tipo" id="tipo" class="form-control select2" onchange="this.form.submit()">
                      <option value="">Todos los tipos</option>
                      <option value="uso" {{ request('tipo') == 'uso' ? 'selected' : '' }}>Uso</option>
                      <option value="compra" {{ request('tipo') == 'compra' ? 'selected' : '' }}>Compra</option>
                      <option value="desuso" {{ request('tipo') == 'desuso' ? 'selected' : '' }}>Desuso</option>
                      <option value="baja" {{ request('tipo') == 'baja' ? 'selected' : '' }}>Baja</option>
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
