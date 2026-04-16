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
        'requires_admin_approval',
        'approval_justification',
        'is_direct_purchase',
        'direct_purchase_justification',
        'direct_purchase_supplier_id',
        'direct_purchase_authorization_requested',
        'direct_purchase_authorization_requested_by',
        'direct_purchase_authorization_requested_at',
        'direct_purchase_authorized_by',
        'direct_purchase_authorized_at',
        'direct_purchase_authorization_rejected',
        'direct_purchase_authorization_rejection_reason',
        'purchase_type',
    ];

    protected $casts = [
        'request_date' => 'date',
        'approved_date' => 'date',
        'total_amount' => 'decimal:2',
        'attachments' => 'array',
        'selected_at' => 'datetime',
        'requires_admin_approval' => 'boolean',
        'is_direct_purchase' => 'boolean',
        'direct_purchase_authorization_requested' => 'boolean',
        'direct_purchase_authorization_requested_at' => 'datetime',
        'direct_purchase_authorized_at' => 'datetime',
        'direct_purchase_authorization_rejected' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(function (PurchaseRequest $purchaseRequest) {
            if ($purchaseRequest->deletionIsForbidden()) {
                abort(403, 'No se puede eliminar una solicitud de compra que ya fue aprobada, está en proceso o está completada.');
            }
        });
    }

    /**
     * Estados en los que la solicitud no puede eliminarse (aprobada o ya en flujo posterior).
     *
     * @return list<string>
     */
    public static function statusesThatPreventDeletion(): array
    {
        return ['Aprobada', 'En Proceso', 'Completada'];
    }

    public function deletionIsForbidden(): bool
    {
        return in_array($this->status, self::statusesThatPreventDeletion(), true);
    }

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
     * Get the supplier for direct purchase.
     */
    public function directPurchaseSupplier()
    {
        return $this->belongsTo(Supplier::class, 'direct_purchase_supplier_id');
    }

    /**
     * Get the user who requested authorization for direct purchase.
     */
    public function directPurchaseAuthorizationRequestedBy()
    {
        return $this->belongsTo(User::class, 'direct_purchase_authorization_requested_by');
    }

    /**
     * Get the user who authorized the direct purchase.
     */
    public function directPurchaseAuthorizedBy()
    {
        return $this->belongsTo(User::class, 'direct_purchase_authorized_by');
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

    /**
     * Check if this purchase request requires admin approval
     */
    public function requiresAdminApproval()
    {
        $comprasLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_responsable_compras');
        return $this->total_amount > $comprasLimit;
    }

    /**
     * Check if a user can approve this purchase request
     */
    public function canBeApprovedBy($user)
    {
        if (!$user) {
            return false;
        }

        // Si requiere aprobación de administrador, el admin del instituto, apoderado o representante legal pueden aprobar
        // pero solo si no supera su límite de monto
        if ($this->requires_admin_approval) {
            if ($user->hasRole('role_admin_institucion', 'backpack')) {
                $adminLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_admin_institucion');
                return $this->total_amount <= $adminLimit;
            }
            if ($user->hasRole('role_apoderado', 'backpack')) {
                $apoderadoLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_apoderado');
                return $this->total_amount <= $apoderadoLimit;
            }
            if ($user->hasRole('role_representante_legal', 'backpack')) {
                $representanteLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_representante_legal');
                return $this->total_amount <= $representanteLimit;
            }
            return false;
        }

        // Si no requiere aprobación de administrador, el responsable de compras puede aprobar
        if ($user->hasRole('role_responsable_compras', 'backpack')) {
            $comprasLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_responsable_compras');
            return $this->total_amount <= $comprasLimit;
        }

        // El administrador del instituto puede aprobar solo si no supera su límite
        if ($user->hasRole('role_admin_institucion', 'backpack')) {
            $adminLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_admin_institucion');
            return $this->total_amount <= $adminLimit;
        }

        // El apoderado puede aprobar solo si no supera su límite
        if ($user->hasRole('role_apoderado', 'backpack')) {
            $apoderadoLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_apoderado');
            return $this->total_amount <= $apoderadoLimit;
        }

        // El representante legal puede aprobar solo si no supera su límite
        if ($user->hasRole('role_representante_legal', 'backpack')) {
            $representanteLimit = \App\Models\PurchaseAuthorizationLimit::getLimitForRole('role_representante_legal');
            return $this->total_amount <= $representanteLimit;
        }

        return false;
    }

    /**
     * Get the age of the request in human readable format.
     */
    public function getAgeAttribute()
    {
        $date = $this->created_at ?? $this->request_date;
        return $date->diffForHumans();
    }

    /**
     * Get the age of the request in days.
     */
    public function getAgeInDaysAttribute()
    {
        $date = $this->created_at ?? $this->request_date;
        return $date->diffInDays(now());
    }

    /**
     * Get the age badge color based on days.
     */
    public function getAgeBadgeColorAttribute()
    {
        $days = $this->age_in_days;
        if ($days < 3) {
            return 'success'; // Verde
        } elseif ($days < 7) {
            return 'warning'; // Amarillo
        } elseif ($days < 15) {
            return 'info'; // Azul/Naranja
        } else {
            return 'danger'; // Rojo
        }
    }

    /**
     * Obtiene los productos de esta solicitud que están cotizados en menos de 3 cotizaciones distintas.
     * Para montos > 60000 cada producto debe aparecer en al menos 3 cotizaciones.
     *
     * @return \Illuminate\Support\Collection<int, Product>
     */
    public function getProductsWithFewerThanThreeQuotations()
    {
        $marketRateIds = $this->marketRates()->pluck('id');
        if ($marketRateIds->isEmpty()) {
            return $this->details()->with('product')->get()->pluck('product')->filter();
        }

        $productIdsWithEnoughQuotations = \App\Models\QuoteDetail::whereIn('market_rate_id', $marketRateIds)
            ->select('product_id')
            ->selectRaw('COUNT(DISTINCT market_rate_id) as quote_count')
            ->groupBy('product_id')
            ->having('quote_count', '>=', 3)
            ->pluck('product_id');

        $allProductIdsInRequest = $this->details()->pluck('product_id')->unique()->values();
        $productIdsWithFewer = $allProductIdsInRequest->diff($productIdsWithEnoughQuotations);

        if ($productIdsWithFewer->isEmpty()) {
            return collect();
        }

        return \App\Models\Product::whereIn('id', $productIdsWithFewer)->get();
    }

    /**
     * Montos mayores a este umbral exigen al menos 3 cotizaciones y cobertura por producto antes de generar OC.
     */
    public static function quotationCoverageThresholdAmount(): float
    {
        return 60000.0;
    }

    /**
     * Cumple la regla de cobertura (cada producto en ≥3 cotizaciones) cuando el monto la exige.
     */
    public function meetsMandatoryQuotationCoveragePerProduct(): bool
    {
        if ((float) $this->total_amount <= self::quotationCoverageThresholdAmount()) {
            return true;
        }

        return $this->getProductsWithFewerThanThreeQuotations()->isEmpty();
    }

    /**
     * Indica si ya hay una cotización elegida (cabecera, bandera en cotización o asignación en todos los ítems).
     */
    public function hasQuotationSelectionResolved(): bool
    {
        if (! empty($this->selected_market_rate_id)) {
            return true;
        }

        if ($this->relationLoaded('marketRates')) {
            if ($this->marketRates->contains(fn ($mr) => (bool) ($mr->is_selected ?? false))) {
                return true;
            }
        } elseif ($this->marketRates()->where('is_selected', true)->exists()) {
            return true;
        }

        $this->loadMissing('details');

        if ($this->details->isEmpty()) {
            return false;
        }

        return $this->details->every(fn ($d) => ! empty($d->selected_market_rate_id));
    }

    /**
     * Solicitudes sin OC, con al menos una cotización cargada, sin selección aplicada
     * (alineado con la lógica de show / generar OC: selected_market_rate_id, is_selected o ítems asignados).
     *
     * Estados: aprobada, en proceso o pendiente (compras puede cargar cotizaciones antes de que cambie el estado).
     *
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public static function queryAwaitingQuoteSelectionWithMinimumQuotations(int $minQuotations = 1): \Illuminate\Database\Eloquent\Builder
    {
        $min = max(1, $minQuotations);

        return static::query()
            ->whereIn('status', ['Aprobada', 'En Proceso', 'Pendiente'])
            ->whereDoesntHave('purchaseOrders')
            ->where(function ($q) {
                $q->where('is_direct_purchase', false)
                    ->orWhereNull('direct_purchase_authorized_by')
                    ->orWhere('direct_purchase_authorization_rejected', true);
            })
            ->whereHas('details')
            ->whereRaw(
                '(select count(*) from market_rates where market_rates.purchase_request_id = purchase_requests.id) >= ?',
                [$min]
            );
    }

    /**
     * @return \Illuminate\Support\Collection<int, static>
     */
    public static function purchaseRequestsAwaitingQuoteSelectionAfterThreeQuotations(): \Illuminate\Support\Collection
    {
        return static::queryAwaitingQuoteSelectionWithMinimumQuotations(1)
            ->with(['marketRates', 'details'])
            ->get()
            ->filter(fn (self $pr) => ! $pr->hasQuotationSelectionResolved())
            ->values();
    }

    public static function purchaseRequestsAwaitingQuoteSelectionAfterThreeQuotationsCount(): int
    {
        return static::purchaseRequestsAwaitingQuoteSelectionAfterThreeQuotations()->count();
    }
}
