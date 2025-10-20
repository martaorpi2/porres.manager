<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\QuoteDetail;
use App\Models\MarketRate;
use App\Models\PurchaseRequestDetail;

class EnsureQuoteDataIntegrity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quotes:ensure-integrity 
                            {--dry-run : Solo mostrar qué se eliminaría sin ejecutar cambios}
                            {--backup : Crear backup antes de hacer cambios}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Asegura que las cotizaciones solo contengan productos que están en solicitudes de compra';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Verificando integridad de datos de cotizaciones...');
        
        // Verificar datos inconsistentes
        $inconsistentQuoteDetails = $this->findInconsistentQuoteDetails();
        $orphanedMarketRates = $this->findOrphanedMarketRates();
        
        if ($inconsistentQuoteDetails->isEmpty() && $orphanedMarketRates->isEmpty()) {
            $this->info('✅ Todos los datos están consistentes. No se requieren cambios.');
            return 0;
        }
        
        // Mostrar resumen de inconsistencias
        $this->showInconsistencies($inconsistentQuoteDetails, $orphanedMarketRates);
        
        if ($this->option('dry-run')) {
            $this->warn('🔍 Modo dry-run: No se realizarán cambios.');
            return 0;
        }
        
        // Confirmar antes de proceder
        if (!$this->confirm('¿Desea proceder con la limpieza de datos inconsistentes?')) {
            $this->info('Operación cancelada.');
            return 0;
        }
        
        // Crear backup si se solicita
        if ($this->option('backup')) {
            $this->createBackup();
        }
        
        // Ejecutar limpieza
        $this->cleanupInconsistentData($inconsistentQuoteDetails, $orphanedMarketRates);
        
        $this->info('✅ Limpieza completada exitosamente.');
        return 0;
    }
    
    private function findInconsistentQuoteDetails()
    {
        return DB::table('quote_details as qd')
            ->leftJoin('purchase_request_details as prd', 'qd.product_id', '=', 'prd.product_id')
            ->whereNull('prd.product_id')
            ->select('qd.*')
            ->get();
    }
    
    private function findOrphanedMarketRates()
    {
        return DB::table('market_rates as mr')
            ->leftJoin('quote_details as qd', 'mr.id', '=', 'qd.market_rate_id')
            ->whereNull('qd.market_rate_id')
            ->select('mr.*')
            ->get();
    }
    
    private function showInconsistencies($inconsistentQuoteDetails, $orphanedMarketRates)
    {
        $this->warn("📊 Resumen de inconsistencias encontradas:");
        
        if (!$inconsistentQuoteDetails->isEmpty()) {
            $this->error("❌ Quote Details inconsistentes: {$inconsistentQuoteDetails->count()}");
            $this->table(
                ['ID', 'Market Rate ID', 'Product ID', 'Quantity', 'Unit Price'],
                $inconsistentQuoteDetails->map(function($item) {
                    return [
                        $item->id,
                        $item->market_rate_id,
                        $item->product_id,
                        $item->quantity,
                        '$' . number_format($item->unit_price, 2)
                    ];
                })
            );
        }
        
        if (!$orphanedMarketRates->isEmpty()) {
            $this->error("❌ Market Rates huérfanos: {$orphanedMarketRates->count()}");
            $this->table(
                ['ID', 'Supplier ID', 'Date', 'Total Amount'],
                $orphanedMarketRates->map(function($item) {
                    return [
                        $item->id,
                        $item->supplier_id,
                        $item->date,
                        '$' . number_format($item->total_amount, 2)
                    ];
                })
            );
        }
    }
    
    private function createBackup()
    {
        $this->info('💾 Creando backup de datos...');
        
        $timestamp = now()->format('Y_m_d_H_i_s');
        
        // Backup de quote_details
        $quoteDetailsBackup = DB::table('quote_details')->get();
        file_put_contents(
            storage_path("app/backups/quote_details_backup_{$timestamp}.json"),
            json_encode($quoteDetailsBackup, JSON_PRETTY_PRINT)
        );
        
        // Backup de market_rates
        $marketRatesBackup = DB::table('market_rates')->get();
        file_put_contents(
            storage_path("app/backups/market_rates_backup_{$timestamp}.json"),
            json_encode($marketRatesBackup, JSON_PRETTY_PRINT)
        );
        
        $this->info("✅ Backup creado en storage/app/backups/");
    }
    
    private function cleanupInconsistentData($inconsistentQuoteDetails, $orphanedMarketRates)
    {
        $this->info('🧹 Limpiando datos inconsistentes...');
        
        // Eliminar quote_details inconsistentes
        if (!$inconsistentQuoteDetails->isEmpty()) {
            $deletedQuoteDetails = DB::table('quote_details')
                ->whereIn('id', $inconsistentQuoteDetails->pluck('id'))
                ->delete();
            
            $this->info("🗑️ Eliminados {$deletedQuoteDetails} quote details inconsistentes.");
        }
        
        // Eliminar market_rates huérfanos
        if (!$orphanedMarketRates->isEmpty()) {
            $deletedMarketRates = DB::table('market_rates')
                ->whereIn('id', $orphanedMarketRates->pluck('id'))
                ->delete();
            
            $this->info("🗑️ Eliminados {$deletedMarketRates} market rates huérfanos.");
        }
        
        // Recalcular totales de market_rates restantes
        $this->recalculateMarketRateTotals();
    }
    
    private function recalculateMarketRateTotals()
    {
        $this->info('💰 Recalculando totales de market rates...');
        
        $marketRates = DB::table('market_rates')->get();
        
        foreach ($marketRates as $marketRate) {
            $total = DB::table('quote_details')
                ->where('market_rate_id', $marketRate->id)
                ->sum(DB::raw('quantity * unit_price'));
            
            DB::table('market_rates')
                ->where('id', $marketRate->id)
                ->update(['total_amount' => $total]);
        }
        
        $this->info('✅ Totales recalculados.');
    }
}