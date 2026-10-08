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

    protected $description = 'Revierte las liquidaciones de Mercado Pago cargadas por Excel para volver a subir el archivo';

    public function handle(): int
    {
        $batches = $this->postedExcelSettlements();
        if ($batches->isEmpty()) {
            $this->info('No hay liquidaciones de Mercado Pago por Excel vigentes.');

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
            $this->warn('Simulación: no se revirtió nada.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('¿Revertir estas liquidaciones de Mercado Pago?')) {
            $this->line('No se revirtió nada.');

            return self::SUCCESS;
        }

        $reverted = DB::transaction(function () use ($batches) {
            $locked = QuotaAccountingBatch::query()
                ->whereIn('id', $batches->pluck('id'))
                ->where('kind', QuotaAccountingBatch::KIND_MP_SETTLEMENT)
                ->where('batch_key', 'like', 'mp-settlement:excel:%')
                ->lockForUpdate()
                ->with('entry')
                ->get();

            $count = 0;
            foreach ($locked as $batch) {
                $entry = $batch->entry;
                if ($entry === null || $entry->status !== AccountingEntry::STATUS_POSTED) {
                    continue;
                }
                $batch->orders()->delete();
                $entry->lines()->delete();
                $entry->update(['status' => AccountingEntry::STATUS_REVERSED]);
                $count++;
            }

            return $count;
        });

        $this->info('Se revirtieron '.$reverted.' liquidaciones de Mercado Pago. Ya se puede volver a subir el Excel.');

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, QuotaAccountingBatch>
     */
    private function postedExcelSettlements()
    {
        return QuotaAccountingBatch::query()
            ->where('kind', QuotaAccountingBatch::KIND_MP_SETTLEMENT)
            ->where('batch_key', 'like', 'mp-settlement:excel:%')
            ->whereHas('entry', fn ($query) => $query->where('status', AccountingEntry::STATUS_POSTED))
            ->with(['entry', 'orders'])
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();
    }
}
