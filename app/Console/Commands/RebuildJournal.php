<?php

namespace App\Console\Commands;

use App\Models\AccountingEntry;
use App\Models\QuotaAccountingBatch;
use App\Services\QuotaAccountingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class RebuildJournal extends Command
{
    protected $signature = 'accounting:rebuild-journal
        {from : Fecha inicial, formato YYYY-MM-DD}
        {to : Fecha final, formato YYYY-MM-DD}
        {--dry-run : Muestra qué borraría sin grabar nada}
        {--force : Borra y regenera sin pedir confirmación}';

    protected $description = 'Borra las cuotas del libro diario en un período y las vuelve a generar desde ePorres';

    public function handle(QuotaAccountingService $quotas): int
    {
        $from = $this->date((string) $this->argument('from'));
        $to = $this->date((string) $this->argument('to'));
        if ($from === null || $to === null) {
            $this->error('Las fechas tienen que tener formato YYYY-MM-DD. Ejemplo: 2026-09-01 2026-10-09');

            return self::FAILURE;
        }
        if ($to->lt($from)) {
            $this->error('La fecha final es anterior a la inicial.');

            return self::FAILURE;
        }

        set_time_limit(0);

        $plan = $this->plan($from, $to);
        $this->line('Período '.$from->toDateString().' a '.$to->toDateString());
        $this->line('Asientos de cuotas a borrar: '.$plan['delete_ids']->count());
        $this->line('Lotes a borrar: '.$plan['batch_ids']->count());
        $this->line('De esos, liquidaciones de archivo: '.$plan['file_imports']);
        $this->line('Los egresos de ese período no se modifican.');

        if ($this->option('dry-run')) {
            $this->warn('Simulación: no se borró ni se generó nada.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('¿Borrar esas cuotas y volver a generarlas desde ePorres?')) {
            $this->line('No se modificó nada.');

            return self::SUCCESS;
        }

        try {
            DB::connection('eporres')->table('orders')->limit(1)->value('id');
        } catch (Throwable $exception) {
            $this->error('No se pudo leer ePorres, así que no se borró el libro: '.$exception->getMessage());

            return self::FAILURE;
        }

        DB::transaction(function () use ($plan): void {
            $this->deletePlan($plan);
        });
        $this->info('Cuotas del período borradas. Las liquidaciones de archivo hay que volver a subirlas.');

        $cursor = $from->copy()->startOfMonth();
        $lastMonth = $to->copy()->startOfMonth();
        while ($cursor->lte($lastMonth)) {
            $month = $cursor->format('Y-m');
            $this->line('Generando '.$month.' desde ePorres...');
            try {
                $result = $quotas->postMonth($cursor->copy());
            } catch (Throwable $exception) {
                $this->error('No se pudieron generar las cuotas de '.$month.': '.$exception->getMessage());

                return self::FAILURE;
            }
            $this->reportMonth($result);
            $cursor->addMonth();
        }

        $posted = AccountingEntry::query()
            ->where('status', AccountingEntry::STATUS_POSTED)
            ->whereIn('kind', self::quotaKinds())
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->count();
        $this->info('Listo. Asientos de cuotas registrados en el período: '.$posted.'.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function reportMonth(array $result): void
    {
        $accrual = $result['accrual']['status'] ?? '';
        $grants = $result['grants']['status'] ?? '';
        $collections = count($result['collections']['groups'] ?? []) + count($result['collections']['updated'] ?? []);
        $settlements = count($result['settlements']['groups'] ?? []) + count($result['settlements']['updated'] ?? []);
        $this->line('  Devengamiento: '.$accrual.'. Becas: '.$grants.'. Cobranzas: '.$collections.'. Liberaciones de Mercado Pago: '.$settlements.'.');
    }

    private function date(string $value): ?Carbon
    {
        $date = Carbon::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date->startOfDay() : null;
    }

    /**
     * @return list<string>
     */
    private static function quotaKinds(): array
    {
        return [
            AccountingEntry::KIND_QUOTA_ACCRUAL,
            AccountingEntry::KIND_QUOTA_GRANT,
            AccountingEntry::KIND_QUOTA_COLLECTION,
            AccountingEntry::KIND_QUOTA_MP_SETTLEMENT,
            AccountingEntry::KIND_QUOTA_NX_SETTLEMENT,
            AccountingEntry::KIND_QUOTA_SOL_SETTLEMENT,
            AccountingEntry::KIND_QUOTA_QR_SETTLEMENT,
        ];
    }

    /**
     * @return array{delete_ids: Collection<int, int>, batch_ids: Collection<int, int>, file_imports: int}
     */
    private function plan(Carbon $from, Carbon $to): array
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $deleteIds = AccountingEntry::query()
            ->whereIn('kind', self::quotaKinds())
            ->whereDate('date', '>=', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->pluck('id');

        if ($deleteIds->isNotEmpty()) {
            $linkedReversals = AccountingEntry::query()
                ->where('kind', AccountingEntry::KIND_REVERSAL)
                ->whereIn('reversed_entry_id', $deleteIds)
                ->pluck('id');
            $deleteIds = $deleteIds->merge($linkedReversals)->unique()->values();
        }

        $batchIds = QuotaAccountingBatch::query()
            ->where(function ($query) use ($fromDate, $toDate, $deleteIds): void {
                $query->whereDate('entry_date', '>=', $fromDate)
                    ->whereDate('entry_date', '<=', $toDate);
                if ($deleteIds->isNotEmpty()) {
                    $query->orWhereIn('accounting_entry_id', $deleteIds);
                }
            })
            ->pluck('id');

        $fileImports = QuotaAccountingBatch::query()
            ->whereIn('id', $batchIds->isEmpty() ? [0] : $batchIds)
            ->where(function ($query): void {
                $query->where('batch_key', 'like', 'mercadopago:%')
                    ->orWhere('batch_key', 'like', 'naranja:%')
                    ->orWhere('batch_key', 'like', 'sol:%')
                    ->orWhere('batch_key', 'like', 'qr:%')
                    ->orWhere('batch_key', 'like', 'mp-settlement:excel:%');
            })
            ->count();

        return [
            'delete_ids' => $deleteIds->map(fn ($id) => (int) $id)->values(),
            'batch_ids' => $batchIds->map(fn ($id) => (int) $id)->values(),
            'file_imports' => $fileImports,
        ];
    }

    /**
     * @param  array{delete_ids: Collection<int, int>, batch_ids: Collection<int, int>}  $plan
     */
    private function deletePlan(array $plan): void
    {
        foreach ($plan['batch_ids']->chunk(500) as $ids) {
            QuotaAccountingBatch::query()->whereIn('id', $ids)->delete();
        }

        $reversalIds = $plan['delete_ids']->isEmpty()
            ? collect()
            : AccountingEntry::query()
                ->whereIn('id', $plan['delete_ids'])
                ->whereNotNull('reversed_entry_id')
                ->pluck('id');

        foreach ($reversalIds->chunk(500) as $ids) {
            AccountingEntry::query()->whereIn('id', $ids)->delete();
        }
        $remaining = $plan['delete_ids']->reject(fn (int $id) => $reversalIds->contains($id));
        foreach ($remaining->chunk(500) as $ids) {
            AccountingEntry::query()->whereIn('id', $ids)->delete();
        }
    }
}
