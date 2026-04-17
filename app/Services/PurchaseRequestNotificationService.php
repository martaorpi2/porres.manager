<?php

namespace App\Services;

use App\Models\MarketRate;
use App\Models\PurchaseAuthorizationLimit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PurchaseRequestNotificationService
{
    private const BACKPACK_GUARD = 'backpack';

    /**
     * Casilla de respaldo si no hay usuarios con el rol correspondiente o el correo no es válido.
     */
    public static function notificationEmail(): string
    {
        return (string) config('purchase_requests.notification_email', 'morpi@ismp.edu.ar');
    }

    /**
     * Roles que pueden aprobar solicitudes que requieren nivel superior (alineado al flujo de compras).
     *
     * @return list<string>
     */
    private static function superiorApproverRoleNames(): array
    {
        return [
            'role_admin_institucion',
            'role_apoderado',
            'role_representante_legal',
        ];
    }

    /**
     * Rol(es) a los que debe dirigirse el correo de aprobación: solo el nivel más bajo cuyo tope
     * de autorización cubre el monto (misma lógica escalonada que canBeApprovedBy cuando requires_admin_approval).
     *
     * @return list<string>
     */
    private static function approverRoleNamesForAmount(float $totalAmount): array
    {
        $adminLimit = (float) PurchaseAuthorizationLimit::getLimitForRole('role_admin_institucion');
        if ($adminLimit > 0 && $totalAmount <= $adminLimit) {
            return ['role_admin_institucion'];
        }
        $apoderadoLimit = (float) PurchaseAuthorizationLimit::getLimitForRole('role_apoderado');
        if ($apoderadoLimit > 0 && $totalAmount <= $apoderadoLimit) {
            return ['role_apoderado'];
        }
        $representanteLimit = (float) PurchaseAuthorizationLimit::getLimitForRole('role_representante_legal');
        if ($representanteLimit > 0 && $totalAmount <= $representanteLimit) {
            return ['role_representante_legal'];
        }

        // Monto fuera de los topes configurados o límites en cero: mantener aviso a todos los niveles
        return self::superiorApproverRoleNames();
    }

    /**
     * Correos de usuarios activos con alguno de los roles indicados (guard backpack).
     *
     * @param  list<string>  $roleNames
     * @return list<string>
     */
    private static function emailsForBackpackRoles(array $roleNames): array
    {
        if ($roleNames === []) {
            return [];
        }

        return User::query()
            ->whereHas('roles', function ($query) use ($roleNames) {
                $query->where('guard_name', self::BACKPACK_GUARD)
                    ->whereIn('name', $roleNames);
            })
            ->pluck('email')
            ->map(fn (?string $email) => $email !== null ? trim($email) : '')
            ->filter(fn (string $email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
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
        $recipients = self::emailsForBackpackRoles(['role_responsable_compras']);
        self::sendHtml($subject, $body, $recipients);
    }

    /**
     * Aviso manual del responsable de área: pide intervención de compras (mismo destinatario que el resto de avisos a compras: rol responsable de compras).
     */
    public static function notifyComprasManualInterventionFromArea(PurchaseRequest $purchaseRequest, ?Authenticatable $requestedBy = null): void
    {
        $url = self::purchaseRequestUrl($purchaseRequest);
        $intro = 'El responsable de área solicitó que el sector de compras intervenga en esta solicitud para continuar y completar el proceso de compra.';
        if ($requestedBy instanceof User) {
            $intro .= ' Notificación enviada por: '.$requestedBy->name.'.';
        }

        $subject = 'Solicitud de compra — intervención de compras — '.($purchaseRequest->request_number ?? '#'.$purchaseRequest->id);
        $body = self::htmlBody($intro, $purchaseRequest, $url);
        $recipients = self::emailsForBackpackRoles(['role_responsable_compras']);
        self::sendHtml($subject, $body, $recipients);
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
        $recipients = self::emailsForBackpackRoles(
            self::approverRoleNamesForAmount((float) ($purchaseRequest->total_amount ?? 0))
        );
        self::sendHtml($subject, $body, $recipients);
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
        $recipients = self::emailsForBackpackRoles(
            self::approverRoleNamesForAmount((float) ($purchaseRequest->total_amount ?? 0))
        );
        self::sendHtml($subject, $body, $recipients);
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
        $recipients = self::emailsForBackpackRoles(['role_responsable_compras']);
        self::sendHtml($subject, $body, $recipients);
    }

    /**
     * Orden(es) de compra generadas: avisar a la administradora del instituto para generar orden de pago.
     *
     * @param  PurchaseOrder|iterable<int, PurchaseOrder>|Collection<int, PurchaseOrder>  $purchaseOrders
     */
    public static function notifyAdministratorPurchaseOrdersCreated(PurchaseRequest $purchaseRequest, PurchaseOrder|iterable|Collection $purchaseOrders): void
    {
        if ($purchaseOrders instanceof PurchaseOrder) {
            $orders = collect([$purchaseOrders]);
        } elseif ($purchaseOrders instanceof Collection) {
            $orders = $purchaseOrders->values();
        } else {
            $orders = collect($purchaseOrders)->values();
        }
        $orders = $orders->filter(fn ($po) => $po instanceof PurchaseOrder);
        if ($orders->isEmpty()) {
            return;
        }

        $purchaseRequest->refresh();
        $prNum = e($purchaseRequest->request_number ?? (string) $purchaseRequest->id);
        $prUrl = e(self::purchaseRequestUrl($purchaseRequest));

        $items = '';
        foreach ($orders as $po) {
            if (! $po instanceof PurchaseOrder) {
                continue;
            }
            $num = e($po->number ?? '#'.$po->id);
            $ocUrl = e(route('purchase-order.show', $po->id, absolute: true));
            $items .= '<li><a href="'.$ocUrl.'">'.$num.'</a></li>';
        }

        if ($items === '') {
            return;
        }

        $first = $orders->first();
        $firstUrl = $first instanceof PurchaseOrder
            ? e(route('purchase-order.show', $first->id, absolute: true))
            : '';

        $html = '<p>'.e('Se generó la orden de compra vinculada a la solicitud. Puede generar la orden de pago desde el detalle de cada orden de compra.').'</p>'
            .'<p><strong>Solicitud de compra:</strong> '.$prNum.'</p>'
            .'<p><strong>Orden(es) de compra:</strong></p><ul>'.$items.'</ul>'
            .($firstUrl !== '' ? '<p><a href="'.$firstUrl.'">Abrir orden de compra</a></p>' : '')
            .'<p><a href="'.$prUrl.'">Abrir solicitud de compra</a></p>';

        $subject = 'Orden de compra generada — '.$prNum;
        $recipients = self::emailsForBackpackRoles(['role_admin_institucion']);
        self::sendHtml($subject, $html, $recipients);
    }

    public static function isAwaitingSuperiorQuotationApproval(PurchaseRequest $purchaseRequest): bool
    {
        if (! in_array($purchaseRequest->status, ['Pendiente', 'En Proceso'], true) || ! $purchaseRequest->requires_admin_approval) {
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

    /**
     * @param  list<string>  $recipients
     */
    private static function sendHtml(string $subject, string $html, array $recipients = []): void
    {
        $to = array_values(array_unique(array_filter(array_map('trim', $recipients))));
        if ($to === []) {
            $fallback = trim(self::notificationEmail());
            if ($fallback !== '' && filter_var($fallback, FILTER_VALIDATE_EMAIL)) {
                $to = [$fallback];
            }
        }

        if ($to === []) {
            Log::warning('No se envió correo de solicitud de compra: sin destinatarios ni correo de respaldo.', [
                'subject' => $subject,
            ]);

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
