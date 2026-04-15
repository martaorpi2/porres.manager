<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketRate extends Model
{
    use CrudTrait;
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'market_rates';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    protected $fillable = [
        'supplier_id',
        'purchase_request_id',
        'date',
        'delivery_date',
        'delivery_term',
        'payment_method',
        'validity_term',
        'total_amount',
        'vat_amount',
        'total_amount_with_vat',
        'document_files',
        'reference_links',
        'is_selected',
    ];
    protected $casts = [
        'date' => 'date',
        'delivery_date' => 'date',
        'total_amount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total_amount_with_vat' => 'decimal:2',
        'document_files' => 'array',
        'is_selected' => 'boolean',
    ];
    // protected $hidden = [];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * Lista ordenada de rutas de disco (disco public) para enlaces de descarga.
     * Ignora entradas vacías y desanida un nivel si un elemento es JSON ["ruta"].
     *
     * @param  array<int|string, mixed>|string|null  $raw
     * @return list<string>
     */
    public static function normalizeDocumentFilesToPathList($raw): array
    {
        if ($raw === null) {
            return [];
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $p) {
            if (! is_string($p)) {
                continue;
            }
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            if (str_starts_with($p, '[')) {
                $nested = json_decode($p, true);
                if (is_array($nested)) {
                    foreach ($nested as $n) {
                        if (is_string($n) && trim($n) !== '') {
                            $out[] = trim($n);
                        }
                    }
                }

                continue;
            }
            $out[] = $p;
        }

        return array_values($out);
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
    
    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }
    
    public function quoteDetails()
    {
        return $this->hasMany(QuoteDetail::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */
    
    public function getSelectionStatusAttribute()
    {
        return $this->is_selected ? 'Seleccionada' : 'No seleccionada';
    }
    
    public function getSelectionBadgeClassAttribute()
    {
        return $this->is_selected ? 'bg-success' : 'bg-secondary';
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
