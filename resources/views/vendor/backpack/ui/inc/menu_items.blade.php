{{-- This file is used for menu items by any Backpack v6 theme --}}
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i> {{ trans('backpack::base.dashboard') }}</a></li>

<x-backpack::menu-item title="Patients" icon="la la-question" :link="backpack_url('patient')" />
<x-backpack::menu-item title="Analysis orders" icon="la la-question" :link="backpack_url('analysis-order')" />
<x-backpack::menu-item title="Determinations" icon="la la-question" :link="backpack_url('determination')" />
<x-backpack::menu-item title="Doctors" icon="la la-question" :link="backpack_url('doctor')" />
<x-backpack::menu-item title="Results" icon="la la-question" :link="backpack_url('result')" />
<x-backpack::menu-item title="Samples" icon="la la-question" :link="backpack_url('sample')" />
<x-backpack::menu-item title="Social works" icon="la la-question" :link="backpack_url('social-work')" />