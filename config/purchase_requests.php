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
    | Correo al notificar a compras desde el detalle (responsable de área)
    |--------------------------------------------------------------------------
    |
    | Botón manual en la solicitud: por defecto morpi@ismp.edu.ar. Sobrescribir
    | con PURCHASE_REQUEST_COMPRAS_MANUAL_NOTIFY_EMAIL en .env si cambia.
    |
    */

    'compras_manual_notify_email' => env('PURCHASE_REQUEST_COMPRAS_MANUAL_NOTIFY_EMAIL', 'morpi@ismp.edu.ar'),

];
