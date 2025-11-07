<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $fillable = [
        'reception_id',
        'general_request_id',
        'delivery_date',
        'delivered_by',
        'received_by',
        'observations',
        'status',
    ];

    protected $casts = [
        'delivery_date' => 'date',
    ];

    /**
     * Get the reception for this delivery.
     */
    public function reception()
    {
        return $this->belongsTo(\App\Models\Reception::class, 'reception_id');
    }
    /**
     * Get the general request for this delivery.
     */
    public function generalRequest()
    {
        return $this->belongsTo(\App\Models\GeneralRequest::class);
    }

    /**
     * Get the user who delivered (responsible of the deposit).
     */
    public function deliveredBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'delivered_by');
    }

    /**
     * Get the user who received (who made the general request).
     */
    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Get the details for this delivery.
     */
    public function details()
    {
        return $this->hasMany(DeliveryDetail::class);
    }

    /**
     * Get the delivery number attribute.
     */
    public function getNumberAttribute()
    {
        return 'ENT-' . $this->id;
    }
}
