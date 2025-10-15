<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Application;
use App\Models\Devolution;
use App\Models\MarketRate;
use App\Models\QuoteDetail;
use App\Models\Reception;
use App\Models\RequestDetail;
use App\Models\User;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use Carbon\Carbon;

class AdditionalTestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creando datos de prueba adicionales...');

        // Obtener datos existentes necesarios
        $users = User::all();
        $products = Product::all();
        $suppliers = Supplier::all();
        $purchaseOrders = PurchaseOrder::all();

        if ($users->isEmpty() || $products->isEmpty() || $suppliers->isEmpty() || $purchaseOrders->isEmpty()) {
            $this->command->error('Error: Se requieren datos básicos (usuarios, productos, proveedores, órdenes de compra) para crear los datos adicionales.');
            $this->command->info('Ejecute primero: php artisan db:seed --class=TestDataSeeder');
            $this->command->info('Y luego: php artisan db:seed --class=InventorySeeder');
            return;
        }

        // 1. Crear Aplicaciones (Applications)
        $this->command->info('Creando Aplicaciones...');
        $applications = [
            [
                'status' => 'Aprobada',
                'user_id' => $users->first()->id,
                'created_at' => now()->subDays(15),
                'updated_at' => now()->subDays(10)
            ],
            [
                'status' => 'Pendiente',
                'user_id' => $users->first()->id,
                'created_at' => now()->subDays(8),
                'updated_at' => now()->subDays(8)
            ],
            [
                'status' => 'Rechazada',
                'user_id' => $users->first()->id,
                'created_at' => now()->subDays(20),
                'updated_at' => now()->subDays(18)
            ],
            [
                'status' => 'Aprobada',
                'user_id' => $users->first()->id,
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(3)
            ],
            [
                'status' => 'Pendiente',
                'user_id' => $users->first()->id,
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2)
            ]
        ];

        $applicationsCreated = [];
        foreach ($applications as $application) {
            $applicationsCreated[] = Application::create($application);
        }

        // 2. Crear Detalles de Solicitudes (Request Details)
        $this->command->info('Creando Detalles de Solicitudes...');
        $requestDetails = [
            ['application_id' => 1, 'product_id' => 1, 'quantity' => 10],
            ['application_id' => 1, 'product_id' => 2, 'quantity' => 5],
            ['application_id' => 1, 'product_id' => 3, 'quantity' => 3],
            ['application_id' => 2, 'product_id' => 4, 'quantity' => 8],
            ['application_id' => 2, 'product_id' => 5, 'quantity' => 12],
            ['application_id' => 3, 'product_id' => 6, 'quantity' => 15],
            ['application_id' => 4, 'product_id' => 7, 'quantity' => 6],
            ['application_id' => 4, 'product_id' => 8, 'quantity' => 4],
            ['application_id' => 5, 'product_id' => 9, 'quantity' => 20],
            ['application_id' => 5, 'product_id' => 10, 'quantity' => 7]
        ];

        foreach ($requestDetails as $detail) {
            RequestDetail::create($detail);
        }

        // 3. Crear Tasas de Mercado (Market Rates)
        $this->command->info('Creando Tasas de Mercado...');
        $marketRates = [
            [
                'supplier_id' => $suppliers->first()->id,
                'application_id' => 1,
                'date' => now()->subDays(12),
                'total_amount' => 1250.50,
                'created_at' => now()->subDays(12),
                'updated_at' => now()->subDays(12)
            ],
            [
                'supplier_id' => $suppliers->skip(1)->first()->id,
                'application_id' => 2,
                'date' => now()->subDays(6),
                'total_amount' => 890.75,
                'created_at' => now()->subDays(6),
                'updated_at' => now()->subDays(6)
            ],
            [
                'supplier_id' => $suppliers->skip(2)->first()->id,
                'application_id' => 4,
                'date' => now()->subDays(3),
                'total_amount' => 2100.00,
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3)
            ],
            [
                'supplier_id' => $suppliers->skip(3)->first()->id,
                'application_id' => 5,
                'date' => now()->subDays(1),
                'total_amount' => 675.25,
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1)
            ]
        ];

        $marketRatesCreated = [];
        foreach ($marketRates as $rate) {
            $marketRatesCreated[] = MarketRate::create($rate);
        }

        // 4. Crear Detalles de Cotizaciones (Quote Details)
        $this->command->info('Creando Detalles de Cotizaciones...');
        $quoteDetails = [
            // Cotización 1
            ['market_rate_id' => 1, 'product_id' => 1, 'quantity' => 10, 'unit_price' => 45.50],
            ['market_rate_id' => 1, 'product_id' => 2, 'quantity' => 5, 'unit_price' => 85.00],
            ['market_rate_id' => 1, 'product_id' => 3, 'quantity' => 3, 'unit_price' => 120.00],
            
            // Cotización 2
            ['market_rate_id' => 2, 'product_id' => 4, 'quantity' => 8, 'unit_price' => 25.00],
            ['market_rate_id' => 2, 'product_id' => 5, 'quantity' => 12, 'unit_price' => 15.75],
            
            // Cotización 3
            ['market_rate_id' => 3, 'product_id' => 7, 'quantity' => 6, 'unit_price' => 12.00],
            ['market_rate_id' => 3, 'product_id' => 8, 'quantity' => 4, 'unit_price' => 25.50],
            ['market_rate_id' => 3, 'product_id' => 9, 'quantity' => 2, 'unit_price' => 45.00],
            
            // Cotización 4
            ['market_rate_id' => 4, 'product_id' => 10, 'quantity' => 7, 'unit_price' => 35.00],
            ['market_rate_id' => 4, 'product_id' => 11, 'quantity' => 3, 'unit_price' => 120.00]
        ];

        foreach ($quoteDetails as $detail) {
            QuoteDetail::create($detail);
        }

        // 5. Crear Recepciones (Receptions)
        $this->command->info('Creando Recepciones...');
        $receptions = [
            [
                'purchase_order_id' => $purchaseOrders->first()->id,
                'date' => now()->subDays(3),
                'according' => 'Si',
                'area_manager_id' => $users->first()->id,
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3)
            ],
            [
                'purchase_order_id' => $purchaseOrders->skip(2)->first()->id,
                'date' => now()->subDays(1),
                'according' => 'No',
                'area_manager_id' => $users->first()->id,
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1)
            ],
            [
                'purchase_order_id' => $purchaseOrders->skip(1)->first()->id,
                'date' => now()->subDays(5),
                'according' => 'Si',
                'area_manager_id' => $users->first()->id,
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5)
            ]
        ];

        $receptionsCreated = [];
        foreach ($receptions as $reception) {
            $receptionsCreated[] = Reception::create($reception);
        }

        // 6. Crear Devoluciones (Devolutions)
        $this->command->info('Creando Devoluciones...');
        $devolutions = [
            [
                'reception_id' => 2, // Recepción que no estuvo conforme
                'reason' => 'Productos recibidos en mal estado. Algunos artículos presentaban daños en el empaque y otros estaban vencidos.',
                'amount_returned' => 240.00,
                'date' => now()->subDays(1),
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1)
            ],
            [
                'reception_id' => 1,
                'reason' => 'Cantidad incorrecta recibida. Se solicitó 10 unidades pero solo se recibieron 8.',
                'amount_returned' => 91.00,
                'date' => now()->subDays(2),
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2)
            ]
        ];

        foreach ($devolutions as $devolution) {
            Devolution::create($devolution);
        }

        $this->command->info('¡Datos de prueba adicionales creados exitosamente!');
        $this->command->info('Resumen:');
        $this->command->info('- ' . count($applications) . ' Aplicaciones');
        $this->command->info('- ' . count($requestDetails) . ' Detalles de Solicitudes');
        $this->command->info('- ' . count($marketRates) . ' Tasas de Mercado');
        $this->command->info('- ' . count($quoteDetails) . ' Detalles de Cotizaciones');
        $this->command->info('- ' . count($receptions) . ' Recepciones');
        $this->command->info('- ' . count($devolutions) . ' Devoluciones');
    }
}
