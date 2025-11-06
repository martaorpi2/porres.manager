<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder para limpiar órdenes de pago con formato antiguo OP-TRAZ
 * Ejecutar con: php artisan db:seed --class=CleanOPTRAZSeeder
 */
class CleanOPTRAZSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Limpiando órdenes de pago con formato OP-TRAZ...');
        
        // Buscar órdenes de pago con formato OP-TRAZ (variaciones posibles)
        $oldPaymentOrders = DB::table('payment_orders')
            ->where(function($query) {
                $query->where('payment_number', 'like', 'OP-TRAZ-%')
                      ->orWhere('payment_number', 'like', 'OP-Traz-%')
                      ->orWhere('payment_number', 'like', 'OP-traz-%')
                      ->orWhere('payment_number', 'like', 'op-TRAZ-%');
            })
            ->get(['id', 'payment_number']);
        
        if ($oldPaymentOrders->isEmpty()) {
            $this->command->info('No se encontraron órdenes de pago con formato OP-TRAZ.');
            $this->command->info('Mostrando todas las órdenes de pago para verificación:');
            $allOrders = DB::table('payment_orders')->select('id', 'payment_number')->orderBy('id', 'desc')->limit(20)->get();
            foreach ($allOrders as $order) {
                $this->command->info('  - ID: ' . $order->id . ' | Número: ' . $order->payment_number);
            }
            return;
        }
        
        $this->command->info('Encontradas ' . $oldPaymentOrders->count() . ' órdenes de pago con formato OP-TRAZ:');
        foreach ($oldPaymentOrders as $order) {
            $this->command->info('  - ' . $order->payment_number . ' (ID: ' . $order->id . ')');
        }
        
        $orderIds = $oldPaymentOrders->pluck('id');
        
        // Eliminar en orden inverso de dependencias
        $deletedCount = 0;
        
        // 1. Eliminar detalles de órdenes de pago
        $deletedOPDetails = DB::table('op_details')->whereIn('payment_order_id', $orderIds)->delete();
        $deletedCount += $deletedOPDetails;
        if ($deletedOPDetails > 0) {
            $this->command->info('Eliminados ' . $deletedOPDetails . ' detalles de órdenes de pago.');
        }
        
        // 2. Eliminar órdenes de pago
        $deletedPaymentOrders = DB::table('payment_orders')->whereIn('id', $orderIds)->delete();
        $deletedCount += $deletedPaymentOrders;
        if ($deletedPaymentOrders > 0) {
            $this->command->info('Eliminadas ' . $deletedPaymentOrders . ' órdenes de pago.');
        }
        
        $this->command->info('');
        $this->command->info('¡Limpieza completada! Total de registros eliminados: ' . $deletedCount);
    }
}

