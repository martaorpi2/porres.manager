<?php

namespace App\Console\Commands;

use App\Models\PurchaseRequest;
use App\Services\PurchaseRequestReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendPurchaseRequestAutoReminders extends Command
{
    protected $signature = 'purchase-requests:send-auto-reminders';

    protected $description = 'Envía recordatorios por correo cada N días a quien deba intervenir en solicitudes de compra abiertas';

    public function handle(): int
    {
        $interval = PurchaseRequestReminderService::reminderIntervalDays();
        $this->info('Intervalo de recordatorios: '.$interval.' día(s).');

        $sent = 0;
        $skipped = 0;
        $reset = 0;

        PurchaseRequest::query()
            ->whereNotIn('status', ['Completada', 'Rechazada'])
            ->with(['purchaseRequestEvents'])
            ->withCount('purchaseOrders')
            ->orderBy('id')
            ->chunkById(100, function ($requests) use ($interval, &$sent, &$skipped, &$reset) {
                foreach ($requests as $purchaseRequest) {
                    $context = PurchaseRequestReminderService::resolveReminderContext($purchaseRequest);

                    if ($context === null) {
                        if ($purchaseRequest->auto_reminder_context_key !== null) {
                            $purchaseRequest->update([
                                'auto_reminder_context_key' => null,
                                'auto_reminder_context_started_at' => null,
                                'auto_reminder_last_sent_at' => null,
                            ]);
                            $reset++;
                        }

                        continue;
                    }

                    if ($purchaseRequest->auto_reminder_context_key !== $context) {
                        $purchaseRequest->update([
                            'auto_reminder_context_key' => $context,
                            'auto_reminder_context_started_at' => now(),
                            'auto_reminder_last_sent_at' => null,
                        ]);
                        $skipped++;

                        continue;
                    }

                    $anchor = $purchaseRequest->auto_reminder_last_sent_at
                        ?? $purchaseRequest->auto_reminder_context_started_at;

                    if ($anchor === null) {
                        $purchaseRequest->update([
                            'auto_reminder_context_started_at' => now(),
                        ]);
                        $skipped++;

                        continue;
                    }

                    if ($anchor->copy()->addDays($interval)->isFuture()) {
                        $skipped++;

                        continue;
                    }

                    $ok = PurchaseRequestReminderService::sendReminderForContext($purchaseRequest, $context);
                    if ($ok) {
                        $purchaseRequest->update(['auto_reminder_last_sent_at' => now()]);
                        $sent++;
                        Log::info('purchase_request.auto_reminder.sent', [
                            'purchase_request_id' => $purchaseRequest->id,
                            'context' => $context,
                        ]);
                    } else {
                        Log::warning('purchase_request.auto_reminder.send_failed', [
                            'purchase_request_id' => $purchaseRequest->id,
                            'context' => $context,
                        ]);
                    }
                }
            });

        $this->info('Recordatorios enviados: '.$sent.'; omitidos (sin plazo o cambio de contexto): '.$skipped.'; estados limpiados: '.$reset.'.');

        return self::SUCCESS;
    }
}
