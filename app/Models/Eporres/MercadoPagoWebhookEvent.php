<?php

namespace App\Models\Eporres;

class MercadoPagoWebhookEvent extends EporresModel
{
    protected $table = 'mercadopago_webhook_events';

    protected $casts = [
        'payload_snapshot' => 'array',
        'transaction_amount' => 'float',
        'date_approved' => 'datetime',
        'date_last_updated' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
