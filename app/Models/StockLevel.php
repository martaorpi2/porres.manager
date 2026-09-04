<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockLevel extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $fillable = [
        'product_id',
        'location_id',
        'quantity',
        'date',
        'document_kind',
        'supplier_invoice_id',
        'remito_id',
        'last_updated_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'date' => 'date',
    ];

    /**
     * Get the product that owns the stock level.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the location that owns the stock level.
     */
    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the user who last updated the stock level.
     */
    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    public function supplierInvoice()
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function remito()
    {
        return $this->belongsTo(Remito::class);
    }

    public function getDocumentLabelAttribute(): string
    {
        if ($this->document_kind === 'factura') {
            return 'Factura: '.($this->supplierInvoice?->identifying_label ?? '—');
        }

        if ($this->document_kind === 'remito') {
            return 'Remito: '.($this->remito?->identifying_label ?? '—');
        }

        return '—';
    }
}
