@extends(backpack_view('blank'))

@push('after_styles')
  @basset('https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.6.0/styles/base16/dracula.min.css')
  <style>
  /* Custom CSS for ePorres Manager - Color Override */
  :root {
      --bs-primary: #871f1f !important;
      --bs-primary-rgb: 135, 31, 31 !important;
      /* Mismo tono para todo estado â€œpositivoâ€ del flujo (chips, OP, recepciÃ³n, etc.) */
      --dashboard-positive-bg: #198754;
      --dashboard-positive-text: #fff;
  }

  /* Estilos para el modal de alertas de stock */
  #stockAlertsModal {
    z-index: 1050 !important;
  }
  
  #stockAlertsModal .modal-dialog {
    z-index: 1051 !important;
  }
  
  #stockAlertsModal .modal-content {
    z-index: 1052 !important;
  }
  
  .modal-backdrop.show {
    z-index: 1040 !important;
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

  .process-step[id] {
      scroll-margin-top: 5.5rem;
  }

  #purchase-request-types-section {
      scroll-margin-top: 5.5rem;
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

  .admin-inbox__icon {
      width: 2.5rem;
      height: 2.5rem;
  }

  .admin-inbox__card,
  .admin-inbox__card:hover,
  .admin-inbox__card h3,
  .admin-inbox__card p {
      color: #000 !important;
  }

  .admin-inbox__card--active:hover {
      transform: translateY(-2px);
      box-shadow: 0 0.35rem 0.75rem rgba(135, 31, 31, 0.15) !important;
  }

  .admin-inbox__card--with-list:hover {
      transform: none;
  }

  .admin-inbox__entry-link:hover {
      background-color: rgba(135, 31, 31, 0.06);
  }

  .admin-inbox__entries .list-group-item:last-child {
      border-bottom: none !important;
  }

  #admin-inbox-section,
  #pending-approval-section {
      scroll-margin-top: 5.5rem;
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

  .process-item-status.status-pendiente { background: #fd7e14; color: #fff; }
  .process-item-status.status-anulada { background: #6c757d; color: #fff; }
  .process-item-status.status-rechazada,
  .process-item-status.status-rechazada-analista { background: #dc3545; color: #fff; }
  /* Positivos: un solo color (completada, recibida, aprobada, conforme implÃ­cito en badge, OP completada, etc.) */
  .process-item-status.status-completada,
  .process-item-status.status-recibida,
  .process-item-status.status-aprobada,
  .process-item-status.status-ejecutada,
  .process-item-status.status-conforme,
  .process-item-status.status-en-proceso,
  .process-item-status.status-entregada-parcialmente,
  .process-item-status.status-entregada-totalmente {
      background: var(--dashboard-positive-bg) !important;
      color: var(--dashboard-positive-text) !important;
  }
  .badge.dashboard-badge-positive {
      background-color: var(--dashboard-positive-bg) !important;
      color: var(--dashboard-positive-text) !important;
  }

  .legal-approval-alert {
      border-left: 6px solid #dc3545;
      background: #fff5f5;
      color: #842029;
      box-shadow: 0 4px 10px rgba(220, 53, 69, 0.12);
      animation: legalApprovalPulse 1.8s ease-in-out infinite;
  }

  .pending-approval-highlight {
      box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.2), 0 4px 12px rgba(220, 53, 69, 0.2);
  }

  @keyframes legalApprovalPulse {
      0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.25); }
      70% { box-shadow: 0 0 0 12px rgba(220, 53, 69, 0); }
      100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
  }

  .compras-superior-approval-alert {
      border-left: 6px solid #0d6efd;
      background: #e7f1ff;
      color: #084298;
      box-shadow: 0 4px 10px rgba(13, 110, 253, 0.12);
      animation: comprasSuperiorPulse 1.8s ease-in-out infinite;
  }

  @keyframes comprasSuperiorPulse {
      0% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.22); }
      70% { box-shadow: 0 0 0 10px rgba(13, 110, 253, 0); }
      100% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0); }
  }

  .compras-op-pendiente-alert {
      border-left: 6px solid #198754;
      background: #e8f5e9;
      color: #0f5132;
      box-shadow: 0 4px 10px rgba(25, 135, 84, 0.12);
      animation: comprasOpPendientePulse 1.8s ease-in-out infinite;
  }

  @keyframes comprasOpPendientePulse {
      0% { box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.22); }
      70% { box-shadow: 0 0 0 10px rgba(25, 135, 84, 0); }
      100% { box-shadow: 0 0 0 0 rgba(25, 135, 84, 0); }
  }

  .compras-seleccion-cotizacion-alert {
      border-left: 6px solid #dc3545;
      background: #f8d7da;
      color: #842029;
      box-shadow: 0 4px 10px rgba(220, 53, 69, 0.15);
      animation: comprasSeleccionCotPulse 1.8s ease-in-out infinite;
  }

  @keyframes comprasSeleccionCotPulse {
      0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.28); }
      70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
      100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
  }

  /* Estados de Solicitudes Generales */
  .process-item-status.status-creada { background: #6c757d !important; color: #fff !important; }
  .process-item-status.status-revisada-area {
      background: var(--dashboard-positive-bg) !important;
      color: var(--dashboard-positive-text) !important;
  }
  .process-item-status.status-pendiente-analisis { background: #fd7e14 !important; color: #fff !important; }
  .process-item-status.status-sin-entrega { background: #ffc107 !important; color: #212529 !important; }
  .process-item-status.status-archivada { background: #495057 !important; color: #fff !important; }

  .stat-card {
      background: white;
      border-radius: 8px;
      padding: 12px 15px;
      text-align: center;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      transition: transform 0.2s;
      height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: center;
  }

  .stat-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.15);
  }

  a.stat-card-link {
      text-decoration: none;
      color: inherit;
      cursor: pointer;
  }

  a.stat-card-link:hover,
  a.stat-card-link:focus {
      text-decoration: none;
      color: inherit;
  }

  a.stat-card-link .stat-card-icon,
  a.stat-card-link .stat-card-number {
      color: #871f1f !important;
  }

  a.stat-card-link .stat-card-label,
  a.stat-card-link .stat-card-pending,
  a.stat-card-link .stat-card-pending small {
      color: #6c757d !important;
  }

  .stat-card-icon {
      font-size: 28px;
      color: #871f1f;
      margin-bottom: 5px;
  }

  .stat-card-number {
      font-size: 24px;
      font-weight: bold;
      color: #871f1f;
      margin-bottom: 3px;
      line-height: 1.2;
  }

  .stat-card-label {
      color: #6c757d;
      font-size: 12px;
      text-transform: uppercase;
      font-weight: bold;
      line-height: 1.3;
      margin-bottom: 3px;
  }

  .stat-card-pending {
      font-size: 11px;
      color: #871f1f;
      margin-top: 2px;
      font-weight: 500;
      line-height: 1.2;
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
  
  @if(isset($isResponsableArea) && $isResponsableArea && isset($stockAlerts) && $stockAlerts->isNotEmpty() && !empty($stockAlertsHtml))
  <script>
    // FunciÃ³n para crear solicitud de compra para un producto especÃ­fico
    function createPurchaseRequestForProduct(productId, quantity, unit, event) {
      if (event) {
        event.stopPropagation();
      }
      
      // Crear el array de productos en el formato esperado
      var products = [{
        product_id: productId,
        quantity: Math.ceil(quantity), // Redondear hacia arriba
        price: 0,
        specifications: 'Solicitud generada automÃ¡ticamente por alerta de stock mÃ­nimo. DÃ©ficit: ' + quantity + ' ' + unit
      }];
      
      // Codificar como JSON y pasar como parÃ¡metro
      var productsJson = encodeURIComponent(JSON.stringify(products));
      var url = '{{ backpack_url("purchase-request/create") }}?selected_products=' + productsJson;
      
      window.location.href = url;
    }
    
    // FunciÃ³n para crear solicitud de compra para todos los productos
    function createPurchaseRequestForAll() {
      var products = [];
      
      @foreach($stockAlerts as $alert)
      products.push({
        product_id: {{ $alert['product']->id }},
        quantity: Math.ceil({{ $alert['deficit'] }}),
        price: 0,
        specifications: 'Solicitud generada automÃ¡ticamente por alerta de stock mÃ­nimo. DÃ©ficit: {{ number_format($alert['deficit'], 2) }} {{ $alert['product']->unit_measurement ?? 'unidades' }}'
      });
      @endforeach
      
      if (products.length === 0) {
        alert('No hay productos para crear la solicitud');
        return;
      }
      
      // Codificar como JSON y pasar como parÃ¡metro
      var productsJson = encodeURIComponent(JSON.stringify(products));
      var url = '{{ backpack_url("purchase-request/create") }}?selected_products=' + productsJson;
      
      window.location.href = url;
    }
  </script>
  <script>
    $(document).ready(function() {
      // Esperar a que SweetAlert estÃ© cargado
      setTimeout(function() {
        var $contentDiv = $('#stockAlertsHtmlContent');
        
        if ($contentDiv.length === 0) {
          console.error('No se encontrÃ³ el div con el contenido de alertas');
          return;
        }
        
        var alertsHtml = $contentDiv.html();
        
        // Verificar que el HTML no estÃ© vacÃ­o
        if (!alertsHtml || alertsHtml.trim() === '') {
          console.error('El HTML de alertas estÃ¡ vacÃ­o');
          console.log('Stock alerts count:', {{ $stockAlerts->count() ?? 0 }});
          console.log('Div encontrado pero vacÃ­o');
          return;
        }
        
        console.log('Mostrando alertas, HTML length:', alertsHtml.length);
        console.log('Primeros 200 caracteres:', alertsHtml.substring(0, 200));
        
        swal({
          title: 'Alertas de Stock MÃ­nimo',
          html: alertsHtml,
          icon: 'warning',
          width: '800px',
          buttons: {
            cancel: {
              text: 'Cerrar',
              value: null,
              visible: true,
              className: 'bg-secondary',
              closeModal: true
            },
            confirm: {
              text: 'Crear Solicitud de Compra (Todos)',
              value: true,
              visible: true,
              className: 'bg-primary'
            }
          },
          dangerMode: false,
          allowOutsideClick: true,
          allowEscapeKey: true
        }).then(function(value) {
          if (value) {
            createPurchaseRequestForAll();
          }
        });
      }, 500);
    });
  </script>
  @endif
@endpush

@section('content')
<div class="container-fluid">
    @php
        $pendingLegalApprovalsCount = isset($pendingApprovalRequests) ? $pendingApprovalRequests->count() : 0;
        $dashboardPanel = backpack_url('dashboard');
    @endphp

    @if(isset($isAdminInstitucion) && $isAdminInstitucion && isset($adminInstitucionInbox))
        @include('admin.dashboard.inc.admin_institucion_inbox')
    @endif

    @if((($isApoderado ?? false) || ($isRepresentanteLegal ?? false)) && !($isAdminInstitucion ?? false) && isset($superiorAuthorityInbox))
        @include('admin.dashboard.inc.superior_authority_inbox')
    @endif

    @if(isset($isResponsableCompras) && $isResponsableCompras && !($isAdminInstitucion ?? false) && ($purchaseRequestsAwaitingQuoteSelectionCount ?? 0) > 0)
    <div class="alert compras-seleccion-cotizacion-alert d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-4" role="alert">
        <div>
            <i class="la la-bell mr-2"></i>
            <strong>SelecciÃ³n de cotizaciÃ³n:</strong> hay {{ $purchaseRequestsAwaitingQuoteSelectionCount }} solicitud(es) de compra con cotizaciones cargadas y aÃºn sin cotizaciÃ³n elegida.
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ $dashboardPanel }}#purchase-requests-process-section" class="btn btn-sm btn-danger">Ir al panel de solicitudes de compra</a>
        </div>
    </div>
    @endif

    @if(($superiorApprovedPurchaseRequestsCount ?? 0) > 0 && (
        (isset($isResponsableCompras) && $isResponsableCompras && !($isAdminInstitucion ?? false))
        || ((isset($isAdminInstitucion) && $isAdminInstitucion) && !\App\Models\User::backpackHasAnyUserWithRole('role_responsable_compras'))
    ))
    <div class="alert compras-superior-approval-alert d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-4" role="alert">
        <div>
            <i class="la la-bell mr-2"></i>
            <strong>{{ (isset($isAdminInstitucion) && $isAdminInstitucion) ? 'Administración del instituto' : 'Compras' }}:</strong>
            hay {{ $superiorApprovedPurchaseRequestsCount }} solicitud(es) aprobada(s) por nivel superior y pendiente(s) de generar orden de compra.
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ backpack_url('purchase-request') }}?aprobadas_por_superior=1" class="btn btn-sm btn-success">Ver y generar OC</a>
            <a href="{{ $dashboardPanel }}#purchase-requests-process-section" class="btn btn-sm btn-outline-primary">Panel de solicitudes</a>
        </div>
    </div>
    @endif

    @if(isset($isPersonal) && $isPersonal)
    <!-- Cards de Estado de Solicitudes para role_personal -->
    <div class="row mb-4">
        <div class="col-md-12 d-flex justify-content-between align-items-center">
            <h2 class="section-title mb-0">Estado de Mis Solicitudes</h2>
            <a href="{{ backpack_url('general-request/create') }}" class="btn btn-primary">
                <i class="la la-plus"></i> Nueva Solicitud General
            </a>
        </div>
    </div>
    <div class="row mb-4">
        <div class="col-md-4 mb-3 mb-md-0">
            <a href="{{ $dashboardPanel }}#general-requests-process-section" class="stat-card stat-card-link" style="border-left: 4px solid var(--dashboard-positive-bg); display: block;">
                <div class="stat-card-icon" style="color: var(--dashboard-positive-bg);">
                    <i class="la la-check-circle"></i>
                </div>
                <div class="stat-card-number" style="color: var(--dashboard-positive-bg);">{{ $stats['general_requests_approved'] ?? 0 }}</div>
                <div class="stat-card-label">Solicitudes Aprobadas</div>
            </a>
        </div>
        <div class="col-md-4 mb-3 mb-md-0">
            <a href="{{ $dashboardPanel }}#general-requests-process-section" class="stat-card stat-card-link" style="border-left: 4px solid var(--dashboard-positive-bg); display: block;">
                <div class="stat-card-icon" style="color: var(--dashboard-positive-bg);">
                    <i class="la la-truck"></i>
                </div>
                <div class="stat-card-number" style="color: var(--dashboard-positive-bg);">{{ $stats['general_requests_entregada'] ?? 0 }}</div>
                <div class="stat-card-label">Solicitudes Entregadas</div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ $dashboardPanel }}#general-requests-process-section" class="stat-card stat-card-link" style="border-left: 4px solid #dc3545; display: block;">
                <div class="stat-card-icon" style="color: #dc3545;">
                    <i class="la la-times-circle"></i>
                </div>
                <div class="stat-card-number" style="color: #dc3545;">{{ $stats['general_requests_rejected'] ?? 0 }}</div>
                <div class="stat-card-label">Solicitudes Rechazadas</div>
            </a>
        </div>
    </div>
    @endif

    @if(isset($isResponsableArea) && $isResponsableArea)
    <div class="row mb-4">
        <div class="col-md-12 d-flex align-items-center flex-wrap gap-2 {{ (!isset($isAutoridadInstituto) || !$isAutoridadInstituto) ? 'justify-content-between' : '' }}">
            @if(isset($isAutoridadInstituto) && $isAutoridadInstituto)
            <a href="{{ backpack_url('purchase-request/create') }}" class="btn btn-primary">
                <i class="la la-plus"></i> Nueva solicitud de compra
            </a>
            @else
            <h2 class="section-title mb-0">Solicitudes de compra</h2>
            <a href="{{ backpack_url('purchase-request/create') }}" class="btn btn-primary">
                <i class="la la-plus"></i> Nueva solicitud de compra
            </a>
            @endif
        </div>
    </div>
    @endif
    
    <!-- EstadÃ­sticas Generales -->
    <div class="row mb-4">
        <div class="col-md-12">
            <h2 class="section-title">Proceso de Solicitudes</h2>
        </div>
    </div>

    @php
        /* Compras sin solicitudes generales: se oculta la 1Âª tarjeta pero las otras quedaban en col-md-3 (9/12 â†’ hueco). */
        $comprasTresTarjetasProceso = isset($isResponsableCompras) && $isResponsableCompras
            && (int) ($stats['general_requests'] ?? 0) === 0
            && (!isset($isPersonal) || !$isPersonal)
            && (!isset($isResponsableArea) || !$isResponsableArea);
        $procesoColGeneralYPr = isset($isPersonal) && $isPersonal
            ? '6'
            : ((isset($isResponsableArea) && $isResponsableArea) ? '4' : ($comprasTresTarjetasProceso ? '4' : '3'));
        $procesoColOrdenes = $comprasTresTarjetasProceso ? '4' : '3';
    @endphp

    <div class="row mb-3">
        @if(!isset($isResponsableCompras) || !$isResponsableCompras || (isset($isResponsableCompras) && $isResponsableCompras && $stats['general_requests'] > 0))
        <div class="col-md-{{ $procesoColGeneralYPr }}">
            <a href="{{ $dashboardPanel }}#general-requests-process-section" class="stat-card stat-card-link" style="display: block;">
                <div class="stat-card-icon">
                    <i class="la la-file-alt"></i>
                </div>
                <div class="stat-card-number">{{ $stats['general_requests'] }}</div>
                <div class="stat-card-label">{{ isset($isPersonal) && $isPersonal ? 'Mis Solicitudes Generales' : (isset($isResponsableArea) && $isResponsableArea ? 'Solicitudes Generales' : (isset($isResponsableCompras) && $isResponsableCompras ? 'Mis Solicitudes Generales' : 'Solicitudes Generales')) }}</div>
                <div class="stat-card-pending">
                    {{ $stats['general_requests_delivered'] }} Entregadas
                    @if(isset($isResponsableArea) && $isResponsableArea && isset($stats['general_requests_pending_delivery']) && $stats['general_requests_pending_delivery'] > 0)
                        <br>
                        <span style="color: #ffc107; font-weight: bold; display: inline-block; margin-top: 3px;">
                            <i class="la la-clock"></i> {{ $stats['general_requests_pending_delivery'] }} Pendientes de entrega
                        </span>
                    @endif
                    @if(isset($generalRequestsAgeStats) && isset($generalRequestsAgeStats['has_pending']) && $generalRequestsAgeStats['has_pending'] && ($isPersonal || $isResponsableArea))
                        <br>
                        <small style="color: #6c757d; font-size: 0.85rem;">
                            <i class="la la-hourglass-half"></i> 
                            MÃ¡s antigua: {{ (int)$generalRequestsAgeStats['max_days'] }} dÃ­a(s)
                            @if($generalRequestsAgeStats['average_days'] >= 0)
                                | Promedio: {{ (int)$generalRequestsAgeStats['average_days'] }} dÃ­a(s)
                            @endif
                        </small>
                    @endif
                </div>
            </a>
        </div>
        @endif
        @if((isset($isResponsableArea) && $isResponsableArea) || (!isset($isPersonal) || !$isPersonal))
        <div class="col-md-{{ $procesoColGeneralYPr }}">
            @if(isset($isResponsableCompras) && $isResponsableCompras && isset($stats['purchase_requests_pending']) && $stats['purchase_requests_pending'] > 0)
            <a href="{{ $dashboardPanel }}#purchase-requests-process-section" class="stat-card stat-card-link" style="display: block;">
                <div class="stat-card-icon">
                    <i class="la la-shopping-cart"></i>
                </div>
                <div class="stat-card-number" style="font-size: 2.5rem; font-weight: bold;">
                    {{ $stats['purchase_requests_pending'] }}
                </div>
                <div class="stat-card-label">{{ isset($isResponsableArea) && $isResponsableArea ? 'Mis Solicitudes de Compra' : 'Solicitudes de Compra' }}</div>
                <div class="stat-card-pending" style="font-size: 0.85rem; color: #6c757d;">
                    Total: {{ $stats['purchase_requests'] }}
                    @if(isset($purchaseRequestsAgeStats) && isset($purchaseRequestsAgeStats['has_pending']) && $purchaseRequestsAgeStats['has_pending'])
                        <br>
                        <small style="color: #6c757d;">
                            <i class="la la-hourglass-half"></i> 
                            MÃ¡s antigua: {{ (int)$purchaseRequestsAgeStats['max_days'] }} dÃ­a(s)
                            @if($purchaseRequestsAgeStats['average_days'] >= 0)
                                | Promedio: {{ (int)$purchaseRequestsAgeStats['average_days'] }} dÃ­a(s)
                            @endif
                        </small>
                    @endif
                </div>
            </a>
            @else
            <a href="{{ $dashboardPanel }}#purchase-requests-process-section" class="stat-card stat-card-link" style="display: block;">
                <div class="stat-card-icon">
                    <i class="la la-shopping-cart"></i>
                </div>
                <div class="stat-card-number">{{ $stats['purchase_requests'] }}</div>
                <div class="stat-card-label">{{ isset($isResponsableArea) && $isResponsableArea ? 'Mis Solicitudes de Compra' : 'Solicitudes de Compra' }}</div>
                <div class="stat-card-pending">
                    {{ $stats['purchase_requests_pending'] }} Pendientes
                    @if(isset($purchaseRequestsAgeStats) && $purchaseRequestsAgeStats['max_days'] > 0)
                        <br>
                        <small style="color: #6c757d; font-size: 0.85rem;">
                            <i class="la la-hourglass-half"></i> 
                            MÃ¡s antigua: {{ (int)$purchaseRequestsAgeStats['max_days'] }} dÃ­a(s)
                            @if($purchaseRequestsAgeStats['average_days'] > 0)
                                | Promedio: {{ (int)$purchaseRequestsAgeStats['average_days'] }} dÃ­a(s)
                            @endif
                        </small>
                    @endif
                </div>
            </a>
            @endif
        </div>
        @endif
        @if((!isset($isPersonal) || !$isPersonal) && (!isset($isResponsableArea) || !$isResponsableArea))
        <div class="col-md-{{ $procesoColOrdenes }}">
            <a href="{{ $dashboardPanel }}#purchase-orders-process-section" class="stat-card stat-card-link" style="display: block;">
                <div class="stat-card-icon">
                    <i class="la la-clipboard-list"></i>
                </div>
                <div class="stat-card-number">{{ $stats['purchase_orders'] }}</div>
                <div class="stat-card-label">Ã“rdenes de Compra</div>
                <div class="stat-card-pending">{{ $stats['purchase_orders_pending'] }} Pendientes</div>
            </a>
        </div>
        <div class="col-md-{{ $procesoColOrdenes }}">
            <a href="{{ $dashboardPanel }}#payment-orders-process-section" class="stat-card stat-card-link" style="display: block;">
                <div class="stat-card-icon">
                    <i class="la la-money-bill-wave"></i>
                </div>
                <div class="stat-card-number">{{ $stats['payment_orders'] }}</div>
                <div class="stat-card-label">Ã“rdenes de Pago</div>
                <div class="stat-card-pending">{{ $stats['payment_orders_pending'] }} Pendientes</div>
            </a>
        </div>
        @endif
        @if(isset($isPersonal) && $isPersonal || (isset($isResponsableArea) && $isResponsableArea))
        <div class="col-md-{{ isset($isPersonal) && $isPersonal ? '6' : '4' }}">
            <a href="{{ $dashboardPanel }}#deliveries-process-section" class="stat-card stat-card-link" style="display: block;">
                <div class="stat-card-icon">
                    <i class="la la-people-carry"></i>
                </div>
                <div class="stat-card-number">{{ $stats['deliveries'] }}</div>
                <div class="stat-card-label">{{ isset($isPersonal) && $isPersonal ? 'Mis Entregas' : 'Entregas' }}</div>
                <div class="stat-card-pending">&nbsp;</div>
            </a>
        </div>
        @endif
    </div>

    @if(!isset($isPersonal) || !$isPersonal)
    <div class="row mb-3">
        @if((!isset($isPersonal) || !$isPersonal) && (!isset($isResponsableArea) || !$isResponsableArea))
        <div class="col-md-4">
            <a href="{{ $dashboardPanel }}#deliveries-process-section" class="stat-card stat-card-link" style="display: block;">
                <div class="stat-card-icon">
                    <i class="la la-people-carry"></i>
                </div>
                <div class="stat-card-number">{{ $stats['deliveries'] }}</div>
                <div class="stat-card-label">Entregas</div>
            </a>
        </div>
        @endif
        <div class="col-md-{{ (!isset($isPersonal) || !$isPersonal) && (!isset($isResponsableArea) || !$isResponsableArea) ? '4' : '6' }}">
            <a href="{{ $dashboardPanel }}#receptions-process-section" class="stat-card stat-card-link" style="display: block;">
                <div class="stat-card-icon">
                    <i class="la la-truck-loading"></i>
                </div>
                <div class="stat-card-number">{{ $stats['receptions'] }}</div>
                <div class="stat-card-label">Recepciones</div>
            </a>
        </div>
        <div class="col-md-{{ (!isset($isPersonal) || !$isPersonal) && (!isset($isResponsableArea) || !$isResponsableArea) ? '4' : '6' }}">
            <a href="{{ $dashboardPanel }}#devolutions-process-section" class="stat-card stat-card-link" style="display: block;">
                <div class="stat-card-icon">
                    <i class="la la-undo-alt"></i>
                </div>
                <div class="stat-card-number">{{ $stats['devolutions'] }}</div>
                <div class="stat-card-label">Devoluciones</div>
            </a>
        </div>
    </div>
    @endif

    @if((isset($isResponsableArea) && $isResponsableArea) || (!isset($isPersonal) || !$isPersonal))
    <!-- Tipos de Compras -->
    <div class="row mb-2">
        <div class="col-md-12">
            <h2 class="section-title" style="margin-bottom: 15px;">Tipos de Compras</h2>
        </div>
    </div>
    <div class="row mb-3" id="purchase-request-types-section">
        <div class="col-md-4 mb-3 mb-md-0">
            <a href="{{ $dashboardPanel }}#purchase-request-types-section" class="stat-card stat-card-link" style="border-left: 4px solid #6c757d; display: block;">
                <div class="stat-card-icon" style="color: #6c757d;">
                    <i class="la la-shopping-bag"></i>
                </div>
                <div class="stat-card-number" style="color: #6c757d;">{{ $stats['purchase_requests_normal'] ?? 0 }}</div>
                <div class="stat-card-label">Compras Normales</div>
                <div class="stat-card-pending">&nbsp;</div>
            </a>
        </div>
        <div class="col-md-4 mb-3 mb-md-0">
            <a href="{{ $dashboardPanel }}#purchase-request-types-section" class="stat-card stat-card-link" style="border-left: 4px solid #17a2b8; display: block;">
                <div class="stat-card-icon" style="color: #17a2b8;">
                    <i class="la la-hand-pointer"></i>
                </div>
                <div class="stat-card-number" style="color: #17a2b8;">{{ $stats['purchase_requests_direct'] ?? 0 }}</div>
                <div class="stat-card-label">Compras Directas</div>
                <div class="stat-card-pending">&nbsp;</div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ $dashboardPanel }}#purchase-request-types-section" class="stat-card stat-card-link" style="border-left: 4px solid #ffc107; display: block;">
                <div class="stat-card-icon" style="color: #ffc107;">
                    <i class="la la-bolt"></i>
                </div>
                <div class="stat-card-number" style="color: #ffc107;">{{ $stats['purchase_requests_quick'] ?? 0 }}</div>
                <div class="stat-card-label">Compras RÃ¡pidas</div>
                <div class="stat-card-pending">&nbsp;</div>
            </a>
        </div>
    </div>
    @endif

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
                        <span class="badge bg-secondary">{{ $supplier->total_ratings }} evaluaciÃ³n(es)</span>
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
                        <i class="la la-calendar"></i> Ãšltima evaluaciÃ³n: 
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

    @if(isset($isResponsableArea) && $isResponsableArea && isset($stockAlerts) && $stockAlerts->isNotEmpty())
    <!-- Alertas de Stock MÃ­nimo (Responsable de Ãrea) -->
    <div class="row mb-4">
        <div class="col-md-12 d-flex justify-content-between align-items-center">
            <h3 class="section-title mb-0">Alertas de Stock</h3>
            <button type="button" class="btn btn-primary" onclick="createPurchaseRequestForAll()">
                <i class="la la-shopping-cart"></i> Crear Solicitud de Compra (Todos)
            </button>
        </div>
    </div>
    <div id="stock-alerts-process-section" class="process-step" style="border-left: 4px solid #dc3545;">
        <div class="process-step-header" style="background-color: #f8d7da;">
            <div class="process-step-title">
                <i class="la la-exclamation-circle process-step-icon" style="color: #721c24;"></i>
                <span style="color: #721c24; font-weight: bold;">Productos con Stock por Debajo del MÃ­nimo</span>
            </div>
            <span class="process-step-count" style="background-color: #dc3545; color: white;">{{ $stockAlerts->count() }}</span>
        </div>
        <div class="process-step-content">
            @foreach($stockAlerts as $alert)
                <div class="process-item-card" style="border-left: 3px solid #dc3545;">
                    <div class="process-item-title" onclick="window.location='{{ backpack_url('stock-level') }}'" style="cursor: pointer;">
                        <i class="la la-box" style="color: #dc3545;"></i> {{ $alert['product']->name }}
                    </div>
                    <div class="process-item-meta">
                        <span style="color: #dc3545; font-weight: bold;">
                            <i class="la la-arrow-down"></i> Stock actual: {{ number_format($alert['current_stock'], 0) }}
                        </span>
                        <span style="color: #856404;">
                            <i class="la la-exclamation-triangle"></i> Stock mÃ­nimo: {{ number_format($alert['minimum_stock'], 0) }}
                        </span>
                    </div>
                    <div class="process-item-meta">
                        <span style="color: #dc3545; font-weight: bold;">
                            <i class="la la-minus-circle"></i> DÃ©ficit: {{ number_format($alert['deficit'], 0) }} {{ $alert['product']->unit_measurement ?? 'unidades' }}
                        </span>
                    </div>
                    @if($alert['locations']->isNotEmpty())
                    <div class="process-item-meta">
                        <span><i class="la la-map-marker"></i> Ubicaciones:</span>
                        @foreach($alert['locations'] as $location)
                            <span class="badge bg-secondary">{{ $location['name'] }}: {{ number_format($location['quantity'], 0) }}</span>
                        @endforeach
                    </div>
                    @endif
                    <div class="process-item-meta mt-2">
                        <button type="button" class="btn btn-sm btn-success" onclick="createPurchaseRequestForProduct({{ $alert['product']->id }}, {{ $alert['deficit'] }}, '{{ $alert['product']->unit_measurement ?? 'unidades' }}', event)">
                            <i class="la la-shopping-cart"></i> Crear Solicitud de Compra
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Flujo del Proceso Visual -->
    <div class="row mb-4">
        <div class="col-md-12">
            <h3 class="section-title">Flujo del Proceso Completo</h3>
        </div>
    </div>

    <!-- Paso 1: Solicitudes Generales -->
    @if(!isset($isResponsableCompras) || !$isResponsableCompras || (isset($isResponsableCompras) && $isResponsableCompras && $generalRequests->count() > 0))
    <div id="general-requests-process-section" class="process-step">
        <div class="process-step-header">
            <div class="process-step-title">
                <i class="la la-file-alt process-step-icon"></i>
                <span>1. Solicitudes Generales</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="process-step-count">{{ $stats['general_requests'] }}</span>
                @if(isset($isPersonal) && $isPersonal)
                <a href="{{ backpack_url('general-request/create') }}" class="btn btn-sm btn-primary">
                    <i class="la la-plus"></i> Nueva Solicitud
                </a>
                @endif
            </div>
        </div>
        <div class="process-step-content">
            @forelse($generalRequests as $generalRequest)
                <div class="process-item-card" onclick="window.location='{{ backpack_url('general-request/' . $generalRequest->id . '/show') }}'">
                    <div class="process-item-title">{{ $generalRequest->number }}</div>
                    <div class="process-item-meta">
                        <span>{{ $generalRequest->title }}</span>
                        @php
                            $status = $generalRequest->status ?? 'N/A';
                            $statusClass = strtolower(str_replace([' ', '_'], '-', $status));
                            
                            $pos = ['bg' => '#198754', 'text' => '#fff'];
                            $statusColors = [
                                'creada' => ['bg' => '#6c757d', 'text' => '#fff', 'label' => 'Creada'],
                                'pendiente-analisis' => ['bg' => '#fd7e14', 'text' => '#fff', 'label' => 'Pendiente anÃ¡lisis'],
                                'revisada-area' => $pos + ['label' => 'Revisada por Ãrea'],
                                'archivada' => ['bg' => '#495057', 'text' => '#fff', 'label' => 'Archivada'],
                                'sin-entrega' => ['bg' => '#ffc107', 'text' => '#212529', 'label' => 'Sin entrega'],
                                'entregada-parcialmente' => $pos + ['label' => 'Entregada parcialmente'],
                                'entregada-totalmente' => $pos + ['label' => 'Entregada totalmente'],
                                'rechazada-analista' => ['bg' => '#dc3545', 'text' => '#fff', 'label' => 'Rechazada analista'],
                            ];
                            $color = $statusColors[$statusClass] ?? ['bg' => '#6c757d', 'text' => '#fff', 'label' => ucfirst(str_replace('_', ' ', $status))];
                            $isConverted = $generalRequest->is_converted ?? false;
                        @endphp
                        <span class="process-item-status status-{{ $statusClass }}" style="background-color: {{ $color['bg'] }} !important; color: {{ $color['text'] }} !important;">
                            {{ $color['label'] }}
                        </span>
                        @if($isConverted)
                            <span class="badge dashboard-badge-positive" style="margin-left: 5px;" title="Convertida a compra">
                                <i class="la la-check-circle"></i> Convertida
                            </span>
                        @else
                            <span class="badge bg-secondary" style="margin-left: 5px;" title="No convertida">
                                <i class="la la-times-circle"></i> No convertida
                            </span>
                        @endif
                    </div>
                    <div class="process-item-meta">
                        <span><i class="la la-user"></i> {{ $generalRequest->createdBy->name ?? 'N/A' }}</span>
                        <span><i class="la la-calendar"></i> {{ $generalRequest->created_at->format('d/m/Y') }}</span>
                        @if(($generalRequest->status == 'creada' || $generalRequest->status == 'pendiente_analisis') && (isset($isPersonal) || isset($isResponsableArea)))
                            @php
                                $ageDays = (int) floor($generalRequest->age_in_days);
                                $badgeColor = $generalRequest->age_badge_color;
                            @endphp
                            <span class="badge bg-{{ $badgeColor }}" style="margin-left: 5px;" title="AntigÃ¼edad: {{ $generalRequest->age }}">
                                <i class="la la-hourglass-half"></i> {{ $ageDays }} dÃ­a(s)
                            </span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-muted">No hay solicitudes generales recientes</div>
            @endforelse
        </div>
    </div>
    @endif

    @if(((isset($isApoderado) && $isApoderado) || (isset($isRepresentanteLegal) && $isRepresentanteLegal)) && !($isAdminInstitucion ?? false) && isset($pendingApprovalRequests))
        @include('admin.dashboard.inc.pending_approval_section')
    @endif

    @if((isset($isResponsableArea) && $isResponsableArea) || (!isset($isPersonal) || !$isPersonal))
    <!-- Paso 2: Solicitudes de Compra -->
    <div id="purchase-requests-process-section" class="process-step">
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
                        @if($purchaseRequest->status == 'Pendiente')
                            @php
                                $ageDays = (int) floor($purchaseRequest->age_in_days);
                                $badgeColor = $purchaseRequest->age_badge_color;
                            @endphp
                            <span class="badge bg-{{ $badgeColor }}" style="margin-left: 5px;" title="AntigÃ¼edad: {{ $purchaseRequest->age }}">
                                <i class="la la-hourglass-half"></i> {{ $ageDays }} dÃ­a(s)
                            </span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-muted">No hay solicitudes de compra recientes</div>
            @endforelse
        </div>
    </div>
    @endif

    @if((!isset($isPersonal) || !$isPersonal) && (!isset($isResponsableArea) || !$isResponsableArea))
    <!-- Paso 3: Ã“rdenes de Compra -->
    <div id="purchase-orders-process-section" class="process-step">
        <div class="process-step-header">
            <div class="process-step-title">
                <i class="la la-clipboard-list process-step-icon"></i>
                <span>3. Ã“rdenes de Compra</span>
            </div>
            <span class="process-step-count">{{ $stats['purchase_orders'] }}</span>
        </div>
        <div class="process-step-content">
            @forelse($purchaseOrders as $purchaseOrder)
                <div class="process-item-card" onclick="window.location='{{ backpack_url('purchase-order/' . $purchaseOrder->id . '/show') }}'">
                    <div class="process-item-title">{{ $purchaseOrder->number ?? 'N/A' }}</div>
                    <div class="process-item-meta">
                        <span><i class="la la-truck"></i> {{ $purchaseOrder->supplier_display_name }}</span>
                        <span class="process-item-status status-{{ strtolower(str_replace(' ', '-', $purchaseOrder->status ?? 'Pendiente')) }}">{{ $purchaseOrder->status ?? 'Pendiente' }}</span>
                    </div>
                    <div class="process-item-meta">
                        <span><i class="la la-calendar"></i> {{ $purchaseOrder->date->format('d/m/Y') ?? 'N/A' }}</span>
                        <span><i class="la la-money-bill"></i> ${{ number_format($purchaseOrder->total ?? 0, 2) }}</span>
                    </div>
                </div>
            @empty
                <div class="text-muted">No hay Ã³rdenes de compra recientes</div>
            @endforelse
        </div>
    </div>
    @endif

    @if(!isset($isPersonal) || !$isPersonal)
    <!-- Paso {{ (isset($isResponsableArea) && $isResponsableArea) ? '3' : '4' }}: Recepciones -->
    <div id="receptions-process-section" class="process-step">
        <div class="process-step-header">
            <div class="process-step-title">
                <i class="la la-truck-loading process-step-icon"></i>
                <span>@if(isset($isResponsableArea) && $isResponsableArea)3.@else 4.@endif Recepciones</span>
            </div>
            <span class="process-step-count">{{ $stats['receptions'] }}</span>
        </div>
        <div class="process-step-content">
            @forelse($receptions as $reception)
                <div class="process-item-card" onclick="window.location='{{ backpack_url('reception/' . $reception->id . '/show') }}'">
                    <div class="process-item-title">
                        {{ $reception->number ?? 'REC-' . $reception->id }}
                        @if(($reception->according ?? '') === 'Si')
                            <span class="badge dashboard-badge-positive ms-2 align-middle text-uppercase">Conforme</span>
                        @endif
                    </div>
                    <div class="process-item-meta">
                        <span><i class="la la-clipboard-list"></i> {{ $reception->purchase_order->number ?? 'N/A' }}</span>
                        <span><i class="la la-truck"></i> {{ $reception->purchase_order->supplier_display_name }}</span>
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
    @endif

    @if((!isset($isPersonal) || !$isPersonal) && (!isset($isResponsableArea) || !$isResponsableArea))
    <!-- Paso 5: Ã“rdenes de Pago -->
    <div id="payment-orders-process-section" class="process-step">
        <div class="process-step-header">
            <div class="process-step-title">
                <i class="la la-money-bill-wave process-step-icon"></i>
                <span>5. Ã“rdenes de Pago</span>
            </div>
            <span class="process-step-count">{{ $stats['payment_orders'] }}</span>
        </div>
        <div class="process-step-content">
            @forelse($paymentOrders as $paymentOrder)
                <div class="process-item-card" onclick="window.location='{{ backpack_url('payment-order/' . $paymentOrder->id . '/show') }}'">
                    <div class="process-item-title">
                        {{ $paymentOrder->payment_number ?? 'N/A' }}
                        <span class="process-item-status status-{{ $paymentOrder->dashboard_payment_status_css_suffix }}">{{ $paymentOrder->dashboard_payment_status_label }}</span>
                    </div>
                    <div class="process-item-meta">
                        <span><i class="la la-truck"></i> {{ $paymentOrder->purchase_order->supplier_display_name }}</span>
                        <span><i class="la la-clipboard-list"></i> {{ $paymentOrder->purchase_order->number ?? 'N/A' }}</span>
                    </div>
                    <div class="process-item-meta">
                        <span><i class="la la-calendar"></i> {{ $paymentOrder->date ? $paymentOrder->date->format('d/m/Y') : 'N/A' }}</span>
                        <span><i class="la la-money-bill"></i> ${{ number_format($paymentOrder->total_amount ?? 0, 2) }}</span>
                        @php $opCur = strtoupper(trim((string) ($paymentOrder->currency_code ?? ''))); @endphp
                        <span><i class="la la-coins"></i> {{ $opCur !== '' ? $opCur : 'ARS' }}</span>
                    </div>
                </div>
            @empty
                <div class="text-muted">No hay Ã³rdenes de pago recientes</div>
            @endforelse
        </div>
    </div>

    <!-- Paso 6: Devoluciones -->
    <div id="devolutions-process-section" class="process-step">
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
    @endif

    <!-- Paso {{ (isset($isPersonal) && $isPersonal) ? '2' : ((isset($isResponsableArea) && $isResponsableArea) ? '4' : '7') }}: Entregas -->
    <div id="deliveries-process-section" class="process-step">
        <div class="process-step-header">
            <div class="process-step-title">
                <i class="la la-people-carry process-step-icon"></i>
                <span>@if(isset($isPersonal) && $isPersonal)2.@elseif(isset($isResponsableArea) && $isResponsableArea)4.@else 7.@endif Entregas</span>
            </div>
            <span class="process-step-count">{{ $stats['deliveries'] }}</span>
        </div>
        <div class="process-step-content">
            @forelse($deliveries as $delivery)
                <div class="process-item-card" onclick="window.location='{{ backpack_url('delivery/' . $delivery->id . '/show') }}'">
                    <div class="process-item-title">
                        {{ $delivery->number ?? 'ENT-' . $delivery->id }}
                        @php $deliveryStatus = $delivery->status ?? 'pendiente'; @endphp
                        @if($deliveryStatus === 'entregada')
                            <span class="badge dashboard-badge-positive ms-2 align-middle"><i class="la la-check-circle"></i> Entrega completada</span>
                        @elseif($deliveryStatus === 'cancelada')
                            <span class="badge bg-secondary ms-2 align-middle"><i class="la la-ban"></i> Cancelada</span>
                        @else
                            <span class="badge bg-warning text-dark ms-2 align-middle"><i class="la la-clock"></i> Pendiente</span>
                        @endif
                    </div>
                    <div class="process-item-meta">
                        @if($delivery->reception)
                        <span><i class="la la-truck-loading"></i> {{ $delivery->reception->number ?? 'REC-' . $delivery->reception_id }}</span>
                        @endif
                        @if($delivery->generalRequest)
                        <span><i class="la la-file-alt"></i> {{ $delivery->generalRequest->number ?? 'N/A' }}</span>
                        @endif
                    </div>
                    <div class="process-item-meta">
                        @if($delivery->deliveredBy)
                        <span><i class="la la-user"></i> Entregado por: {{ $delivery->deliveredBy->name ?? 'N/A' }}</span>
                        @endif
                        @if($delivery->receivedBy)
                        <span><i class="la la-user"></i> Recibido por: {{ $delivery->receivedBy->name ?? 'N/A' }}</span>
                        @endif
                    </div>
                    <div class="process-item-meta">
                        <span><i class="la la-calendar"></i> {{ $delivery->delivery_date ? $delivery->delivery_date->format('d/m/Y') : $delivery->created_at->format('d/m/Y') }}</span>
                    </div>
                </div>
            @empty
                <div class="text-muted">No hay entregas recientes</div>
            @endforelse
        </div>
    </div>

    <!-- Flujos Completos de Procesos -->
    @if(isset($processFlows) && count($processFlows) > 0)
    <div class="row mb-4">
        <div class="col-md-12">
            <h3 class="section-title">Trazabilidad Completa de Procesos</h3>
            <p class="text-muted">Incluye solicitudes generales con compra asociada y solicitudes de compra creadas directamente (sin solicitud general), hasta Ã³rdenes de compra, pagos, recepciones y devoluciones si aplica.</p>
        </div>
    </div>

    @foreach($processFlows as $flow)
        @php
            $flowHasGeneral = isset($flow['general_request']) && $flow['general_request'];
            $flowHasPurchaseRequests = isset($flow['purchase_requests']) && count($flow['purchase_requests']) > 0;
        @endphp
        @if($flowHasGeneral || $flowHasPurchaseRequests)
            <div class="card mb-4">
                <div class="card-header {{ $flowHasGeneral ? 'bg-primary text-white' : 'bg-light text-dark border' }}">
                    <h5 class="mb-0 {{ $flowHasGeneral ? '' : 'text-dark' }}">
                        @if($flowHasGeneral)
                            <i class="la la-file-alt"></i>
                            Solicitud General: {{ $flow['general_request']->number }}
                        @else
                            @php $flowLeadPr = $flow['purchase_requests'][0] ?? null; @endphp
                            <i class="la la-shopping-cart"></i>
                            Flujo desde solicitud de compra
                            @if($flowLeadPr)
                                : <a href="{{ backpack_url('purchase-request/' . $flowLeadPr->id . '/show') }}" class="text-dark text-decoration-underline">{{ $flowLeadPr->request_number ?? 'SC-' . $flowLeadPr->id }}</a>
                            @endif
                        @endif
                    </h5>
                </div>
                <div class="card-body">
                    <div class="flow-timeline">
                        @if($flowHasGeneral)
                        <div class="flow-timeline-item">
                            <div class="flow-timeline-content">
                                <strong>Solicitud General:</strong> {{ $flow['general_request']->title }}
                                <br>
                                <small class="text-muted">
                                    Creada por: {{ $flow['general_request']->createdBy->name ?? 'N/A' }} 
                                    | {{ $flow['general_request']->created_at->format('d/m/Y H:i') }}
                                    | Estado: 
                                    @php
                                        $status = $flow['general_request']->status ?? 'N/A';
                                        // Convertir guiones bajos a guiones y espacios a guiones
                                        $statusClass = strtolower(str_replace([' ', '_'], '-', $status));
                                        
                                        $pos = ['bg' => '#198754', 'text' => '#fff'];
                                        $statusColors = [
                                            'creada' => ['bg' => '#6c757d', 'text' => '#fff', 'label' => 'Creada'],
                                            'pendiente-analisis' => ['bg' => '#fd7e14', 'text' => '#fff', 'label' => 'Pendiente anÃ¡lisis'],
                                            'revisada-area' => $pos + ['label' => 'Revisada por Ãrea'],
                                            'archivada' => ['bg' => '#495057', 'text' => '#fff', 'label' => 'Archivada'],
                                            'sin-entrega' => ['bg' => '#ffc107', 'text' => '#212529', 'label' => 'Sin entrega'],
                                            'entregada-parcialmente' => $pos + ['label' => 'Entregada parcialmente'],
                                            'entregada-totalmente' => $pos + ['label' => 'Entregada totalmente'],
                                            'rechazada-analista' => ['bg' => '#dc3545', 'text' => '#fff', 'label' => 'Rechazada analista'],
                                        ];
                                        $color = $statusColors[$statusClass] ?? ['bg' => '#6c757d', 'text' => '#fff', 'label' => ucfirst(str_replace('_', ' ', $status))];
                                        $isConverted = $flow['general_request']->is_converted ?? false;
                                    @endphp
                                    <span class="process-item-status status-{{ $statusClass }}" style="background-color: {{ $color['bg'] }} !important; color: {{ $color['text'] }} !important;">
                                        {{ $color['label'] }}
                                    </span>
                                    @if($isConverted)
                                        <span class="badge dashboard-badge-positive" style="margin-left: 5px;" title="Convertida a compra">
                                            <i class="la la-check-circle"></i> Convertida
                                        </span>
                                    @else
                                        <span class="badge bg-secondary" style="margin-left: 5px;" title="No convertida">
                                            <i class="la la-times-circle"></i> No convertida
                                        </span>
                                    @endif
                                </small>
                            </div>
                        </div>
                        @endif

                        @if($flowHasPurchaseRequests)
                            @foreach($flow['purchase_requests'] as $pr)
                                <div class="flow-timeline-item">
                                    <div class="flow-timeline-content">
                                        <strong>Solicitud de Compra:</strong> 
                                        <a href="{{ backpack_url('purchase-request/' . $pr->id . '/show') }}" class="text-primary">
                                            {{ $pr->request_number ?? 'N/A' }}
                                        </a>
                                        <br>
                                        <small class="text-muted">
                                            Estado: {{ $pr->status }} | 
                                            Fecha: {{ $pr->request_date ? $pr->request_date->format('d/m/Y') : 'N/A' }}
                                            @if($pr->selectedMarketRate)
                                                | CotizaciÃ³n seleccionada: {{ $pr->selectedMarketRate->supplier->name ?? 'N/A' }}
                                            @endif
                                        </small>
                                    </div>
                                </div>
                            @endforeach
                        @elseif($flowHasGeneral)
                            <div class="flow-timeline-item">
                                <div class="flow-timeline-content">
                                    <small class="text-muted">No hay solicitudes de compra generadas aÃºn</small>
                                </div>
                            </div>
                        @endif

                        @if((!isset($isResponsableArea) || !$isResponsableArea) && isset($flow['purchase_orders']) && count($flow['purchase_orders']) > 0)
                            @foreach($flow['purchase_orders'] as $po)
                                <div class="flow-timeline-item">
                                    <div class="flow-timeline-content">
                                        <strong><i class="la la-clipboard-list"></i> Orden de Compra:</strong> 
                                        <a href="{{ backpack_url('purchase-order/' . $po->id . '/show') }}" class="text-primary">
                                            {{ $po->number ?? 'N/A' }}
                                        </a>
                                        <br>
                                        <small class="text-muted">
                                            Proveedor: {{ $po->supplier_display_name }} | 
                                            Estado: {{ $po->status ?? 'N/A' }}
                                            @if($po->date)
                                                | Fecha: {{ $po->date->format('d/m/Y') }}
                                            @endif
                                            @if($po->total)
                                                | Total: ${{ number_format($po->total, 2) }}
                                            @endif
                                        </small>
                                    </div>
                                </div>

                                {{-- Mostrar recepciones relacionadas con esta orden de compra (antes que OP en la lÃ­nea de tiempo) --}}
                                @if($po->receptions && $po->receptions->count() > 0)
                                    @foreach($po->receptions as $reception)
                                        <div class="flow-timeline-item">
                                            <div class="flow-timeline-content" style="margin-left: 20px; border-left: 3px solid var(--dashboard-positive-bg);">
                                                <strong><i class="la la-truck-loading"></i> RecepciÃ³n:</strong> 
                                                <a href="{{ backpack_url('reception/' . $reception->id . '/show') }}" class="text-primary">
                                                    {{ $reception->number ?? 'REC-' . $reception->id }}
                                                </a>
                                                @if(($reception->according ?? '') === 'Si')
                                                    @if($flowHasGeneral)
                                                        <span class="badge dashboard-badge-positive ms-1 align-middle text-uppercase">Conforme</span>
                                                    @else
                                                        <span class="text-muted ms-1 text-uppercase">Conforme</span>
                                                    @endif
                                                @endif
                                                <br>
                                                <small class="text-muted">
                                                    Fecha: {{ $reception->created_at->format('d/m/Y H:i') }}
                                                    @if($reception->user)
                                                        | Recibido por: {{ $reception->user->name }}
                                                    @endif
                                                </small>
                                            </div>
                                        </div>

                                        {{-- Mostrar devoluciones relacionadas con esta recepciÃ³n --}}
                                        @if($reception->devolutions && $reception->devolutions->count() > 0)
                                            @foreach($reception->devolutions as $devolution)
                                                <div class="flow-timeline-item">
                                                    <div class="flow-timeline-content" style="margin-left: 40px; border-left: 3px solid #dc3545;">
                                                        <strong><i class="la la-undo-alt"></i> DevoluciÃ³n:</strong> 
                                                        <a href="{{ backpack_url('devolution/' . $devolution->id . '/show') }}" class="text-primary">
                                                            DEV-{{ $devolution->id }}
                                                        </a>
                                                        <br>
                                                        <small class="text-muted">
                                                            Fecha: {{ $devolution->created_at->format('d/m/Y H:i') }}
                                                            @if($devolution->user)
                                                                | Realizada por: {{ $devolution->user->name }}
                                                            @endif
                                                            @if($devolution->reason)
                                                                | Motivo: {{ $devolution->reason }}
                                                            @endif
                                                        </small>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @endif

                                        {{-- Mostrar entregas relacionadas con esta recepciÃ³n --}}
                                        @if($reception->deliveries && $reception->deliveries->count() > 0)
                                            @foreach($reception->deliveries as $delivery)
                                                <div class="flow-timeline-item">
                                                    <div class="flow-timeline-content" style="margin-left: 40px; border-left: 3px solid #ffc107;">
                                                        <strong><i class="la la-people-carry"></i> Entrega:</strong> 
                                                        <a href="{{ backpack_url('delivery/' . $delivery->id . '/show') }}" class="text-primary">
                                                            {{ $delivery->number ?? 'ENT-' . $delivery->id }}
                                                        </a>
                                                        @php $flowDeliveryStatus = $delivery->status ?? 'pendiente'; @endphp
                                                        @if($flowDeliveryStatus === 'entregada')
                                                            @if($flowHasGeneral)
                                                                <span class="badge dashboard-badge-positive ms-1 align-middle"><i class="la la-check-circle"></i> Entrega completada</span>
                                                            @else
                                                                <span class="text-success ms-1 small fw-semibold"><i class="la la-check-circle"></i> Entrega completada</span>
                                                            @endif
                                                        @elseif($flowDeliveryStatus === 'cancelada')
                                                            <span class="badge bg-secondary ms-1 align-middle">Cancelada</span>
                                                        @else
                                                            <span class="badge bg-warning text-dark ms-1 align-middle">Pendiente</span>
                                                        @endif
                                                        <br>
                                                        <small class="text-muted">
                                                            Solicitud General: {{ $delivery->generalRequest?->number ?? 'â€”' }}
                                                            @if($delivery->delivery_date)
                                                                | Fecha: {{ $delivery->delivery_date->format('d/m/Y') }}
                                                            @endif
                                                            @if($delivery->deliveredBy)
                                                                | Entregado por: {{ $delivery->deliveredBy->name }}
                                                            @endif
                                                            @if($delivery->receivedBy)
                                                                | Recibido por: {{ $delivery->receivedBy->name }}
                                                            @endif
                                                        </small>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @endif
                                    @endforeach
                                @else
                                    <div class="flow-timeline-item">
                                        <div class="flow-timeline-content" style="margin-left: 20px;">
                                            <small class="text-muted"><i class="la la-info-circle"></i> No hay recepciones registradas para esta orden de compra</small>
                                        </div>
                                    </div>
                                @endif

                                {{-- Mostrar Ã³rdenes de pago relacionadas con esta orden de compra (despuÃ©s de recepciones en la lÃ­nea de tiempo) --}}
                                @if($po->paymentOrders && $po->paymentOrders->count() > 0)
                                    @foreach($po->paymentOrders as $paymentOrder)
                                        <div class="flow-timeline-item">
                                            <div class="flow-timeline-content" style="margin-left: 20px; border-left: 3px solid var(--dashboard-positive-bg);">
                                                <strong><i class="la la-money-bill-wave"></i> Orden de Pago:</strong> 
                                                <a href="{{ backpack_url('payment-order/' . $paymentOrder->id . '/show') }}" class="text-primary">
                                                    {{ $paymentOrder->payment_number ?? 'N/A' }}
                                                </a>
                                                <br>
                                                <small class="text-muted">
                                                    Fecha: {{ $paymentOrder->date ? $paymentOrder->date->format('d/m/Y') : 'N/A' }}
                                                    | Monto: ${{ number_format($paymentOrder->total_amount ?? 0, 2) }}
                                                    @php $flowOpCur = strtoupper(trim((string) ($paymentOrder->currency_code ?? ''))); @endphp
                                                    | Moneda: {{ $flowOpCur !== '' ? $flowOpCur : 'ARS' }}
                                                    | Estado:
                                                    @if($flowHasGeneral)
                                                        <span class="process-item-status status-{{ $paymentOrder->dashboard_payment_status_css_suffix }}">{{ $paymentOrder->dashboard_payment_status_label }}</span>
                                                    @else
                                                        {{ $paymentOrder->dashboard_payment_status_label }}
                                                    @endif
                                                    @if($paymentOrder->payment_date)
                                                        | Fecha de pago: {{ $paymentOrder->payment_date->format('d/m/Y') }}
                                                    @endif
                                                    @if($paymentOrder->user)
                                                        | Autorizado por: {{ $paymentOrder->user->name }}
                                                    @endif
                                                </small>
                                            </div>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="flow-timeline-item">
                                        <div class="flow-timeline-content" style="margin-left: 20px;">
                                            <small class="text-muted"><i class="la la-info-circle"></i> No hay Ã³rdenes de pago registradas para esta orden de compra</small>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @else
                            <div class="flow-timeline-item">
                                <div class="flow-timeline-content">
                                    <small class="text-muted">No hay Ã³rdenes de compra generadas aÃºn</small>
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
                No hay procesos para mostrar todavÃ­a. La trazabilidad incluye solicitudes generales con compras asociadas, o solicitudes de compra creadas directamente (sin solicitud general), con su seguimiento hasta Ã³rdenes de compra y recepciones.
            </div>
        </div>
    </div>
    @endif

    @if(isset($isResponsableArea) && $isResponsableArea && isset($stockAlerts) && $stockAlerts->isNotEmpty() && !empty($stockAlertsHtml))
    <!-- Contenedor oculto para el HTML de alertas (usado por SweetAlert) -->
    <div id="stockAlertsHtmlContent" style="display: none !important; visibility: hidden; position: absolute; left: -9999px;">{!! $stockAlertsHtml !!}</div>
    @endif
</div>
@endsection
