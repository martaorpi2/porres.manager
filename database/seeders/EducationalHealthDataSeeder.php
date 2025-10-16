<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SuppliersHeading;
use App\Models\Sector;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\Input;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\PaymentOrder;
use App\Models\Application;
use App\Models\RequestDetail;
use App\Models\MarketRate;
use App\Models\QuoteDetail;
use App\Models\Reception;
use App\Models\Devolution;
use App\Models\InventoryMovement;
use App\Models\GeneralRequestDetail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EducationalHealthDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Genera datos para un instituto educativo con productos de salud
     */
    public function run(): void
    {
        // 1. Crear rubros de proveedores
        $this->createSuppliersHeadings();
        
        // 2. Crear sectores
        $this->createSectors();
        
        // 3. Crear proveedores
        $this->createSuppliers();
        
        // 4. Crear categorías de productos
        $this->createCategories();
        
        // 5. Crear ubicaciones
        $this->createLocations();
        
        // 6. Crear productos
        $this->createProducts();
        
        // 7. Crear niveles de stock
        $this->createStockLevels();
        
        // 8. Crear insumos
        $this->createInputs();
        
        // 9. Crear órdenes de compra
        $this->createPurchaseOrders();
        
        // 10. Crear detalles de órdenes de compra
        $this->createPurchaseOrderDetails();
        
        // 11. Crear órdenes de pago
        $this->createPaymentOrders();
        
        // 12. Crear detalles de órdenes de pago
        $this->createPaymentOrderDetails();
        
        // 13. Crear relación proveedores-sectores
        $this->createSuppliersSectors();
        
        // 14. Crear aplicaciones
        $this->createApplications();
        
        // 15. Crear detalles de solicitud
        $this->createRequestDetails();
        
        // 16. Crear tasas de mercado
        $this->createMarketRates();
        
        // 17. Crear detalles de cotización
        $this->createQuoteDetails();
        
        // 18. Crear recepciones
        $this->createReceptions();
        
        // 19. Crear devoluciones
        $this->createDevolutions();
        
        // 20. Crear movimientos de inventario
        $this->createInventoryMovements();
        
        // 21. Crear áreas de responsabilidad
        $this->createResponsibilityAreas();
        
        // 22. Crear solicitudes de compra
        $this->createPurchaseRequests();
        
        // 23. Crear solicitudes generales
        $this->createGeneralRequests();
        
        // 24. Crear detalles de solicitudes generales
        $this->createGeneralRequestDetails();
        
        $this->command->info('Datos educativos y de salud generados exitosamente.');
    }

    private function createSuppliersHeadings()
    {
        $headings = [
            'Equipos Médicos y Laboratorio',
            'Insumos de Laboratorio',
            'Material Quirúrgico',
            'Equipos de Radiología',
            'Material Educativo',
            'Equipos de Hemoterapia',
            'Instrumentación Quirúrgica',
            'Reactivos y Soluciones',
        ];

        foreach ($headings as $heading) {
            SuppliersHeading::create(['name' => $heading]);
        }
    }

    private function createSectors()
    {
        $sectors = [
            ['name' => 'Laboratorio Clínico', 'descripcion' => 'Análisis clínicos y bioquímicos'],
            ['name' => 'Hemoterapia', 'descripcion' => 'Banco de sangre y hemoderivados'],
            ['name' => 'Radiología', 'descripcion' => 'Diagnóstico por imágenes'],
            ['name' => 'Instrumentación Quirúrgica', 'descripcion' => 'Equipos y materiales quirúrgicos'],
            ['name' => 'Educación', 'descripcion' => 'Material didáctico y equipos educativos'],
        ];

        foreach ($sectors as $sector) {
            Sector::create($sector);
        }
    }

    private function createSuppliers()
    {
        $suppliers = [
            [
                'company_name' => 'MedEquip S.A.',
                'cuit' => '30-12345678-9',
                'address' => 'Av. Corrientes 1234, CABA',
                'contact' => 'Juan Pérez - juan@medequip.com',
                'supplier_heading_id' => 1,
            ],
            [
                'company_name' => 'LabSupply Argentina',
                'cuit' => '30-87654321-0',
                'address' => 'Rivadavia 5678, CABA',
                'contact' => 'María González - maria@labsupply.com',
                'supplier_heading_id' => 2,
            ],
            [
                'company_name' => 'Quirúrgica del Sur',
                'cuit' => '30-11223344-5',
                'address' => 'San Martín 9012, CABA',
                'contact' => 'Carlos Rodríguez - carlos@quirurgica.com',
                'supplier_heading_id' => 3,
            ],
            [
                'company_name' => 'RadTech Solutions',
                'cuit' => '30-55667788-1',
                'address' => 'Belgrano 3456, CABA',
                'contact' => 'Ana Martínez - ana@radtech.com',
                'supplier_heading_id' => 4,
            ],
            [
                'company_name' => 'EduMed Supplies',
                'cuit' => '30-99887766-2',
                'address' => 'Cabildo 7890, CABA',
                'contact' => 'Luis Fernández - luis@edumed.com',
                'supplier_heading_id' => 5,
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::create($supplier);
        }
    }

    private function createCategories()
    {
        $categories = [
            'Equipos de Laboratorio',
            'Reactivos y Soluciones',
            'Material Descartable',
            'Equipos de Radiología',
            'Instrumentos Quirúrgicos',
            'Material Educativo',
            'Equipos de Hemoterapia',
            'Consumibles Médicos',
            'Equipos de Diagnóstico',
            'Material de Sutura',
        ];

        foreach ($categories as $category) {
            Category::create(['name' => $category]);
        }
    }

    private function createLocations()
    {
        $locations = [
            'Laboratorio Principal',
            'Laboratorio de Microbiología',
            'Sala de Radiología',
            'Quirófano 1',
            'Quirófano 2',
            'Banco de Sangre',
            'Aula de Prácticas',
            'Depósito Central',
            'Sala de Instrumentación',
            'Consultorio Médico',
        ];

        foreach ($locations as $location) {
            Location::create(['name' => $location]);
        }
    }

    private function createProducts()
    {
        $products = [
            // Equipos de Laboratorio
            [
                'name' => 'Microscopio Óptico Binocular',
                'description' => 'Microscopio para análisis celular y microbiológico',
                'unit_measurement' => 'Unidad',
                'minimum_stock' => 2,
                'expiration_date' => null,
                'location' => 'Laboratorio Principal',
                'utilization_percentage' => '85%',
                'category_id' => 1,
            ],
            [
                'name' => 'Centrífuga de Laboratorio',
                'description' => 'Centrífuga para separación de muestras sanguíneas',
                'unit_measurement' => 'Unidad',
                'minimum_stock' => 1,
                'expiration_date' => null,
                'location' => 'Laboratorio Principal',
                'utilization_percentage' => '90%',
                'category_id' => 1,
            ],
            [
                'name' => 'Autoclave de Laboratorio',
                'description' => 'Esterilizador por vapor para material de laboratorio',
                'unit_measurement' => 'Unidad',
                'minimum_stock' => 1,
                'expiration_date' => null,
                'location' => 'Laboratorio Principal',
                'utilization_percentage' => '75%',
                'category_id' => 1,
            ],

            // Reactivos y Soluciones
            [
                'name' => 'Reactivo para Glucosa',
                'description' => 'Kit de reactivos para determinación de glucosa en sangre',
                'unit_measurement' => 'Kit',
                'minimum_stock' => 10,
                'expiration_date' => Carbon::now()->addMonths(12),
                'location' => 'Laboratorio Principal',
                'utilization_percentage' => '95%',
                'category_id' => 2,
            ],
            [
                'name' => 'Solución Salina Fisiológica',
                'description' => 'Solución salina 0.9% para diluciones y lavados',
                'unit_measurement' => 'Litro',
                'minimum_stock' => 20,
                'expiration_date' => Carbon::now()->addMonths(24),
                'location' => 'Depósito Central',
                'utilization_percentage' => '100%',
                'category_id' => 2,
            ],
            [
                'name' => 'Reactivo para Hemoglobina',
                'description' => 'Reactivo para determinación de hemoglobina',
                'unit_measurement' => 'Kit',
                'minimum_stock' => 8,
                'expiration_date' => Carbon::now()->addMonths(8),
                'location' => 'Laboratorio Principal',
                'utilization_percentage' => '88%',
                'category_id' => 2,
            ],

            // Material Descartable
            [
                'name' => 'Jeringas Descartables 5ml',
                'description' => 'Jeringas estériles de 5ml para extracción de muestras',
                'unit_measurement' => 'Caja x 100',
                'minimum_stock' => 15,
                'expiration_date' => Carbon::now()->addMonths(36),
                'location' => 'Depósito Central',
                'utilization_percentage' => '98%',
                'category_id' => 3,
            ],
            [
                'name' => 'Guantes de Látex Talla M',
                'description' => 'Guantes estériles de látex para procedimientos',
                'unit_measurement' => 'Caja x 100',
                'minimum_stock' => 25,
                'expiration_date' => Carbon::now()->addMonths(24),
                'location' => 'Depósito Central',
                'utilization_percentage' => '100%',
                'category_id' => 3,
            ],
            [
                'name' => 'Tubos de Ensayo Estériles',
                'description' => 'Tubos de ensayo de vidrio estériles 15ml',
                'unit_measurement' => 'Caja x 50',
                'minimum_stock' => 12,
                'expiration_date' => null,
                'location' => 'Laboratorio Principal',
                'utilization_percentage' => '92%',
                'category_id' => 3,
            ],

            // Equipos de Radiología
            [
                'name' => 'Equipo de Rayos X Digital',
                'description' => 'Sistema de radiografía digital portátil',
                'unit_measurement' => 'Unidad',
                'minimum_stock' => 1,
                'expiration_date' => null,
                'location' => 'Sala de Radiología',
                'utilization_percentage' => '80%',
                'category_id' => 4,
            ],
            [
                'name' => 'Delantal Plomado',
                'description' => 'Delantal de protección radiológica con plomo',
                'unit_measurement' => 'Unidad',
                'minimum_stock' => 5,
                'expiration_date' => null,
                'location' => 'Sala de Radiología',
                'utilization_percentage' => '85%',
                'category_id' => 4,
            ],

            // Instrumentos Quirúrgicos
            [
                'name' => 'Bisturí N°11',
                'description' => 'Bisturí desechable con hoja N°11',
                'unit_measurement' => 'Caja x 25',
                'minimum_stock' => 8,
                'expiration_date' => Carbon::now()->addMonths(48),
                'location' => 'Quirófano 1',
                'utilization_percentage' => '90%',
                'category_id' => 5,
            ],
            [
                'name' => 'Pinzas de Disección',
                'description' => 'Pinzas de disección anatómica reutilizables',
                'unit_measurement' => 'Unidad',
                'minimum_stock' => 10,
                'expiration_date' => null,
                'location' => 'Sala de Instrumentación',
                'utilization_percentage' => '75%',
                'category_id' => 5,
            ],

            // Equipos de Hemoterapia
            [
                'name' => 'Aguja para Flebotomía 21G',
                'description' => 'Agujas estériles para extracción de sangre',
                'unit_measurement' => 'Caja x 100',
                'minimum_stock' => 20,
                'expiration_date' => Carbon::now()->addMonths(36),
                'location' => 'Banco de Sangre',
                'utilization_percentage' => '95%',
                'category_id' => 7,
            ],
            [
                'name' => 'Bolsas para Sangre 450ml',
                'description' => 'Bolsas estériles para recolección de sangre',
                'unit_measurement' => 'Caja x 25',
                'minimum_stock' => 15,
                'expiration_date' => Carbon::now()->addMonths(24),
                'location' => 'Banco de Sangre',
                'utilization_percentage' => '88%',
                'category_id' => 7,
            ],

            // Material Educativo
            [
                'name' => 'Modelo Anatómico de Corazón',
                'description' => 'Modelo didáctico del corazón humano desmontable',
                'unit_measurement' => 'Unidad',
                'minimum_stock' => 3,
                'expiration_date' => null,
                'location' => 'Aula de Prácticas',
                'utilization_percentage' => '70%',
                'category_id' => 6,
            ],
            [
                'name' => 'Esqueleto Humano Didáctico',
                'description' => 'Esqueleto completo para enseñanza de anatomía',
                'unit_measurement' => 'Unidad',
                'minimum_stock' => 2,
                'expiration_date' => null,
                'location' => 'Aula de Prácticas',
                'utilization_percentage' => '65%',
                'category_id' => 6,
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }

    private function createStockLevels()
    {
        $products = Product::all();
        $locations = Location::all();
        $users = \App\Models\User::all();
        
        foreach ($products as $product) {
            $currentStock = rand($product->minimum_stock, $product->minimum_stock * 3);
            $location = $locations->random();
            $user = $users->random();
            
            StockLevel::create([
                'product_id' => $product->id,
                'location_id' => $location->id,
                'quantity' => $currentStock,
                'last_cost' => rand(50, 5000) + (rand(0, 99) / 100),
                'last_updated_by' => $user->id,
            ]);
        }
    }

    private function createInputs()
    {
        $inputs = [
            [
                'name' => 'Reactivo para Glucosa',
                'description' => 'Kit de reactivos para determinación de glucosa en sangre',
                'unit' => 'Kit',
                'price' => 850.00,
            ],
            [
                'name' => 'Jeringas Descartables 5ml',
                'description' => 'Jeringas estériles de 5ml para extracción de muestras',
                'unit' => 'Caja x 100',
                'price' => 45.00,
            ],
            [
                'name' => 'Equipo de Rayos X Digital',
                'description' => 'Sistema de radiografía digital portátil',
                'unit' => 'Unidad',
                'price' => 45000.00,
            ],
            [
                'name' => 'Guantes de Látex Talla M',
                'description' => 'Guantes estériles de látex para procedimientos',
                'unit' => 'Caja x 100',
                'price' => 25.50,
            ],
            [
                'name' => 'Solución Salina Fisiológica',
                'description' => 'Solución salina 0.9% para diluciones y lavados',
                'unit' => 'Litro',
                'price' => 15.00,
            ],
            [
                'name' => 'Microscopio Óptico Binocular',
                'description' => 'Microscopio para análisis celular y microbiológico',
                'unit' => 'Unidad',
                'price' => 7500.00,
            ],
            [
                'name' => 'Aguja para Flebotomía 21G',
                'description' => 'Agujas estériles para extracción de sangre',
                'unit' => 'Caja x 100',
                'price' => 35.00,
            ],
            [
                'name' => 'Bolsas para Sangre 450ml',
                'description' => 'Bolsas estériles para recolección de sangre',
                'unit' => 'Caja x 25',
                'price' => 120.00,
            ],
        ];

        foreach ($inputs as $input) {
            Input::create($input);
        }
    }

    private function createPurchaseOrders()
    {
        $users = \App\Models\User::all();
        
        $orders = [
            [
                'number' => 'OC-2024-001',
                'date' => Carbon::now()->subDays(20),
                'status' => 'Recibida',
                'supplier_id' => 1,
                'authorizing_user_id' => $users->random()->id,
            ],
            [
                'number' => 'OC-2024-002',
                'date' => Carbon::now()->subDays(5),
                'status' => 'Pendiente',
                'supplier_id' => 2,
                'authorizing_user_id' => $users->random()->id,
            ],
            [
                'number' => 'OC-2024-003',
                'date' => Carbon::now()->subDays(12),
                'status' => 'Aprobada',
                'supplier_id' => 3,
                'authorizing_user_id' => $users->random()->id,
            ],
            [
                'number' => 'OC-2024-004',
                'date' => Carbon::now()->subDays(8),
                'status' => 'Recibida',
                'supplier_id' => 4,
                'authorizing_user_id' => $users->random()->id,
            ],
            [
                'number' => 'OC-2024-005',
                'date' => Carbon::now()->subDays(3),
                'status' => 'Pendiente',
                'supplier_id' => 5,
                'authorizing_user_id' => $users->random()->id,
            ],
        ];

        foreach ($orders as $order) {
            PurchaseOrder::create($order);
        }
    }

    private function createPurchaseOrderDetails()
    {
        $inputs = Input::all();
        $purchaseOrders = PurchaseOrder::all();
        
        $details = [
            [
                'purchase_order_id' => 1,
                'input_id' => 1, // Reactivo para Glucosa
                'quantity' => 2,
                'unit_price' => 850.00,
            ],
            [
                'purchase_order_id' => 1,
                'input_id' => 2, // Jeringas Descartables
                'quantity' => 5,
                'unit_price' => 45.00,
            ],
            [
                'purchase_order_id' => 2,
                'input_id' => 6, // Microscopio
                'quantity' => 1,
                'unit_price' => 7500.00,
            ],
            [
                'purchase_order_id' => 3,
                'input_id' => 3, // Equipo de Rayos X
                'quantity' => 1,
                'unit_price' => 45000.00,
            ],
            [
                'purchase_order_id' => 4,
                'input_id' => 4, // Guantes de Látex
                'quantity' => 10,
                'unit_price' => 25.50,
            ],
            [
                'purchase_order_id' => 5,
                'input_id' => 7, // Agujas para Flebotomía
                'quantity' => 3,
                'unit_price' => 35.00,
            ],
        ];

        foreach ($details as $detail) {
            DB::table('oc_details')->insert($detail);
        }
    }

    private function createPaymentOrders()
    {
        $users = \App\Models\User::all();
        $purchaseOrders = PurchaseOrder::all();
        
        $payments = [
            [
                'payment_number' => 'OP-2024-001',
                'date' => Carbon::now()->subDays(15),
                'total_amount' => 15000.00,
                'status' => 'Ejecutada',
                'purchase_order_id' => 1,
                'authorizing_user_id' => $users->random()->id,
            ],
            [
                'payment_number' => 'OP-2024-002',
                'date' => Carbon::now()->subDays(8),
                'total_amount' => 8500.00,
                'status' => 'Aprobada',
                'purchase_order_id' => 2,
                'authorizing_user_id' => $users->random()->id,
            ],
            [
                'payment_number' => 'OP-2024-003',
                'date' => Carbon::now()->subDays(3),
                'total_amount' => 45000.00,
                'status' => 'Pendiente',
                'purchase_order_id' => 3,
                'authorizing_user_id' => $users->random()->id,
            ],
            [
                'payment_number' => 'OP-2024-004',
                'date' => Carbon::now()->subDays(1),
                'total_amount' => 1700.00,
                'status' => 'Aprobada',
                'purchase_order_id' => 4,
                'authorizing_user_id' => $users->random()->id,
            ],
        ];

        foreach ($payments as $payment) {
            PaymentOrder::create($payment);
        }
    }

    private function createPaymentOrderDetails()
    {
        $paymentOrders = PaymentOrder::all();
        
        $details = [
            [
                'payment_order_id' => 1,
                'concept' => 'advance',
                'amount' => 5000.00,
                'method_payment' => 'Transferencia Bancaria',
                'expiration_date' => Carbon::now()->subDays(10),
                'actual_payment_date' => Carbon::now()->subDays(10),
            ],
            [
                'payment_order_id' => 1,
                'concept' => 'residue',
                'amount' => 10000.00,
                'method_payment' => 'Cheque',
                'expiration_date' => Carbon::now()->subDays(5),
                'actual_payment_date' => Carbon::now()->subDays(5),
            ],
            [
                'payment_order_id' => 2,
                'concept' => 'partiality',
                'amount' => 8500.00,
                'method_payment' => 'Transferencia Bancaria',
                'expiration_date' => Carbon::now()->addDays(5),
                'actual_payment_date' => null,
            ],
            [
                'payment_order_id' => 3,
                'concept' => 'advance',
                'amount' => 15000.00,
                'method_payment' => 'Efectivo',
                'expiration_date' => Carbon::now()->addDays(10),
                'actual_payment_date' => null,
            ],
            [
                'payment_order_id' => 3,
                'concept' => 'residue',
                'amount' => 30000.00,
                'method_payment' => 'Transferencia Bancaria',
                'expiration_date' => Carbon::now()->addDays(20),
                'actual_payment_date' => null,
            ],
            [
                'payment_order_id' => 4,
                'concept' => 'partiality',
                'amount' => 1700.00,
                'method_payment' => 'Cheque',
                'expiration_date' => Carbon::now()->addDays(3),
                'actual_payment_date' => null,
            ],
        ];

        foreach ($details as $detail) {
            DB::table('op_details')->insert($detail);
        }
    }

    private function createApplications()
    {
        $users = \App\Models\User::all();
        
        $applications = [
            [
                'status' => 'Aprobada',
                'user_id' => $users->random()->id,
            ],
            [
                'status' => 'Pendiente',
                'user_id' => $users->random()->id,
            ],
            [
                'status' => 'Aprobada',
                'user_id' => $users->random()->id,
            ],
            [
                'status' => 'Rechazada',
                'user_id' => $users->random()->id,
            ],
            [
                'status' => 'Pendiente',
                'user_id' => $users->random()->id,
            ],
        ];

        foreach ($applications as $application) {
            Application::create($application);
        }
    }

    private function createRequestDetails()
    {
        $applications = Application::all();
        $products = Product::all();
        
        $requests = [
            [
                'application_id' => 1,
                'product_id' => 4, // Reactivo para Glucosa
                'quantity' => 10,
            ],
            [
                'application_id' => 1,
                'product_id' => 6, // Jeringas Descartables
                'quantity' => 5,
            ],
            [
                'application_id' => 2,
                'product_id' => 1, // Microscopio
                'quantity' => 1,
            ],
            [
                'application_id' => 3,
                'product_id' => 10, // Equipo de Rayos X
                'quantity' => 1,
            ],
            [
                'application_id' => 4,
                'product_id' => 13, // Aguja para Flebotomía
                'quantity' => 15,
            ],
            [
                'application_id' => 5,
                'product_id' => 7, // Guantes de Látex
                'quantity' => 8,
            ],
        ];

        foreach ($requests as $request) {
            RequestDetail::create($request);
        }
    }

    private function createMarketRates()
    {
        $suppliers = Supplier::all();
        $applications = Application::all();
        
        $rates = [
            [
                'supplier_id' => 1,
                'application_id' => 1,
                'date' => Carbon::now()->subDays(30),
                'total_amount' => 15000.00,
            ],
            [
                'supplier_id' => 2,
                'application_id' => 2,
                'date' => Carbon::now()->subDays(20),
                'total_amount' => 8500.00,
            ],
            [
                'supplier_id' => 3,
                'application_id' => 3,
                'date' => Carbon::now()->subDays(15),
                'total_amount' => 3200.00,
            ],
            [
                'supplier_id' => 4,
                'application_id' => 4,
                'date' => Carbon::now()->subDays(10),
                'total_amount' => 45000.00,
            ],
            [
                'supplier_id' => 5,
                'application_id' => 5,
                'date' => Carbon::now()->subDays(5),
                'total_amount' => 1200.00,
            ],
        ];

        foreach ($rates as $rate) {
            MarketRate::create($rate);
        }
    }

    private function createQuoteDetails()
    {
        $marketRates = MarketRate::all();
        $products = Product::all();
        
        $quotes = [
            [
                'market_rate_id' => 1,
                'product_id' => 1, // Microscopio
                'quantity' => 1,
                'unit_price' => 7500.00,
            ],
            [
                'market_rate_id' => 1,
                'product_id' => 4, // Reactivo para Glucosa
                'quantity' => 2,
                'unit_price' => 850.00,
            ],
            [
                'market_rate_id' => 2,
                'product_id' => 6, // Jeringas Descartables
                'quantity' => 5,
                'unit_price' => 45.00,
            ],
            [
                'market_rate_id' => 3,
                'product_id' => 7, // Guantes de Látex
                'quantity' => 8,
                'unit_price' => 25.50,
            ],
            [
                'market_rate_id' => 4,
                'product_id' => 10, // Equipo de Rayos X
                'quantity' => 1,
                'unit_price' => 45000.00,
            ],
            [
                'market_rate_id' => 5,
                'product_id' => 13, // Aguja para Flebotomía
                'quantity' => 3,
                'unit_price' => 35.00,
            ],
        ];

        foreach ($quotes as $quote) {
            QuoteDetail::create($quote);
        }
    }

    private function createReceptions()
    {
        $users = \App\Models\User::all();
        $purchaseOrders = PurchaseOrder::all();
        
        $receptions = [
            [
                'purchase_order_id' => 1,
                'date' => Carbon::now()->subDays(10),
                'according' => 'Si',
                'area_manager_id' => $users->random()->id,
            ],
            [
                'purchase_order_id' => 2,
                'date' => Carbon::now()->subDays(3),
                'according' => 'No',
                'area_manager_id' => $users->random()->id,
            ],
            [
                'purchase_order_id' => 3,
                'date' => Carbon::now()->subDays(1),
                'according' => 'Si',
                'area_manager_id' => $users->random()->id,
            ],
            [
                'purchase_order_id' => 4,
                'date' => Carbon::now()->subDays(5),
                'according' => 'Si',
                'area_manager_id' => $users->random()->id,
            ],
        ];

        foreach ($receptions as $reception) {
            Reception::create($reception);
        }
    }

    private function createDevolutions()
    {
        $receptions = Reception::all();
        
        $devolutions = [
            [
                'reception_id' => 1,
                'reason' => 'Producto vencido al momento de la recepción',
                'amount_returned' => 1700.00,
                'date' => Carbon::now()->subDays(5),
            ],
            [
                'reception_id' => 2,
                'reason' => 'Defecto de fabricación detectado en inspección',
                'amount_returned' => 225.00,
                'date' => Carbon::now()->subDays(2),
            ],
            [
                'reception_id' => 3,
                'reason' => 'Producto no conforme con especificaciones técnicas',
                'amount_returned' => 45000.00,
                'date' => Carbon::now()->subDays(1),
            ],
        ];

        foreach ($devolutions as $devolution) {
            Devolution::create($devolution);
        }
    }

    private function createInventoryMovements()
    {
        $products = Product::all();
        $locations = Location::all();
        $users = \App\Models\User::all();
        
        $movements = [
            [
                'product_id' => 4, // Reactivo para Glucosa
                'location_id' => 1, // Laboratorio Principal
                'quantity' => 10.00,
                'type' => 'purchase',
                'reference' => 'OC-2024-001',
                'user_id' => $users->random()->id,
                'notes' => 'Ingreso de reactivos nuevos',
            ],
            [
                'product_id' => 4, // Reactivo para Glucosa
                'location_id' => 1, // Laboratorio Principal
                'quantity' => -5.00,
                'type' => 'usage',
                'reference' => 'PRACT-001',
                'user_id' => $users->random()->id,
                'notes' => 'Uso en laboratorio',
            ],
            [
                'product_id' => 6, // Jeringas Descartables
                'location_id' => 8, // Depósito Central
                'quantity' => 5.00,
                'type' => 'purchase',
                'reference' => 'OC-2024-002',
                'user_id' => $users->random()->id,
                'notes' => 'Reposición de jeringas',
            ],
            [
                'product_id' => 6, // Jeringas Descartables
                'location_id' => 7, // Aula de Prácticas
                'quantity' => -2.00,
                'type' => 'usage',
                'reference' => 'PRACT-002',
                'user_id' => $users->random()->id,
                'notes' => 'Uso en prácticas',
            ],
            [
                'product_id' => 1, // Microscopio
                'location_id' => 1, // Laboratorio Principal
                'quantity' => 1.00,
                'type' => 'purchase',
                'reference' => 'OC-2024-003',
                'user_id' => $users->random()->id,
                'notes' => 'Nuevo microscopio adquirido',
            ],
            [
                'product_id' => 10, // Equipo de Rayos X
                'location_id' => 3, // Sala de Radiología
                'quantity' => 1.00,
                'type' => 'purchase',
                'reference' => 'OC-2024-004',
                'user_id' => $users->random()->id,
                'notes' => 'Equipo de radiología instalado',
            ],
        ];

        foreach ($movements as $movement) {
            InventoryMovement::create($movement);
        }
    }

    private function createSuppliersSectors()
    {
        $suppliers = Supplier::all();
        $sectors = Sector::all();
        
        // Asignar sectores a proveedores basado en su especialidad
        $assignments = [
            // MedEquip S.A. - Equipos médicos y laboratorio
            ['supplier_id' => 1, 'sector_id' => 1], // Laboratorio Clínico
            ['supplier_id' => 1, 'sector_id' => 5], // Educación
            
            // LabSupply Argentina - Insumos de laboratorio
            ['supplier_id' => 2, 'sector_id' => 1], // Laboratorio Clínico
            ['supplier_id' => 2, 'sector_id' => 2], // Hemoterapia
            
            // Quirúrgica del Sur - Material quirúrgico
            ['supplier_id' => 3, 'sector_id' => 4], // Instrumentación Quirúrgica
            ['supplier_id' => 3, 'sector_id' => 5], // Educación
            
            // RadTech Solutions - Equipos de radiología
            ['supplier_id' => 4, 'sector_id' => 3], // Radiología
            ['supplier_id' => 4, 'sector_id' => 5], // Educación
            
            // EduMed Supplies - Material educativo
            ['supplier_id' => 5, 'sector_id' => 5], // Educación
            ['supplier_id' => 5, 'sector_id' => 1], // Laboratorio Clínico
        ];

        foreach ($assignments as $assignment) {
            DB::table('suppliers_sectors')->insert($assignment);
        }
    }

    private function createResponsibilityAreas()
    {
        $users = \App\Models\User::all();
        
        $areas = [
            [
                'name' => 'Informática',
                'description' => 'Responsable de equipos de cómputo, software y sistemas informáticos',
                'responsible_user_id' => $users->random()->id,
                'is_active' => true,
            ],
            [
                'name' => 'Insumos de Salud',
                'description' => 'Responsable de material médico, reactivos y equipos de laboratorio',
                'responsible_user_id' => $users->random()->id,
                'is_active' => true,
            ],
            [
                'name' => 'Mantenimiento',
                'description' => 'Responsable de herramientas, repuestos y materiales de mantenimiento',
                'responsible_user_id' => $users->random()->id,
                'is_active' => true,
            ],
            [
                'name' => 'Insumos Generales',
                'description' => 'Responsable de material de oficina, limpieza y suministros generales',
                'responsible_user_id' => $users->random()->id,
                'is_active' => true,
            ],
        ];

        foreach ($areas as $area) {
            \App\Models\ResponsibilityArea::create($area);
        }
    }

    private function createPurchaseRequests()
    {
        $users = \App\Models\User::all();
        $areas = \App\Models\ResponsibilityArea::all();
        $products = Product::all();
        
        $requests = [
            [
                'request_number' => 'SR-2024-0001',
                'request_date' => Carbon::now()->subDays(10),
                'status' => 'Aprobada',
                'priority' => 'Alta',
                'justification' => 'Reposición de equipos informáticos para el laboratorio de cómputo',
                'observations' => 'Urgente para el inicio del semestre',
                'responsibility_area_id' => 1, // Informática
                'requesting_user_id' => $users->random()->id,
                'approved_by' => $users->random()->id,
                'approved_date' => Carbon::now()->subDays(8),
                'total_amount' => 15000.00,
            ],
            [
                'request_number' => 'SR-2024-0002',
                'request_date' => Carbon::now()->subDays(5),
                'status' => 'Pendiente',
                'priority' => 'Media',
                'justification' => 'Compra de reactivos para análisis clínicos',
                'observations' => 'Stock mínimo alcanzado',
                'responsibility_area_id' => 2, // Insumos de Salud
                'requesting_user_id' => $users->random()->id,
                'total_amount' => 8500.00,
            ],
            [
                'request_number' => 'SR-2024-0003',
                'request_date' => Carbon::now()->subDays(3),
                'status' => 'En Proceso',
                'priority' => 'Urgente',
                'justification' => 'Herramientas para mantenimiento de equipos médicos',
                'observations' => 'Equipos fuera de servicio',
                'responsibility_area_id' => 3, // Mantenimiento
                'requesting_user_id' => $users->random()->id,
                'approved_by' => $users->random()->id,
                'approved_date' => Carbon::now()->subDays(2),
                'total_amount' => 3200.00,
            ],
        ];

        foreach ($requests as $request) {
            \App\Models\PurchaseRequest::create($request);
        }
        
        // Crear algunas solicitudes de compra desde solicitudes generales convertidas
        $convertedGeneralRequests = \App\Models\GeneralRequest::where('status', 'convertida_a_compra')->take(2)->get();
        
        foreach ($convertedGeneralRequests as $generalRequest) {
            $purchaseRequest = \App\Models\PurchaseRequest::create([
                'request_number' => \App\Models\PurchaseRequest::generateNextNumber(),
                'request_date' => Carbon::now()->subDays(2),
                'status' => 'Pendiente',
                'priority' => $generalRequest->priority,
                'justification' => $generalRequest->description,
                'observations' => 'Convertida desde solicitud general: ' . $generalRequest->number,
                'responsibility_area_id' => $generalRequest->area_id,
                'requesting_user_id' => $generalRequest->created_by,
                'total_amount' => rand(5000, 20000),
                'converted_from_general_request_id' => $generalRequest->id,
            ]);
        }

        // Crear detalles de solicitudes
        $this->createPurchaseRequestDetails();
    }

    private function createPurchaseRequestDetails()
    {
        $requests = \App\Models\PurchaseRequest::all();
        $products = Product::all();
        
        $details = [
            // Detalles para SR-2024-0001 (Informática)
            [
                'purchase_request_id' => 1,
                'product_id' => 1, // Microscopio (como ejemplo de equipo)
                'requested_quantity' => 2,
                'specifications' => 'Equipos con garantía extendida',
                'justification' => 'Para prácticas de laboratorio',
                'estimated_unit_price' => 7500.00,
                'estimated_total' => 15000.00,
                'status' => 'Aprobada',
            ],
            // Detalles para SR-2024-0002 (Insumos de Salud)
            [
                'purchase_request_id' => 2,
                'product_id' => 4, // Reactivo para Glucosa
                'requested_quantity' => 10,
                'specifications' => 'Reactivos de alta pureza',
                'justification' => 'Reposición de stock',
                'estimated_unit_price' => 850.00,
                'estimated_total' => 8500.00,
                'status' => 'Pendiente',
            ],
            [
                'purchase_request_id' => 2,
                'product_id' => 6, // Jeringas Descartables
                'requested_quantity' => 5,
                'specifications' => 'Jeringas estériles 5ml',
                'justification' => 'Uso en prácticas',
                'estimated_unit_price' => 45.00,
                'estimated_total' => 225.00,
                'status' => 'Pendiente',
            ],
            // Detalles para SR-2024-0003 (Mantenimiento)
            [
                'purchase_request_id' => 3,
                'product_id' => 7, // Guantes de Látex
                'requested_quantity' => 8,
                'specifications' => 'Guantes de protección industrial',
                'justification' => 'Para trabajos de mantenimiento',
                'estimated_unit_price' => 25.50,
                'estimated_total' => 204.00,
                'status' => 'En Cotización',
            ],
        ];

        foreach ($details as $detail) {
            \App\Models\PurchaseRequestDetail::create($detail);
        }
    }

    private function createGeneralRequests()
    {
        $users = \App\Models\User::all();
        $areas = \App\Models\ResponsibilityArea::all();
        
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
                'status' => 'convertida_a_compra',
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
        $products = Product::all();
        
        $details = [
            // Detalles para la primera solicitud general (Reactivos para Laboratorio)
            [
                'general_request_id' => 1,
                'product_id' => 4, // Reactivo para Glucosa
                'requested_quantity' => 15,
                'specifications' => 'Reactivos de alta pureza para análisis clínicos',
                'justification' => 'Necesarios para las prácticas de laboratorio clínico de los estudiantes',
                'estimated_unit_price' => 850.00,
                'estimated_total' => 12750.00,
                'status' => 'Pendiente',
            ],
            [
                'general_request_id' => 1,
                'product_id' => 5, // Reactivo para Hemoglobina
                'requested_quantity' => 10,
                'specifications' => 'Reactivos para determinación de hemoglobina',
                'justification' => 'Para completar el panel de análisis hematológicos',
                'estimated_unit_price' => 650.00,
                'estimated_total' => 6500.00,
                'status' => 'Pendiente',
            ],
            
            // Detalles para la segunda solicitud general (Guantes de Protección)
            [
                'general_request_id' => 2,
                'product_id' => 7, // Guantes de Látex Talla M
                'requested_quantity' => 20,
                'specifications' => 'Guantes de nitrilo estériles talla M',
                'justification' => 'Reposición de stock para prácticas de anatomía y laboratorio',
                'estimated_unit_price' => 25.50,
                'estimated_total' => 510.00,
                'status' => 'Aprobada',
            ],
            [
                'general_request_id' => 2,
                'product_id' => 6, // Jeringas Descartables 5ml
                'requested_quantity' => 8,
                'specifications' => 'Jeringas estériles de 5ml',
                'justification' => 'Para extracción de muestras en prácticas',
                'estimated_unit_price' => 45.00,
                'estimated_total' => 360.00,
                'status' => 'Aprobada',
            ],
            
            // Detalles para la tercera solicitud general (Equipos de Computación)
            [
                'general_request_id' => 3,
                'product_id' => 1, // Microscopio (como ejemplo de equipo)
                'requested_quantity' => 5,
                'specifications' => 'Equipos con garantía extendida y soporte técnico',
                'justification' => 'Para renovar el aula de informática médica',
                'estimated_unit_price' => 7500.00,
                'estimated_total' => 37500.00,
                'status' => 'En Cotización',
            ],
            
            // Detalles para la cuarta solicitud general (Material de Limpieza)
            [
                'general_request_id' => 4,
                'product_id' => 5, // Solución Salina Fisiológica
                'requested_quantity' => 25,
                'specifications' => 'Productos de limpieza especializados para equipos de laboratorio',
                'justification' => 'Para mantenimiento y limpieza de equipos especializados',
                'estimated_unit_price' => 15.00,
                'estimated_total' => 375.00,
                'status' => 'Rechazada',
            ],
            
            // Detalles para la quinta solicitud general (Microscopios para Histología)
            [
                'general_request_id' => 5,
                'product_id' => 1, // Microscopio Óptico Binocular
                'requested_quantity' => 3,
                'specifications' => 'Microscopios ópticos con aumento 40x-1000x',
                'justification' => 'Para las prácticas de histología y citología de los estudiantes',
                'estimated_unit_price' => 7500.00,
                'estimated_total' => 22500.00,
                'status' => 'Comprada',
            ],
            [
                'general_request_id' => 5,
                'product_id' => 2, // Centrífuga de Laboratorio
                'requested_quantity' => 1,
                'specifications' => 'Centrífuga con capacidad para 12 tubos',
                'justification' => 'Para complementar el laboratorio de histología',
                'estimated_unit_price' => 12000.00,
                'estimated_total' => 12000.00,
                'status' => 'Comprada',
            ],
        ];

        foreach ($details as $detail) {
            \App\Models\GeneralRequestDetail::create($detail);
        }
    }
}
