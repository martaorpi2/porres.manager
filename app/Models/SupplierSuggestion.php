<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierSuggestion extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $fillable = [
        'purchase_request_id',
        'supplier_id',
        'suggested_by',
        'justification',
    ];

    /**
     * Get the purchase request for this suggestion.
     */
    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    /**
     * Get the supplier for this suggestion.
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the user who made this suggestion.
     */
    public function suggestedBy()
    {
        return $this->belongsTo(User::class, 'suggested_by');
    }
}

