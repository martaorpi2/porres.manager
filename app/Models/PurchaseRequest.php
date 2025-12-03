<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequest extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $fillable = [
        'request_number',
        'request_date',
        'status',
        'priority',
        'justification',
        'observations',
        'responsibility_area_id',
        'requesting_user_id',
        'approved_by',
        'approved_date',
        'total_amount',
        'converted_from_general_request_id',
        'attachments',
        'selected_market_rate_id',
        'selection_justification',
        'selected_by',
        'selected_at',
    ];

    protected $casts = [
        'request_date' => 'date',
        'approved_date' => 'date',
        'total_amount' => 'decimal:2',
        'attachments' => 'array',
        'selected_at' => 'datetime',
    ];

    /**
     * Get the responsibility area for this request.
     */
    public function responsibilityArea()
    {
        return $this->belongsTo(ResponsibilityArea::class);
    }

    /**
     * Get the user who made this request.
     */
    public function requestingUser()
    {
        return $this->belongsTo(User::class, 'requesting_user_id');
    }

    /**
     * Get the user who approved this request.
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the details for this purchase request.
     */
    public function details()
    {
        return $this->hasMany(PurchaseRequestDetail::class);
    }

    /**
     * Get the general request this was converted from.
     */
    public function convertedFromGeneralRequest()
    {
        return $this->belongsTo(GeneralRequest::class, 'converted_from_general_request_id');
    }

    /**
     * Get all market rates for this purchase request.
     */
    public function marketRates()
    {
        return $this->hasMany(MarketRate::class);
    }

    /**
     * Get the selected market rate for this purchase request.
     */
    public function selectedMarketRate()
    {
        return $this->belongsTo(MarketRate::class, 'selected_market_rate_id');
    }

    /**
     * Get the user who selected the market rate.
     */
    public function selectedBy()
    {
        return $this->belongsTo(User::class, 'selected_by');
    }

    /**
     * Get the purchase orders generated from this purchase request.
     */
    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class, 'purchase_request_id');
    }

    /**
     * Get the deliveries for this purchase request.
     */
    public function deliveries()
    {
        return $this->hasMany(Delivery::class, 'purchase_request_id');
    }

    /**
     * Get all supplier suggestions for this purchase request.
     */
    public function supplierSuggestions()
    {
        return $this->hasMany(SupplierSuggestion::class);
    }

    /**
     * Generate the next request number.
     */
    public static function generateNextNumber(): string
    {
        $year = now()->year;
        $prefix = 'SC-' . $year . '-';

        $last = static::query()
            ->where('request_number', 'like', $prefix . '%')
            ->orderByDesc('request_number')
            ->value('request_number');

        $nextSequence = 1;
        if ($last) {
            $parts = explode('-', $last);
            $suffix = end($parts);
            $seq = (int) ltrim($suffix, '0');
            $nextSequence = $seq + 1;
        }

        return $prefix . str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
    }
}
