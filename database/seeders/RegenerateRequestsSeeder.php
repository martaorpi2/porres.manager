<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Seeder para regenerar datos de solicitudes relacionadas
 * Ejecutar con: php artisan db:seed --class=RegenerateRequestsSeeder
 */
class RegenerateRequestsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Regenerando solicitudes generales y de compra...');
        
        // Eliminar solicitudes de compra existentes
        \App\Models\PurchaseRequestDetail::query()->delete();
        \App\Models\PurchaseRequest::query()->delete();
        
        // Eliminar solicitudes generales existentes
        \App\Models\GeneralRequestDetail::query()->delete();
        \App\Models\GeneralRequest::query()->delete();
        
        $this->command->info('Solicitudes eliminadas. Creando nuevas...');
        
        // Crear áreas de responsabilidad si no existen
        if (\App\Models\ResponsibilityArea::count() == 0) {
            $this->command->info('Creando áreas de responsabilidad...');
            \App\Models\ResponsibilityArea::create(['name' => 'Informática']);
            \App\Models\ResponsibilityArea::create(['name' => 'Salud']);
            \App\Models\ResponsibilityArea::create(['name' => 'Mantenimiento']);
        }
        
        // Crear solicitudes generales
        $this->createGeneralRequests();
        
        // Crear detalles de solicitudes generales
        $this->createGeneralRequestDetails();
        
        // Crear solicitudes de compra (incluyendo las convertidas desde generales)
        $this->createPurchaseRequests();
        
        // Crear detalles de solicitudes de compra
        $this->createPurchaseRequestDetails();
        
        $this->command->info('¡Solicitudes regeneradas exitosamente!');
    }
    
    private function createGeneralRequests()
    {
        $users = \App\Models\User::all();
        $areas = \App\Models\ResponsibilityArea::all();
        
        if ($users->isEmpty() || $areas->isEmpty()) {
            $this->command->warn('No hay usuarios o áreas disponibles. Creando datos básicos...');
            return;
        }
        
        $requests = [
            [
                'created_by' => $users->random()->id,
                'area_id' => $areas->random()->id,
                'title' => 'Solicitud de Reactivos para Laboratorio Clínico',
                'description' => 'Necesitamos reactivos para análisis de glucosa, colesterol y triglicéridos para las prácticas de los estudiantes de medicina.',
                'priority' => 'Alta',
                'status' => 'creada',
            ],
            [
                'created_by' => $users->random()->id,
                'area_id' => $areas->random()->id,
                'title' => 'Reposición de Guantes de Protección',
                'description' => 'Se requiere reposición de guantes de nitrilo para las prácticas de anatomía y laboratorio.',
                'priority' => 'Media',
                'status' => 'revisada_area',
            ],
            [
                'created_by' => $users->random()->id,
                'area_id' => $areas->random()->id,
                'title' => 'Equipos de Computación para Aula de Informática',
                'description' => 'Solicitud de 5 computadoras para renovar el aula de informática médica.',
                'priority' => 'Baja',
                'status' => 'creada',
            ],
            [
                'created_by' => $users->random()->id,
                'area_id' => $areas->random()->id,
                'title' => 'Material de Limpieza para Laboratorios',
                'description' => 'Necesitamos productos de limpieza especializados para equipos de laboratorio.',
                'priority' => 'Media',
                'status' => 'archivada',
            ],
            [
                'created_by' => $users->random()->id,
                'area_id' => $areas->random()->id,
                'title' => 'Microscopios para Prácticas de Histología',
                'description' => 'Solicitud de 3 microscopios ópticos para las prácticas de histología y citología.',
                'priority' => 'Urgente',
                'status' => 'revisada_area',
            ],
        ];

        foreach ($requests as $request) {
            $request['number'] = \App\Models\GeneralRequest::generateNextNumber();
            \App\Models\GeneralRequest::create($request);
        }
    }
    
    private function createGeneralRequestDetails()
    {
        $generalRequests = \App\Models\GeneralRequest::all();
        $products = \App\Models\Product::all();
        
        if ($generalRequests->isEmpty() || $products->isEmpty()) {
            return;
        }
        
        foreach ($generalRequests as $idx => $generalRequest) {
            $selectedProducts = $products->random(min(2, $products->count()));
            foreach ($selectedProducts as $product) {
                \App\Models\GeneralRequestDetail::create([
                    'general_request_id' => $generalRequest->id,
                    'product_id' => $product->id,
                    'requested_quantity' => rand(5, 20),
                    'specifications' => 'Especificaciones según solicitud',
                    'justification' => 'Necesario para el área',
                    'estimated_unit_price' => rand(100, 1000),
                    'estimated_total' => rand(500, 10000),
                    'status' => 'Pendiente',
                ]);
            }
        }
    }
    
    private function createPurchaseRequests()
    {
        $users = \App\Models\User::all();
        $areas = \App\Models\ResponsibilityArea::all();
        
        if ($users->isEmpty() || $areas->isEmpty()) {
            return;
        }
        
        // Crear algunas solicitudes de compra independientes
        $requests = [
            [
                'request_number' => 'SR-2024-0001',
                'request_date' => now()->subDays(5),
                'status' => 'Aprobada',
                'priority' => 'Media',
                'justification' => 'Equipos informáticos para aulas',
                'observations' => 'Solicitud directa del área de informática',
                'responsibility_area_id' => $areas->first()->id,
                'requesting_user_id' => $users->random()->id,
                'approved_by' => $users->random()->id,
                'approved_date' => now()->subDays(4),
                'total_amount' => 15000.00,
            ],
        ];

        foreach ($requests as $request) {
            \App\Models\PurchaseRequest::create($request);
        }
        
        // Convertir solicitudes generales en solicitudes de compra
        $generalRequests = \App\Models\GeneralRequest::all();
        $convertedCount = 0;
        
        foreach ($generalRequests as $generalRequest) {
            if ($convertedCount < 4) {
                $purchaseRequest = \App\Models\PurchaseRequest::create([
                    'request_number' => \App\Models\PurchaseRequest::generateNextNumber(),
                    'request_date' => now()->subDays(rand(1, 5)),
                    'status' => ['Pendiente', 'Aprobada', 'En Proceso'][rand(0, 2)],
                    'priority' => $generalRequest->priority,
                    'justification' => $generalRequest->description,
                    'observations' => 'Convertida desde solicitud general: ' . $generalRequest->number,
                    'responsibility_area_id' => $generalRequest->area_id,
                    'requesting_user_id' => $generalRequest->created_by,
                    'approved_by' => $users->random()->id,
                    'approved_date' => now()->subDays(rand(1, 3)),
                    'total_amount' => rand(5000, 20000),
                    'converted_from_general_request_id' => $generalRequest->id,
                ]);
                
                $generalRequest->update(['status' => 'convertida_a_compra']);
                $convertedCount++;
            }
        }
    }
    
    private function createPurchaseRequestDetails()
    {
        $requests = \App\Models\PurchaseRequest::all();
        $products = \App\Models\Product::all();
        
        if ($requests->isEmpty() || $products->isEmpty()) {
            return;
        }
        
        foreach ($requests as $request) {
            if ($request->converted_from_general_request_id) {
                // Usar productos de la solicitud general
                $generalRequest = \App\Models\GeneralRequest::find($request->converted_from_general_request_id);
                if ($generalRequest && $generalRequest->details->isNotEmpty()) {
                    foreach ($generalRequest->details as $generalDetail) {
                        \App\Models\PurchaseRequestDetail::create([
                            'purchase_request_id' => $request->id,
                            'product_id' => $generalDetail->product_id,
                            'requested_quantity' => $generalDetail->requested_quantity,
                            'specifications' => $generalDetail->specifications,
                            'justification' => $generalDetail->justification,
                            'estimated_unit_price' => $generalDetail->estimated_unit_price,
                            'estimated_total' => $generalDetail->estimated_total,
                            'status' => 'Pendiente',
                        ]);
                    }
                } else {
                    // Si no tiene detalles, crear algunos genéricos
                    $selectedProducts = $products->random(min(2, $products->count()));
                    foreach ($selectedProducts as $product) {
                        $quantity = rand(1, 10);
                        $unitPrice = rand(100, 1000);
                        \App\Models\PurchaseRequestDetail::create([
                            'purchase_request_id' => $request->id,
                            'product_id' => $product->id,
                            'requested_quantity' => $quantity,
                            'specifications' => 'Especificaciones según solicitud general',
                            'justification' => 'Convertida desde solicitud general',
                            'estimated_unit_price' => $unitPrice,
                            'estimated_total' => $quantity * $unitPrice,
                            'status' => 'Pendiente',
                        ]);
                    }
                }
            } else {
                // Para solicitudes independientes, crear detalles aleatorios
                $selectedProducts = $products->random(min(2, $products->count()));
                foreach ($selectedProducts as $product) {
                    $quantity = rand(1, 10);
                    $unitPrice = rand(100, 1000);
                    \App\Models\PurchaseRequestDetail::create([
                        'purchase_request_id' => $request->id,
                        'product_id' => $product->id,
                        'requested_quantity' => $quantity,
                        'specifications' => 'Especificaciones estándar',
                        'justification' => 'Para uso en el área',
                        'estimated_unit_price' => $unitPrice,
                        'estimated_total' => $quantity * $unitPrice,
                        'status' => 'Pendiente',
                    ]);
                }
            }
        }
    }
}

