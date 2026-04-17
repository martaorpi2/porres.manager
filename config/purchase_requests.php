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

];
