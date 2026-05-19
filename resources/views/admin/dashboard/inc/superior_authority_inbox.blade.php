@php
    $profileLabel = $superiorAuthorityInbox['profile_label'] ?? 'nivel superior';
    $workInbox = $superiorAuthorityInbox ?? null;
    $workInboxSectionId = 'superior-authority-inbox-section';
    $workInboxHeaderTitle = 'Su bandeja de trabajo';
    $workInboxHeaderSubtitle = 'Accesos directos a solicitudes de compra y escalamientos que requieren su intervención como <strong>'.$profileLabel.'</strong>.';
@endphp
@include('admin.dashboard.inc.work_inbox')
