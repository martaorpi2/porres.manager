<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\ResponsibilityArea;
use App\Models\Supplier;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\GeneralRequest;
use App\Models\GeneralRequestDetail;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestDetail;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\MarketRate;
use App\Models\QuoteDetail;
use App\Models\Reception;
use App\Models\Delivery;
use App\Models\DeliveryDetail;
use App\Models\PaymentOrder;
use App\Models\Input;
use App\Models\Application;
use App\Models\Sector;
use App\Models\SupplierRating;
use App\Models\RequestDetail;
use App\Models\InventoryMovement;
use App\Models\Devolution;
use Spatie\Permission\Models\Role;
use Carbon\Carbon;

class CompleteDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('=== INICIANDO SEEDER COMPLETO ===');
        
        // 1. Limpiar base de datos
        $this->command->info('1. Limpiando base de datos...');
        $this->cleanDatabase();
        
        // 2. Crear roles y permisos
        $this->command->info('2. Creando roles y permisos...');
        $this->call(RolesAndPermissionsSeeder::class);
        
        // 3. Crear usuarios
        $this->command->info('3. Creando usuarios...');
        $users = $this->createUsers();
        
        // 4. Crear áreas de responsabilidad
        $this->command->info('4. Creando áreas de responsabilidad...');
        $areas = $this->createResponsibilityAreas($users);
        
        // 5. Crear categorías
        $this->command->info('5. Creando categorías...');
        $categories = $this->createCategories();
        
        // 6. Crear productos por área
        $this->command->info('6. Creando productos...');
        $products = $this->createProducts($categories, $areas);
        
        // 6.5. Crear inputs desde productos
        $this->command->info('6.5. Creando inputs desde productos...');
        $this->createInputsFromProducts($products);
        
        // 7. Crear ubicaciones
        $this->command->info('7. Creando ubicaciones...');
        $locations = $this->createLocations();
        
        // 8. Crear niveles de stock
        $this->command->info('8. Creando niveles de stock...');
        $this->createStockLevels($products, $locations);
        
        // 9. Crear rubros de proveedores
        $this->command->info('9. Creando rubros de proveedores...');
        $suppliersHeadings = $this->createSuppliersHeadings();
        
        // 10. Crear proveedores
        $this->command->info('10. Creando proveedores...');
        $suppliers = $this->createSuppliers($suppliersHeadings);
        
        // 11. Crear solicitudes generales
        $this->command->info('11. Creando solicitudes generales...');
        $generalRequests = $this->createGeneralRequests($users, $areas, $products);
        
        // 12. Crear solicitudes de compra
        $this->command->info('12. Creando solicitudes de compra...');
        $purchaseRequests = $this->createPurchaseRequests($users, $areas, $generalRequests, $products);
        
        // 12.5. Ejecutar migración para cambiar market_rates si es necesario
        $this->command->info('12.5. Verificando estructura de market_rates...');
        $this->ensureMarketRatesStructure();
        
        // 13. Crear cotizaciones
        $this->command->info('13. Creando cotizaciones...');
        $this->createMarketRates($purchaseRequests, $suppliers, $products);
        
        // 14. Crear órdenes de compra
        $this->command->info('14. Creando órdenes de compra...');
        $purchaseOrders = $this->createPurchaseOrders($purchaseRequests, $suppliers);
        
        // 15. Crear recepciones
        $this->command->info('15. Creando recepciones...');
        $receptions = $this->createReceptions($purchaseOrders, $users);
        
        // 16. Crear entregas
        $this->command->info('16. Creando entregas...');
        $this->createDeliveries($generalRequests, $users, $products);
        
        // 17. Crear órdenes de pago
        $this->command->info('17. Creando órdenes de pago...');
        $paymentOrders = $this->createPaymentOrders($purchaseOrders, $users);
        
        // 18. Crear sectores
        $this->command->info('18. Creando sectores...');
        $sectors = $this->createSectors();
        
        // 19. Crear relación proveedores-sectores
        $this->command->info('19. Creando relación proveedores-sectores...');
        $this->createSuppliersSectors($suppliers, $sectors);
        
        // 20. Crear calificaciones de proveedores
        $this->command->info('20. Creando calificaciones de proveedores...');
        $this->createSupplierRatings($purchaseOrders, $users);
        
        // 21. Crear aplicaciones
        $this->command->info('21. Creando aplicaciones...');
        $applications = $this->createApplications($users);
        
        // 22. Crear detalles de solicitud
        $this->command->info('22. Creando detalles de solicitud...');
        $this->createRequestDetails($applications, $products);
        
        // 23. Crear detalles de órdenes de pago
        $this->command->info('23. Creando detalles de órdenes de pago...');
        $this->createPaymentOrderDetails($paymentOrders);
        
        // 24. Crear movimientos de inventario
        $this->command->info('24. Creando movimientos de inventario...');
        $this->createInventoryMovements($products, $locations, $users, $purchaseOrders);
        
        // 25. Crear devoluciones
        $this->command->info('25. Creando devoluciones...');
        $this->createDevolutions($users, $receptions);
        
        $this->command->info('');
        $this->command->info('=== SEEDER COMPLETO FINALIZADO ===');
        $this->command->info('Usuario admin: admin@admin / password: password');
    }
    
    private function cleanDatabase()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Función auxiliar para truncar solo si la tabla existe
        $truncateIfExists = function($tableName, $model = null) {
            if (Schema::hasTable($tableName)) {
                if ($model) {
                    $model::truncate();
                } else {
                    DB::table($tableName)->truncate();
                }
            }
        };
        
        // Limpiar tablas en orden correcto (solo si existen)
        $truncateIfExists('delivery_details', DeliveryDetail::class);
        $truncateIfExists('deliveries', Delivery::class);
        $truncateIfExists('payment_orders', PaymentOrder::class);
        $truncateIfExists('receptions', Reception::class);
        $truncateIfExists('purchase_order_details', PurchaseOrderDetail::class);
        $truncateIfExists('purchase_orders', PurchaseOrder::class);
        $truncateIfExists('quote_details', QuoteDetail::class);
        $truncateIfExists('market_rates', MarketRate::class);
        $truncateIfExists('purchase_request_details', PurchaseRequestDetail::class);
        $truncateIfExists('purchase_requests', PurchaseRequest::class);
        $truncateIfExists('general_request_details', GeneralRequestDetail::class);
        $truncateIfExists('general_requests', GeneralRequest::class);
        $truncateIfExists('applications', \App\Models\Application::class);
        $truncateIfExists('op_details');
        $truncateIfExists('supplier_ratings');
        $truncateIfExists('suppliers_sectors');
        $truncateIfExists('sectors', Sector::class);
        $truncateIfExists('request_details');
        $truncateIfExists('inventory_movements', InventoryMovement::class);
        $truncateIfExists('devolutions', Devolution::class);
        $truncateIfExists('stock_levels', StockLevel::class);
        $truncateIfExists('inputs', Input::class);
        $truncateIfExists('products', Product::class);
        $truncateIfExists('locations', Location::class);
        $truncateIfExists('suppliers', Supplier::class);
        $truncateIfExists('suppliers_headings', \App\Models\SuppliersHeading::class);
        $truncateIfExists('responsibility_areas', ResponsibilityArea::class);
        $truncateIfExists('categories', Category::class);
        
        // Limpiar usuarios excepto el admin si existe
        if (Schema::hasTable('users')) {
            User::where('email', '!=', 'admin@admin')->delete();
        }
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
    
    private function createUsers()
    {
        $users = [];
        
        // Usuario admin
        $admin = User::updateOrCreate(
            ['email' => 'admin@admin'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $adminRole = Role::where('name', 'role_admin_sistema')->where('guard_name', 'backpack')->first();
        if ($adminRole) {
            $admin->assignRole($adminRole);
        }
        $users['admin'] = $admin;
        $this->command->info('  ✓ Usuario admin@admin creado');
        
        // Usuarios por rol
        $rolesData = [
            'role_personal' => ['name' => 'Personal Solicitante', 'email' => 'personal@admin'],
            'role_responsable_compras' => ['name' => 'Responsable Compras', 'email' => 'compras@admin'],
            'role_admin_institucion' => ['name' => 'Admin Institucional', 'email' => 'admininst@admin'],
            'role_apoderado' => ['name' => 'Apoderado Legal', 'email' => 'apoderado@admin'],
            'role_representante_legal' => ['name' => 'Representante Legal', 'email' => 'representante@admin'],
            'role_consejo' => ['name' => 'Consejo', 'email' => 'consejo@admin'],
            'role_tesoreria' => ['name' => 'Tesorería', 'email' => 'tesoreria@admin'],
            'role_contabilidad' => ['name' => 'Contabilidad', 'email' => 'contabilidad@admin'],
        ];
        
        foreach ($rolesData as $roleName => $userData) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $user = User::updateOrCreate(
                    ['email' => $userData['email']],
                    [
                        'name' => $userData['name'],
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                    ]
                );
                $role = Role::where('name', $roleName)->where('guard_name', 'backpack')->first();
                if ($role) {
                    $user->assignRole($role);
                }
                $users[$roleName] = $user;
                $this->command->info("  ✓ Usuario {$userData['email']} creado con rol {$roleName}");
            }
        }
        
        // Crear usuarios responsables por área (uno para cada área)
        $responsableRole = Role::where('name', 'role_responsable_area')->where('guard_name', 'backpack')->first();
        if ($responsableRole) {
            $responsablesData = [
                'responsable_informatica' => ['name' => 'Responsable Informática', 'email' => 'responsable.informatica@admin', 'area' => 'Informática'],
                'responsable_mantenimiento' => ['name' => 'Responsable Mantenimiento', 'email' => 'responsable.mantenimiento@admin', 'area' => 'Mantenimiento'],
                'responsable_salud' => ['name' => 'Responsable Salud', 'email' => 'responsable.salud@admin', 'area' => 'Salud'],
                'responsable_insumos' => ['name' => 'Responsable Insumos Generales', 'email' => 'responsable.insumos@admin', 'area' => 'Insumos Generales'],
            ];
            
            foreach ($responsablesData as $key => $responsableData) {
                $user = User::updateOrCreate(
                    ['email' => $responsableData['email']],
                    [
                        'name' => $responsableData['name'],
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                    ]
                );
                $user->assignRole($responsableRole);
                $users[$key] = $user;
                $this->command->info("  ✓ Usuario {$responsableData['email']} creado como responsable de {$responsableData['area']}");
            }
        }
        
        return $users;
    }
    
    private function createResponsibilityAreas($users)
    {
        $areasData = [
            [
                'name' => 'Informática',
                'description' => 'Responsable de equipos de cómputo, software y sistemas informáticos',
                'responsible_user_id' => $users['responsable_informatica']->id ?? null,
            ],
            [
                'name' => 'Mantenimiento',
                'description' => 'Responsable de herramientas, repuestos y materiales de mantenimiento',
                'responsible_user_id' => $users['responsable_mantenimiento']->id ?? null,
            ],
            [
                'name' => 'Salud',
                'description' => 'Responsable de material médico, reactivos y equipos de laboratorio',
                'responsible_user_id' => $users['responsable_salud']->id ?? null,
            ],
            [
                'name' => 'Insumos Generales',
                'description' => 'Responsable de material de oficina, limpieza y suministros generales',
                'responsible_user_id' => $users['responsable_insumos']->id ?? null,
            ],
        ];
        
        $areas = [];
        foreach ($areasData as $areaData) {
            $area = ResponsibilityArea::create($areaData);
            $areas[$area->name] = $area;
            $this->command->info("  ✓ Área {$area->name} creada con responsable ID: {$area->responsible_user_id}");
        }
        
        return $areas;
    }
    
    private function createCategories()
    {
        $categoriesData = [
            'Equipos Informáticos',
            'Software',
            'Herramientas',
            'Repuestos',
            'Material Médico',
            'Reactivos',
            'Material de Oficina',
            'Limpieza',
            'Insumos Generales',
        ];
        
        $categories = [];
        foreach ($categoriesData as $catName) {
            $category = Category::create(['name' => $catName]);
            $categories[$catName] = $category;
        }
        
        return $categories;
    }
    
    private function createProducts($categories, $areas)
    {
        $products = [];
        
        // Productos para Informática
        $infoProducts = [
            ['name' => 'Laptop Dell', 'description' => 'Laptop Dell Inspiron 15', 'unit_measurement' => 'unidad', 'category' => 'Equipos Informáticos', 'minimum_stock' => 5],
            ['name' => 'Mouse Inalámbrico', 'description' => 'Mouse inalámbrico Logitech', 'unit_measurement' => 'unidad', 'category' => 'Equipos Informáticos', 'minimum_stock' => 10],
            ['name' => 'Teclado Mecánico', 'description' => 'Teclado mecánico RGB', 'unit_measurement' => 'unidad', 'category' => 'Equipos Informáticos', 'minimum_stock' => 8],
            ['name' => 'Monitor 24"', 'description' => 'Monitor LED 24 pulgadas', 'unit_measurement' => 'unidad', 'category' => 'Equipos Informáticos', 'minimum_stock' => 6],
            ['name' => 'Windows 11 Pro', 'description' => 'Licencia Windows 11 Professional', 'unit_measurement' => 'licencia', 'category' => 'Software', 'minimum_stock' => 20],
        ];
        
        // Productos para Mantenimiento
        $mantProducts = [
            ['name' => 'Destornillador Phillips', 'description' => 'Destornillador Phillips #2', 'unit_measurement' => 'unidad', 'category' => 'Herramientas', 'minimum_stock' => 15],
            ['name' => 'Llave Inglesa', 'description' => 'Llave inglesa ajustable 8"', 'unit_measurement' => 'unidad', 'category' => 'Herramientas', 'minimum_stock' => 10],
            ['name' => 'Cable Eléctrico', 'description' => 'Cable eléctrico 2.5mm', 'unit_measurement' => 'metro', 'category' => 'Repuestos', 'minimum_stock' => 100],
            ['name' => 'Fusible 10A', 'description' => 'Fusible 10 amperios', 'unit_measurement' => 'unidad', 'category' => 'Repuestos', 'minimum_stock' => 50],
        ];
        
        // Productos para Salud
        $saludProducts = [
            ['name' => 'Guantes de Nitrilo', 'description' => 'Guantes de nitrilo desechables', 'unit_measurement' => 'caja', 'category' => 'Material Médico', 'minimum_stock' => 20],
            ['name' => 'Jeringas 5ml', 'description' => 'Jeringas desechables 5ml', 'unit_measurement' => 'caja', 'category' => 'Material Médico', 'minimum_stock' => 15],
            ['name' => 'Reactivo Glucosa', 'description' => 'Reactivo para análisis de glucosa', 'unit_measurement' => 'frasco', 'category' => 'Reactivos', 'minimum_stock' => 10],
            ['name' => 'Reactivo Colesterol', 'description' => 'Reactivo para análisis de colesterol', 'unit_measurement' => 'frasco', 'category' => 'Reactivos', 'minimum_stock' => 8],
        ];
        
        // Productos para Insumos Generales
        $generalProducts = [
            ['name' => 'Papel A4', 'description' => 'Resma de papel A4 75g', 'unit_measurement' => 'resma', 'category' => 'Material de Oficina', 'minimum_stock' => 30],
            ['name' => 'Lapiceras Bic', 'description' => 'Lapiceras Bic azules', 'unit_measurement' => 'caja', 'category' => 'Material de Oficina', 'minimum_stock' => 20],
            ['name' => 'Detergente', 'description' => 'Detergente líquido 5L', 'unit_measurement' => 'unidad', 'category' => 'Limpieza', 'minimum_stock' => 15],
            ['name' => 'Papel Higiénico', 'description' => 'Papel higiénico 4 rollos', 'unit_measurement' => 'paquete', 'category' => 'Limpieza', 'minimum_stock' => 25],
        ];
        
        $allProducts = [
            'Informática' => $infoProducts,
            'Mantenimiento' => $mantProducts,
            'Salud' => $saludProducts,
            'Insumos Generales' => $generalProducts,
        ];
        
        foreach ($allProducts as $areaName => $prods) {
            foreach ($prods as $prodData) {
                $category = $categories[$prodData['category']] ?? $categories['Insumos Generales'];
                $product = Product::create([
                    'name' => $prodData['name'],
                    'description' => $prodData['description'],
                    'unit_measurement' => $prodData['unit_measurement'],
                    'category_id' => $category->id,
                    'minimum_stock' => $prodData['minimum_stock'],
                ]);
                $products[] = $product;
            }
        }
        
        $this->command->info("  ✓ " . count($products) . " productos creados");
        return collect($products);
    }
    
    private function createInputsFromProducts($products)
    {
        foreach ($products as $product) {
            // Verificar si ya existe un input con el mismo nombre
            $existingInput = Input::where('name', $product->name)->first();
            
            if (!$existingInput) {
                Input::create([
                    'name' => $product->name,
                    'description' => $product->description ?? '',
                    'unit' => $product->unit_measurement ?? 'unidad',
                    'price' => 0,
                ]);
            }
        }
        
        $this->command->info("  ✓ Inputs creados desde productos");
    }
    
    private function createLocations()
    {
        $locationsData = [
            ['name' => 'Informática', 'description' => 'Depósito de equipos informáticos'],
            ['name' => 'Mantenimiento', 'description' => 'Depósito de herramientas y repuestos'],
            ['name' => 'Insumos de Salud', 'description' => 'Depósito de material médico y reactivos'],
            ['name' => 'Insumos Generales', 'description' => 'Depósito de material de oficina y limpieza'],
            ['name' => 'Almacén Principal', 'description' => 'Almacén principal del instituto'],
        ];
        
        $locations = [];
        foreach ($locationsData as $locData) {
            $location = Location::create($locData);
            $locations[] = $location;
        }
        
        return $locations;
    }
    
    private function createStockLevels($products, $locations)
    {
        foreach ($products as $product) {
            foreach ($locations as $location) {
                if (rand(0, 1)) { // 50% de probabilidad de tener stock
                    StockLevel::create([
                        'product_id' => $product->id,
                        'location_id' => $location->id,
                        'quantity' => rand(0, $product->minimum_stock * 3),
                    ]);
                }
            }
        }
        
        $this->command->info("  ✓ Niveles de stock creados");
    }
    
    private function createSuppliersHeadings()
    {
        $headings = [
            \App\Models\SuppliersHeading::create(['name' => 'Tecnología']),
            \App\Models\SuppliersHeading::create(['name' => 'Herramientas']),
            \App\Models\SuppliersHeading::create(['name' => 'Salud']),
            \App\Models\SuppliersHeading::create(['name' => 'Oficina']),
            \App\Models\SuppliersHeading::create(['name' => 'Insumos Generales']),
        ];
        
        return $headings;
    }
    
    private function createSuppliers($suppliersHeadings)
    {
        $suppliersData = [
            ['company_name' => 'Tech Solutions S.A.', 'cuit' => '20-12345678-9', 'contact' => 'Juan Pérez', 'address' => 'Av. Principal 123', 'supplier_heading_id' => $suppliersHeadings[0]->id],
            ['company_name' => 'Herramientas del Sur', 'cuit' => '20-98765432-1', 'contact' => 'María González', 'address' => 'Calle Sur 456', 'supplier_heading_id' => $suppliersHeadings[1]->id],
            ['company_name' => 'MedSupply S.R.L.', 'cuit' => '20-55555555-5', 'contact' => 'Carlos Rodríguez', 'address' => 'Bv. Norte 789', 'supplier_heading_id' => $suppliersHeadings[2]->id],
            ['company_name' => 'Oficina Total', 'cuit' => '20-11122233-3', 'contact' => 'Ana Martínez', 'address' => 'Av. Central 321', 'supplier_heading_id' => $suppliersHeadings[3]->id],
        ];
        
        $suppliers = [];
        foreach ($suppliersData as $supData) {
            $supplier = Supplier::create($supData);
            $suppliers[] = $supplier;
        }
        
        $this->command->info("  ✓ " . count($suppliers) . " proveedores creados");
        return $suppliers;
    }
    
    private function createGeneralRequests($users, $areas, $products)
    {
        $generalRequests = [];
        $personalUser = $users['role_personal'] ?? $users['admin'];
        
        for ($i = 0; $i < 10; $i++) {
            $area = $areas[array_rand($areas)];
            $status = ['creada', 'revisada_area', 'archivada'][rand(0, 2)];
            $isConverted = rand(0, 1) == 1 && $status != 'archivada';
            
            // Si el status es 'creada' o 'revisada_area', mantenerlo; si no, usar 'sin_entrega'
            $finalStatus = in_array($status, ['creada', 'revisada_area', 'archivada']) ? $status : 'sin_entrega';
            
            $gr = GeneralRequest::create([
                'number' => GeneralRequest::generateNextNumber(),
                'created_by' => $personalUser->id,
                'area_id' => $area->id,
                'title' => 'Solicitud General ' . ($i + 1),
                'description' => 'Descripción de la solicitud general ' . ($i + 1),
                'priority' => ['Baja', 'Media', 'Alta', 'Urgente'][rand(0, 3)],
                'status' => $finalStatus,
                'is_converted' => $isConverted,
            ]);
            
            // Agregar productos
            $selectedProducts = $products->random(min(3, count($products)));
            foreach ($selectedProducts as $product) {
                GeneralRequestDetail::create([
                    'general_request_id' => $gr->id,
                    'product_id' => $product->id,
                    'requested_quantity' => rand(5, 50),
                    'specifications' => 'Especificaciones del producto',
                    'estimated_unit_price' => rand(100, 5000),
                    'estimated_total' => rand(500, 10000),
                    'status' => 'Pendiente',
                ]);
            }
            
            $generalRequests[] = $gr;
        }
        
        $this->command->info("  ✓ " . count($generalRequests) . " solicitudes generales creadas");
        return $generalRequests;
    }
    
    private function createPurchaseRequests($users, $areas, $generalRequests, $products)
    {
        $purchaseRequests = [];
        $convertedCount = 0;
        
        foreach ($generalRequests as $gr) {
            if ($gr->is_converted && $convertedCount < 5) {
                // Asegurar que al menos algunas tengan status 'Aprobada' para generar cotizaciones
                $status = $convertedCount < 3 ? 'Aprobada' : ['Pendiente', 'Aprobada', 'En Proceso'][rand(0, 2)];
                
                $pr = PurchaseRequest::create([
                    'request_number' => PurchaseRequest::generateNextNumber(),
                    'request_date' => now()->subDays(rand(1, 30)),
                    'status' => $status,
                    'priority' => $gr->priority,
                    'justification' => $gr->description,
                    'responsibility_area_id' => $gr->area_id,
                    'requesting_user_id' => $gr->created_by,
                    'approved_by' => $users['role_responsable_compras']->id ?? $users['admin']->id,
                    'approved_date' => now()->subDays(rand(1, 20)),
                    'total_amount' => rand(5000, 50000),
                    'converted_from_general_request_id' => $gr->id,
                ]);
                
                // Replicar detalles
                $gr->load('details');
                foreach ($gr->details as $detail) {
                    PurchaseRequestDetail::create([
                        'purchase_request_id' => $pr->id,
                        'product_id' => $detail->product_id,
                        'requested_quantity' => $detail->requested_quantity,
                        'specifications' => $detail->specifications,
                        'estimated_unit_price' => $detail->estimated_unit_price,
                        'estimated_total' => $detail->estimated_total,
                        'status' => 'Pendiente',
                    ]);
                }
                
                $purchaseRequests[] = $pr;
                $convertedCount++;
            }
        }
        
        $this->command->info("  ✓ " . count($purchaseRequests) . " solicitudes de compra creadas");
        return $purchaseRequests;
    }
    
    private function ensureMarketRatesStructure()
    {
        // Verificar si la tabla tiene purchase_request_id
        $hasPurchaseRequestId = \Illuminate\Support\Facades\Schema::hasColumn('market_rates', 'purchase_request_id');
        
        if (!$hasPurchaseRequestId && \App\Models\PurchaseRequest::count() > 0) {
            // Ejecutar la migración manualmente
            $firstPurchaseRequest = \App\Models\PurchaseRequest::first();
            
            // Agregar la columna purchase_request_id
            \Illuminate\Support\Facades\Schema::table('market_rates', function ($table) {
                $table->unsignedBigInteger('purchase_request_id')->nullable();
            });
            
            // Poblar con el primer purchase_request si hay datos
            if (\Illuminate\Support\Facades\DB::table('market_rates')->exists()) {
                \Illuminate\Support\Facades\DB::table('market_rates')
                    ->update(['purchase_request_id' => $firstPurchaseRequest->id]);
            }
            
            // Eliminar application_id si existe
            if (\Illuminate\Support\Facades\Schema::hasColumn('market_rates', 'application_id')) {
                \Illuminate\Support\Facades\Schema::table('market_rates', function ($table) {
                    $table->dropForeign(['application_id']);
                    $table->dropColumn('application_id');
                });
            }
            
            // Hacer purchase_request_id no nullable
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE `market_rates` MODIFY COLUMN `purchase_request_id` BIGINT UNSIGNED NOT NULL');
            
            // Agregar foreign key
            \Illuminate\Support\Facades\Schema::table('market_rates', function ($table) {
                $table->foreign('purchase_request_id')->references('id')->on('purchase_requests')->onDelete('cascade');
            });
            
            $this->command->info("  ✓ Estructura de market_rates actualizada");
        }
    }
    
    private function createMarketRates($purchaseRequests, $suppliers, $products)
    {
        // Verificar si la tabla tiene purchase_request_id (después de las migraciones)
        $hasPurchaseRequestId = \Illuminate\Support\Facades\Schema::hasColumn('market_rates', 'purchase_request_id');
        
        if (!$hasPurchaseRequestId) {
            $this->command->warn("  ⚠ La tabla market_rates no tiene purchase_request_id. Omitiendo creación de cotizaciones.");
            return;
        }
        
        $marketRatesCreated = 0;
        $quoteDetailsCreated = 0;
        
        foreach ($purchaseRequests as $pr) {
            // Crear cotizaciones para todas las solicitudes aprobadas, y al menos para algunas otras
            if ($pr->status == 'Aprobada' || rand(0, 2) == 0) {
                $pr->load('details');
                
                if ($pr->details->count() > 0) {
                    $numQuotes = rand(2, 4);
                    $selectedSupplier = null;
                    $selectedMarketRate = null;
                    
                    for ($i = 0; $i < $numQuotes; $i++) {
                        $supplier = $suppliers[array_rand($suppliers)];
                        // Asegurar que la primera cotización siempre esté seleccionada
                        $isSelected = $i == 0;
                        
                        // Calcular total desde detalles
                        $totalAmount = $pr->details->sum(function($detail) {
                            $unitPrice = $detail->estimated_unit_price * (0.9 + rand(0, 20) / 100);
                            return $unitPrice * $detail->requested_quantity;
                        });
                        
                        $mr = MarketRate::create([
                            'purchase_request_id' => $pr->id,
                            'supplier_id' => $supplier->id,
                            'date' => now()->subDays(rand(1, 10)),
                            'total_amount' => $totalAmount > 0 ? $totalAmount : rand(4000, 60000),
                            'is_selected' => $isSelected,
                        ]);
                        
                        $marketRatesCreated++;
                        
                        if ($isSelected) {
                            $pr->update(['selected_market_rate_id' => $mr->id]);
                            $selectedSupplier = $supplier;
                            $selectedMarketRate = $mr;
                        }
                        
                        // Crear detalles de cotización
                        foreach ($pr->details as $detail) {
                            $unitPrice = $detail->estimated_unit_price * (0.9 + rand(0, 20) / 100);
                            QuoteDetail::create([
                                'market_rate_id' => $mr->id,
                                'product_id' => $detail->product_id,
                                'quantity' => $detail->requested_quantity,
                                'unit_price' => $unitPrice,
                            ]);
                            $quoteDetailsCreated++;
                        }
                    }
                }
            }
        }
        
        $this->command->info("  ✓ {$marketRatesCreated} cotizaciones creadas con {$quoteDetailsCreated} detalles");
    }
    
    private function createPurchaseOrders($purchaseRequests, $suppliers)
    {
        $purchaseOrders = [];
        $adminUser = \App\Models\User::where('email', 'admin@admin')->first() ?? \App\Models\User::first();
        $detailsCreated = 0;
        
        foreach ($purchaseRequests as $pr) {
            // Buscar cotización seleccionada
            $mr = MarketRate::where('purchase_request_id', $pr->id)
                            ->where('is_selected', true)
                            ->first();
            
            if (!$mr) {
                // Si no hay seleccionada, seleccionar la primera y marcarla
                $mr = MarketRate::where('purchase_request_id', $pr->id)->first();
                if ($mr) {
                    $mr->update(['is_selected' => true]);
                    $pr->update(['selected_market_rate_id' => $mr->id]);
                }
            } else {
                // Actualizar la solicitud con la cotización seleccionada
                $pr->update(['selected_market_rate_id' => $mr->id]);
            }
            
            if ($mr) {
                // Generar número de orden
                $ultimo = PurchaseOrder::max('id') ?? 0;
                $orderNumber = 'OC-' . date('Y') . '-' . str_pad(($ultimo + 1), 3, '0', STR_PAD_LEFT);
                
                $po = PurchaseOrder::create([
                    'purchase_request_id' => $pr->id,
                    'supplier_id' => $mr->supplier_id,
                    'number' => $orderNumber,
                    'date' => now()->subDays(rand(1, 15)),
                    'issue_date' => now()->subDays(rand(1, 15)),
                    'estimated_delivery_date' => now()->addDays(rand(7, 30)),
                    'status' => ['Pendiente', 'Aprobada', 'Recibida'][rand(0, 2)],
                    'authorizing_user_id' => $adminUser->id,
                ]);
                
                // Crear detalles usando inputs
                $pr->load('details.product');
                foreach ($pr->details as $detail) {
                    if (!$detail->product) {
                        continue;
                    }
                    
                    // Buscar o crear input desde producto
                    $input = Input::where('name', $detail->product->name)->first();
                    if (!$input) {
                        $input = Input::create([
                            'name' => $detail->product->name,
                            'description' => $detail->product->description ?? '',
                            'unit' => $detail->product->unit_measurement ?? 'unidad',
                            'price' => $detail->estimated_unit_price ?? 0,
                        ]);
                    }
                    
                    PurchaseOrderDetail::create([
                        'purchase_order_id' => $po->id,
                        'input_id' => $input->id,
                        'quantity' => $detail->requested_quantity,
                        'unit_price' => $detail->estimated_unit_price ?? 0,
                    ]);
                    $detailsCreated++;
                }
                
                $purchaseOrders[] = $po;
            }
        }
        
        $this->command->info("  ✓ " . count($purchaseOrders) . " órdenes de compra creadas con {$detailsCreated} detalles");
        return $purchaseOrders;
    }
    
    private function createReceptions($purchaseOrders, $users)
    {
        $responsableUser = $users['responsable_informatica'] ?? \App\Models\User::first();
        $receptions = [];
        
        // Asegurar que se creen al menos 2 recepciones
        $minReceptions = min(2, count($purchaseOrders));
        $receptionCount = 0;
        
        foreach ($purchaseOrders as $po) {
            // Crear recepción para al menos algunas órdenes
            if ($receptionCount < $minReceptions || rand(0, 1) == 1) {
                $reception = Reception::create([
                    'purchase_order_id' => $po->id,
                    'date' => now()->subDays(rand(1, 5)),
                    'area_manager_id' => $responsableUser->id,
                    'according' => ['Si', 'No'][rand(0, 1)],
                ]);
                $receptions[] = $reception;
                // Actualizar estado de la orden a Recibida
                $po->update(['status' => 'Recibida']);
                $receptionCount++;
            }
        }
        
        $this->command->info("  ✓ " . count($receptions) . " recepciones creadas");
        return $receptions;
    }
    
    private function createDeliveries($generalRequests, $users, $products)
    {
        $responsableUser = $users['responsable_informatica'] ?? \App\Models\User::first();
        $deliveriesCreated = 0;
        
        // Asegurar que se creen al menos 5 entregas
        $minDeliveries = min(5, count($generalRequests));
        $deliveryCount = 0;
        
        foreach ($generalRequests as $gr) {
            // Crear entregas para solicitudes no convertidas o algunas convertidas también
            // Asegurar al menos minDeliveries entregas
            $shouldCreate = false;
            
            if (!$gr->is_converted) {
                // Para solicitudes no convertidas, crear más entregas
                $shouldCreate = ($deliveryCount < $minDeliveries) || rand(0, 1) == 1;
            } else {
                // Para solicitudes convertidas, crear algunas entregas también
                $shouldCreate = ($deliveryCount < $minDeliveries) && rand(0, 2) == 0;
            }
            
            if ($shouldCreate) {
                // Cargar los detalles de la solicitud general
                $gr->load('details');
                
                if ($gr->details->count() > 0) {
                    $delivery = Delivery::create([
                        'general_request_id' => $gr->id,
                        'delivery_date' => now()->subDays(rand(1, 10)),
                        'delivered_by' => $responsableUser->id,
                        'received_by' => $gr->created_by,
                        'observations' => 'Entrega completada',
                    ]);
                    
                    foreach ($gr->details as $detail) {
                        DeliveryDetail::create([
                            'delivery_id' => $delivery->id,
                            'product_id' => $detail->product_id,
                            'delivered_quantity' => rand(1, max(1, $detail->requested_quantity)),
                        ]);
                    }
                    
                    // Actualizar estado de entrega
                    $statusOptions = ['entregada_totalmente', 'entregada_parcialmente', 'sin_entrega'];
                    $newStatus = rand(0, 1) == 1 ? 'entregada_totalmente' : 'entregada_parcialmente';
                    $gr->update(['status' => $newStatus]);
                    
                    $deliveriesCreated++;
                    $deliveryCount++;
                }
            }
        }
        
        $this->command->info("  ✓ {$deliveriesCreated} entregas creadas con sus detalles");
    }
    
    private function createPaymentOrders($purchaseOrders, $users)
    {
        $tesoreriaUser = \App\Models\User::where('email', 'tesoreria@admin')->first() ?? \App\Models\User::first();
        $paymentOrders = [];
        
        // Obtener órdenes recibidas o actualizar algunas a recibidas
        $receivedOrders = collect($purchaseOrders)->filter(function($po) {
            return $po->status == 'Recibida';
        });
        
        // Si no hay órdenes recibidas, marcar algunas como recibidas
        if ($receivedOrders->isEmpty() && count($purchaseOrders) > 0) {
            $ordersToMark = min(2, count($purchaseOrders));
            foreach ($purchaseOrders->take($ordersToMark) as $po) {
                $po->update(['status' => 'Recibida']);
                $receivedOrders->push($po);
            }
        }
        
        // Asegurar que se creen al menos 2 órdenes de pago
        $minPaymentOrders = min(2, $receivedOrders->count());
        $paymentOrderCount = 0;
        
        foreach ($receivedOrders as $po) {
            // Cargar detalles si no están cargados
            if (!$po->relationLoaded('details')) {
                $po->load('details');
            }
            
            // Calcular total desde detalles
            $totalAmount = $po->details->sum(function($detail) {
                return $detail->quantity * $detail->unit_price;
            });
            
            // Si no hay total, usar un valor por defecto
            if ($totalAmount <= 0) {
                $totalAmount = rand(5000, 50000);
            }
            
            // Crear orden de pago si hay total o si necesitamos más órdenes
            if ($totalAmount > 0 && ($paymentOrderCount < $minPaymentOrders || rand(0, 1) == 1)) {
                $paymentOrder = PaymentOrder::create([
                    'purchase_order_id' => $po->id,
                    'payment_number' => 'OP-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                    'date' => now()->subDays(rand(1, 3)),
                    'total_amount' => $totalAmount,
                    'status' => ['Pendiente', 'Aprobada', 'Ejecutada'][rand(0, 2)],
                    'authorizing_user_id' => $tesoreriaUser->id,
                ]);
                
                $paymentOrders[] = $paymentOrder;
                $paymentOrderCount++;
            }
        }
        
        $this->command->info("  ✓ " . count($paymentOrders) . " órdenes de pago creadas");
        return $paymentOrders;
    }
    
    private function createSectors()
    {
        $sectorsData = [
            ['name' => 'Público', 'descripcion' => 'Sector público'],
            ['name' => 'Privado', 'descripcion' => 'Sector privado'],
            ['name' => 'Educación', 'descripcion' => 'Sector educativo'],
            ['name' => 'Salud', 'descripcion' => 'Sector salud'],
        ];
        
        $sectors = [];
        foreach ($sectorsData as $sectorData) {
            $sector = Sector::create($sectorData);
            $sectors[] = $sector;
        }
        
        $this->command->info("  ✓ " . count($sectors) . " sectores creados");
        return $sectors;
    }
    
    private function createSuppliersSectors($suppliers, $sectors)
    {
        foreach ($suppliers as $supplier) {
            // Asignar 1-2 sectores aleatorios a cada proveedor
            $selectedSectors = collect($sectors)->random(rand(1, 2));
            foreach ($selectedSectors as $sector) {
                DB::table('suppliers_sectors')->insert([
                    'supplier_id' => $supplier->id,
                    'sector_id' => $sector->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        $this->command->info("  ✓ Relaciones proveedores-sectores creadas");
    }
    
    private function createSupplierRatings($purchaseOrders, $users)
    {
        $ratings = [];
        
        // Obtener órdenes recibidas
        $receivedOrders = collect($purchaseOrders)->filter(function($po) {
            return $po->status == 'Recibida';
        });
        
        // Si no hay recibidas, usar todas las órdenes
        if ($receivedOrders->isEmpty()) {
            $receivedOrders = collect($purchaseOrders);
        }
        
        // Asegurar que se creen al menos 2 calificaciones
        $minRatings = min(2, $receivedOrders->count());
        $ratingsToCreate = max($minRatings, min(3, $receivedOrders->count()));
        
        foreach ($receivedOrders->take($ratingsToCreate) as $po) {
            $userKeys = array_keys($users);
            $randomKey = $userKeys[array_rand($userKeys)];
            $ratedBy = $users[$randomKey];
            
            $rating = SupplierRating::create([
                'supplier_id' => $po->supplier_id,
                'rated_by' => $ratedBy->id,
                'purchase_order_id' => $po->id,
                'quality_rating' => rand(3, 5),
                'price_rating' => rand(3, 5),
                'delivery_time_rating' => rand(3, 5),
                'service_rating' => rand(3, 5),
                'overall_rating' => rand(3, 5),
                'comments' => 'Calificación del proveedor basada en la orden de compra ' . $po->number,
                'evaluation_date' => now()->subDays(rand(1, 5)),
            ]);
            
            $ratings[] = $rating;
        }
        
        $this->command->info("  ✓ " . count($ratings) . " calificaciones de proveedores creadas");
    }
    
    private function createApplications($users)
    {
        $applications = [];
        $personalUser = $users['role_personal'] ?? $users['admin'];
        
        for ($i = 0; $i < 8; $i++) {
            $application = Application::create([
                'user_id' => $personalUser->id,
                'status' => ['Pendiente', 'Aprobada', 'Rechazada'][rand(0, 2)],
            ]);
            $applications[] = $application;
        }
        
        $this->command->info("  ✓ " . count($applications) . " aplicaciones creadas");
        return $applications;
    }
    
    private function createRequestDetails($applications, $products)
    {
        $requestDetails = [];
        
        foreach ($applications as $application) {
            // Agregar 1-3 productos a cada aplicación
            $selectedProducts = $products->random(min(3, count($products)));
            foreach ($selectedProducts as $product) {
                $requestDetail = RequestDetail::create([
                    'application_id' => $application->id,
                    'product_id' => $product->id,
                    'quantity' => rand(1, 20),
                ]);
                $requestDetails[] = $requestDetail;
            }
        }
        
        $this->command->info("  ✓ " . count($requestDetails) . " detalles de solicitud creados");
    }
    
    private function createPaymentOrderDetails($paymentOrders)
    {
        $details = [];
        
        if (empty($paymentOrders)) {
            $this->command->warn("  ⚠ No hay órdenes de pago disponibles. Omitiendo creación de detalles.");
            return;
        }
        
        foreach ($paymentOrders as $paymentOrder) {
            // Crear 1-2 detalles por orden de pago
            $numDetails = rand(1, 2);
            $totalAmount = $paymentOrder->total_amount;
            $amountPerDetail = $totalAmount / $numDetails;
            
            for ($i = 0; $i < $numDetails; $i++) {
                $isLast = ($i == $numDetails - 1);
                $amount = $isLast ? ($totalAmount - ($amountPerDetail * ($numDetails - 1))) : $amountPerDetail;
                
                DB::table('op_details')->insert([
                    'payment_order_id' => $paymentOrder->id,
                    'concept' => ['advance', 'residue', 'partiality'][rand(0, 2)],
                    'amount' => round($amount, 2),
                    'method_payment' => ['Transferencia', 'Cheque', 'Efectivo'][rand(0, 2)],
                    'expiration_date' => now()->addDays(rand(7, 30)),
                    'actual_payment_date' => $paymentOrder->status == 'Ejecutada' ? now()->subDays(rand(1, 5)) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $details[] = true;
            }
        }
        
        $this->command->info("  ✓ " . count($details) . " detalles de órdenes de pago creados");
    }
    
    private function createInventoryMovements($products, $locations, $users, $purchaseOrders)
    {
        $movements = [];
        $types = ['uso', 'compra', 'desuso', 'baja'];
        
        // Crear movimientos de compra desde recepciones
        $receptions = Reception::all();
        foreach ($receptions as $reception) {
            $po = $reception->purchase_order;
            if ($po) {
                foreach ($po->details as $detail) {
                    $input = $detail->input;
                    if ($input) {
                        // Buscar producto por nombre
                        $product = $products->firstWhere('name', $input->name);
                        if ($product) {
                            $location = $locations[array_rand($locations)];
                            $user = $users[array_rand($users)];
                            
                            InventoryMovement::create([
                                'product_id' => $product->id,
                                'location_id' => $location->id,
                                'quantity' => $detail->quantity,
                                'type' => 'compra',
                                'reference' => 'OC-' . $po->number,
                                'user_id' => $user->id,
                                'notes' => 'Compra desde orden ' . $po->number,
                            ]);
                            $movements[] = true;
                        }
                    }
                }
            }
        }
        
        // Crear otros tipos de movimientos
        foreach ($products->random(min(10, count($products))) as $product) {
            $location = $locations[array_rand($locations)];
            $user = $users[array_rand($users)];
            $type = $types[array_rand($types)];
            
            InventoryMovement::create([
                'product_id' => $product->id,
                'location_id' => $location->id,
                'quantity' => $type == 'compra' ? rand(10, 100) : -rand(1, 20),
                'type' => $type,
                'reference' => 'REF-' . rand(1000, 9999),
                'user_id' => $user->id,
                'notes' => 'Movimiento de inventario tipo ' . $type,
            ]);
            $movements[] = true;
        }
        
        $this->command->info("  ✓ " . count($movements) . " movimientos de inventario creados");
    }
    
    private function createDevolutions($users, $receptions = null)
    {
        $devolutions = [];
        
        if ($receptions === null) {
            $receptions = Reception::all();
        }
        
        // Convertir a colección si es array
        $receptionsCollection = is_array($receptions) ? collect($receptions) : $receptions;
        
        if ($receptionsCollection->isEmpty()) {
            $this->command->warn("  ⚠ No hay recepciones disponibles. Omitiendo creación de devoluciones.");
            return;
        }
        
        // Asegurar que se creen al menos 2 devoluciones
        $minDevolutions = min(2, count($receptionsCollection));
        $numDevolutions = max($minDevolutions, min(3, count($receptionsCollection)));
        
        $selectedReceptions = $receptionsCollection->count() >= $numDevolutions 
            ? $receptionsCollection->random($numDevolutions) 
            : $receptionsCollection;
            
        foreach ($selectedReceptions as $reception) {
            $userKeys = array_keys($users);
            $randomKey = $userKeys[array_rand($userKeys)];
            $user = $users[$randomKey];
            
            $devolution = Devolution::create([
                'reception_id' => $reception->id,
                'user_id' => $user->id,
                'reason' => 'Producto defectuoso o no conforme',
                'date' => now()->subDays(rand(1, 5)),
            ]);
            $devolutions[] = $devolution;
        }
        
        $this->command->info("  ✓ " . count($devolutions) . " devoluciones creadas");
    }
}

