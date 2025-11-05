<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use CrudTrait;
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'suppliers';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    // protected $fillable = [];
    // protected $hidden = [];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function sectors()
    {
        return $this->belongsToMany(\App\Models\Sector::class, 'suppliers_sectors', 'supplier_id', 'sector_id');
    }

    public function heading()
    {
        return $this->belongsTo(\App\Models\SuppliersHeading::class, 'supplier_heading_id');
    }

    /**
     * Get all ratings for this supplier.
     */
    public function ratings()
    {
        return $this->hasMany(\App\Models\SupplierRating::class);
    }

    /**
     * Get the average rating for this supplier.
     */
    public function getAverageRatingAttribute()
    {
        $ratings = $this->ratings;
        if ($ratings->isEmpty()) {
            return 0;
        }
        
        $total = $ratings->sum(function($rating) {
            return $rating->average_rating;
        });
        
        return round($total / $ratings->count(), 2);
    }

    /**
     * Get the total number of ratings for this supplier.
     */
    public function getTotalRatingsAttribute()
    {
        return $this->ratings()->count();
    }
    /*
     * |--------------------------------------------------------------------------
     * | SCOPES
     * |--------------------------------------------------------------------------
     */

    /*
     * |--------------------------------------------------------------------------
     * | ACCESSORS
     * |--------------------------------------------------------------------------
     */

    /*
     * |--------------------------------------------------------------------------
     * | MUTATORS
     * |--------------------------------------------------------------------------
     */
}
