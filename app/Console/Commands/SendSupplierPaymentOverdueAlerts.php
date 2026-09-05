<?php

namespace App\Console\Commands;

use App\Services\SupplierPaymentOverdueAlertService;
use Illuminate\Console\Command;

class SendSupplierPaymentOverdueAlerts extends Command
{
    protected $signature = 'supplier-invoices:send-overdue-payment-alerts';

    protected $description = 'Envía un aviso si hay facturas o órdenes de compra con 20 días o más sin pago al proveedor';

    public function handle(SupplierPaymentOverdueAlertService $service): int
    {
        $invoices = $service->overdueInvoices();
        $purchaseOrders = $service->overduePurchaseOrdersWithoutPayment();
        $days = SupplierPaymentOverdueAlertService::alertAfterDays();

        $this->info('Facturas impagas (≥ '.$days.' días): '.$invoices->count());
        $this->info('OC sin factura ni pago ejecutado (≥ '.$days.' días): '.$purchaseOrders->count());

        if ($invoices->isEmpty() && $purchaseOrders->isEmpty()) {
            $this->info('No hay deudas vencidas para avisar.');

            return self::SUCCESS;
        }

        $ok = $service->sendDailyDigest();
        if ($ok) {
            $this->info('Alerta enviada.');

            return self::SUCCESS;
        }

        $this->warn('No se pudo enviar la alerta (sin destinatarios o error de correo).');

        return self::FAILURE;
    }
}
