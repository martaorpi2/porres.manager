<?php

namespace App\Console\Commands;

use App\Models\AccountingEntry;
use App\Models\QuotaAccountingBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReverseMercadoPagoExcelEntries extends Command
{
    protected $signature = 'accounting:reverse-mp-excel
        {--dry-run : Muestra las liquidaciones sin revertirlas}
        {--force : Revierte sin pedir confirmación}';

    protected $description = 'Elimina las liquidaciones de Mercado Pago cargadas por Excel para volver a subir el archivo';

    public function handle(): int
    {
        $batches = $this->excelSettlements();
        if ($batches->isEmpty()) {
            $this->info('No hay liquidaciones de Mercado Pago por Excel para eliminar.');

            return self::SUCCESS;
        }

        $this->table(
            ['Fecha', 'Asiento', 'Órdenes'],
            $batches->map(fn (QuotaAccountingBatch $batch) => [
                $batch->entry_date->format('d/m/Y'),
                $batch->entry?->entry_number,
                $batch->orders->count(),
            ])->all()
        );
        $this->line($batches->count().' liquidaciones de Mercado Pago por Excel.');

        if ($this->option('dry-run')) {
            $this->warn('Simulación: no se eliminó nada.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('¿Eliminar estos asientos de Mercado Pago?')) {
            $this->line('No se eliminó nada.');

            return self::SUCCESS;
        }

        $deleted = DB::transaction(function () use ($batches) {
            $locked = QuotaAccountingBatch::query()
                ->whereIn('id', $batches->pluck('id'))
                ->where('kind', QuotaAccountingBatch::KIND_MP_SETTLEMENT)
                ->where(function ($query) {
                    $query->where('batch_key', 'like', 'mp-settlement:excel:%')
                        ->orWhere('batch_key', 'like', 'mercadopago:%');
                })
                ->lockForUpdate()
                ->with('entry')
                ->get();

            $count = 0;
            foreach ($locked as $batch) {
                $entry = $batch->entry;
                if ($entry === null || ! in_array($entry->status, [
                    AccountingEntry::STATUS_POSTED,
                    AccountingEntry::STATUS_REVERSED,
                ], true)) {
                    continue;
                }
                $batch->orders()->delete();
                $entry->lines()->delete();
                $batch->delete();
                $entry->delete();
                $count++;
            }

            return $count;
        });

        $this->info('Se eliminaron '.$deleted.' asientos de Mercado Pago. Ya se puede volver a subir el Excel.');

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, QuotaAccountingBatch>
     */
    private function excelSettlements()
    {
        return QuotaAccountingBatch::query()
            ->where('kind', QuotaAccountingBatch::KIND_MP_SETTLEMENT)
            ->where(function ($query) {
                $query->where('batch_key', 'like', 'mp-settlement:excel:%')
                    ->orWhere('batch_key', 'like', 'mercadopago:%');
            })
            ->whereHas('entry', fn ($query) => $query->whereIn('status', [
                AccountingEntry::STATUS_POSTED,
                AccountingEntry::STATUS_REVERSED,
            ]))
            ->with(['entry', 'orders'])
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();
    }
}
