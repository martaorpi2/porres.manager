<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder para crear solicitudes generales que NO estén convertidas
 * Ejecutar con: php artisan db:seed --class=GeneralRequestsNotConvertedSeeder
 */
class GeneralRequestsNotConvertedSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creando solicitudes generales no convertidas...');
        
        $users = \App\Models\User::all();
        $areas = \App\Models\ResponsibilityArea::all();
        $products = \App\Models\Product::all();
        
        if ($users->isEmpty() || $areas->isEmpty()) {
            $this->command->warn('No hay usuarios o áreas disponibles. Creando datos básicos...');
            return;
        }
        
        // Crear solicitudes generales con diferentes estados (ninguna convertida)
        $requests = [
            [
                'created_by' => $users->random()->id,
                'area_id' => $areas->random()->id,
                'title' => 'Solicitud de Material Didáctico para Aulas',
                'description' => 'Necesitamos material didáctico actualizado para las clases de anatomía y fisiología.',
                'priority' => 'Alta',
                'status' => 'creada',
            ],
            [
                'created_by' => $users->random()->id,
                'area_id' => $areas->random()->id,
                'title' => 'Reposición de Equipos de Protección Personal',
                'description' => 'Se requiere reposición de batas, mascarillas y protectores oculares para laboratorios.',
                'priority' => 'Urgente',
                'status' => 'revisada_area',
            ],
            [
                'created_by' => $users->random()->id,
                'area_id' => $areas->random()->id,
                'title' => 'Equipamiento para Sala de Simulación',
                'description' => 'Solicitud de maniquíes y simuladores para prácticas de enfermería y medicina.',
                'priority' => 'Media',
                'status' => 'creada',
            ],
            [
                'created_by' => $users->random()->id,
                'area_id' => $areas->random()->id,
                'title' => 'Mobiliario para Biblioteca',
                'description' => 'Necesitamos estanterías y mesas nuevas para la biblioteca del instituto.',
                'priority' => 'Baja',
                'status' => 'revisada_area',
            ],
            [
                'created_by' => $users->random()->id,
                'area_id' => $areas->random()->id,
                'title' => 'Insumos para Laboratorio de Química',
                'description' => 'Solicitud de reactivos y materiales de vidrio para prácticas de química orgánica.',
                'priority' => 'Alta',
                'status' => 'creada',
            ],
            [
                'created_by' => $users->random()->id,
                'area_id' => $areas->random()->id,
                'title' => 'Equipos de Aire Acondicionado',
                'description' => 'Instalación de aires acondicionados en las aulas principales.',
                'priority' => 'Media',
                'status' => 'revisada_area',
            ],
            [
                'created_by' => $users->random()->id,
                'area_id' => $areas->random()->id,
                'title' => 'Material de Oficina y Papelería',
                'description' => 'Reposición de material de oficina para todas las áreas administrativas.',
                'priority' => 'Baja',
                'status' => 'creada',
            ],
            [
                'created_by' => $users->random()->id,
                'area_id' => $areas->random()->id,
                'title' => 'Equipos de Proyección y Audio',
                'description' => 'Solicitud de proyectores y sistemas de audio para mejorar las clases.',
                'priority' => 'Media',
                'status' => 'revisada_area',
            ],
        ];

        $createdRequests = [];
        foreach ($requests as $request) {
            $request['number'] = \App\Models\GeneralRequest::generateNextNumber();
            $generalRequest = \App\Models\GeneralRequest::create($request);
            $createdRequests[] = $generalRequest;
        }
        
        // Agregar productos/detalles a las solicitudes creadas
        if (!$products->isEmpty()) {
            foreach ($createdRequests as $generalRequest) {
                $selectedProducts = $products->random(min(rand(1, 3), $products->count()));
                foreach ($selectedProducts as $product) {
                    $quantity = rand(5, 25);
                    $unitPrice = rand(100, 1500);
                    $total = $quantity * $unitPrice;
                    
                    \App\Models\GeneralRequestDetail::create([
                        'general_request_id' => $generalRequest->id,
                        'product_id' => $product->id,
                        'requested_quantity' => $quantity,
                        'specifications' => 'Especificaciones según solicitud ' . $generalRequest->number,
                        'justification' => 'Necesario para ' . ($generalRequest->title ?? 'el área'),
                        'estimated_unit_price' => $unitPrice,
                        'estimated_total' => $total,
                        'status' => 'Pendiente',
                    ]);
                }
            }
        }
        
        $this->command->info('¡Se crearon ' . count($createdRequests) . ' solicitudes generales no convertidas exitosamente!');
    }
}

