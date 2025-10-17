<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StockLevel;
use App\Models\Product;
use App\Models\Location;
use App\Models\User;

class StockLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creando niveles de stock para depósitos de áreas de responsabilidad...');

        // Obtener un usuario para last_updated_by
        $user = User::first();

        // Definir las 4 ubicaciones que son depósitos de áreas de responsabilidad
        $responsibilityLocations = [
            'Insumos Generales',
            'Mantenimiento', 
            'Insumos de Salud',
            'Informática'
        ];

        // Obtener los depósitos de responsabilidad
        $locations = Location::whereIn('name', $responsibilityLocations)->get();
        
        if ($locations->isEmpty()) {
            $this->command->warn('No se encontraron los depósitos de responsabilidad. Creándolos...');
            
            foreach ($responsibilityLocations as $locationName) {
                Location::create([
                    'name' => $locationName,
                    'description' => 'Depósito de área de responsabilidad'
                ]);
            }
            
            $locations = Location::whereIn('name', $responsibilityLocations)->get();
        }

        // Obtener algunos productos para crear stock
        $products = Product::take(20)->get();
        
        if ($products->isEmpty()) {
            $this->command->warn('No hay productos disponibles. Creando productos básicos...');
            
            $basicProducts = [
                ['name' => 'Producto de Prueba 1', 'description' => 'Producto básico para testing'],
                ['name' => 'Producto de Prueba 2', 'description' => 'Producto básico para testing'],
                ['name' => 'Producto de Prueba 3', 'description' => 'Producto básico para testing'],
                ['name' => 'Producto de Prueba 4', 'description' => 'Producto básico para testing'],
                ['name' => 'Producto de Prueba 5', 'description' => 'Producto básico para testing'],
            ];
            
            foreach ($basicProducts as $productData) {
                Product::create($productData);
            }
            
            $products = Product::take(20)->get();
        }

        // Limpiar stock levels existentes
        StockLevel::truncate();

        // Crear niveles de stock para cada depósito de responsabilidad
        $stockLevels = [];
        
        foreach ($locations as $location) {
            $this->command->info("Creando stock para: {$location->name}");
            
            // Asignar productos aleatoriamente a cada depósito
            $productsForLocation = $products->random(rand(3, 8));
            
            foreach ($productsForLocation as $product) {
                $stockLevels[] = [
                    'product_id' => $product->id,
                    'location_id' => $location->id,
                    'quantity' => rand(5, 100), // Cantidad aleatoria entre 5 y 100
                    'last_updated_by' => $user ? $user->id : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Insertar todos los niveles de stock
        StockLevel::insert($stockLevels);

        $this->command->info('Niveles de stock creados exitosamente:');
        $this->command->info('- Total de registros: ' . count($stockLevels));
        $this->command->info('- Depósitos utilizados: ' . $locations->count());
        $this->command->info('- Productos utilizados: ' . $products->count());
    }
}