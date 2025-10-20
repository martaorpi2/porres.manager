@basset('https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.compat.css')
@basset('https://cdn.jsdelivr.net/npm/noty@3.2.0-beta-deprecated/lib/noty.css')

@basset('https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css')
@basset('https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/fonts/la-regular-400.woff2')
@basset('https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/fonts/la-solid-900.woff2')
@basset('https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/fonts/la-brands-400.woff2')
@basset('https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/fonts/la-regular-400.woff')
@basset('https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/fonts/la-solid-900.woff')
@basset('https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/fonts/la-brands-400.woff')
@basset('https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/fonts/la-regular-400.ttf')
@basset('https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/fonts/la-solid-900.ttf')
@basset('https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/fonts/la-brands-400.ttf')

@basset(base_path('vendor/backpack/crud/src/resources/assets/css/common.css'))

@if (backpack_theme_config('styles') && count(backpack_theme_config('styles')))
    @foreach (backpack_theme_config('styles') as $path)
        @if(is_array($path))
            @basset(...$path)
        @else
            @basset($path)
        @endif
    @endforeach
@endif

@if (backpack_theme_config('mix_styles') && count(backpack_theme_config('mix_styles')))
    @foreach (backpack_theme_config('mix_styles') as $path => $manifest)
        <link rel="stylesheet" type="text/css" href="{{ mix($path, $manifest) }}">
    @endforeach
@endif

@if (backpack_theme_config('vite_styles') && count(backpack_theme_config('vite_styles')))
    @vite(backpack_theme_config('vite_styles'))
@endif

{{-- Custom CSS for ePorres Manager --}}
<style>
/* Override Bootstrap primary color */
:root {
    --bs-primary: #871f1f !important;
    --bs-primary-rgb: 135, 31, 31 !important;
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
