<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestDetail;
use App\Models\MarketRate;
use App\Models\QuoteDetail;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\ResponsibilityArea;
use App\Models\User;

class PurchaseRequestQuoteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear datos de ejemplo que cumplan con la restricción
        
        // 1. Crear algunas solicitudes de compra con productos
        $this->createPurchaseRequestsWithProducts();
        
        // 2. Crear cotizaciones solo para productos que están en solicitudes de compra
        $this->createMarketRatesForPurchaseRequestProducts();
    }

    private function createPurchaseRequestsWithProducts()
    {
        // Obtener datos existentes o crear por defecto
        $area = ResponsibilityArea::first() ?? ResponsibilityArea::create([
            'name' => 'Área de Ejemplo',
            'description' => 'Área de responsabilidad de ejemplo'
        ]);

        $user = User::first() ?? User::create([
            'name' => 'Usuario Ejemplo',
            'email' => 'ejemplo@test.com',
            'password' => bcrypt('password')
        ]);

        // Crear productos de ejemplo
        $products = [];
        for ($i = 1; $i <= 5; $i++) {
            $products[] = Product::create([
                'name' => "Producto Ejemplo $i",
                'description' => "Descripción del producto ejemplo $i",
                'unit_measurement' => 'unidad',
                'category_id' => 1,
                'minimum_stock' => 10
            ]);
        }

        // Crear solicitudes de compra con estos productos
        for ($i = 1; $i <= 3; $i++) {
            $purchaseRequest = PurchaseRequest::create([
                'request_number' => 'SC-2024-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'request_date' => now()->subDays($i),
                'status' => 'Pendiente',
                'priority' => 'Media',
                'justification' => "Solicitud de compra ejemplo $i",
                'responsibility_area_id' => $area->id,
                'requesting_user_id' => $user->id,
                'total_amount' => 0
            ]);

            // Agregar algunos productos a cada solicitud
            $selectedProducts = collect($products)->random(rand(2, 4));
            $totalAmount = 0;

            foreach ($selectedProducts as $product) {
                $quantity = rand(1, 10);
                $unitPrice = rand(100, 1000);
                $estimatedTotal = $quantity * $unitPrice;
                $totalAmount += $estimatedTotal;

                PurchaseRequestDetail::create([
                    'purchase_request_id' => $purchaseRequest->id,
                    'product_id' => $product->id,
                    'requested_quantity' => $quantity,
                    'specifications' => "Especificaciones para {$product->name}",
                    'estimated_unit_price' => $unitPrice,
                    'estimated_total' => $estimatedTotal,
                    'status' => 'Pendiente'
                ]);
            }

            // Actualizar el monto total
            $purchaseRequest->update(['total_amount' => $totalAmount]);
        }
    }

    private function createMarketRatesForPurchaseRequestProducts()
    {
        // Obtener proveedores existentes o crear uno
        $supplier = Supplier::first() ?? Supplier::create([
            'company_name' => 'Proveedor Ejemplo',
            'contact_name' => 'Contacto Ejemplo',
            'email' => 'proveedor@ejemplo.com',
            'phone' => '123456789',
            'address' => 'Dirección ejemplo'
        ]);

        // Obtener todos los productos que están en solicitudes de compra
        $productsInPurchaseRequests = PurchaseRequestDetail::with('product')
            ->get()
            ->pluck('product')
            ->unique('id');

        if ($productsInPurchaseRequests->isEmpty()) {
            $this->command->warn('No hay productos en solicitudes de compra para crear cotizaciones.');
            return;
        }

        // Crear cotizaciones para estos productos
        for ($i = 1; $i <= 2; $i++) {
            $marketRate = MarketRate::create([
                'supplier_id' => $supplier->id,
                'application_id' => 1, // Asumiendo que existe
                'date' => now()->subDays($i),
                'total_amount' => 0
            ]);

            $totalAmount = 0;
            $selectedProducts = $productsInPurchaseRequests->random(rand(2, min(4, $productsInPurchaseRequests->count())));

            foreach ($selectedProducts as $product) {
                $quantity = rand(1, 5);
                $unitPrice = rand(80, 1200); // Precio diferente al estimado
                $subtotal = $quantity * $unitPrice;
                $totalAmount += $subtotal;

                QuoteDetail::create([
                    'market_rate_id' => $marketRate->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice
                ]);
            }

            // Actualizar el monto total
            $marketRate->update(['total_amount' => $totalAmount]);
        }

        $this->command->info('Cotizaciones creadas exitosamente para productos en solicitudes de compra.');
    }
}