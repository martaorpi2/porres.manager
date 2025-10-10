{{-- This file is used for menu items by any Backpack v6 theme 
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i> {{ trans('backpack::base.dashboard') }}</a></li>--}}
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i> Inicio</a></li>

{{--<x-backpack::menu-item title="Pacientes" icon="la la-question" :link="backpack_url('patient')" />--}}

<x-backpack::menu-dropdown title="Proveedores" icon="la la-truck">
    <x-backpack::menu-dropdown-item title="Listado" :link="backpack_url('supplier')" />
    <x-backpack::menu-dropdown-item title="Rubros" :link="backpack_url('suppliers-heading')" />
    <x-backpack::menu-dropdown-item title="Sectores" :link="backpack_url('sector')" />
</x-backpack::menu-dropdown>

<x-backpack::menu-dropdown title="Productos/Insumos" icon="la la-truck">
    <x-backpack::menu-dropdown-item title="Listado" :link="backpack_url('product')" />
    <x-backpack::menu-dropdown-item title="Categorías" :link="backpack_url('category')" />
    <x-backpack::menu-dropdown-item title="Ubicaciones" :link="backpack_url('location')" />
</x-backpack::menu-dropdown>
<x-backpack::menu-item title="Ordenes de Compra" icon="la la-question" :link="backpack_url('purchase-order')" />
<x-backpack::menu-item title="Ordenes de Pago" icon="la la-question" :link="backpack_url('payment-order')" />

@push('after_scripts')
<script>
$(document).ready(function() {
    console.log('Script cargado, buscando dropdowns...');
    
    // Encontrar todos los dropdowns y hacer que todo el enlace sea clickeable
    $('.nav-item.dropdown').each(function() {
        const $dropdown = $(this);
        const $link = $dropdown.find('.nav-link');
        const $menu = $dropdown.find('.dropdown-menu');
        
        console.log('Encontrado dropdown:', $dropdown[0]);
        console.log('Link:', $link[0]);
        console.log('Menu:', $menu[0]);
        
        // Remover todos los atributos de Bootstrap que puedan interferir
        $link.removeAttr('data-bs-toggle data-toggle role aria-expanded');
        
        // Hacer que todo el enlace sea clickeable
        $link.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Click detectado en dropdown');
            
            // Cerrar otros dropdowns
            $('.nav-item.dropdown').not($dropdown).removeClass('show');
            $('.dropdown-menu').not($menu).removeClass('show');
            
            // Alternar el dropdown actual
            $dropdown.toggleClass('show');
            $menu.toggleClass('show');
            
            console.log('Dropdown estado:', $dropdown.hasClass('show') ? 'abierto' : 'cerrado');
        });
    });
    
    // Cerrar dropdowns al hacer click fuera
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.nav-item.dropdown').length) {
            $('.nav-item.dropdown').removeClass('show');
            $('.dropdown-menu').removeClass('show');
        }
    });
    
    // También manejar clic en el texto específicamente
    $('.nav-item.dropdown .nav-link span').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $link = $(this).closest('.nav-link');
        $link.trigger('click');
    });
});
</script>
@endpush
