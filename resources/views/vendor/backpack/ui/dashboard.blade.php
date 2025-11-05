@extends(backpack_view('blank'))

@push('after_styles')
  @basset('https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.6.0/styles/base16/dracula.min.css')
  <style>
  /* Custom CSS for ePorres Manager - Color Override */
  :root {
      --bs-primary: #871f1f !important;
      --bs-primary-rgb: 135, 31, 31 !important;
  }

  /* Dashboard Process Flow Styles */
  .process-flow-container {
      background: #f8f9fa;
      padding: 30px;
      border-radius: 10px;
      margin-bottom: 30px;
  }

  .process-step {
      position: relative;
      background: white;
      border-radius: 8px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      transition: transform 0.2s, box-shadow 0.2s;
  }

  .process-step:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.15);
  }

  .process-step-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
      padding-bottom: 10px;
      border-bottom: 2px solid #871f1f;
  }

  .process-step-title {
      font-size: 18px;
      font-weight: bold;
      color: #871f1f;
      display: flex;
      align-items: center;
      gap: 10px;
  }

  .process-step-icon {
      font-size: 24px;
  }

  .process-step-count {
      background: #871f1f;
      color: white;
      padding: 5px 12px;
      border-radius: 20px;
      font-weight: bold;
  }

  .process-step-content {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 15px;
  }

  .process-item-card {
      background: #f8f9fa;
      border-left: 4px solid #871f1f;
      padding: 15px;
      border-radius: 5px;
      cursor: pointer;
      transition: all 0.2s;
  }

  .process-item-card:hover {
      background: #e9ecef;
      border-left-width: 6px;
  }

  .process-item-title {
      font-weight: bold;
      color: #871f1f;
      margin-bottom: 5px;
  }

  .process-item-meta {
      font-size: 12px;
      color: #6c757d;
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
  }

  .process-item-status {
      display: inline-block;
      padding: 3px 8px;
      border-radius: 3px;
      font-size: 11px;
      font-weight: bold;
      text-transform: uppercase;
  }

  .status-pendiente { background: #ffc107; color: #000; }
  .status-aprobada { background: #28a745; color: #fff; }
  .status-en-proceso { background: #17a2b8; color: #fff; }
  .status-completada { background: #28a745; color: #fff; }
  .status-rechazada { background: #dc3545; color: #fff; }
  .status-recibida { background: #28a745; color: #fff; }

  .stat-card {
      background: white;
      border-radius: 8px;
      padding: 20px;
      text-align: center;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      transition: transform 0.2s;
  }

  .stat-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.15);
  }

  .stat-card-icon {
      font-size: 40px;
      color: #871f1f;
      margin-bottom: 10px;
  }

  .stat-card-number {
      font-size: 32px;
      font-weight: bold;
      color: #871f1f;
      margin-bottom: 5px;
  }

  .stat-card-label {
      color: #6c757d;
      font-size: 14px;
      text-transform: uppercase;
  }

  .stat-card-pending {
      font-size: 14px;
      color: #ffc107;
      margin-top: 5px;
  }

  .supplier-rating-card {
      transition: transform 0.2s, box-shadow 0.2s;
      border-left: 4px solid #871f1f;
  }

  .supplier-rating-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.15);
      border-left-width: 6px;
  }

  .supplier-rating-stars {
      font-size: 18px;
      margin-bottom: 8px;
  }

  .supplier-rating-stars .la-star,
  .supplier-rating-stars .la-star-half-alt {
      margin-right: 2px;
  }

  .rating-score {
      font-weight: bold;
      color: #871f1f;
      font-size: 16px;
  }

  .supplier-rating-btn {
      border-color: #871f1f;
      color: #871f1f;
  }

  .supplier-rating-btn:hover {
      background-color: #871f1f;
      border-color: #871f1f;
      color: white;
  }

  .flow-timeline {
      position: relative;
      padding: 20px 0;
  }

  .flow-timeline::before {
      content: '';
      position: absolute;
      left: 30px;
      top: 0;
      bottom: 0;
      width: 3px;
      background: #871f1f;
  }

  .flow-timeline-item {
      position: relative;
      padding-left: 70px;
      margin-bottom: 30px;
  }

  .flow-timeline-item::before {
      content: '';
      position: absolute;
      left: 21px;
      top: 5px;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      background: #871f1f;
      border: 3px solid white;
      box-shadow: 0 0 0 3px #871f1f;
  }

  .flow-timeline-content {
      background: white;
      padding: 15px;
      border-radius: 5px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  }

  .section-title {
      font-size: 24px;
      font-weight: bold;
      color: #871f1f;
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 3px solid #871f1f;
  }

  /* Override any existing primary color definitions */
  .bg-primary {
      background-color: #871f1f !important;
      color: white !important;
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

@push('after_scripts')
  @basset('https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.6.0/highlight.min.js')
  <script>hljs.highlightAll();</script>
@endpush

@section('content')
<div class="container-fluid">
    <!-- Estadísticas Generales -->
    <div class="row mb-4">
        <div class="col-md-12">
            <h2 class="section-title">Proceso de Solicitudes</h2>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-card-icon">
                    <i class="la la-file-alt"></i>
                </div>
                <div class="stat-card-number">{{ $stats['general_requests'] }}</div>
                <div class="stat-card-label">Solicitudes Generales</div>
                <div class="stat-card-pending">{{ $stats['general_requests_pending'] }} Pendientes</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-card-icon">
                    <i class="la la-shopping-cart"></i>
                </div>
                <div class="stat-card-number">{{ $stats['purchase_requests'] }}</div>
                <div class="stat-card-label">Solicitudes de Compra</div>
                <div class="stat-card-pending">{{ $stats['purchase_requests_pending'] }} Pendientes</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-card-icon">
                    <i class="la la-clipboard-list"></i>
                </div>
                <div class="stat-card-number">{{ $stats['purchase_orders'] }}</div>
                <div class="stat-card-label">Órdenes de Compra</div>
                <div class="stat-card-pending">{{ $stats['purchase_orders_pending'] }} Pendientes</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-card-icon">
                    <i class="la la-money-bill-wave"></i>
                </div>
                <div class="stat-card-number">{{ $stats['payment_orders'] }}</div>
                <div class="stat-card-label">Órdenes de Pago</div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="stat-card">
                <div class="stat-card-icon">
                    <i class="la la-truck-loading"></i>
                </div>
                <div class="stat-card-number">{{ $stats['receptions'] }}</div>
                <div class="stat-card-label">Recepciones</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card">
                <div class="stat-card-icon">
                    <i class="la la-undo-alt"></i>
                </div>
                <div class="stat-card-number">{{ $stats['devolutions'] }}</div>
                <div class="stat-card-label">Devoluciones</div>
            </div>
        </div>
    </div>

    <!-- Proveedores con Calificaciones -->
    @if(isset($suppliersWithRatings) && $suppliersWithRatings->count() > 0)
    <div class="row mb-4">
        <div class="col-md-12">
            <h3 class="section-title">
                <i class="la la-truck"></i> Top Proveedores Mejor Calificados
            </h3>
        </div>
    </div>

    <div class="row mb-4">
        @foreach($suppliersWithRatings as $supplier)
        <div class="col-md-3 mb-3">
            <div class="card h-100 supplier-rating-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="card-title mb-0">{{ $supplier->company_name }}</h5>
                        <span class="badge bg-secondary">{{ $supplier->total_ratings }} evaluación(es)</span>
                    </div>
                    <div class="mb-2">
                        @php
                            $avg = $supplier->average_rating;
                            $fullStars = floor($avg);
                            $hasHalfStar = ($avg - $fullStars) >= 0.5;
                        @endphp
                        <div class="supplier-rating-stars">
                            @for($i = 1; $i <= 5; $i++)
                                @if($i <= $fullStars)
                                    <i class="la la-star text-warning"></i>
                                @elseif($i == $fullStars + 1 && $hasHalfStar)
                                    <i class="la la-star-half-alt text-warning"></i>
                                @else
                                    <i class="la la-star text-secondary"></i>
                                @endif
                            @endfor
                            <span class="ms-2 rating-score">{{ number_format($avg, 1) }}/5.0</span>
                        </div>
                    </div>
                    <div class="text-muted small">
                        <i class="la la-calendar"></i> Última evaluación: 
                        @if($supplier->ratings->isNotEmpty())
                            {{ $supplier->ratings->sortByDesc('evaluation_date')->first()->evaluation_date->format('d/m/Y') }}
                        @else
                            N/A
                        @endif
                    </div>
                    <div class="mt-2">
                        <a href="{{ backpack_url('supplier-rating?proveedor=' . $supplier->id) }}" class="btn btn-sm btn-outline-primary supplier-rating-btn">
                            Ver Calificaciones
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    <!-- Flujo del Proceso Visual -->
    <div class="row mb-4">
        <div class="col-md-12">
            <h3 class="section-title">Flujo del Proceso Completo</h3>
        </div>
    </div>

    <!-- Paso 1: Solicitudes Generales -->
    <div class="process-step">
        <div class="process-step-header">
            <div class="process-step-title">
                <i class="la la-file-alt process-step-icon"></i>
                <span>1. Solicitudes Generales</span>
            </div>
            <span class="process-step-count">{{ $stats['general_requests'] }}</span>
        </div>
        <div class="process-step-content">
            @forelse($generalRequests as $generalRequest)
                <div class="process-item-card" onclick="window.location='{{ backpack_url('general-request/' . $generalRequest->id . '/show') }}'">
                    <div class="process-item-title">{{ $generalRequest->number }}</div>
                    <div class="process-item-meta">
                        <span>{{ $generalRequest->title }}</span>
                        <span class="process-item-status status-{{ strtolower(str_replace(' ', '-', $generalRequest->status)) }}">{{ $generalRequest->status }}</span>
                    </div>
                    <div class="process-item-meta">
                        <span><i class="la la-user"></i> {{ $generalRequest->createdBy->name ?? 'N/A' }}</span>
                        <span><i class="la la-calendar"></i> {{ $generalRequest->created_at->format('d/m/Y') }}</span>
                    </div>
                </div>
            @empty
                <div class="text-muted">No hay solicitudes generales recientes</div>
            @endforelse
        </div>
    </div>

    <!-- Paso 2: Solicitudes de Compra -->
    <div class="process-step">
        <div class="process-step-header">
            <div class="process-step-title">
                <i class="la la-shopping-cart process-step-icon"></i>
                <span>2. Solicitudes de Compra</span>
            </div>
            <span class="process-step-count">{{ $stats['purchase_requests'] }}</span>
        </div>
        <div class="process-step-content">
            @forelse($purchaseRequests as $purchaseRequest)
                <div class="process-item-card" onclick="window.location='{{ backpack_url('purchase-request/' . $purchaseRequest->id . '/show') }}'">
                    <div class="process-item-title">{{ $purchaseRequest->request_number }}</div>
                    <div class="process-item-meta">
                        <span>{{ $purchaseRequest->convertedFromGeneralRequest->number ?? 'N/A' }}</span>
                        <span class="process-item-status status-{{ strtolower(str_replace(' ', '-', $purchaseRequest->status)) }}">{{ $purchaseRequest->status }}</span>
                    </div>
                    <div class="process-item-meta">
                        <span><i class="la la-user"></i> {{ $purchaseRequest->requestingUser->name ?? 'N/A' }}</span>
                        <span><i class="la la-calendar"></i> {{ $purchaseRequest->request_date->format('d/m/Y') ?? 'N/A' }}</span>
                    </div>
                </div>
            @empty
                <div class="text-muted">No hay solicitudes de compra recientes</div>
            @endforelse
        </div>
    </div>

    <!-- Paso 3: Órdenes de Compra -->
    <div class="process-step">
        <div class="process-step-header">
            <div class="process-step-title">
                <i class="la la-clipboard-list process-step-icon"></i>
                <span>3. Órdenes de Compra</span>
            </div>
            <span class="process-step-count">{{ $stats['purchase_orders'] }}</span>
        </div>
        <div class="process-step-content">
            @forelse($purchaseOrders as $purchaseOrder)
                <div class="process-item-card" onclick="window.location='{{ backpack_url('purchase-order/' . $purchaseOrder->id . '/show') }}'">
                    <div class="process-item-title">{{ $purchaseOrder->number ?? 'N/A' }}</div>
                    <div class="process-item-meta">
                        <span><i class="la la-truck"></i> {{ $purchaseOrder->supplier->name ?? 'N/A' }}</span>
                        <span class="process-item-status status-{{ strtolower(str_replace(' ', '-', $purchaseOrder->status ?? 'Pendiente')) }}">{{ $purchaseOrder->status ?? 'Pendiente' }}</span>
                    </div>
                    <div class="process-item-meta">
                        <span><i class="la la-calendar"></i> {{ $purchaseOrder->date->format('d/m/Y') ?? 'N/A' }}</span>
                        <span><i class="la la-money-bill"></i> ${{ number_format($purchaseOrder->total ?? 0, 2) }}</span>
                    </div>
                </div>
            @empty
                <div class="text-muted">No hay órdenes de compra recientes</div>
            @endforelse
        </div>
    </div>

    <!-- Paso 4: Órdenes de Pago -->
    <div class="process-step">
        <div class="process-step-header">
            <div class="process-step-title">
                <i class="la la-money-bill-wave process-step-icon"></i>
                <span>4. Órdenes de Pago</span>
            </div>
            <span class="process-step-count">{{ $stats['payment_orders'] }}</span>
        </div>
        <div class="process-step-content">
            @forelse($paymentOrders as $paymentOrder)
                <div class="process-item-card" onclick="window.location='{{ backpack_url('payment-order/' . $paymentOrder->id . '/show') }}'">
                    <div class="process-item-title">{{ $paymentOrder->payment_number ?? 'N/A' }}</div>
                    <div class="process-item-meta">
                        <span><i class="la la-truck"></i> {{ $paymentOrder->purchase_order->supplier->name ?? 'N/A' }}</span>
                        <span><i class="la la-clipboard-list"></i> {{ $paymentOrder->purchase_order->number ?? 'N/A' }}</span>
                    </div>
                    <div class="process-item-meta">
                        <span><i class="la la-calendar"></i> {{ $paymentOrder->date ? $paymentOrder->date->format('d/m/Y') : 'N/A' }}</span>
                        <span><i class="la la-money-bill"></i> ${{ number_format($paymentOrder->total_amount ?? 0, 2) }}</span>
                    </div>
                </div>
            @empty
                <div class="text-muted">No hay órdenes de pago recientes</div>
            @endforelse
        </div>
    </div>

    <!-- Paso 5: Recepciones -->
    <div class="process-step">
        <div class="process-step-header">
            <div class="process-step-title">
                <i class="la la-truck-loading process-step-icon"></i>
                <span>5. Recepciones</span>
            </div>
            <span class="process-step-count">{{ $stats['receptions'] }}</span>
        </div>
        <div class="process-step-content">
            @forelse($receptions as $reception)
                <div class="process-item-card" onclick="window.location='{{ backpack_url('reception/' . $reception->id . '/show') }}'">
                    <div class="process-item-title">{{ $reception->number ?? 'REC-' . $reception->id }}</div>
                    <div class="process-item-meta">
                        <span><i class="la la-clipboard-list"></i> {{ $reception->purchase_order->number ?? 'N/A' }}</span>
                        <span><i class="la la-truck"></i> {{ $reception->purchase_order->supplier->name ?? 'N/A' }}</span>
                    </div>
                    <div class="process-item-meta">
                        <span><i class="la la-calendar"></i> {{ $reception->created_at->format('d/m/Y') }}</span>
                    </div>
                </div>
            @empty
                <div class="text-muted">No hay recepciones recientes</div>
            @endforelse
        </div>
    </div>

    <!-- Paso 6: Devoluciones -->
    <div class="process-step">
        <div class="process-step-header">
            <div class="process-step-title">
                <i class="la la-undo-alt process-step-icon"></i>
                <span>6. Devoluciones</span>
            </div>
            <span class="process-step-count">{{ $stats['devolutions'] }}</span>
        </div>
        <div class="process-step-content">
            @forelse($devolutions as $devolution)
                <div class="process-item-card" onclick="window.location='{{ backpack_url('devolution/' . $devolution->id . '/show') }}'">
                    <div class="process-item-title">DEV-{{ $devolution->id }}</div>
                    <div class="process-item-meta">
                        <span><i class="la la-truck-loading"></i> {{ $devolution->reception->number ?? 'N/A' }}</span>
                        <span><i class="la la-user"></i> {{ $devolution->user->name ?? 'N/A' }}</span>
                    </div>
                    <div class="process-item-meta">
                        <span><i class="la la-calendar"></i> {{ $devolution->created_at->format('d/m/Y') }}</span>
                    </div>
                </div>
            @empty
                <div class="text-muted">No hay devoluciones recientes</div>
            @endforelse
        </div>
    </div>

    <!-- Flujos Completos de Procesos -->
    @if(isset($processFlows) && count($processFlows) > 0)
    <div class="row mb-4">
        <div class="col-md-12">
            <h3 class="section-title">Trazabilidad Completa de Procesos</h3>
            <p class="text-muted">Muestra el flujo completo desde las solicitudes generales hasta las devoluciones (si aplica)</p>
        </div>
    </div>

    @foreach($processFlows as $flow)
        @if(isset($flow['general_request']) && $flow['general_request'])
            <div class="card mb-4">
                <div class="card-header bg-primary">
                    <h5 class="mb-0">
                        <i class="la la-file-alt"></i> 
                        Solicitud General: {{ $flow['general_request']->number }}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="flow-timeline">
                        <div class="flow-timeline-item">
                            <div class="flow-timeline-content">
                                <strong>Solicitud General:</strong> {{ $flow['general_request']->title }}
                                <br>
                                <small class="text-muted">
                                    Creada por: {{ $flow['general_request']->createdBy->name ?? 'N/A' }} 
                                    | {{ $flow['general_request']->created_at->format('d/m/Y H:i') }}
                                    | Estado: {{ $flow['general_request']->status ?? 'N/A' }}
                                </small>
                            </div>
                        </div>

                        @if(isset($flow['purchase_requests']) && count($flow['purchase_requests']) > 0)
                            @foreach($flow['purchase_requests'] as $pr)
                                <div class="flow-timeline-item">
                                    <div class="flow-timeline-content">
                                        <strong>Solicitud de Compra:</strong> {{ $pr->request_number }}
                                        <br>
                                        <small class="text-muted">
                                            Estado: {{ $pr->status }} | 
                                            Fecha: {{ $pr->request_date ? $pr->request_date->format('d/m/Y') : 'N/A' }}
                                            @if($pr->selectedMarketRate)
                                                | Cotización seleccionada: {{ $pr->selectedMarketRate->supplier->name ?? 'N/A' }}
                                            @endif
                                        </small>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="flow-timeline-item">
                                <div class="flow-timeline-content">
                                    <small class="text-muted">No hay solicitudes de compra generadas aún</small>
                                </div>
                            </div>
                        @endif

                        @if(isset($flow['purchase_orders']) && count($flow['purchase_orders']) > 0)
                            @foreach($flow['purchase_orders'] as $po)
                                <div class="flow-timeline-item">
                                    <div class="flow-timeline-content">
                                        <strong>Orden de Compra:</strong> {{ $po->number ?? 'N/A' }}
                                        <br>
                                        <small class="text-muted">
                                            Proveedor: {{ $po->supplier->name ?? 'N/A' }} | 
                                            Estado: {{ $po->status ?? 'N/A' }}
                                            @if($po->date)
                                                | Fecha: {{ $po->date->format('d/m/Y') }}
                                            @endif
                                        </small>
                                    </div>
                                </div>

                                @if(isset($flow['payment_orders']) && count($flow['payment_orders']) > 0)
                                    @foreach($flow['payment_orders'] as $paymentOrder)
                                        @if($paymentOrder->purchase_order_id == $po->id)
                                            <div class="flow-timeline-item">
                                                <div class="flow-timeline-content">
                                                    <strong>Orden de Pago:</strong> {{ $paymentOrder->payment_number ?? 'N/A' }}
                                                    <br>
                                                    <small class="text-muted">
                                                        Fecha: {{ $paymentOrder->date ? $paymentOrder->date->format('d/m/Y') : 'N/A' }}
                                                        | Monto: ${{ number_format($paymentOrder->total_amount ?? 0, 2) }}
                                                        | Estado: {{ $paymentOrder->status ?? 'N/A' }}
                                                    </small>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                @endif

                                @if(isset($flow['receptions']) && count($flow['receptions']) > 0)
                                    @foreach($flow['receptions'] as $reception)
                                        @if($reception->purchase_order_id == $po->id)
                                            <div class="flow-timeline-item">
                                                <div class="flow-timeline-content">
                                                    <strong>Recepción:</strong> {{ $reception->number ?? 'REC-' . $reception->id }}
                                                    <br>
                                                    <small class="text-muted">
                                                        Fecha: {{ $reception->created_at->format('d/m/Y') }}
                                                    </small>
                                                </div>
                                            </div>

                                            @if(isset($flow['devolutions']) && count($flow['devolutions']) > 0)
                                                @foreach($flow['devolutions'] as $devolution)
                                                    @if($devolution->reception_id == $reception->id)
                                                        <div class="flow-timeline-item">
                                                            <div class="flow-timeline-content">
                                                                <strong>Devolución:</strong> DEV-{{ $devolution->id }}
                                                                <br>
                                                                <small class="text-muted">
                                                                    Fecha: {{ $devolution->created_at->format('d/m/Y') }}
                                                                </small>
                                                            </div>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            @endif
                                        @endif
                                    @endforeach
                                @else
                                    @if(count($flow['purchase_orders']) > 0)
                                        <div class="flow-timeline-item">
                                            <div class="flow-timeline-content">
                                                <small class="text-muted">No hay recepciones registradas aún</small>
                                            </div>
                                        </div>
                                    @endif
                                @endif
                            @endforeach
                        @else
                            <div class="flow-timeline-item">
                                <div class="flow-timeline-content">
                                    <small class="text-muted">No hay órdenes de compra generadas aún</small>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    @endforeach
    @else
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="alert alert-info">
                <i class="la la-info-circle"></i> 
                No hay procesos completos para mostrar. La trazabilidad aparecerá cuando las solicitudes generales tengan solicitudes de compra asociadas.
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
