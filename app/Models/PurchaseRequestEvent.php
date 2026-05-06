<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestEvent extends Model
{
    /** Sector de compras (o admin. sistema) solicitó revisión de cotización a la administradora del instituto. */
    public const EVENT_COMPRAS_ADMINISTRATOR_REVIEW_REQUESTED = 'compras_administrator_review_requested';

    /** Administración del instituto registró decisión por ítem; pendiente aprobación por monto (nivel superior). */
    public const EVENT_ADMINISTRATION_INITIAL_REVIEW_PENDING_SUPERIOR = 'administration_initial_review_pending_superior';

    public $timestamps = false;

    protected $fillable = [
        'purchase_request_id',
        'event_type',
        'user_id',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public static function record(PurchaseRequest|int $purchaseRequest, string $eventType, ?int $userId = null, ?array $payload = null): self
    {
        $purchaseRequestId = $purchaseRequest instanceof PurchaseRequest
            ? $purchaseRequest->id
            : $purchaseRequest;

        return self::query()->create([
            'purchase_request_id' => $purchaseRequestId,
            'event_type' => $eventType,
            'user_id' => $userId,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
