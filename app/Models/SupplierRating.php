<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierRating extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $table = 'supplier_ratings';
    
    protected $fillable = [
        'supplier_id',
        'rated_by',
        'purchase_order_id',
        'quality_rating',
        'price_rating',
        'delivery_time_rating',
        'service_rating',
        'overall_rating',
        'comments',
        'evaluation_date',
    ];

    protected $casts = [
        'evaluation_date' => 'date',
        'quality_rating' => 'integer',
        'price_rating' => 'integer',
        'delivery_time_rating' => 'integer',
        'service_rating' => 'integer',
        'overall_rating' => 'integer',
    ];

    /**
     * Get the supplier that was rated.
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the user who rated the supplier.
     */
    public function ratedBy()
    {
        return $this->belongsTo(User::class, 'rated_by');
    }

    /**
     * Get the purchase order related to this rating (if any).
     */
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Calculate the average rating from all criteria.
     */
    public function getAverageRatingAttribute()
    {
        return round((
            $this->quality_rating +
            $this->price_rating +
            $this->delivery_time_rating +
            $this->service_rating +
            $this->overall_rating
        ) / 5, 2);
    }

    /**
     * Get rating label for display.
     */
    public function getRatingLabelAttribute()
    {
        $avg = $this->average_rating;
        
        if ($avg >= 4.5) {
            return 'Excelente';
        } elseif ($avg >= 3.5) {
            return 'Bueno';
        } elseif ($avg >= 2.5) {
            return 'Regular';
        } elseif ($avg >= 1.5) {
            return 'Deficiente';
        } else {
            return 'Muy Deficiente';
        }
    }
}

