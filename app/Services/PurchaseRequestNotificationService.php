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
     * Primera aprobación de cotizaciones: siempre administradora del instituto.
     *
     * @return list<string>
     */
    private static function administratorApproverRoleNames(): array
    {
        return ['role_admin_institucion'];
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
     * Escalamiento desde administradora: devuelve el siguiente nivel por monto, excluyendo admin.
     *
     * @return list<string>
     */
    private static function superiorApproverRoleNamesForAmountFromAdministrator(float $totalAmount): array
    {
        $apoderadoLimit = (float) PurchaseAuthorizationLimit::getLimitForRole('role_apoderado');
        if ($apoderadoLimit > 0 && $totalAmount <= $apoderadoLimit) {
            return ['role_apoderado'];
        }
        $representanteLimit = (float) PurchaseAuthorizationLimit::getLimitForRole('role_representante_legal');
        if ($representanteLimit > 0 && $totalAmount <= $representanteLimit) {
            return ['role_representante_legal'];
        }

        // Monto fuera de topes: escalar a ambos niveles superiores.
        return ['role_apoderado', 'role_representante_legal'];
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
     * Compras solicita revisión inicial de cotizaciones: siempre a administradora.
     */
    public static function notifyAdministratorQuotationApprovalNeeded(PurchaseRequest $purchaseRequest): void
    {
        $url = self::purchaseRequestUrl($purchaseRequest);
        $nro = $purchaseRequest->request_number ?? ('#'.$purchaseRequest->id);
        $subject = 'Solicitud de revisión y aprobación – Solicitud Nº '.$nro;
        $safeRequestNumber = e($purchaseRequest->request_number ?? (string) $purchaseRequest->id);
        $safeUrl = e($url);
        $body = '<p>Se informa que el sector de Compras ha realizado la selección de cotización(es) para la siguiente solicitud.</p>'
            .'<p><strong>Número de solicitud:</strong> '.$safeRequestNumber.'</p>'
            .'<p>Se solicita su revisión y aprobación inicial a fin de continuar con el circuito de compra.</p>'
            .'<p><a href="'.$safeUrl.'">Acceder a la solicitud en el sistema</a></p>'
            .'<p>Este es un mensaje automático generado por el sistema de compras.</p>';
        $recipients = self::emailsForBackpackRoles(self::administratorApproverRoleNames());
        self::sendHtml($subject, $body, $recipients);
    }

    /**
     * Administradora solicita aprobación al nivel superior que corresponde por monto.
     */
    public static function notifySuperiorQuotationApprovalNeededFromAdministrator(PurchaseRequest $purchaseRequest): void
    {
        $url = self::purchaseRequestUrl($purchaseRequest);
        $nro = $purchaseRequest->request_number ?? ('#'.$purchaseRequest->id);
        $subject = 'Solicitud de aprobación superior – Solicitud Nº '.$nro;
        $safeRequestNumber = e($purchaseRequest->request_number ?? (string) $purchaseRequest->id);
        $safeUrl = e($url);
        $body = '<p>Se informa que la solicitud de compra detallada a continuación ha sido aprobada por la Administración y, debido al monto involucrado, requiere la aprobación de un nivel superior.</p>'
            .'<p><strong>Número de solicitud:</strong> '.$safeRequestNumber.'</p>'
            .'<p>Se solicita revisar y emitir la aprobación correspondiente para continuar con el circuito de compra.</p>'
            .'<p><a href="'.$safeUrl.'">Acceder a la solicitud en el sistema</a></p>'
            .'<p>Este es un mensaje automático generado por el sistema de compras.</p>';
        $recipients = self::emailsForBackpackRoles(
            self::superiorApproverRoleNamesForAmountFromAdministrator((float) ($purchaseRequest->total_amount ?? 0))
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

    public static function isAwaitingAdministratorQuotationApproval(PurchaseRequest $purchaseRequest): bool
    {
        if (! in_array($purchaseRequest->status, ['Pendiente', 'En Proceso'], true)) {
            return false;
        }
        if ($purchaseRequest->is_direct_purchase) {
            return false;
        }

        return self::hasSelectedQuotation($purchaseRequest);
    }

    public static function shouldAdministratorEscalateQuotationApproval(PurchaseRequest $purchaseRequest): bool
    {
        if (! self::isAwaitingAdministratorQuotationApproval($purchaseRequest)) {
            return false;
        }

        $adminLimit = (float) PurchaseAuthorizationLimit::getLimitForRole('role_admin_institucion');

        return $adminLimit > 0 && (float) ($purchaseRequest->total_amount ?? 0) > $adminLimit;
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
     * Destinatarios de correo: solo el responsable del área de la solicitud (no el usuario solicitante nominado).
     *
     * @return list<string>
     */
    private static function emailsForPurchaseRequestAreaResponsible(PurchaseRequest $purchaseRequest): array
    {
        $purchaseRequest->loadMissing(['responsibilityArea.responsibleUser']);
        $responsible = $purchaseRequest->responsibilityArea?->responsibleUser;
        if (! $responsible) {
            return [];
        }
        $email = trim((string) $responsible->email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [];
        }

        return [$email];
    }

    /**
     * Ítems no autorizados para compra: correo únicamente al responsable del área (incluye rechazos de administración o nivel superior).
     *
     * @param  list<array{label: string, reason: string}>  $rejectedItems
     */
    public static function notifyAreaResponsiblePurchaseLinesRejected(PurchaseRequest $purchaseRequest, string $actorName, array $rejectedItems): void
    {
        if ($rejectedItems === []) {
            return;
        }

        $url = self::purchaseRequestUrl($purchaseRequest);
        $nro = e($purchaseRequest->request_number ?? (string) $purchaseRequest->id);
        $itemsHtml = '<ul>';
        foreach ($rejectedItems as $row) {
            $label = e($row['label'] ?? '');
            $reason = e($row['reason'] ?? '');
            $itemsHtml .= '<li><strong>'.$label.'</strong> — '.$reason.'</li>';
        }
        $itemsHtml .= '</ul>';

        $html = '<p>'.e('Se registró la no autorización de compra de uno o más ítems de la solicitud.').'</p>'
            .'<p><strong>Solicitud Nº</strong> '.$nro.'</p>'
            .'<p><strong>Decisión registrada por:</strong> '.e($actorName).'</p>'
            .'<p><strong>Detalle:</strong></p>'.$itemsHtml
            .'<p><a href="'.e($url).'">Abrir solicitud en el sistema</a></p>'
            .'<p class="small text-muted">'.e('Mensaje automático del sistema de compras.').'</p>';

        $subject = 'Ítems no autorizados — Solicitud '.($purchaseRequest->request_number ?? '#'.$purchaseRequest->id);

        self::sendHtml($subject, $html, self::emailsForPurchaseRequestAreaResponsible($purchaseRequest));
    }

    /**
     * Rechazo total de la solicitud de compra: aviso al responsable del área.
     */
    public static function notifyAreaResponsiblePurchaseRequestFullyRejected(PurchaseRequest $purchaseRequest, string $actorName): void
    {
        $url = self::purchaseRequestUrl($purchaseRequest);
        $nro = e($purchaseRequest->request_number ?? (string) $purchaseRequest->id);
        $html = '<p>'.e('La solicitud de compra fue rechazada en su totalidad.').'</p>'
            .'<p><strong>Solicitud Nº</strong> '.$nro.'</p>'
            .'<p><strong>Decisión registrada por:</strong> '.e($actorName).'</p>'
            .'<p><a href="'.e($url).'">Abrir solicitud en el sistema</a></p>'
            .'<p class="small text-muted">'.e('Mensaje automático del sistema de compras.').'</p>';
        $subject = 'Solicitud de compra rechazada — '.($purchaseRequest->request_number ?? '#'.$purchaseRequest->id);
        self::sendHtml($subject, $html, self::emailsForPurchaseRequestAreaResponsible($purchaseRequest));
    }

    /**
     * Rechazo de la autorización de compra directa: aviso al responsable del área.
     */
    public static function notifyAreaResponsibleDirectPurchaseAuthorizationRejected(PurchaseRequest $purchaseRequest, string $actorName, string $rejectionReason): void
    {
        $url = self::purchaseRequestUrl($purchaseRequest);
        $nro = e($purchaseRequest->request_number ?? (string) $purchaseRequest->id);
        $safeReason = nl2br(e($rejectionReason));
        $html = '<p>'.e('No se autorizó la compra directa solicitada para esta solicitud.').'</p>'
            .'<p><strong>Solicitud Nº</strong> '.$nro.'</p>'
            .'<p><strong>Decisión registrada por:</strong> '.e($actorName).'</p>'
            .'<p><strong>Motivo:</strong></p><p>'.$safeReason.'</p>'
            .'<p><a href="'.e($url).'">Abrir solicitud en el sistema</a></p>'
            .'<p class="small text-muted">'.e('Mensaje automático del sistema de compras.').'</p>';
        $subject = 'Compra directa no autorizada — Solicitud '.($purchaseRequest->request_number ?? '#'.$purchaseRequest->id);
        self::sendHtml($subject, $html, self::emailsForPurchaseRequestAreaResponsible($purchaseRequest));
    }

    /**
     * Tras revisión administrativa: se requiere de nuevo autorización del nivel superior (monto sobre tope de administradora).
     */
    public static function notifySuperiorReapprovalAfterAdministrativeRevision(PurchaseRequest $purchaseRequest): void
    {
        $url = self::purchaseRequestUrl($purchaseRequest);
        $nro = $purchaseRequest->request_number ?? ('#'.$purchaseRequest->id);
        $safeRequestNumber = e($purchaseRequest->request_number ?? (string) $purchaseRequest->id);
        $safeUrl = e($url);
        $subject = 'Nueva autorización requerida (revisión) – Solicitud Nº '.$nro;
        $body = '<p>'.e('La administración del instituto reenvía esta solicitud para una nueva autorización por ítem, tras revisar observaciones del nivel superior o ajustes en cotización. Debe ingresar al sistema y registrar la decisión.').'</p>'
            .'<p><strong>Número de solicitud:</strong> '.$safeRequestNumber.'</p>'
            .'<p><a href="'.$safeUrl.'">Acceder a la solicitud en el sistema</a></p>'
            .'<p>'.e('Este es un mensaje automático generado por el sistema de compras.').'</p>';
        $recipients = self::emailsForBackpackRoles(
            self::superiorApproverRoleNamesForAmountFromAdministrator((float) ($purchaseRequest->total_amount ?? 0))
        );
        self::sendHtml($subject, $body, $recipients);
    }

    /**
     * Reapertura para nueva decisión: monto dentro del tope de administradora (notificación a administradoras del instituto).
     */
    public static function notifyAdministratorPurchaseRequestReopenedAfterSuperiorObservations(PurchaseRequest $purchaseRequest): void
    {
        $url = self::purchaseRequestUrl($purchaseRequest);
        $nro = $purchaseRequest->request_number ?? ('#'.$purchaseRequest->id);
        $subject = 'Solicitud reabierta para nueva decisión – Solicitud Nº '.$nro;
        $body = self::htmlBody(
            'La solicitud fue reabierta para una nueva autorización por ítem (revisión tras observaciones del nivel superior o ajustes). El monto actual está dentro del ámbito de decisión de la administración del instituto.',
            $purchaseRequest,
            $url
        );
        self::sendHtml($subject, $body, self::emailsForBackpackRoles(self::administratorApproverRoleNames()));
    }

    /**
     * Reapertura con monto que no exige aprobación de administración ni superior: aviso a compras.
     */
    public static function notifyComprasPurchaseRequestReopenedAfterAdministrativeRevision(PurchaseRequest $purchaseRequest): void
    {
        $url = self::purchaseRequestUrl($purchaseRequest);
        $nro = $purchaseRequest->request_number ?? ('#'.$purchaseRequest->id);
        $subject = 'Solicitud reabierta para nueva decisión – Solicitud Nº '.$nro;
        $body = self::htmlBody(
            'La administración del instituto reabrió esta solicitud para una nueva autorización por ítem. El monto actual puede ser gestionado por el sector de compras según los roles del sistema.',
            $purchaseRequest,
            $url
        );
        self::sendHtml($subject, $body, self::emailsForBackpackRoles(['role_responsable_compras']));
    }

    public static function notifyAutomaticReminderCompras(PurchaseRequest $purchaseRequest): bool
    {
        $days = max(1, (int) config('purchase_requests.reminder_interval_days', 5));
        $intro = 'Recordatorio automático (cada '.$days.' días): la solicitud sigue pendiente de gestión por el sector de compras en el circuito de compras.';
        $url = self::purchaseRequestUrl($purchaseRequest);
        $subject = '[Recordatorio] Solicitud de compra — '.($purchaseRequest->request_number ?? '#'.$purchaseRequest->id);

        return self::sendHtml($subject, self::htmlBody($intro, $purchaseRequest, $url), self::emailsForBackpackRoles(['role_responsable_compras']));
    }

    public static function notifyAutomaticReminderAdministratorQuotation(PurchaseRequest $purchaseRequest): bool
    {
        $days = max(1, (int) config('purchase_requests.reminder_interval_days', 5));
        $url = self::purchaseRequestUrl($purchaseRequest);
        $nro = $purchaseRequest->request_number ?? ('#'.$purchaseRequest->id);
        $subject = '[Recordatorio] Revisión de cotización — Solicitud Nº '.$nro;
        $safeRequestNumber = e($purchaseRequest->request_number ?? (string) $purchaseRequest->id);
        $safeUrl = e($url);
        $body = '<p>'.e('Recordatorio automático (cada '.$days.' días): el sector de compras solicitó revisión y aprobación inicial de cotización(es) y la solicitud sigue pendiente de su intervención.').'</p>'
            .'<p><strong>Número de solicitud:</strong> '.$safeRequestNumber.'</p>'
            .'<p><a href="'.$safeUrl.'">Acceder a la solicitud en el sistema</a></p>'
            .'<p>'.e('Este es un mensaje automático generado por el sistema de compras.').'</p>';

        return self::sendHtml($subject, $body, self::emailsForBackpackRoles(self::administratorApproverRoleNames()));
    }

    public static function notifyAutomaticReminderSuperiorQuotation(PurchaseRequest $purchaseRequest): bool
    {
        $days = max(1, (int) config('purchase_requests.reminder_interval_days', 5));
        $url = self::purchaseRequestUrl($purchaseRequest);
        $intro = 'Recordatorio automático (cada '.$days.' días): la solicitud sigue pendiente de aprobación por el nivel que corresponde según el monto (cotización seleccionada y circuito de autorización).';
        $subject = '[Recordatorio] Aprobar solicitud de compra '.($purchaseRequest->request_number ?? '#'.$purchaseRequest->id);

        return self::sendHtml(
            $subject,
            self::htmlBody($intro, $purchaseRequest, $url),
            self::emailsForBackpackRoles(
                self::approverRoleNamesForAmount((float) ($purchaseRequest->total_amount ?? 0))
            )
        );
    }

    public static function notifyAutomaticReminderDirectPurchaseAuthorization(PurchaseRequest $purchaseRequest): bool
    {
        $days = max(1, (int) config('purchase_requests.reminder_interval_days', 5));
        $url = self::purchaseRequestUrl($purchaseRequest);
        $intro = 'Recordatorio automático (cada '.$days.' días): sigue pendiente la autorización de compra directa registrada en el sistema.';
        $subject = '[Recordatorio] Autorizar compra directa '.($purchaseRequest->request_number ?? '#'.$purchaseRequest->id);

        return self::sendHtml(
            $subject,
            self::htmlBody($intro, $purchaseRequest, $url),
            self::emailsForBackpackRoles(
                self::approverRoleNamesForAmount((float) ($purchaseRequest->total_amount ?? 0))
            )
        );
    }

    private static function sendHtml(string $subject, string $html, array $recipients = []): bool
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

            return false;
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

            return false;
        }

        return true;
    }
}
