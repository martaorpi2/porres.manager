<?php

namespace App\Console\Commands;

use App\Models\AccountingEntry;
use App\Models\QuotaAccountingBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReverseQrCollectionEntries extends Command
{
    protected $signature = 'accounting:reverse-qr-collections
        {--dry-run : Muestra las cobranzas de QR sin revertirlas}
        {--force : Revierte sin pedir confirmación}';

    protected $description = 'Revierte asientos de cobranza de QR ya registrados';

    public function handle(): int
    {
        $batches = $this->postedQrCollections();
        if ($batches->isEmpty()) {
            $this->info('No hay cobranzas de QR vigentes para revertir.');

            return self::SUCCESS;
        }

        $this->table(
            ['Fecha', 'Asiento', 'Descripción'],
            $batches->map(fn (QuotaAccountingBatch $batch) => [
                $batch->entry_date->format('d/m/Y'),
                $batch->entry?->entry_number,
                $batch->entry?->description,
            ])->all()
        );
        $this->line($batches->count().' cobranzas de QR vigentes.');

        if ($this->option('dry-run')) {
            $this->warn('Simulación: no se revirtió nada.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('¿Revertir estas cobranzas de QR?')) {
            $this->line('No se revirtió nada.');

            return self::SUCCESS;
        }

        $reverted = DB::transaction(function () use ($batches) {
            $locked = QuotaAccountingBatch::query()
                ->whereIn('id', $batches->pluck('id'))
                ->where('kind', QuotaAccountingBatch::KIND_COLLECTION)
                ->where('payment_type', 'QR')
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

        $this->info('Se revirtieron '.$reverted.' cobranzas de QR.');

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, QuotaAccountingBatch>
     */
    private function postedQrCollections()
    {
        return QuotaAccountingBatch::query()
            ->where('kind', QuotaAccountingBatch::KIND_COLLECTION)
            ->where('payment_type', 'QR')
            ->whereHas('entry', fn ($query) => $query->where('status', AccountingEntry::STATUS_POSTED))
            ->with('entry')
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();
    }
}
