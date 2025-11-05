<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SupplierRating;
use App\Models\Supplier;
use App\Models\User;
use App\Models\PurchaseOrder;
use Carbon\Carbon;

class SupplierRatingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Generando calificaciones de ejemplo para proveedores...');

        $suppliers = Supplier::all();
        $users = User::all();
        $purchaseOrders = PurchaseOrder::all();

        if ($suppliers->isEmpty()) {
            $this->command->warn('No hay proveedores disponibles. Creando calificaciones de ejemplo...');
            return;
        }

        if ($users->isEmpty()) {
            $this->command->warn('No hay usuarios disponibles. Creando calificaciones de ejemplo...');
            return;
        }

        // Comentarios de ejemplo variados
        $comments = [
            'Excelente calidad en los productos entregados.',
            'Buen servicio al cliente, siempre responden rápido.',
            'Productos recibidos en tiempo y forma.',
            'Los precios son competitivos.',
            'Calidad aceptable pero podría mejorar el tiempo de entrega.',
            'Muy satisfecho con el servicio en general.',
            'El producto cumplió con las especificaciones solicitadas.',
            'Hubo un pequeño retraso pero el producto es de buena calidad.',
            'Proveedor confiable, recomendado para futuras compras.',
            'La atención al cliente es excelente.',
            'Productos de buena calidad a precios razonables.',
            'Entrega puntual y productos conforme a lo solicitado.',
            'Algunos productos llegaron con defectos menores.',
            'Buen proveedor en general, seguir trabajando con ellos.',
            'Los tiempos de entrega son adecuados.',
            'Calidad superior a lo esperado.',
            'Precios un poco elevados pero calidad justifica el costo.',
            'Servicio profesional y eficiente.',
            'Muy contento con la transacción.',
            'Productos de calidad estándar.',
        ];

        $ratingsGenerated = 0;

        // Generar calificaciones para cada proveedor (2-5 calificaciones por proveedor)
        foreach ($suppliers as $supplier) {
            $numRatings = rand(2, 5);
            
            for ($i = 0; $i < $numRatings; $i++) {
                // Generar calificaciones variadas (algunos proveedores mejor evaluados que otros)
                // Usar distribución normal para que algunos proveedores tengan mejor promedio
                $baseRating = rand(3, 5); // Base entre 3 y 5
                
                // Calificaciones individuales con variación
                $qualityRating = max(1, min(5, $baseRating + rand(-1, 1)));
                $priceRating = max(1, min(5, $baseRating + rand(-2, 0))); // Precio generalmente más bajo
                $deliveryRating = max(1, min(5, $baseRating + rand(-1, 1)));
                $serviceRating = max(1, min(5, $baseRating + rand(-1, 1)));
                $overallRating = max(1, min(5, $baseRating + rand(-1, 1)));

                // Fecha de evaluación aleatoria en los últimos 6 meses
                $evaluationDate = Carbon::now()->subDays(rand(0, 180));

                // Seleccionar una orden de compra aleatoria (opcional)
                $purchaseOrderId = null;
                if ($purchaseOrders->isNotEmpty() && rand(0, 1)) {
                    $purchaseOrderId = $purchaseOrders->random()->id;
                }

                SupplierRating::create([
                    'supplier_id' => $supplier->id,
                    'rated_by' => $users->random()->id,
                    'purchase_order_id' => $purchaseOrderId,
                    'quality_rating' => $qualityRating,
                    'price_rating' => $priceRating,
                    'delivery_time_rating' => $deliveryRating,
                    'service_rating' => $serviceRating,
                    'overall_rating' => $overallRating,
                    'comments' => $comments[array_rand($comments)],
                    'evaluation_date' => $evaluationDate,
                ]);

                $ratingsGenerated++;
            }
        }

        // Generar algunas calificaciones adicionales con diferentes perfiles
        // Algunos proveedores excelentes
        $excellentSuppliers = $suppliers->random(min(3, $suppliers->count()));
        foreach ($excellentSuppliers as $supplier) {
            $numRatings = rand(2, 4);
            for ($i = 0; $i < $numRatings; $i++) {
                SupplierRating::create([
                    'supplier_id' => $supplier->id,
                    'rated_by' => $users->random()->id,
                    'purchase_order_id' => $purchaseOrders->isNotEmpty() && rand(0, 1) ? $purchaseOrders->random()->id : null,
                    'quality_rating' => rand(4, 5),
                    'price_rating' => rand(4, 5),
                    'delivery_time_rating' => rand(4, 5),
                    'service_rating' => rand(4, 5),
                    'overall_rating' => rand(4, 5),
                    'comments' => 'Excelente proveedor, muy recomendado.',
                    'evaluation_date' => Carbon::now()->subDays(rand(0, 120)),
                ]);
                $ratingsGenerated++;
            }
        }

        // Algunos proveedores con problemas
        $problematicSuppliers = $suppliers->diff($excellentSuppliers)->random(min(2, max(1, $suppliers->count() - 3)));
        foreach ($problematicSuppliers as $supplier) {
            $numRatings = rand(1, 2);
            for ($i = 0; $i < $numRatings; $i++) {
                SupplierRating::create([
                    'supplier_id' => $supplier->id,
                    'rated_by' => $users->random()->id,
                    'purchase_order_id' => $purchaseOrders->isNotEmpty() && rand(0, 1) ? $purchaseOrders->random()->id : null,
                    'quality_rating' => rand(1, 3),
                    'price_rating' => rand(2, 3),
                    'delivery_time_rating' => rand(1, 2),
                    'service_rating' => rand(2, 3),
                    'overall_rating' => rand(2, 3),
                    'comments' => 'Hubo algunos problemas con la entrega y calidad.',
                    'evaluation_date' => Carbon::now()->subDays(rand(0, 150)),
                ]);
                $ratingsGenerated++;
            }
        }

        $this->command->info("¡Se generaron {$ratingsGenerated} calificaciones de ejemplo!");
    }
}

