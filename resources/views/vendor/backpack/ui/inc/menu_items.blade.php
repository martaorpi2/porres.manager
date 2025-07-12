{{-- This file is used for menu items by any Backpack v6 theme 
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i> {{ trans('backpack::base.dashboard') }}</a></li>--}}
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i> Inicio</a></li>

<x-backpack::menu-item title="Pacientes" icon="la la-question" :link="backpack_url('patient')" />
<x-backpack::menu-item title="Médicos" icon="la la-question" :link="backpack_url('doctor')" />
<x-backpack::menu-item title="Ordenes de Analisis" icon="la la-question" :link="backpack_url('analysis-order')" />
<x-backpack::menu-item title="Muestras" icon="la la-question" :link="backpack_url('sample')" />
<x-backpack::menu-item title="Resultados" icon="la la-question" :link="backpack_url('result')" />
<x-backpack::menu-item title="Determinaciones" icon="la la-question" :link="backpack_url('determination')" />
<x-backpack::menu-item title="Obras Sociales" icon="la la-question" :link="backpack_url('social-work')" />