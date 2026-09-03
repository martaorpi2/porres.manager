<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Models\SuppliersHeading;
use App\Models\Sector;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\User;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Crear Rubros de Proveedores (SuppliersHeading)
        $this->command->info('Creando Rubros de Proveedores...');
        $rubros = [
            ['name' => 'Tecnología', 'description' => 'Proveedores de equipos y servicios tecnológicos'],
            ['name' => 'Construcción', 'description' => 'Materiales y servicios de construcción'],
            ['name' => 'Alimentación', 'description' => 'Proveedores de alimentos y bebidas'],
            ['name' => 'Limpieza', 'description' => 'Productos y servicios de limpieza'],
            ['name' => 'Oficina', 'description' => 'Materiales y servicios de oficina'],
            ['name' => 'Insumos Generales', 'description' => 'Proveedores de insumos de uso general'],
        ];

        foreach ($rubros as $rubro) {
            SuppliersHeading::create($rubro);
        }

        // 2. Crear Sectores
        $this->command->info('Creando Sectores...');
        $sectores = [
            ['name' => 'Administración', 'descripcion' => 'Sector administrativo de la empresa'],
            ['name' => 'Producción', 'descripcion' => 'Sector de producción y manufactura'],
            ['name' => 'Ventas', 'descripcion' => 'Sector comercial y de ventas'],
            ['name' => 'Logística', 'descripcion' => 'Sector de distribución y logística'],
            ['name' => 'Recursos Humanos', 'descripcion' => 'Sector de gestión de personal']
        ];

        foreach ($sectores as $sector) {
            Sector::create($sector);
        }

        // 3. Crear Proveedores
        $this->command->info('Creando Proveedores...');
        $proveedores = [
            [
                'company_name' => 'Tech Solutions S.A.',
                'cuit' => '20-12345678-9',
                'address' => 'Av. Corrientes 1234, CABA',
                'contact' => 'Juan Pérez - juan@techsolutions.com',
                'supplier_heading_id' => 1
            ],
            [
                'company_name' => 'Construcciones del Norte S.R.L.',
                'cuit' => '30-87654321-0',
                'address' => 'Ruta 9 Km 45, Pilar',
                'contact' => 'María González - maria@construcciones.com',
                'supplier_heading_id' => 2
            ],
            [
                'company_name' => 'Alimentos Frescos S.A.',
                'cuit' => '27-11223344-5',
                'address' => 'Av. San Martín 567, San Isidro',
                'contact' => 'Carlos Rodríguez - carlos@alimentos.com',
                'supplier_heading_id' => 3
            ],
            [
                'company_name' => 'Limpieza Total S.R.L.',
                'cuit' => '30-55667788-1',
                'address' => 'Belgrano 890, Vicente López',
                'contact' => 'Ana Martínez - ana@limpieza.com',
                'supplier_heading_id' => 4
            ],
            [
                'company_name' => 'Oficina Plus S.A.',
                'cuit' => '20-99887766-2',
                'address' => 'Florida 1000, CABA',
                'contact' => 'Roberto Silva - roberto@oficina.com',
                'supplier_heading_id' => 5
            ]
        ];

        $proveedoresCreados = [];
        foreach ($proveedores as $proveedor) {
            $proveedoresCreados[] = Supplier::create($proveedor);
        }

        // 4. Asociar Proveedores con Sectores (relación many-to-many)
        $this->command->info('Asociando Proveedores con Sectores...');
        $proveedoresCreados[0]->sectors()->attach([1, 2]); // Tech Solutions con Admin y Producción
        $proveedoresCreados[1]->sectors()->attach([2, 4]); // Construcciones con Producción y Logística
        $proveedoresCreados[2]->sectors()->attach([1, 3]); // Alimentos con Admin y Ventas
        $proveedoresCreados[3]->sectors()->attach([1, 5]); // Limpieza con Admin y RRHH
        $proveedoresCreados[4]->sectors()->attach([1, 3]); // Oficina con Admin y Ventas

        // 5. Crear Usuario de prueba
        $this->command->info('Creando Usuario de prueba...');
        $user = User::firstOrCreate(
            ['email' => 'admin@porres.com'],
            [
                'name' => 'Administrador',
                'password' => bcrypt('password123'),
                'email_verified_at' => now()
            ]
        );

        // 6. Crear tabla inputs si no existe
        $this->command->info('Verificando tabla inputs...');
        if (!Schema::hasTable('inputs')) {
            Schema::create('inputs', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('unit', 50)->default('unidad');
                $table->decimal('price', 10, 2)->default(0);
                $table->timestamps();
            });
        }

        // 7. Crear Insumos de prueba
        $this->command->info('Creando Insumos...');
        $insumos = [
            ['name' => 'Papel A4', 'description' => 'Papel bond tamaño A4 75g', 'unit' => 'resma', 'price' => 25.50],
            ['name' => 'Tinta para Impresora', 'description' => 'Cartucho de tinta negra HP', 'unit' => 'unidad', 'price' => 45.00],
            ['name' => 'Café Premium', 'description' => 'Café en grano tostado', 'unit' => 'kg', 'price' => 120.00],
            ['name' => 'Detergente', 'description' => 'Detergente líquido concentrado', 'unit' => 'litro', 'price' => 15.75],
            ['name' => 'Cemento', 'description' => 'Cemento Portland tipo I', 'unit' => 'bolsa', 'price' => 8.50]
        ];

        foreach ($insumos as $insumo) {
            DB::table('inputs')->insert(array_merge($insumo, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }

        // 8. Crear Órdenes de Compra
        $this->command->info('Creando Órdenes de Compra...');
        $ordenesCompra = [
            [
                'number' => 'OC-2025-001',
                'date' => now()->subDays(10),
                'status' => 'Aprobada',
                'supplier_id' => 1,
                'authorizing_user_id' => $user->id
            ],
            [
                'number' => 'OC-2025-002',
                'date' => now()->subDays(5),
                'status' => 'Pendiente',
                'supplier_id' => 2,
                'authorizing_user_id' => $user->id
            ],
            [
                'number' => 'OC-2025-003',
                'date' => now()->subDays(2),
                'status' => 'Recibida',
                'supplier_id' => 3,
                'authorizing_user_id' => $user->id
            ]
        ];

        foreach ($ordenesCompra as $orden) {
            PurchaseOrder::create($orden);
        }

        // 9. Crear Detalles de Órdenes de Compra
        $this->command->info('Creando Detalles de Órdenes de Compra...');
        $detallesOC = [
            ['purchase_order_id' => 1, 'input_id' => 1, 'quantity' => 10, 'unit_price' => 25.50],
            ['purchase_order_id' => 1, 'input_id' => 2, 'quantity' => 5, 'unit_price' => 45.00],
            ['purchase_order_id' => 2, 'input_id' => 5, 'quantity' => 20, 'unit_price' => 8.50],
            ['purchase_order_id' => 3, 'input_id' => 3, 'quantity' => 2, 'unit_price' => 120.00]
        ];

        foreach ($detallesOC as $detalle) {
            DB::table('oc_details')->insert(array_merge($detalle, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }

        // 10. Crear Órdenes de Pago
        $this->command->info('Creando Órdenes de Pago...');
        $ordenesPago = [
            [
                'payment_number' => 'OP-2025-001',
                'date' => now()->subDays(8),
                'total_amount' => 480.00,
                'status' => 'Aprobada',
                'purchase_order_id' => 1,
                'authorizing_user_id' => $user->id
            ],
            [
                'payment_number' => 'OP-2025-002',
                'date' => now()->subDays(1),
                'total_amount' => 170.00,
                'status' => 'Pendiente',
                'purchase_order_id' => 2,
                'authorizing_user_id' => $user->id
            ]
        ];

        foreach ($ordenesPago as $orden) {
            DB::table('payment_orders')->insert(array_merge($orden, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }

        // 11. Crear Detalles de Órdenes de Pago
        $this->command->info('Creando Detalles de Órdenes de Pago...');
        $detallesOP = [
            [
                'payment_order_id' => 1,
                'concept' => 'advance',
                'amount' => 200.00,
                'method_payment' => 'Transferencia bancaria',
                'expiration_date' => now()->addDays(30),
                'actual_payment_date' => now()->subDays(5)
            ],
            [
                'payment_order_id' => 1,
                'concept' => 'residue',
                'amount' => 280.00,
                'method_payment' => 'Cheque',
                'expiration_date' => now()->addDays(45),
                'actual_payment_date' => null
            ],
            [
                'payment_order_id' => 2,
                'concept' => 'partiality',
                'amount' => 170.00,
                'method_payment' => 'Efectivo',
                'expiration_date' => now()->addDays(15),
                'actual_payment_date' => null
            ]
        ];

        foreach ($detallesOP as $detalle) {
            DB::table('op_details')->insert(array_merge($detalle, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }

        $this->command->info('¡Datos de prueba creados exitosamente!');
        $this->command->info('Resumen:');
        $this->command->info('- 5 Rubros de Proveedores');
        $this->command->info('- 5 Sectores');
        $this->command->info('- 5 Proveedores');
        $this->command->info('- 5 Insumos');
        $this->command->info('- 3 Órdenes de Compra con detalles');
        $this->command->info('- 2 Órdenes de Pago con detalles');
        $this->command->info('- 1 Usuario administrador');
    }
}