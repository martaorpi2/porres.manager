<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder para limpiar órdenes de compra con formato antiguo OC-TRAZ
 * Ejecutar con: php artisan db:seed --class=CleanOCTRAZSeeder
 */
class CleanOCTRAZSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Limpiando órdenes de compra con formato OC-TRAZ...');
        
        // Buscar órdenes de compra con formato OC-TRAZ (variaciones posibles)
        $oldPurchaseOrders = DB::table('purchase_orders')
            ->where(function($query) {
                $query->where('number', 'like', 'OC-TRAZ-%')
                      ->orWhere('number', 'like', 'OC-Traz-%')
                      ->orWhere('number', 'like', 'OC-traz-%')
                      ->orWhere('number', 'like', 'oc-TRAZ-%');
            })
            ->get(['id', 'number']);
        
        if ($oldPurchaseOrders->isEmpty()) {
            $this->command->info('No se encontraron órdenes de compra con formato OC-TRAZ.');
            $this->command->info('Mostrando todas las órdenes de compra para verificación:');
            $allOrders = DB::table('purchase_orders')->select('id', 'number')->orderBy('id', 'desc')->limit(20)->get();
            foreach ($allOrders as $order) {
                $this->command->info('  - ID: ' . $order->id . ' | Número: ' . $order->number);
            }
            return;
        }
        
        $this->command->info('Encontradas ' . $oldPurchaseOrders->count() . ' órdenes de compra con formato OC-TRAZ:');
        foreach ($oldPurchaseOrders as $order) {
            $this->command->info('  - ' . $order->number . ' (ID: ' . $order->id . ')');
        }
        
        $orderIds = $oldPurchaseOrders->pluck('id');
        
        // Eliminar en orden inverso de dependencias
        $deletedCount = 0;
        
        // 1. Eliminar devoluciones relacionadas
        $deletedDevolutions = DB::table('devolutions')->whereIn('reception_id', function($query) use ($orderIds) {
            $query->select('id')->from('receptions')
                ->whereIn('purchase_order_id', $orderIds);
        })->delete();
        $deletedCount += $deletedDevolutions;
        if ($deletedDevolutions > 0) {
            $this->command->info('Eliminadas ' . $deletedDevolutions . ' devoluciones.');
        }
        
        // 2. Eliminar recepciones relacionadas
        $deletedReceptions = DB::table('receptions')->whereIn('purchase_order_id', $orderIds)->delete();
        $deletedCount += $deletedReceptions;
        if ($deletedReceptions > 0) {
            $this->command->info('Eliminadas ' . $deletedReceptions . ' recepciones.');
        }
        
        // 3. Eliminar detalles de órdenes de pago relacionadas
        $deletedOPDetails = DB::table('op_details')->whereIn('payment_order_id', function($query) use ($orderIds) {
            $query->select('id')->from('payment_orders')
                ->whereIn('purchase_order_id', $orderIds);
        })->delete();
        $deletedCount += $deletedOPDetails;
        if ($deletedOPDetails > 0) {
            $this->command->info('Eliminados ' . $deletedOPDetails . ' detalles de órdenes de pago.');
        }
        
        // 4. Eliminar órdenes de pago relacionadas
        $deletedPaymentOrders = DB::table('payment_orders')->whereIn('purchase_order_id', $orderIds)->delete();
        $deletedCount += $deletedPaymentOrders;
        if ($deletedPaymentOrders > 0) {
            $this->command->info('Eliminadas ' . $deletedPaymentOrders . ' órdenes de pago.');
        }
        
        // 5. Eliminar detalles de órdenes de compra
        $deletedOCDetails = DB::table('oc_details')->whereIn('purchase_order_id', $orderIds)->delete();
        $deletedCount += $deletedOCDetails;
        if ($deletedOCDetails > 0) {
            $this->command->info('Eliminados ' . $deletedOCDetails . ' detalles de órdenes de compra.');
        }
        
        // 6. Eliminar órdenes de compra
        $deletedPurchaseOrders = DB::table('purchase_orders')->whereIn('id', $orderIds)->delete();
        $deletedCount += $deletedPurchaseOrders;
        if ($deletedPurchaseOrders > 0) {
            $this->command->info('Eliminadas ' . $deletedPurchaseOrders . ' órdenes de compra.');
        }
        
        $this->command->info('');
        $this->command->info('¡Limpieza completada! Total de registros eliminados: ' . $deletedCount);
    }
}

