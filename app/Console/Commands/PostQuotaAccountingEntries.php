<?php

namespace App\Console\Commands;

use App\Services\QuotaAccountingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class PostQuotaAccountingEntries extends Command
{
    protected $signature = 'accounting:post-quota-entries
        {month : Mes en formato YYYY-MM}
        {--dry-run : Muestra los asientos sin grabarlos}';

    protected $description = 'Asienta el devengamiento y la cobranza de cuotas de ePorres para un mes';

    public function handle(QuotaAccountingService $service): int
    {
        $monthArg = (string) $this->argument('month');
        $month = Carbon::createFromFormat('!Y-m', $monthArg);
        if ($month === false || $month->format('Y-m') !== $monthArg) {
            $this->error('El mes debe tener formato YYYY-MM. Ejemplo: 2026-09');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $service->postMonth($month, $dryRun);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Simulación: no se grabó nada.');
        }

        $this->line('Mes '.$result['month']);
        $this->reportAccrual($result['accrual']);
        $this->reportCollections($result['collections']);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $accrual
     */
    private function reportAccrual(array $accrual): void
    {
        $this->newLine();
        $this->info('Devengamiento');

        if ($accrual['status'] === 'already_posted') {
            $this->line('Ese mes ya tiene asiento de devengamiento. No se creó otro.');

            return;
        }

        if ($accrual['status'] === 'empty') {
            $this->line('No hay cuotas de ese mes para devengar.');

            return;
        }

        $this->line($accrual['description']);
        $this->line('Cuotas: '.$accrual['orders']);
        $this->line('Deudores / Cuotas: '.$this->money($accrual['amount']));
        if ($accrual['entry_number']) {
            $this->line('Asiento: '.$accrual['entry_number']);
        }
    }

    /**
     * @param  array<string, mixed>  $collections
     */
    private function reportCollections(array $collections): void
    {
        $this->newLine();
        $this->info('Cobranza');

        if ($collections['groups'] === [] && ($collections['updated'] ?? []) === []) {
            $this->line('No hay cobros nuevos ni correcciones para asentar.');
        }

        foreach ($collections['updated'] ?? [] as $group) {
            $this->line(sprintf(
                'Actualizado  %s  %s  asiento %s  antes: %s  ahora: %s  pagos: %d',
                $group['date'],
                $group['payment_type'],
                $group['entry_number'] ?? '—',
                $this->money($group['previous_bank']),
                $this->money($group['bank']),
                $group['orders'],
            ));
        }

        foreach ($collections['groups'] as $group) {
            $this->line(sprintf(
                '%s  %s  (%s)  pagos: %d  banco: %s  deudores: %s  mora: %s%s',
                $group['date'],
                $group['payment_type'],
                $group['bank_code'],
                $group['orders'],
                $this->money($group['bank']),
                $this->money($group['debtors']),
                $this->money($group['interest']),
                $group['entry_number'] ? '  asiento '.$group['entry_number'] : '',
            ));
        }

        $skipped = $collections['skipped'];
        if ($skipped['split'] > 0) {
            $this->line('Omitidos por pago dividido: '.$skipped['split']);
        }
        if ($skipped['plan'] > 0) {
            $this->line('Omitidos por plan de pago: '.$skipped['plan']);
        }
        if ($skipped['no_payment_type'] > 0) {
            $this->line('Omitidos sin medio de pago: '.$skipped['no_payment_type']);
        }
        if ($skipped['zero'] > 0) {
            $this->line('Omitidos sin importe de cuota ni mora: '.$skipped['zero']);
        }
        foreach ($skipped['unmapped'] as $type => $count) {
            $this->line('Omitidos sin cuenta de fondos ('.$type.'): '.$count);
        }
        if ($collections['unposted_surcharge'] > 0) {
            $this->line('Recargos de medio de pago no asentados: '.$this->money($collections['unposted_surcharge']));
        }
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, ',', '.');
    }
}
