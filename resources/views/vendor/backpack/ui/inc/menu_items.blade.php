{{-- This file is used for menu items by any Backpack v6 theme 
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i> {{ trans('backpack::base.dashboard') }}</a></li>--}}
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i> Inicio</a></li>

@if(backpack_user() && backpack_user()->hasRole('role_personal'))
    {{-- Menú para usuarios con rol role_personal --}}    
    <x-backpack::menu-item title="Solicitudes Generales" icon="la la-file-alt" :link="backpack_url('general-request')" />
    <x-backpack::menu-item title="Entregas" icon="la la-people-carry" :link="backpack_url('delivery')" />
@elseif(backpack_user() && backpack_user()->hasResponsableAreaOrInstituteAuthorityRole())
    {{-- Menú para responsable de área / autoridad del instituto --}}
    <x-backpack::menu-dropdown title="Solicitudes" icon="la la-file-alt" trigger="click">
        <x-backpack::menu-dropdown-item title="Solicitudes Generales" :link="backpack_url('general-request')" />
        <x-backpack::menu-dropdown-item title="Solicitudes de Compra" :link="backpack_url('purchase-request')" />
    </x-backpack::menu-dropdown>
    
    <x-backpack::menu-item title="Productos" icon="la la-cube" :link="backpack_url('product')" />
    <x-backpack::menu-item title="Proveedores" icon="la la-truck" :link="backpack_url('supplier')" />
    <x-backpack::menu-item title="Stock" icon="la la-boxes" :link="backpack_url('stock-level')" />
    <x-backpack::menu-item title="Entregas" icon="la la-people-carry" :link="backpack_url('delivery')" />
    <x-backpack::menu-item title="Recepciones" icon="la la-truck-loading" :link="backpack_url('reception')" />
    <x-backpack::menu-item title="Devoluciones" icon="la la-undo-alt" :link="backpack_url('devolution')" />
@elseif(backpack_user() && backpack_user()->hasRole('role_apoderado', 'backpack'))
    {{-- Menú para usuarios con rol role_apoderado --}}
    <x-backpack::menu-item title="Solicitudes de Compra" icon="la la-shopping-cart" :link="backpack_url('purchase-request')" />
    <x-backpack::menu-item title="Cotizaciones" icon="la la-calculator" :link="backpack_url('market-rate')" />
    <x-backpack::menu-item title="Ordenes de Compra" icon="la la-clipboard-list" :link="backpack_url('purchase-order')" />
    <x-backpack::menu-item title="Ordenes de Pago" icon="la la-money-bill-wave" :link="backpack_url('payment-order')" />
@elseif(backpack_user() && backpack_user()->hasRole('role_representante_legal', 'backpack'))
    {{-- Menú para usuarios con rol role_representante_legal --}}
    <x-backpack::menu-item title="Solicitudes de Compra" icon="la la-shopping-cart" :link="backpack_url('purchase-request')" />
    <x-backpack::menu-item title="Cotizaciones" icon="la la-calculator" :link="backpack_url('market-rate')" />
    <x-backpack::menu-item title="Ordenes de Compra" icon="la la-clipboard-list" :link="backpack_url('purchase-order')" />
    <x-backpack::menu-item title="Ordenes de Pago" icon="la la-money-bill-wave" :link="backpack_url('payment-order')" />
    <x-backpack::menu-dropdown title="Inventario" icon="la la-boxes" trigger="click">
        <x-backpack::menu-dropdown-item title="Productos" :link="backpack_url('product')" />
        <x-backpack::menu-dropdown-item title="Categorías" :link="backpack_url('category')" />
        <x-backpack::menu-dropdown-item title="Ubicaciones" :link="backpack_url('location')" />
        <x-backpack::menu-item title="Stock" :link="backpack_url('stock-level')" />
        <x-backpack::menu-item title="Movimientos" :link="backpack_url('inventory-movement')" />
    </x-backpack::menu-dropdown>
@else
    {{-- Menú completo para otros roles --}}
    <x-backpack::menu-dropdown title="Proveedores" icon="la la-truck" trigger="click">
        <x-backpack::menu-dropdown-item title="Listado" :link="backpack_url('supplier')" />
        <x-backpack::menu-dropdown-item title="Calificaciones" :link="backpack_url('supplier-rating')" />
        <x-backpack::menu-dropdown-item title="Rubros" :link="backpack_url('suppliers-heading')" />
        <x-backpack::menu-dropdown-item title="Cuentas contables" :link="backpack_url('accounting-account')" />
    </x-backpack::menu-dropdown>

    <x-backpack::menu-dropdown title="Inventario" icon="la la-boxes" trigger="click">
        <x-backpack::menu-dropdown-item title="Productos" :link="backpack_url('product')" />
        @unless(backpack_user() && backpack_user()->hasRole('role_admin_institucion', 'backpack'))
            <x-backpack::menu-dropdown-item title="Categorías" :link="backpack_url('category')" />
            <x-backpack::menu-dropdown-item title="Ubicaciones" :link="backpack_url('location')" />
        @endunless
        <x-backpack::menu-item title="Stock" :link="backpack_url('stock-level')" />
        <x-backpack::menu-item title="Movimientos" :link="backpack_url('inventory-movement')" />
    </x-backpack::menu-dropdown>

    <x-backpack::menu-dropdown title="Solicitudes" icon="la la-file-alt" trigger="click">
        <x-backpack::menu-dropdown-item title="Solicitudes Generales" :link="backpack_url('general-request')" />
        <x-backpack::menu-dropdown-item title="Solicitudes de Compra" :link="backpack_url('purchase-request')" />
        {{-- <x-backpack::menu-item title="Asignación de Productos" :link="backpack_url('product-assignment')" /> --}}
        @unless(backpack_user() && backpack_user()->hasRole('role_responsable_compras'))
            <x-backpack::menu-dropdown-item title="Áreas de Responsabilidad" :link="backpack_url('responsibility-area')" />
        @endunless
    </x-backpack::menu-dropdown>

    {{--<x-backpack::menu-dropdown title="Cotizaciones" icon="la la-calculator" trigger="click">
        <x-backpack::menu-item title="Listado" :link="backpack_url('market-rate')" />
        <x-backpack::menu-item title="Detalle" :link="backpack_url('quote-detail')" />
    </x-backpack::menu-dropdown>--}}
    <x-backpack::menu-item title="Cotizaciones" icon="la la-calculator" :link="backpack_url('market-rate')" />

    <x-backpack::menu-item title="Ordenes de Compra" icon="la la-shopping-cart" :link="backpack_url('purchase-order')" />
    <x-backpack::menu-item title="Ordenes de Pago" icon="la la-money-bill-wave" :link="backpack_url('payment-order')" />
    @if(backpack_user() instanceof \App\Models\User && backpack_user()->canManageSupplierInvoices())
        <x-backpack::menu-item title="Facturas proveedor" icon="la la-file-invoice-dollar" :link="backpack_url('supplier-invoice')" />
    @endif

    <x-backpack::menu-item title="Recepciones" icon="la la-truck-loading" :link="backpack_url('reception')" />
    <x-backpack::menu-item title="Devoluciones" icon="la la-undo-alt" :link="backpack_url('devolution')" />
    <x-backpack::menu-item title="Entregas" icon="la la-people-carry" :link="backpack_url('delivery')" />

    @can('admin.usuarios')
    <x-backpack::menu-dropdown title="Usuarios" icon="la la-users" trigger="click">
        <x-backpack::menu-dropdown-item title="Usuarios" :link="backpack_url('user')" />
        <x-backpack::menu-dropdown-item title="Roles" :link="backpack_url('role')" />
        <x-backpack::menu-dropdown-item title="Permisos" :link="backpack_url('permission')" />
    </x-backpack::menu-dropdown>
    @endcan
@endif

@push('after_scripts')
<script>
$(document).ready(function() {
    // Manejar dropdowns de CoreUI v2
    $('.nav-item.nav-dropdown').each(function() {
        const $dropdown = $(this);
        const $link = $dropdown.find('.nav-link');
        
        // Hacer que solo el enlace principal sea clickeable para abrir/cerrar
        $link.on('click', function(e) {
            // Solo prevenir el comportamiento si no es un enlace a una página específica
            if ($(this).attr('href') === '#' || !$(this).attr('href')) {
                e.preventDefault();
                e.stopPropagation();
                
                // Cerrar otros dropdowns
                $('.nav-item.nav-dropdown').not($dropdown).removeClass('open');
                
                // Alternar el dropdown actual
                $dropdown.toggleClass('open');
                
                // Actualizar aria-expanded
                const isExpanded = $dropdown.hasClass('open');
                $link.attr('aria-expanded', isExpanded);
            }
            // Si tiene un href válido, permitir que navegue normalmente
        });
    });
    
    // Cerrar dropdowns al hacer click fuera
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.nav-item.nav-dropdown').length) {
            $('.nav-item.nav-dropdown').removeClass('open');
            $('.nav-item.nav-dropdown .nav-link').attr('aria-expanded', 'false');
        }
    });
    
    // Manejar la activación correcta de elementos dropdown
    $('.dropdown-item').on('click', function(e) {
        // Remover clase active de todos los elementos dropdown en el mismo dropdown
        const $currentDropdown = $(this).closest('.dropdown-menu');
        $currentDropdown.find('.dropdown-item').removeClass('active');
        
        // Agregar clase active solo al elemento clickeado
        $(this).addClass('active');
    });

    // Función para verificar y corregir estados activos al cargar la página
    function fixDropdownActiveStates() {
        // Obtener la URL actual
        const currentUrl = window.location.pathname;
        
        // Remover active de todos los dropdown items
        $('.dropdown-item').removeClass('active');
        
        // Encontrar y activar el elemento que coincide con la URL actual
        $('.dropdown-item').each(function() {
            const $item = $(this);
            const itemHref = $item.attr('href');
            
            if (itemHref && currentUrl.includes(itemHref.replace('/admin/', ''))) {
                $item.addClass('active');
            }
        });
    }

    // Ejecutar al cargar la página
    fixDropdownActiveStates();
    
    // Ejecutar también cuando cambie la URL (para navegación SPA)
    $(window).on('popstate', function() {
        setTimeout(fixDropdownActiveStates, 100);
    });
});
</script>
@endpush
