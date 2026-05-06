<?php

namespace App\Services;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestEvent;
use Illuminate\Support\Facades\Log;

class PurchaseRequestReminderService
{
    public const CONTEXT_DIRECT_PURCHASE_AUTH = 'direct_purchase_auth';

    public const CONTEXT_ADMIN_QUOTATION_REVIEW = 'admin_quotation_review';

    public const CONTEXT_SUPERIOR_QUOTATION_APPROVAL = 'superior_quotation_approval';

    /** Compras: solicitud en Pendiente o En proceso (misma fase operativa). */
    public const CONTEXT_COMPRAS_PIPELINE = 'compras_pipeline';

    /** Compras: solicitud aprobada y aún sin orden(es) de compra. */
    public const CONTEXT_COMPRAS_POST_APPROVAL = 'compras_post_approval';

    /**
     * Identifica si hay un actor al que corresponde recordar acción, o null si no aplica.
     */
    public static function resolveReminderContext(PurchaseRequest $purchaseRequest): ?string
    {
        $purchaseRequest->loadMissing(['purchaseRequestEvents', 'details', 'marketRates']);

        if (in_array($purchaseRequest->status, ['Completada', 'Rechazada'], true)) {
            return null;
        }

        if (self::isDirectPurchaseAuthorizationPending($purchaseRequest)) {
            return self::CONTEXT_DIRECT_PURCHASE_AUTH;
        }

        if (in_array($purchaseRequest->status, ['Pendiente', 'En Proceso'], true)) {
            if (
                ! $purchaseRequest->is_direct_purchase
                && $purchaseRequest->hasQuotationSelectionResolved()
            ) {
                $comprasAskedAdmin = $purchaseRequest->purchaseRequestEvents->contains(
                    fn ($e) => $e->event_type === PurchaseRequestEvent::EVENT_COMPRAS_ADMINISTRATOR_REVIEW_REQUESTED
                );

                if ($comprasAskedAdmin && $purchaseRequest->admin_quotation_reviewed_at === null) {
                    return self::CONTEXT_ADMIN_QUOTATION_REVIEW;
                }

                if (
                    $purchaseRequest->requires_admin_approval
                    && $comprasAskedAdmin
                    && $purchaseRequest->admin_quotation_reviewed_at !== null
                    && PurchaseRequestNotificationService::isAwaitingSuperiorQuotationApproval($purchaseRequest)
                ) {
                    return self::CONTEXT_SUPERIOR_QUOTATION_APPROVAL;
                }

                if ($purchaseRequest->requires_admin_approval && ! $comprasAskedAdmin) {
                    return self::CONTEXT_COMPRAS_PIPELINE;
                }
            }

            return self::CONTEXT_COMPRAS_PIPELINE;
        }

        if ($purchaseRequest->status === 'Aprobada') {
            $orderCount = $purchaseRequest->purchase_orders_count ?? $purchaseRequest->purchaseOrders()->count();
            if ((int) $orderCount === 0) {
                return self::CONTEXT_COMPRAS_POST_APPROVAL;
            }
        }

        return null;
    }

    public static function isDirectPurchaseAuthorizationPending(PurchaseRequest $purchaseRequest): bool
    {
        if (! $purchaseRequest->is_direct_purchase) {
            return false;
        }
        if (! $purchaseRequest->direct_purchase_authorization_requested) {
            return false;
        }
        if (! empty($purchaseRequest->direct_purchase_authorized_by)) {
            return false;
        }
        if ((bool) $purchaseRequest->direct_purchase_authorization_rejected) {
            return false;
        }

        return true;
    }

    public static function sendReminderForContext(PurchaseRequest $purchaseRequest, string $context): bool
    {
        return match ($context) {
            self::CONTEXT_DIRECT_PURCHASE_AUTH => PurchaseRequestNotificationService::notifyAutomaticReminderDirectPurchaseAuthorization($purchaseRequest),
            self::CONTEXT_ADMIN_QUOTATION_REVIEW => PurchaseRequestNotificationService::notifyAutomaticReminderAdministratorQuotation($purchaseRequest),
            self::CONTEXT_SUPERIOR_QUOTATION_APPROVAL => PurchaseRequestNotificationService::notifyAutomaticReminderSuperiorQuotation($purchaseRequest),
            self::CONTEXT_COMPRAS_PIPELINE, self::CONTEXT_COMPRAS_POST_APPROVAL => PurchaseRequestNotificationService::notifyAutomaticReminderCompras($purchaseRequest),
            default => tap(false, function () use ($purchaseRequest, $context) {
                Log::warning('purchase_request.auto_reminder.unknown_context', [
                    'purchase_request_id' => $purchaseRequest->id,
                    'context' => $context,
                ]);
            }),
        };
    }

    public static function reminderIntervalDays(): int
    {
        $n = (int) config('purchase_requests.reminder_interval_days', 5);

        return max(1, $n);
    }
}
