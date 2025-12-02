<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\GeneralRequest;

class FixDeliveryStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:general-request-status {--user-id= : ID del usuario específico}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrige el status unificado de las solicitudes generales basándose en las entregas reales';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->option('user-id');
        
        $query = GeneralRequest::with('details.product', 'deliveries.details');
        
        if ($userId) {
            $query->where('created_by', $userId);
            $this->info("Corrigiendo delivery_status para el usuario ID: {$userId}");
        } else {
            $this->info("Corrigiendo delivery_status para todas las solicitudes generales");
        }
        
        $generalRequests = $query->get();
        $fixed = 0;
        
        foreach ($generalRequests as $generalRequest) {
            $hasDetails = false;
            $hasAnyDelivery = false;
            $allDelivered = true;
            
            // Verificar el estado de entrega de cada producto
            foreach ($generalRequest->details as $detail) {
                $requestedQty = $detail->requested_quantity ?? 0;
                
                if ($requestedQty <= 0) {
                    continue;
                }
                
                $hasDetails = true;
                
                // Calcular cantidad entregada
                $deliveredQty = 0;
                foreach ($generalRequest->deliveries as $delivery) {
                    $deliveryDetail = $delivery->details->where('product_id', $detail->product_id)->first();
                    if ($deliveryDetail) {
                        $deliveredQty += $deliveryDetail->delivered_quantity ?? 0;
                    }
                }
                
                if ($deliveredQty > 0) {
                    $hasAnyDelivery = true;
                }
                
                // Si este producto no está completamente entregado, entonces no todos están entregados
                if ($deliveredQty < $requestedQty) {
                    $allDelivered = false;
                }
            }
            
            // Determinar el status correcto
            // No actualizar si está archivada
            if ($generalRequest->status === 'archivada') {
                continue;
            }
            
            $correctStatus = 'sin_entrega';
            if ($hasDetails && $hasAnyDelivery) {
                $correctStatus = $allDelivered ? 'entregada_totalmente' : 'entregada_parcialmente';
            } else {
                // Si no hay entregas, mantener el estado del flujo si existe
                if (in_array($generalRequest->status, ['creada', 'revisada_area'])) {
                    $correctStatus = $generalRequest->status;
                }
            }
            
            // Actualizar si es diferente
            if ($generalRequest->status !== $correctStatus) {
                $oldStatus = $generalRequest->status ?? 'NULL';
                $generalRequest->status = $correctStatus;
                $generalRequest->save();
                
                $this->info("Solicitud #{$generalRequest->id} ({$generalRequest->number}): {$oldStatus} -> {$correctStatus}");
                $fixed++;
            }
        }
        
        $this->info("\nTotal de solicitudes corregidas: {$fixed}");
        
        return 0;
    }
}

