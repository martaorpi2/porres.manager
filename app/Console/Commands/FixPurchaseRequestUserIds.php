<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PurchaseRequest;
use App\Models\GeneralRequest;
use App\Models\ResponsibilityArea;

class FixPurchaseRequestUserIds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'purchase-requests:fix-user-ids';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrige el requesting_user_id de las solicitudes de compra convertidas desde solicitudes generales';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Buscando solicitudes de compra convertidas desde solicitudes generales...');
        
        // Obtener todas las solicitudes de compra que fueron convertidas
        $purchaseRequests = PurchaseRequest::whereNotNull('converted_from_general_request_id')
            ->with(['convertedFromGeneralRequest.area'])
            ->get();
        
        if ($purchaseRequests->isEmpty()) {
            $this->info('No se encontraron solicitudes de compra convertidas.');
            return 0;
        }
        
        $this->info("Se encontraron {$purchaseRequests->count()} solicitudes de compra convertidas.");
        
        $fixed = 0;
        $skipped = 0;
        
        foreach ($purchaseRequests as $purchaseRequest) {
            $generalRequest = $purchaseRequest->convertedFromGeneralRequest;
            
            if (!$generalRequest) {
                $this->warn("Solicitud de compra {$purchaseRequest->request_number} tiene un converted_from_general_request_id inválido.");
                $skipped++;
                continue;
            }
            
            // Obtener el área de la solicitud general
            $area = $generalRequest->area;
            
            if (!$area) {
                $this->warn("Solicitud general {$generalRequest->number} no tiene área asignada.");
                $skipped++;
                continue;
            }
            
            // Obtener el responsable del área
            $responsibleUser = $area->responsibleUser;
            
            if (!$responsibleUser) {
                $this->warn("Área {$area->name} no tiene responsable asignado.");
                $skipped++;
                continue;
            }
            
            // Verificar si el requesting_user_id actual es diferente del responsable del área
            if ($purchaseRequest->requesting_user_id != $responsibleUser->id) {
                $oldUserId = $purchaseRequest->requesting_user_id;
                $oldUser = \App\Models\User::find($oldUserId);
                
                $this->info("Corrigiendo solicitud de compra {$purchaseRequest->request_number}:");
                $this->line("  - Usuario actual: " . ($oldUser ? $oldUser->email : "ID {$oldUserId}"));
                $this->line("  - Usuario correcto: {$responsibleUser->email} (Responsable de área {$area->name})");
                
                $purchaseRequest->requesting_user_id = $responsibleUser->id;
                $purchaseRequest->save();
                
                $this->info("  ✓ Corregido exitosamente.");
                $fixed++;
            } else {
                $this->line("Solicitud de compra {$purchaseRequest->request_number} ya tiene el usuario correcto.");
                $skipped++;
            }
        }
        
        $this->newLine();
        $this->info("Resumen:");
        $this->info("  - Corregidas: {$fixed}");
        $this->info("  - Omitidas: {$skipped}");
        $this->info("  - Total: {$purchaseRequests->count()}");
        
        return 0;
    }
}

