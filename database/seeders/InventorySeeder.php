<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Product;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\InventoryMovement;
use App\Models\User;
use Carbon\Carbon;

class InventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creando datos de inventario...');

        // 1. Crear Categorías
        $this->command->info('Creando Categorías...');
        $categorias = [
            ['name' => 'Alimentos y Bebidas'],
            ['name' => 'Limpieza y Aseo'],
            ['name' => 'Oficina y Papelería'],
            ['name' => 'Tecnología'],
            ['name' => 'Construcción y Mantenimiento'],
            ['name' => 'Medicamentos'],
            ['name' => 'Textiles'],
            ['name' => 'Herramientas'],
        ];

        $categoriasCreadas = [];
        foreach ($categorias as $categoria) {
            $categoriasCreadas[] = Category::create($categoria);
        }

        // 2. Crear Ubicaciones
        $this->command->info('Creando Ubicaciones...');
        $ubicaciones = [
            ['name' => 'Almacén Principal', 'description' => 'Almacén central de la empresa'],
            ['name' => 'Depósito A', 'description' => 'Depósito para productos perecederos'],
            ['name' => 'Depósito B', 'description' => 'Depósito para productos no perecederos'],
            ['name' => 'Oficina', 'description' => 'Almacén de oficina para suministros'],
            ['name' => 'Cocina', 'description' => 'Almacén de cocina y comedor'],
            ['name' => 'Limpieza', 'description' => 'Almacén de productos de limpieza'],
        ];

        $ubicacionesCreadas = [];
        foreach ($ubicaciones as $ubicacion) {
            $ubicacionesCreadas[] = Location::create($ubicacion);
        }

        // 3. Crear Productos
        $this->command->info('Creando Productos...');
        $productos = [
            // Alimentos y Bebidas
            [
                'name' => 'Arroz Premium',
                'description' => 'Arroz de grano largo, bolsa de 5kg',
                'unit_measurement' => 'kg',
                'minimum_stock' => 50,
                'expiration_date' => Carbon::now()->addMonths(12),
                'location' => 'Estante A1',
                'utilization_percentage' => '85%',
                'category_id' => 1
            ],
            [
                'name' => 'Aceite de Girasol',
                'description' => 'Aceite de girasol refinado, botella 1L',
                'unit_measurement' => 'litro',
                'minimum_stock' => 20,
                'expiration_date' => Carbon::now()->addMonths(18),
                'location' => 'Estante A2',
                'utilization_percentage' => '90%',
                'category_id' => 1
            ],
            [
                'name' => 'Café Molido',
                'description' => 'Café molido tostado, paquete 500g',
                'unit_measurement' => 'kg',
                'minimum_stock' => 10,
                'expiration_date' => Carbon::now()->addMonths(6),
                'location' => 'Estante A3',
                'utilization_percentage' => '95%',
                'category_id' => 1
            ],
            [
                'name' => 'Azúcar Refinada',
                'description' => 'Azúcar blanca refinada, bolsa 1kg',
                'unit_measurement' => 'kg',
                'minimum_stock' => 30,
                'expiration_date' => Carbon::now()->addYears(2),
                'location' => 'Estante A4',
                'utilization_percentage' => '80%',
                'category_id' => 1
            ],

            // Limpieza y Aseo
            [
                'name' => 'Detergente Líquido',
                'description' => 'Detergente concentrado para ropa, botella 3L',
                'unit_measurement' => 'litro',
                'minimum_stock' => 15,
                'expiration_date' => Carbon::now()->addMonths(24),
                'location' => 'Estante B1',
                'utilization_percentage' => '88%',
                'category_id' => 2
            ],
            [
                'name' => 'Lavandina',
                'description' => 'Lavandina concentrada, botella 1L',
                'unit_measurement' => 'litro',
                'minimum_stock' => 25,
                'expiration_date' => Carbon::now()->addMonths(12),
                'location' => 'Estante B2',
                'utilization_percentage' => '92%',
                'category_id' => 2
            ],
            [
                'name' => 'Papel Higiénico',
                'description' => 'Papel higiénico doble hoja, paquete 4 unidades',
                'unit_measurement' => 'paquete',
                'minimum_stock' => 20,
                'expiration_date' => null,
                'location' => 'Estante B3',
                'utilization_percentage' => '98%',
                'category_id' => 2
            ],

            // Oficina y Papelería
            [
                'name' => 'Papel A4',
                'description' => 'Papel bond tamaño A4, resma 500 hojas',
                'unit_measurement' => 'resma',
                'minimum_stock' => 10,
                'expiration_date' => null,
                'location' => 'Estante C1',
                'utilization_percentage' => '75%',
                'category_id' => 3
            ],
            [
                'name' => 'Bolígrafos Azules',
                'description' => 'Bolígrafos de tinta azul, caja 12 unidades',
                'unit_measurement' => 'caja',
                'minimum_stock' => 5,
                'expiration_date' => null,
                'location' => 'Estante C2',
                'utilization_percentage' => '85%',
                'category_id' => 3
            ],
            [
                'name' => 'Carpetas A4',
                'description' => 'Carpetas colgantes tamaño A4, caja 10 unidades',
                'unit_measurement' => 'caja',
                'minimum_stock' => 3,
                'expiration_date' => null,
                'location' => 'Estante C3',
                'utilization_percentage' => '70%',
                'category_id' => 3
            ],

            // Tecnología
            [
                'name' => 'Cartucho de Tinta HP',
                'description' => 'Cartucho de tinta negra HP 304XL',
                'unit_measurement' => 'unidad',
                'minimum_stock' => 5,
                'expiration_date' => Carbon::now()->addMonths(24),
                'location' => 'Estante D1',
                'utilization_percentage' => '60%',
                'category_id' => 4
            ],
            [
                'name' => 'Cable USB-C',
                'description' => 'Cable USB-C a USB-A, 1.5 metros',
                'unit_measurement' => 'unidad',
                'minimum_stock' => 10,
                'expiration_date' => null,
                'location' => 'Estante D2',
                'utilization_percentage' => '45%',
                'category_id' => 4
            ],

            // Construcción y Mantenimiento
            [
                'name' => 'Cemento Portland',
                'description' => 'Cemento Portland tipo I, bolsa 50kg',
                'unit_measurement' => 'bolsa',
                'minimum_stock' => 20,
                'expiration_date' => Carbon::now()->addMonths(6),
                'location' => 'Estante E1',
                'utilization_percentage' => '55%',
                'category_id' => 5
            ],
            [
                'name' => 'Pintura Blanca',
                'description' => 'Pintura látex blanca, balde 4L',
                'unit_measurement' => 'litro',
                'minimum_stock' => 8,
                'expiration_date' => Carbon::now()->addMonths(36),
                'location' => 'Estante E2',
                'utilization_percentage' => '40%',
                'category_id' => 5
            ],

            // Medicamentos
            [
                'name' => 'Paracetamol 500mg',
                'description' => 'Paracetamol comprimidos 500mg, caja 20 unidades',
                'unit_measurement' => 'caja',
                'minimum_stock' => 5,
                'expiration_date' => Carbon::now()->addMonths(24),
                'location' => 'Botiquín',
                'utilization_percentage' => '30%',
                'category_id' => 6
            ],
            [
                'name' => 'Alcohol en Gel',
                'description' => 'Alcohol en gel 70%, frasco 500ml',
                'unit_measurement' => 'unidad',
                'minimum_stock' => 10,
                'expiration_date' => Carbon::now()->addMonths(12),
                'location' => 'Botiquín',
                'utilization_percentage' => '95%',
                'category_id' => 6
            ],
        ];

        $productosCreados = [];
        foreach ($productos as $producto) {
            $productosCreados[] = Product::create($producto);
        }

        // 4. Obtener usuario para las relaciones
        $user = User::first();

        // 5. Crear Niveles de Stock
        $this->command->info('Creando Niveles de Stock...');
        $stockLevels = [
            // Almacén Principal
            ['product_id' => 1, 'location_id' => 1, 'quantity' => 75.00, 'last_cost' => 45.50],
            ['product_id' => 2, 'location_id' => 1, 'quantity' => 30.00, 'last_cost' => 85.00],
            ['product_id' => 3, 'location_id' => 1, 'quantity' => 15.00, 'last_cost' => 120.00],
            ['product_id' => 4, 'location_id' => 1, 'quantity' => 45.00, 'last_cost' => 25.00],
            ['product_id' => 5, 'location_id' => 1, 'quantity' => 20.00, 'last_cost' => 15.75],
            ['product_id' => 6, 'location_id' => 1, 'quantity' => 35.00, 'last_cost' => 8.50],
            ['product_id' => 7, 'location_id' => 1, 'quantity' => 25.00, 'last_cost' => 12.00],

            // Depósito A
            ['product_id' => 1, 'location_id' => 2, 'quantity' => 25.00, 'last_cost' => 45.50],
            ['product_id' => 2, 'location_id' => 2, 'quantity' => 15.00, 'last_cost' => 85.00],
            ['product_id' => 3, 'location_id' => 2, 'quantity' => 8.00, 'last_cost' => 120.00],

            // Depósito B
            ['product_id' => 5, 'location_id' => 3, 'quantity' => 10.00, 'last_cost' => 15.75],
            ['product_id' => 6, 'location_id' => 3, 'quantity' => 20.00, 'last_cost' => 8.50],
            ['product_id' => 7, 'location_id' => 3, 'quantity' => 15.00, 'last_cost' => 12.00],

            // Oficina
            ['product_id' => 8, 'location_id' => 4, 'quantity' => 15.00, 'last_cost' => 25.50],
            ['product_id' => 9, 'location_id' => 4, 'quantity' => 8.00, 'last_cost' => 45.00],
            ['product_id' => 10, 'location_id' => 4, 'quantity' => 5.00, 'last_cost' => 35.00],
            ['product_id' => 11, 'location_id' => 4, 'quantity' => 3.00, 'last_cost' => 120.00],
            ['product_id' => 12, 'location_id' => 4, 'quantity' => 12.00, 'last_cost' => 15.00],

            // Cocina
            ['product_id' => 1, 'location_id' => 5, 'quantity' => 10.00, 'last_cost' => 45.50],
            ['product_id' => 2, 'location_id' => 5, 'quantity' => 5.00, 'last_cost' => 85.00],
            ['product_id' => 3, 'location_id' => 5, 'quantity' => 3.00, 'last_cost' => 120.00],
            ['product_id' => 4, 'location_id' => 5, 'quantity' => 8.00, 'last_cost' => 25.00],

            // Limpieza
            ['product_id' => 5, 'location_id' => 6, 'quantity' => 8.00, 'last_cost' => 15.75],
            ['product_id' => 6, 'location_id' => 6, 'quantity' => 12.00, 'last_cost' => 8.50],
            ['product_id' => 7, 'location_id' => 6, 'quantity' => 6.00, 'last_cost' => 12.00],
        ];

        foreach ($stockLevels as $stock) {
            StockLevel::create(array_merge($stock, [
                'last_updated_by' => $user ? $user->id : null
            ]));
        }

        // 6. Crear Movimientos de Inventario
        $this->command->info('Creando Movimientos de Inventario...');
        $movimientos = [
            // Entradas por compra
            ['product_id' => 1, 'location_id' => 1, 'quantity' => 50.00, 'type' => 'purchase', 'reference' => 'OC-2025-001', 'notes' => 'Compra inicial de arroz'],
            ['product_id' => 2, 'location_id' => 1, 'quantity' => 20.00, 'type' => 'purchase', 'reference' => 'OC-2025-001', 'notes' => 'Compra inicial de aceite'],
            ['product_id' => 3, 'location_id' => 1, 'quantity' => 10.00, 'type' => 'purchase', 'reference' => 'OC-2025-002', 'notes' => 'Compra inicial de café'],
            ['product_id' => 4, 'location_id' => 1, 'quantity' => 30.00, 'type' => 'purchase', 'reference' => 'OC-2025-002', 'notes' => 'Compra inicial de azúcar'],
            ['product_id' => 5, 'location_id' => 1, 'quantity' => 15.00, 'type' => 'purchase', 'reference' => 'OC-2025-003', 'notes' => 'Compra inicial de detergente'],

            // Salidas por consumo
            ['product_id' => 1, 'location_id' => 1, 'quantity' => -5.00, 'type' => 'consumption', 'reference' => 'CONS-001', 'notes' => 'Consumo diario cocina'],
            ['product_id' => 2, 'location_id' => 1, 'quantity' => -2.00, 'type' => 'consumption', 'reference' => 'CONS-002', 'notes' => 'Consumo diario cocina'],
            ['product_id' => 3, 'location_id' => 1, 'quantity' => -1.00, 'type' => 'consumption', 'reference' => 'CONS-003', 'notes' => 'Consumo diario oficina'],
            ['product_id' => 8, 'location_id' => 4, 'quantity' => -2.00, 'type' => 'consumption', 'reference' => 'CONS-004', 'notes' => 'Uso diario oficina'],

            // Ajustes de inventario
            ['product_id' => 1, 'location_id' => 1, 'quantity' => 30.00, 'type' => 'adjustment', 'reference' => 'AJ-001', 'notes' => 'Ajuste por inventario físico'],
            ['product_id' => 6, 'location_id' => 1, 'quantity' => 5.00, 'type' => 'adjustment', 'reference' => 'AJ-002', 'notes' => 'Ajuste por inventario físico'],

            // Transferencias entre ubicaciones
            ['product_id' => 1, 'location_id' => 1, 'quantity' => -10.00, 'type' => 'transfer', 'reference' => 'TR-001', 'notes' => 'Transferencia a Depósito A'],
            ['product_id' => 1, 'location_id' => 2, 'quantity' => 10.00, 'type' => 'transfer', 'reference' => 'TR-001', 'notes' => 'Recibido de Almacén Principal'],
            ['product_id' => 2, 'location_id' => 1, 'quantity' => -5.00, 'type' => 'transfer', 'reference' => 'TR-002', 'notes' => 'Transferencia a Depósito A'],
            ['product_id' => 2, 'location_id' => 2, 'quantity' => 5.00, 'type' => 'transfer', 'reference' => 'TR-002', 'notes' => 'Recibido de Almacén Principal'],

            // Pérdidas por vencimiento
            ['product_id' => 3, 'location_id' => 1, 'quantity' => -2.00, 'type' => 'spoilage', 'reference' => 'VEN-001', 'notes' => 'Producto vencido'],
            ['product_id' => 4, 'location_id' => 1, 'quantity' => -1.00, 'type' => 'spoilage', 'reference' => 'VEN-002', 'notes' => 'Producto dañado'],

            // Ventas
            ['product_id' => 8, 'location_id' => 4, 'quantity' => -3.00, 'type' => 'sale', 'reference' => 'VTA-001', 'notes' => 'Venta a cliente externo'],
            ['product_id' => 9, 'location_id' => 4, 'quantity' => -1.00, 'type' => 'sale', 'reference' => 'VTA-002', 'notes' => 'Venta a cliente externo'],
        ];

        foreach ($movimientos as $movimiento) {
            InventoryMovement::create(array_merge($movimiento, [
                'user_id' => $user ? $user->id : null
            ]));
        }

        $this->command->info('¡Datos de inventario creados exitosamente!');
        $this->command->info('Resumen:');
        $this->command->info('- ' . count($categorias) . ' Categorías');
        $this->command->info('- ' . count($ubicaciones) . ' Ubicaciones');
        $this->command->info('- ' . count($productos) . ' Productos');
        $this->command->info('- ' . count($stockLevels) . ' Niveles de Stock');
        $this->command->info('- ' . count($movimientos) . ' Movimientos de Inventario');
    }
}


