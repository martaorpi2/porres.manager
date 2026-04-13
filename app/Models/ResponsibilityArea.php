<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResponsibilityArea extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'responsible_user_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the user responsible for this area.
     */
    public function responsibleUser()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * Get the purchase requests for this area.
     */
    public function purchaseRequests()
    {
        return $this->hasMany(PurchaseRequest::class);
    }

    /**
     * Letra usada en el número de orden de compra (OC-{letra}-…).
     */
    public function purchaseOrderLetter(): string
    {
        return match ($this->name) {
            'Mantenimiento' => 'M',
            'Insumos Generales' => 'G',
            'Insumos de Salud' => 'S',
            'Informática' => 'I',
            default => strtoupper(mb_substr($this->name ?: 'X', 0, 1)),
        };
    }
}
