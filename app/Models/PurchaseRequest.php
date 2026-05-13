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
        'created_by',
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
        'admin_quotation_reviewed_at',
        'admin_quotation_reviewed_by',
        'admin_quotation_review_justification',
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
        'auto_reminder_context_key',
        'auto_reminder_context_started_at',
        'auto_reminder_last_sent_at',
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
        'admin_quotation_reviewed_at' => 'datetime',
        'auto_reminder_context_started_at' => 'datetime',
        'auto_reminder_last_sent_at' => 'datetime',
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
     * Usuario que registró la solicitud en el sistema (puede diferir del solicitante nominal).
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Puede gestionar la solicitud como quien la cargó: created_by o, en datos previos a esa columna, el solicitante nominal.
     */
    public function isActingAsCreatingUser(int $userId): bool
    {
        if ($this->created_by !== null) {
            return (int) $this->created_by === $userId;
        }

        return (int) $this->requesting_user_id === $userId;
    }

    /**
     * Get the user who approved this request.
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function adminQuotationReviewedBy()
    {
        return $this->belongsTo(User::class, 'admin_quotation_reviewed_by');
    }

    /**
     * Indica si ya existe aprobación registrada (aprobado por / fecha, estado global o compra directa autorizada),
     * independientemente de inconsistencias puntuales entre status y esos campos.
     */
    public function hasAdministrativeApprovalRecorded(): bool
    {
        if ($this->status === 'Rechazada') {
            return false;
        }
        if (in_array($this->status, ['Aprobada', 'Completada'], true)) {
            return true;
        }
        if (! empty($this->approved_by) && $this->approved_date) {
            return true;
        }
        if ($this->is_direct_purchase
            && ! empty($this->direct_purchase_authorized_by)
            && $this->direct_purchase_authorized_at
            && ! (bool) $this->direct_purchase_authorization_rejected) {
            return true;
        }

        return false;
    }

    /**
     * Get the details for this purchase request.
     */
    public function details()
    {
        return $this->hasMany(PurchaseRequestDetail::class);
    }

    /**
     * Trazabilidad de hitos (solicitud de revisión a administración, revisión inicial con escalamiento, etc.).
     */
    public function purchaseRequestEvents()
    {
        return $this->hasMany(PurchaseRequestEvent::class)->orderByDesc('created_at');
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
        $prefix = 'SC-'.$year.'-';

        $last = static::query()
            ->where('request_number', 'like', $prefix.'%')
            ->orderByDesc('request_number')
            ->value('request_number');

        $nextSequence = 1;
        if ($last) {
            $parts = explode('-', $last);
            $suffix = end($parts);
            $seq = (int) ltrim($suffix, '0');
            $nextSequence = $seq + 1;
        }

        return $prefix.str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
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
        if (! $user) {
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
     * Cada ítem de la solicitud tiene cotización aplicable (línea en la cotización elegida).
     * Con dos o más cotizaciones cargadas exige asignación por producto en cada detalle.
     */
    public function hasQuotationsAssignedToAllRequestProducts(): bool
    {
        $this->loadMissing(['details', 'marketRates.quoteDetails']);

        if ($this->details->isEmpty() || $this->marketRates->isEmpty()) {
            return false;
        }

        $rateCoversProduct = static function ($marketRate, int $productId): bool {
            if (! $marketRate || $productId <= 0) {
                return false;
            }
            $marketRate->loadMissing('quoteDetails');

            return $marketRate->quoteDetails->contains('product_id', $productId);
        };

        if ($this->marketRates->count() >= 2) {
            return $this->details->every(function ($detail) use ($rateCoversProduct) {
                $productId = (int) ($detail->product_id ?? 0);
                $rateId = (int) ($detail->selected_market_rate_id ?? 0);
                if ($productId <= 0 || $rateId <= 0) {
                    return false;
                }
                $mr = $this->marketRates->firstWhere('id', $rateId);

                return $mr && $rateCoversProduct($mr, $productId);
            });
        }

        if (! empty($this->selected_market_rate_id)) {
            $mr = $this->marketRates->firstWhere('id', (int) $this->selected_market_rate_id);
            if (! $mr) {
                return false;
            }

            return $this->details->every(function ($detail) use ($mr, $rateCoversProduct) {
                $productId = (int) ($detail->product_id ?? 0);

                return $productId > 0 && $rateCoversProduct($mr, $productId);
            });
        }

        return $this->details->every(function ($detail) use ($rateCoversProduct) {
            $productId = (int) ($detail->product_id ?? 0);
            $rateId = (int) ($detail->selected_market_rate_id ?? 0);
            if ($productId <= 0 || $rateId <= 0) {
                return false;
            }
            $mr = $this->marketRates->firstWhere('id', $rateId);

            return $mr && $rateCoversProduct($mr, $productId);
        });
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

    /**
     * Autorización por ítem (Fase A): al menos una línea rechazada para compra.
     */
    public function hasRejectedLineAuthorizations(): bool
    {
        return $this->details()->where('line_authorization_status', PurchaseRequestDetail::LINE_AUTH_REJECTED)->exists();
    }

    /**
     * Autorización por ítem (Fase A): al menos una línea aprobada para compra.
     */
    public function hasApprovedLineAuthorizations(): bool
    {
        return $this->details()->where('line_authorization_status', PurchaseRequestDetail::LINE_AUTH_APPROVED)->exists();
    }

    /**
     * Tras observaciones del nivel superior: la administración puede reabrir la solicitud
     * para ajustar cotización/asignación y disparar un nuevo circuito de autorización por ítem.
     * Requiere solicitud aprobada, cotización resuelta, sin OC y al menos un ítem no autorizado.
     */
    public function canReopenForSuperiorAuthorizationAfterRevision(): bool
    {
        if ($this->status !== 'Aprobada' || $this->is_direct_purchase) {
            return false;
        }
        if ($this->purchaseOrders()->exists()) {
            return false;
        }
        if (! $this->hasQuotationSelectionResolved()) {
            return false;
        }

        return $this->hasRejectedLineAuthorizations();
    }
}
