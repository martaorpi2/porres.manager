<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Delivery;
use App\Models\DeliveryDetail;
use App\Models\Reception;
use App\Models\GeneralRequest;
use App\Models\GeneralRequestDetail;
use App\Models\User;
use App\Models\Product;
use Carbon\Carbon;

class DeliverySeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Este seeder crea entregas relacionando recepciones existentes con solicitudes generales existentes.
     */
    public function run(): void
    {
        $this->command->info('Generando entregas de ejemplo...');
        
        // Obtener recepciones existentes
        $receptions = Reception::all();
        if ($receptions->isEmpty()) {
            $this->command->warn('No hay recepciones disponibles. Por favor, ejecuta primero los seeders que crean recepciones.');
            return;
        }
        
        // Obtener solicitudes generales existentes
        $generalRequests = GeneralRequest::all();
        if ($generalRequests->isEmpty()) {
            $this->command->warn('No hay solicitudes generales disponibles. Por favor, ejecuta primero los seeders que crean solicitudes generales.');
            return;
        }
        
        // Obtener usuarios
        $users = User::all();
        if ($users->isEmpty()) {
            $this->command->warn('No hay usuarios disponibles. Por favor, crea usuarios primero.');
            return;
        }
        
        // Obtener productos
        $products = Product::all();
        if ($products->isEmpty()) {
            $this->command->warn('No hay productos disponibles. Por favor, ejecuta primero los seeders que crean productos.');
            return;
        }
        
        // Limpiar entregas anteriores del seeder si existen
        $this->command->info('Limpiando entregas anteriores del seeder...');
        try {
            DeliveryDetail::whereHas('delivery', function($query) {
                $query->where('observations', 'like', '%Ejemplo de entrega%');
            })->delete();
            
            Delivery::where('observations', 'like', '%Ejemplo de entrega%')->delete();
        } catch (\Exception $e) {
            $this->command->warn('Error al limpiar entregas anteriores: ' . $e->getMessage());
        }
        
        // Crear entregas relacionando recepciones con solicitudes generales
        $this->command->info('Creando entregas...');
        
        $deliveryCount = 0;
        $maxDeliveries = min(5, $receptions->count(), $generalRequests->count());
        
        // Seleccionar recepciones y solicitudes generales aleatoriamente
        $selectedReceptions = $receptions->random(min($maxDeliveries, $receptions->count()));
        $selectedGeneralRequests = $generalRequests->random(min($maxDeliveries, $generalRequests->count()));
        
        foreach ($selectedReceptions as $index => $reception) {
            if ($index >= $selectedGeneralRequests->count()) {
                break;
            }
            
            $generalRequest = $selectedGeneralRequests[$index];
            
            // Obtener el usuario que creó la solicitud general (received_by)
            $receivedBy = User::find($generalRequest->created_by);
            if (!$receivedBy) {
                $receivedBy = $users->random();
            }
            
            // Seleccionar un usuario aleatorio para delivered_by (responsable del depósito)
            $deliveredBy = $users->random();
            
            // Crear la entrega
            $delivery = Delivery::create([
                'reception_id' => $reception->id,
                'general_request_id' => $generalRequest->id,
                'delivery_date' => Carbon::now()->subDays(rand(1, 10)),
                'delivered_by' => $deliveredBy->id,
                'received_by' => $receivedBy->id,
                'observations' => 'Ejemplo de entrega relacionando recepción ' . $reception->number . ' con solicitud general ' . $generalRequest->number,
                'status' => $this->getRandomStatus(),
            ]);
            
            // Obtener los productos de la solicitud general
            $generalRequestDetails = GeneralRequestDetail::where('general_request_id', $generalRequest->id)->get();
            
            if ($generalRequestDetails->isEmpty()) {
                // Si no hay detalles en la solicitud general, usar productos aleatorios
                $selectedProducts = $products->random(min(2, $products->count()));
                
                foreach ($selectedProducts as $product) {
                    DeliveryDetail::create([
                        'delivery_id' => $delivery->id,
                        'product_id' => $product->id,
                        'delivered_quantity' => rand(1, 10),
                        'observations' => 'Producto entregado desde recepción ' . $reception->number,
                    ]);
                }
            } else {
                // Usar los productos de la solicitud general
                foreach ($generalRequestDetails as $detail) {
                    if ($detail->product_id) {
                        // La cantidad entregada puede ser menor o igual a la solicitada
                        $deliveredQuantity = min(
                            $detail->requested_quantity,
                            rand(1, $detail->requested_quantity)
                        );
                        
                        DeliveryDetail::create([
                            'delivery_id' => $delivery->id,
                            'product_id' => $detail->product_id,
                            'delivered_quantity' => $deliveredQuantity,
                            'observations' => 'Producto entregado según solicitud general ' . $generalRequest->number,
                        ]);
                    }
                }
            }
            
            $deliveryCount++;
            $this->command->info("  ✓ Creada entrega {$delivery->number} (Recepción: {$reception->number}, Solicitud: {$generalRequest->number})");
        }
        
        $this->command->info('');
        $this->command->info("¡Se crearon {$deliveryCount} entregas exitosamente!");
        $this->command->info('');
        $this->command->info('Resumen:');
        $this->command->info("- {$deliveryCount} Entregas creadas");
        $this->command->info("- Cada entrega relaciona una recepción con una solicitud general");
        $this->command->info("- Los detalles de entrega incluyen productos de las solicitudes generales");
    }
    
    /**
     * Obtener un estado aleatorio para la entrega
     */
    private function getRandomStatus(): string
    {
        $statuses = ['pendiente', 'entregada', 'cancelada'];
        $weights = [20, 70, 10]; // 20% pendiente, 70% entregada, 10% cancelada
        
        $random = rand(1, 100);
        $cumulative = 0;
        
        foreach ($statuses as $index => $status) {
            $cumulative += $weights[$index];
            if ($random <= $cumulative) {
                return $status;
            }
        }
        
        return 'entregada'; // Por defecto
    }
}

