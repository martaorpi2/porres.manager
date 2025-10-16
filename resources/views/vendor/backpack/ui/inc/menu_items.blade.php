{{-- This file is used for menu items by any Backpack v6 theme 
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i> {{ trans('backpack::base.dashboard') }}</a></li>--}}
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i> Inicio</a></li>

<x-backpack::menu-dropdown title="Proveedores" icon="la la-truck" trigger="click">
    <x-backpack::menu-dropdown-item title="Listado" :link="backpack_url('supplier')" />
    <x-backpack::menu-dropdown-item title="Rubros" :link="backpack_url('suppliers-heading')" />
    <x-backpack::menu-dropdown-item title="Sectores" :link="backpack_url('sector')" />
</x-backpack::menu-dropdown>

<x-backpack::menu-dropdown title="Inventario" icon="la la-boxes" trigger="click">
    <x-backpack::menu-dropdown-item title="Productos" :link="backpack_url('product')" />
    <x-backpack::menu-dropdown-item title="Categorías" :link="backpack_url('category')" />
    <x-backpack::menu-dropdown-item title="Ubicaciones" :link="backpack_url('location')" />
    <x-backpack::menu-item title="Stock" :link="backpack_url('stock-level')" />
    <x-backpack::menu-item title="Movimientos" :link="backpack_url('inventory-movement')" />
</x-backpack::menu-dropdown>

<x-backpack::menu-dropdown title="Solicitudes" icon="la la-file-alt" trigger="click">
    <x-backpack::menu-dropdown-item title="Solicitudes Generales" :link="backpack_url('general-request')" />
    <x-backpack::menu-dropdown-item title="Solicitudes de Compra" :link="backpack_url('purchase-request')" />
    <x-backpack::menu-dropdown-item title="Áreas de Responsabilidad" :link="backpack_url('responsibility-area')" />
</x-backpack::menu-dropdown>

<x-backpack::menu-dropdown title="Cotizaciones" icon="la la-calculator" trigger="click">
    <x-backpack::menu-item title="Listado" :link="backpack_url('market-rate')" />
    <x-backpack::menu-item title="Detalle" :link="backpack_url('quote-detail')" />
</x-backpack::menu-dropdown>

<x-backpack::menu-item title="Ordenes de Compra" icon="la la-shopping-cart" :link="backpack_url('purchase-order')" />
<x-backpack::menu-item title="Ordenes de Pago" icon="la la-money-bill-wave" :link="backpack_url('payment-order')" />

<x-backpack::menu-item title="Recepciones" icon="la la-truck-loading" :link="backpack_url('reception')" />
<x-backpack::menu-item title="Devoluciones" icon="la la-undo-alt" :link="backpack_url('devolution')" />

@push('after_scripts')
<script>
$(document).ready(function() {
    console.log('Script cargado para CoreUI v2 dropdowns...');
    
    // Manejar dropdowns de CoreUI v2
    $('.nav-item.nav-dropdown').each(function() {
        const $dropdown = $(this);
        const $link = $dropdown.find('.nav-link');
        const $menu = $dropdown.find('.nav-dropdown-items');
        
        console.log('Encontrado dropdown CoreUI:', $dropdown[0]);
        
        // Hacer que solo el enlace principal sea clickeable para abrir/cerrar
        $link.on('click', function(e) {
            // Solo prevenir el comportamiento si no es un enlace a una página específica
            if ($(this).attr('href') === '#' || !$(this).attr('href')) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Click detectado en dropdown CoreUI');
                
                // Cerrar otros dropdowns
                $('.nav-item.nav-dropdown').not($dropdown).removeClass('open');
                
                // Alternar el dropdown actual
                $dropdown.toggleClass('open');
                
                // Actualizar aria-expanded
                const isExpanded = $dropdown.hasClass('open');
                $link.attr('aria-expanded', isExpanded);
                
                console.log('Dropdown estado:', $dropdown.hasClass('open') ? 'abierto' : 'cerrado');
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
    
    // Permitir que los elementos del menú funcionen normalmente
    $('.nav-dropdown-items a').on('click', function(e) {
        // No prevenir el comportamiento - permitir navegación normal
        console.log('Navegando a:', $(this).attr('href'));
    });
});
</script>
@endpush
