@php
    $actsAsCompras = $adminInstitucionInbox['acts_as_compras'] ?? false;
    $workInbox = $adminInstitucionInbox ?? null;
    $workInboxSectionId = 'admin-inbox-section';
    $workInboxHeaderTitle = 'Su bandeja de trabajo';
    $workInboxHeaderSubtitle = $actsAsCompras
        ? 'No hay usuarios con rol <strong>responsable de compras</strong>: usted debe cubrir autorizaciones, cotizaciones y seguimiento del circuito.'
        : 'Accesos directos a lo que requiere su intervención como administración del instituto.';
@endphp
@include('admin.dashboard.inc.work_inbox')
