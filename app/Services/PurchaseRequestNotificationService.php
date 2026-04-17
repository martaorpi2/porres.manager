<?php

namespace App\Services;

use App\Models\MarketRate;
use App\Models\PurchaseRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PurchaseRequestNotificationService
{
    /**
     * Por ahora todas las notificaciones de solicitudes de compra van a esta casilla.
     */
    public static function notificationEmail(): string
    {
        return (string) config('purchase_requests.notification_email', 'morpi@ismp.edu.ar');
    }

    public static function purchaseRequestUrl(PurchaseRequest $purchaseRequest): string
    {
        return route('purchase-request.show', $purchaseRequest->id, absolute: true);
    }

    /**
     * Solicitud creada por responsable de área.
     */
    public static function notifyComprasNewRequestFromArea(PurchaseRequest $purchaseRequest): void
    {
        $url = self::purchaseRequestUrl($purchaseRequest);
        $subject = 'Nueva solicitud de compra '.($purchaseRequest->request_number ?? '#'.$purchaseRequest->id);
        $body = self::htmlBody(
            'Se generó una nueva solicitud de compra.',
            $purchaseRequest,
            $url
        );
        self::sendHtml($subject, $body);
    }

    /**
     * Compras seleccionó cotización(es) y la solicitud queda pendiente de aprobación de nivel superior.
     */
    public static function notifySuperiorsQuotationApprovalNeeded(PurchaseRequest $purchaseRequest): void
    {
        $url = self::purchaseRequestUrl($purchaseRequest);
        $subject = 'Aprobar solicitud de compra (cotización seleccionada) '.($purchaseRequest->request_number ?? '#'.$purchaseRequest->id);
        $body = self::htmlBody(
            'El sector de compras seleccionó cotización(es) y la solicitud requiere su aprobación por monto o autorización.',
            $purchaseRequest,
            $url
        );
        self::sendHtml($subject, $body);
    }

    /**
     * Compras solicitó autorización de compra directa.
     */
    public static function notifySuperiorsDirectPurchaseAuthorizationRequested(PurchaseRequest $purchaseRequest): void
    {
        $url = self::purchaseRequestUrl($purchaseRequest);
        $subject = 'Autorizar compra directa '.($purchaseRequest->request_number ?? '#'.$purchaseRequest->id);
        $body = self::htmlBody(
            'Se solicitó autorización de compra directa. Debe revisar y aprobar en el sistema.',
            $purchaseRequest,
            $url
        );
        self::sendHtml($subject, $body);
    }

    /**
     * Nivel superior aprobó: avisar a compras.
     */
    public static function notifyComprasRequestApprovedBySuperior(PurchaseRequest $purchaseRequest): void
    {
        $url = self::purchaseRequestUrl($purchaseRequest);
        $subject = 'Solicitud de compra aprobada '.($purchaseRequest->request_number ?? '#'.$purchaseRequest->id);
        $body = self::htmlBody(
            'La solicitud de compra fue aprobada por el nivel superior. Puede continuar el proceso en el sistema.',
            $purchaseRequest,
            $url
        );
        self::sendHtml($subject, $body);
    }

    public static function isAwaitingSuperiorQuotationApproval(PurchaseRequest $purchaseRequest): bool
    {
        if ($purchaseRequest->status !== 'Pendiente' || ! $purchaseRequest->requires_admin_approval) {
            return false;
        }
        if ($purchaseRequest->is_direct_purchase) {
            return false;
        }

        return self::hasSelectedQuotation($purchaseRequest);
    }

    public static function hasSelectedQuotation(PurchaseRequest $purchaseRequest): bool
    {
        if (! empty($purchaseRequest->selected_market_rate_id)) {
            return true;
        }

        return MarketRate::query()
            ->where('purchase_request_id', $purchaseRequest->id)
            ->where('is_selected', true)
            ->exists();
    }

    private static function htmlBody(string $intro, PurchaseRequest $purchaseRequest, string $url): string
    {
        $num = e($purchaseRequest->request_number ?? (string) $purchaseRequest->id);
        $safeIntro = e($intro);
        $safeUrl = e($url);

        return '<p>'.$safeIntro.'</p>'
            .'<p><strong>Solicitud:</strong> '.$num.'</p>'
            .'<p><a href="'.$safeUrl.'">Abrir solicitud en el sistema</a></p>';
    }

    private static function sendHtml(string $subject, string $html): void
    {
        $to = trim(self::notificationEmail());
        if ($to === '') {
            return;
        }

        try {
            Mail::html($html, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::error('Fallo al enviar correo de solicitud de compra', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
