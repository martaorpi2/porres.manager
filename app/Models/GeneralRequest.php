<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class GeneralRequest extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $fillable = [
        'number',
        'created_by',
        'requesting_user_id',
        'area_id',
        'title',
        'description',
        'priority',
        'attachments',
        'status',
        'is_converted',
        'analyzed_by',
        'analyzed_at',
        'analysis_status',
        'analysis_notes',
        'rejected_reason',
    ];

    protected $casts = [
        'attachments' => 'array',
        'is_converted' => 'boolean',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Usuario que solicita la mercadería (cuando compras registra en nombre de otra persona).
     */
    public function requestingUser()
    {
        return $this->belongsTo(User::class, 'requesting_user_id');
    }

    /**
     * ID del usuario considerado solicitante para flujos (entrega, conversión a compra, listados).
     */
    public function solicitingUserId(): int
    {
        return (int) ($this->requesting_user_id ?? $this->created_by);
    }

    /**
     * El usuario es quien cargó la solicitud o el solicitante nominado.
     */
    public function isCreatedByOrNominatedRequester(?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }
        if ((int) $this->created_by === (int) $userId) {
            return true;
        }
        if ($this->requesting_user_id === null) {
            return false;
        }

        return (int) $this->requesting_user_id === (int) $userId;
    }

    public function area()
    {
        return $this->belongsTo(ResponsibilityArea::class, 'area_id');
    }

    public function purchaseRequests()
    {
        return $this->hasMany(PurchaseRequest::class, 'converted_from_general_request_id');
    }

    /**
     * Get the details for this general request.
     */
    public function details()
    {
        return $this->hasMany(GeneralRequestDetail::class);
    }

    /**
     * Get the deliveries for this general request.
     */
    public function deliveries()
    {
        return $this->hasMany(Delivery::class, 'general_request_id');
    }

    public static function generateNextNumber(): string
    {
        $year = now()->year;
        $prefix = 'SG-' . $year . '-';

        $last = static::query()
            ->where('number', 'like', $prefix . '%')
            ->orderByDesc('number')
            ->value('number');

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
     * Get the count of details for this general request.
     */
    public function getDetailsCountAttribute()
    {
        return $this->details()->count() . ' productos';
    }

    /**
     * Get the user who analyzed this request.
     */
    public function analyzedBy()
    {
        return $this->belongsTo(User::class, 'analyzed_by');
    }

    /**
     * Scope a query to only include requests pending analysis.
     */
    public function scopePendingAnalysis($query)
    {
        if (! Schema::hasColumn($this->getTable(), 'analysis_status')) {
            return $query->where('status', 'pendiente_analisis');
        }

        return $query->where('analysis_status', 'pendiente')
            ->where('status', 'pendiente_analisis');
    }

    /**
     * Scope a query to only include requests approved by analyst.
     */
    public function scopeApprovedByAnalyst($query)
    {
        if (! Schema::hasColumn($this->getTable(), 'analysis_status')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('analysis_status', 'aprobada')
            ->whereNotNull('analyzed_by');
    }

    /**
     * Get the age of the request in human readable format.
     */
    public function getAgeAttribute()
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Get the age of the request in days.
     */
    public function getAgeInDaysAttribute()
    {
        return $this->created_at->diffInDays(now());
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
}
