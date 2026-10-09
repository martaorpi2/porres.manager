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
        $this->reportGrants($result['grants']);
        $this->reportCollections($result['collections']);
        $this->reportSettlements($result['settlements']);

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
     * @param  array<string, mixed>  $grants
     */
    private function reportGrants(array $grants): void
    {
        $this->newLine();
        $this->info('Becas otorgadas');

        if ($grants['status'] === 'already_posted') {
            $this->line('Ese mes ya tiene el asiento de becas. No se modificó.');

            return;
        }

        if ($grants['status'] === 'adjusted') {
            $this->line('El asiento de becas fue modificado a mano. No se reescribió.');

            return;
        }

        if ($grants['status'] === 'empty') {
            $this->line('No hay becas de ese mes para asentar.');

            return;
        }

        $this->line($grants['description']);
        $this->line('Cuotas: '.$grants['orders']);
        $this->line('Descuentos por beca / Deudores: '.$this->money($grants['amount']));
        if ($grants['entry_number']) {
            $this->line('Asiento: '.$grants['entry_number']);
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
        if (($skipped['adjusted'] ?? 0) > 0) {
            $this->line('Asientos modificados a mano, sin reescribir: '.$skipped['adjusted']);
        }
    }

    /**
     * @param  array<string, mixed>  $settlements
     */
    private function reportSettlements(array $settlements): void
    {
        $this->newLine();
        $this->info('Acreditación Mercado Pago');

        if ($settlements['groups'] === [] && ($settlements['updated'] ?? []) === []) {
            $this->line('No hay liberaciones nuevas de Mercado Pago para asentar.');
        }

        foreach ($settlements['groups'] as $group) {
            $this->line(sprintf(
                '%s  Mercado Pago  pagos: %d  cuenta: %s  comisión: %s  a cobrar: %s%s',
                $group['date'],
                $group['orders'],
                $this->money($group['net']),
                $this->money($group['commission']),
                $this->money($group['gross']),
                $group['entry_number'] ? '  asiento '.$group['entry_number'] : '',
            ));
        }

        foreach ($settlements['updated'] ?? [] as $group) {
            $this->line(sprintf(
                'Actualizado  %s  asiento %s  cuenta: %s  comisión: %s  a cobrar: %s  pagos: %d',
                $group['date'],
                $group['entry_number'] ?? '—',
                $this->money($group['net']),
                $this->money($group['commission']),
                $this->money($group['gross']),
                $group['orders'],
            ));
        }

        $skipped = $settlements['skipped'];
        if ($skipped['pending_collection'] > 0) {
            $this->line('Liberados sin cobranza asentada todavía: '.$skipped['pending_collection']);
        }
        if ($skipped['not_collected'] > 0) {
            $this->line('Liberados que no entran en la cobranza de cuotas: '.$skipped['not_collected']);
        }
        if ($skipped['amount_mismatch'] > 0) {
            $this->line('Liberados con importe distinto al cobro: '.$skipped['amount_mismatch']);
        }
        if (($skipped['adjusted'] ?? 0) > 0) {
            $this->line('Acreditaciones modificadas a mano, sin reescribir: '.$skipped['adjusted']);
        }
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, ',', '.');
    }
}
