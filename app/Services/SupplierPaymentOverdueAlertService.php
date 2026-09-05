<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\SupplierInvoice;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class SupplierPaymentOverdueAlertService
{
    private const BACKPACK_GUARD = 'backpack';

    public static function alertAfterDays(): int
    {
        return SupplierInvoice::UNPAID_ALERT_AFTER_DAYS;
    }

    public static function invoicesTableReady(): bool
    {
        return Schema::hasTable('supplier_invoices');
    }

    /**
     * Facturas con saldo pendiente y 20 días o más desde la fecha de factura.
     *
     * @return Collection<int, SupplierInvoice>
     */
    public function overdueInvoices(): Collection
    {
        if (! self::invoicesTableReady()) {
            return collect();
        }

        return SupplierInvoice::query()
            ->overdueUnpaid()
            ->with(['supplier', 'purchaseOrder'])
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Órdenes de compra antiguas sin factura ni pago ejecutado.
     *
     * @return Collection<int, PurchaseOrder>
     */
    public function overduePurchaseOrdersWithoutPayment(): Collection
    {
        if (! Schema::hasTable('purchase_orders')) {
            return collect();
        }

        return PurchaseOrder::query()
            ->overdueWithoutPayment()
            ->with('supplier')
            ->orderByRaw('COALESCE(issue_date, date, created_at)')
            ->orderBy('id')
            ->get();
    }

    public function totalOverdueCount(): int
    {
        return $this->overdueInvoices()->count() + $this->overduePurchaseOrdersWithoutPayment()->count();
    }

    /**
     * Envía un resumen diario a administradora y sector de compras.
     */
    public function sendDailyDigest(): bool
    {
        $invoices = $this->overdueInvoices();
        $purchaseOrders = $this->overduePurchaseOrdersWithoutPayment();
        if ($invoices->isEmpty() && $purchaseOrders->isEmpty()) {
            return false;
        }

        $days = self::alertAfterDays();
        $subject = 'Alerta: proveedores sin pago hace '.$days.' días o más';
        $html = $this->digestHtml($invoices, $purchaseOrders, $days);

        return $this->sendHtml($subject, $html, $this->recipientEmails());
    }

    /**
     * @return list<string>
     */
    private function recipientEmails(): array
    {
        $roles = ['role_admin_institucion', 'role_responsable_compras'];

        return User::query()
            ->whereHas('roles', function ($query) use ($roles) {
                $query->where('guard_name', self::BACKPACK_GUARD)
                    ->whereIn('name', $roles);
            })
            ->pluck('email')
            ->map(fn (?string $email) => $email !== null ? trim($email) : '')
            ->filter(fn (string $email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, SupplierInvoice>  $invoices
     * @param  Collection<int, PurchaseOrder>  $purchaseOrders
     */
    private function digestHtml(Collection $invoices, Collection $purchaseOrders, int $days): string
    {
        $invoiceUrl = backpack_url('supplier-invoice').'?impagas_20_dias=1';
        $html = '<p>Hay deudas con proveedores con <strong>'.$days.' días o más</strong> sin pago.</p>';

        if ($invoices->isNotEmpty()) {
            $html .= '<p><strong>Facturas impagas ('.$invoices->count().')</strong></p>';
            $html .= '<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse;font-size:13px;">';
            $html .= '<tr><th>Proveedor</th><th>Factura</th><th>Fecha</th><th>Días</th><th>Saldo</th></tr>';
            foreach ($invoices as $invoice) {
                $html .= '<tr>'
                    .'<td>'.e($invoice->supplier?->company_name ?? '—').'</td>'
                    .'<td>'.e($invoice->invoice_number).'</td>'
                    .'<td>'.e($invoice->invoice_date?->format('d/m/Y') ?? '—').'</td>'
                    .'<td>'.$invoice->daysSinceInvoice().'</td>'
                    .'<td>$'.number_format($invoice->openBalance(), 2, ',', '.').'</td>'
                    .'</tr>';
            }
            $html .= '</table>';
            $html .= '<p><a href="'.e($invoiceUrl).'">Ver facturas impagas</a></p>';
        }

        if ($purchaseOrders->isNotEmpty()) {
            $html .= '<p><strong>Órdenes de compra sin factura ni pago ejecutado ('.$purchaseOrders->count().')</strong></p>';
            $html .= '<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse;font-size:13px;">';
            $html .= '<tr><th>Proveedor</th><th>OC</th><th>Fecha</th><th>Días</th></tr>';
            foreach ($purchaseOrders as $purchaseOrder) {
                $aging = $purchaseOrder->paymentAgingDate();
                $html .= '<tr>'
                    .'<td>'.e($purchaseOrder->supplier?->company_name ?? '—').'</td>'
                    .'<td>'.e($purchaseOrder->number ?? ('#'.$purchaseOrder->id)).'</td>'
                    .'<td>'.e($aging ? $aging->format('d/m/Y') : '—').'</td>'
                    .'<td>'.$purchaseOrder->daysSinceIssue().'</td>'
                    .'</tr>';
            }
            $html .= '</table>';
            $html .= '<p><a href="'.e(backpack_url('purchase-order')).'">Ver órdenes de compra</a></p>';
        }

        $html .= '<p style="color:#666;font-size:12px;">Aviso automático de porresManager.</p>';

        return $html;
    }

    /**
     * @param  list<string>  $recipients
     */
    private function sendHtml(string $subject, string $html, array $recipients): bool
    {
        $intended = $this->parseEmailList($recipients);
        $fallback = trim((string) config('purchase_requests.notification_email', ''));
        $alwaysTo = $this->parseEmailList(config('purchase_requests.always_to', ''));
        $alwaysCc = $this->parseEmailList(config('purchase_requests.always_cc', ''));
        $forceToNotification = (bool) config('purchase_requests.force_all_to_notification_email', false);

        $to = $intended;
        if ($alwaysTo !== []) {
            $to = $alwaysTo;
        } elseif ($forceToNotification || $to === []) {
            if ($fallback !== '' && filter_var($fallback, FILTER_VALIDATE_EMAIL)) {
                $to = [$fallback];
            }
        }

        $cc = array_values(array_diff($alwaysCc, $to));

        if ($to === []) {
            Log::warning('No se envió alerta de pago a proveedor: sin destinatarios.', [
                'subject' => $subject,
            ]);

            return false;
        }

        try {
            Mail::html($html, function ($message) use ($to, $cc, $subject) {
                $message->to($to)->subject($subject);
                if ($cc !== []) {
                    $message->cc($cc);
                }
            });
        } catch (\Throwable $e) {
            Log::error('Fallo al enviar alerta de pago a proveedor', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function parseEmailList(mixed $value): array
    {
        if (is_array($value)) {
            $parts = $value;
        } else {
            $parts = preg_split('/[,;]+/', (string) $value) ?: [];
        }

        $emails = [];
        foreach ($parts as $part) {
            $email = strtolower(trim((string) $part));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }
}
