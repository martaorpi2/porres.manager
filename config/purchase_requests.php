<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Correo único para notificaciones de solicitudes de compra
    |--------------------------------------------------------------------------
    |
    | Por ahora todos los avisos (compras, aprobaciones, nivel superior, etc.)
    | se envían a esta dirección. Cambiar con PURCHASE_REQUEST_NOTIFICATION_EMAIL en .env.
    |
    */

    'notification_email' => env('PURCHASE_REQUEST_NOTIFICATION_EMAIL', 'morpi@ismp.edu.ar'),

];
