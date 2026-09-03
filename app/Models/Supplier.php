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

    /**
     * Get all market rates for this supplier.
     */
    public function marketRates()
    {
        return $this->hasMany(\App\Models\MarketRate::class);
    }

    /**
     * Get all supplier suggestions for this supplier.
     */
    public function supplierSuggestions()
    {
        return $this->hasMany(\App\Models\SupplierSuggestion::class);
    }

    public function invoices()
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    /**
     * Proveedores visibles en Backpack para el usuario (misma regla que el listado de proveedores).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Supplier>  $query
     */
    public function scopeVisibleForBackpackUser($query, $user)
    {
        if (! $user || ! $user->hasResponsableAreaOrInstituteAuthorityRole()) {
            return $query;
        }

        $userAreas = ResponsibilityArea::where('responsible_user_id', $user->id)->get();
        if ($userAreas->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        $areaRubroMap = [
            'Informática' => ['Tecnología', 'Plataforma de e-commerce', 'Plataforma e-commerce'],
            'Salud' => ['Salud'],
            'Insumos de Salud' => ['Salud'],
            'Mantenimiento' => ['Herramientas'],
            'Insumos Generales' => ['Oficina', 'Insumos Generales'],
        ];

        $allowedRubroNames = collect();
        foreach ($userAreas as $area) {
            $areaName = $area->name;
            if (isset($areaRubroMap[$areaName])) {
                $allowedRubroNames = $allowedRubroNames->merge($areaRubroMap[$areaName]);
            }
        }

        $allowedRubroIds = SuppliersHeading::whereIn('name', $allowedRubroNames->unique())->pluck('id');
        if ($allowedRubroIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('supplier_heading_id', $allowedRubroIds);
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
