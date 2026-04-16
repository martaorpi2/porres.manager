<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpDetail extends Model
{
    protected $table = 'op_details';

    protected $fillable = [
        'payment_order_id',
        'concept',
        'amount',
        'method_payment',
        'expiration_date',
        'actual_payment_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expiration_date' => 'date',
        'actual_payment_date' => 'date',
    ];

    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class, 'payment_order_id');
    }

    public static function conceptLabel(string $concept): string
    {
        return match ($concept) {
            'advance' => 'Anticipo',
            'partiality' => 'Parcialidad',
            'residue' => 'Saldo',
            default => $concept,
        };
    }
}
