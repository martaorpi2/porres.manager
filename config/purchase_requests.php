<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Correo de respaldo para notificaciones de solicitudes de compra
    |--------------------------------------------------------------------------
    |
    | Los avisos se envían a los usuarios del sistema según su rol (guard backpack).
    | Si no hay ningún usuario con el rol correspondiente o sin email válido, se usa
    | esta dirección. Configurar con PURCHASE_REQUEST_NOTIFICATION_EMAIL en .env.
    |
    */

    'notification_email' => env('PURCHASE_REQUEST_NOTIFICATION_EMAIL', 'morpi@ismp.edu.ar'),

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
