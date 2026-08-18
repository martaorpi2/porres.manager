<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Correo de respaldo para notificaciones de solicitudes de compra
    |--------------------------------------------------------------------------
    |
    | Los avisos se envían a los usuarios del sistema según su rol (guard backpack).
    | En pruebas, force_all_to_notification_email redirige todos esos avisos a
    | notification_email. Configurar con PURCHASE_REQUEST_NOTIFICATION_EMAIL en .env.
    |
    */

    'notification_email' => env('PURCHASE_REQUEST_NOTIFICATION_EMAIL', 'morpi@ismp.edu.ar'),

    /*
    | Si es true, los avisos del circuito de compras se envían solo a
    | notification_email (no a los correos de cada usuario/rol).
    */
    'force_all_to_notification_email' => filter_var(
        env('PURCHASE_REQUEST_FORCE_ALL_EMAILS_TO_NOTIFICATION', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
    |--------------------------------------------------------------------------
    | Recordatorios automáticos por correo
    |--------------------------------------------------------------------------
    |
    | El comando programado purchase-requests:send-auto-reminders evalúa solicitudes
    | no finalizadas y envía un correo al actor que corresponda si pasaron al menos
    | N días desde el último cambio de contexto o desde el último recordatorio.
    |
    */

    'reminder_interval_days' => (int) env('PURCHASE_REQUEST_REMINDER_INTERVAL_DAYS', 5),

];
