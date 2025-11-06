<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GeneralRequest;
use App\Models\GeneralRequestDetail;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestDetail;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\PaymentOrder;
use App\Models\Reception;
use App\Models\Devolution;
use App\Models\User;
use App\Models\Supplier;
use App\Models\ResponsibilityArea;
use App\Models\MarketRate;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TraceabilityExampleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Este seeder crea ejemplos de trazabilidad completa desde solicitud general hasta recepción:
     * - Solicitudes Generales
     * - Solicitudes de Compra (relacionadas con solicitudes generales)
     * - Órdenes de Compra (relacionadas con solicitudes de compra)
     * - Órdenes de Pago (relacionadas con órdenes de compra)
     * - Recepciones (relacionadas con órdenes de compra)
     * - Devoluciones (relacionadas con recepciones)
     */
    public function run(): void
    {
        $this->command->info('Generando ejemplos de trazabilidad completa...');
        
        // Limpiar datos anteriores del seeder si existen
        $this->command->info('Limpiando datos anteriores del seeder...');
        try {
            // Primero eliminar órdenes de pago con formato OP-TRAZ (formato antiguo)
            $oldPaymentOrders = DB::table('payment_orders')
                ->where(function($query) {
                    $query->where('payment_number', 'like', 'OP-TRAZ-%')
                          ->orWhere('payment_number', 'like', 'OP-Traz-%')
                          ->orWhere('payment_number', 'like', 'OP-traz-%')
                          ->orWhere('payment_number', 'like', 'op-TRAZ-%');
                })
                ->pluck('id');
            
            if ($oldPaymentOrders->isNotEmpty()) {
                $this->command->info('Eliminando ' . $oldPaymentOrders->count() . ' órdenes de pago con formato OP-TRAZ...');
                
                // Eliminar detalles de órdenes de pago
                DB::table('op_details')->whereIn('payment_order_id', $oldPaymentOrders)->delete();
                
                // Eliminar órdenes de pago
                DB::table('payment_orders')->whereIn('id', $oldPaymentOrders)->delete();
            }
            
            // Eliminar órdenes de compra con formato OC-TRAZ (formato antiguo)
            $oldPurchaseOrders = DB::table('purchase_orders')
                ->where(function($query) {
                    $query->where('number', 'like', 'OC-TRAZ-%')
                          ->orWhere('number', 'like', 'OC-Traz-%')
                          ->orWhere('number', 'like', 'OC-traz-%')
                          ->orWhere('number', 'like', 'oc-TRAZ-%');
                })
                ->pluck('id');
            
            if ($oldPurchaseOrders->isNotEmpty()) {
                $this->command->info('Eliminando ' . $oldPurchaseOrders->count() . ' órdenes de compra con formato OC-TRAZ...');
                
                // Eliminar devoluciones relacionadas
                DB::table('devolutions')->whereIn('reception_id', function($query) use ($oldPurchaseOrders) {
                    $query->select('id')->from('receptions')
                        ->whereIn('purchase_order_id', $oldPurchaseOrders);
                })->delete();
                
                // Eliminar recepciones relacionadas
                DB::table('receptions')->whereIn('purchase_order_id', $oldPurchaseOrders)->delete();
                
                // Eliminar detalles de órdenes de pago relacionadas
                DB::table('op_details')->whereIn('payment_order_id', function($query) use ($oldPurchaseOrders) {
                    $query->select('id')->from('payment_orders')
                        ->whereIn('purchase_order_id', $oldPurchaseOrders);
                })->delete();
                
                // Eliminar órdenes de pago relacionadas
                DB::table('payment_orders')->whereIn('purchase_order_id', $oldPurchaseOrders)->delete();
                
                // Eliminar detalles de órdenes de compra
                DB::table('oc_details')->whereIn('purchase_order_id', $oldPurchaseOrders)->delete();
                
                // Eliminar órdenes de compra
                DB::table('purchase_orders')->whereIn('id', $oldPurchaseOrders)->delete();
            }
            
            // Eliminar datos relacionados con solicitudes generales de ejemplo
            $exampleGeneralRequests = DB::table('general_requests')
                ->where('title', 'like', '%Ejemplo%')
                ->pluck('id');
            
            if ($exampleGeneralRequests->isNotEmpty()) {
                // Eliminar solicitudes de compra relacionadas
                $examplePurchaseRequests = DB::table('purchase_requests')
                    ->whereIn('converted_from_general_request_id', $exampleGeneralRequests)
                    ->pluck('id');
                
                if ($examplePurchaseRequests->isNotEmpty()) {
                    // Eliminar órdenes de compra relacionadas
                    $examplePurchaseOrders = DB::table('purchase_orders')
                        ->whereIn('purchase_request_id', $examplePurchaseRequests)
                        ->pluck('id');
                    
                    if ($examplePurchaseOrders->isNotEmpty()) {
                        // Eliminar devoluciones
                        DB::table('devolutions')->whereIn('reception_id', function($query) use ($examplePurchaseOrders) {
                            $query->select('id')->from('receptions')
                                ->whereIn('purchase_order_id', $examplePurchaseOrders);
                        })->delete();
                        
                        // Eliminar recepciones
                        DB::table('receptions')->whereIn('purchase_order_id', $examplePurchaseOrders)->delete();
                        
                        // Eliminar detalles de órdenes de pago
                        DB::table('op_details')->whereIn('payment_order_id', function($query) use ($examplePurchaseOrders) {
                            $query->select('id')->from('payment_orders')
                                ->whereIn('purchase_order_id', $examplePurchaseOrders);
                        })->delete();
                        
                        // Eliminar órdenes de pago
                        DB::table('payment_orders')->whereIn('purchase_order_id', $examplePurchaseOrders)->delete();
                        
                        // Eliminar detalles de órdenes de compra
                        DB::table('oc_details')->whereIn('purchase_order_id', $examplePurchaseOrders)->delete();
                        
                        // Eliminar órdenes de compra
                        DB::table('purchase_orders')->whereIn('id', $examplePurchaseOrders)->delete();
                    }
                    
                    // Eliminar solicitudes de compra
                    DB::table('purchase_requests')->whereIn('id', $examplePurchaseRequests)->delete();
                }
                
                // Eliminar solicitudes generales
                DB::table('general_requests')->whereIn('id', $exampleGeneralRequests)->delete();
            }
        } catch (\Exception $e) {
            $this->command->warn('Error al limpiar datos anteriores: ' . $e->getMessage());
        }

        // Obtener o crear un usuario para las relaciones
        $user = User::first();
        if (!$user) {
            $user = User::create([
                'name' => 'Usuario Administrador',
                'email' => 'admin@porres.com',
                'password' => bcrypt('password123'),
                'email_verified_at' => now()
            ]);
        }

        // Obtener o crear área de responsabilidad
        $area = ResponsibilityArea::first();
        if (!$area) {
            $area = ResponsibilityArea::create([
                'name' => 'Área de Ejemplo',
                'description' => 'Área creada para ejemplos de trazabilidad',
                'responsible_user_id' => $user->id,
                'is_active' => true
            ]);
        }

        // Obtener proveedores existentes o crear uno
        $supplier = Supplier::first();
        if (!$supplier) {
            $supplier = Supplier::create([
                'company_name' => 'Proveedor Ejemplo S.A.',
                'cuit' => '20-12345678-9',
                'address' => 'Av. Ejemplo 123',
                'contact' => 'contacto@proveedor.com'
            ]);
        }

        // Obtener o crear una categoría (necesaria para productos si se usan)
        $category = Category::first();
        if (!$category) {
            $category = Category::create([
                'name' => 'Categoría Ejemplo'
            ]);
        }

        // Crear solicitudes generales
        $this->command->info('Creando solicitudes generales...');
        
        $generalRequest1 = GeneralRequest::create([
            'number' => GeneralRequest::generateNextNumber(),
            'created_by' => $user->id,
            'area_id' => $area->id,
            'title' => 'Solicitud General de Ejemplo 1',
            'description' => 'Esta es una solicitud general de ejemplo para probar la trazabilidad completa',
            'priority' => 'Alta',
            'status' => 'convertida_a_compra',
        ]);

        $generalRequest2 = GeneralRequest::create([
            'number' => GeneralRequest::generateNextNumber(),
            'created_by' => $user->id,
            'area_id' => $area->id,
            'title' => 'Solicitud General de Ejemplo 2',
            'description' => 'Segunda solicitud general de ejemplo',
            'priority' => 'Media',
            'status' => 'convertida_a_compra',
        ]);

        // Crear solicitudes generales incompletas (sin solicitudes de compra)
        $this->command->info('Creando solicitudes generales incompletas...');
        
        $generalRequest3 = GeneralRequest::create([
            'number' => GeneralRequest::generateNextNumber(),
            'created_by' => $user->id,
            'area_id' => $area->id,
            'title' => 'Solicitud General Pendiente',
            'description' => 'Solicitud general que aún no ha sido convertida a solicitud de compra',
            'priority' => 'Baja',
            'status' => 'revisada_area',
        ]);

        $generalRequest4 = GeneralRequest::create([
            'number' => GeneralRequest::generateNextNumber(),
            'created_by' => $user->id,
            'area_id' => $area->id,
            'title' => 'Solicitud General Recién Creada',
            'description' => 'Solicitud general recién creada, aún en revisión',
            'priority' => 'Media',
            'status' => 'creada',
        ]);

        // Crear productos asociados a todas las solicitudes generales
        $this->command->info('Creando productos asociados a las solicitudes generales...');
        
        // Obtener o crear productos para asociar
        $products = Product::all();
        
        if ($products->isEmpty()) {
            // Si no hay productos, crear algunos básicos
            $this->command->info('No hay productos disponibles. Creando productos básicos...');
            
            // Asegurarse de que existe una categoría
            $category = Category::first();
            if (!$category) {
                $category = Category::create([
                    'name' => 'Categoría General',
                    'description' => 'Categoría por defecto para productos de ejemplo'
                ]);
            }
            
            $products = collect([
                Product::create([
                    'name' => 'Producto de Ejemplo 1',
                    'description' => 'Producto básico para solicitudes generales',
                    'unit_measurement' => 'unidad',
                    'minimum_stock' => 10,
                    'category_id' => $category->id,
                ]),
                Product::create([
                    'name' => 'Producto de Ejemplo 2',
                    'description' => 'Segundo producto básico para solicitudes generales',
                    'unit_measurement' => 'unidad',
                    'minimum_stock' => 15,
                    'category_id' => $category->id,
                ]),
                Product::create([
                    'name' => 'Producto de Ejemplo 3',
                    'description' => 'Tercer producto básico para solicitudes generales',
                    'unit_measurement' => 'unidad',
                    'minimum_stock' => 20,
                    'category_id' => $category->id,
                ]),
            ]);
        } else {
            // Usar productos existentes (tomar hasta 3)
            $products = $products->take(3);
        }
        
        // Crear detalles para cada solicitud general
        $generalRequests = [
            $generalRequest1,
            $generalRequest2,
            $generalRequest3,
            $generalRequest4,
        ];
        
        foreach ($generalRequests as $idx => $gr) {
            // Asignar 1-2 productos por solicitud general
            $productsForRequest = $products->random(min(2, $products->count()));
            
            foreach ($productsForRequest as $product) {
                $quantity = rand(5, 25);
                $unitPrice = rand(100, 1000);
                $total = $quantity * $unitPrice;
                
                GeneralRequestDetail::create([
                    'general_request_id' => $gr->id,
                    'product_id' => $product->id,
                    'requested_quantity' => $quantity,
                    'specifications' => 'Especificaciones según solicitud general ' . $gr->number,
                    'justification' => 'Necesario para el área ' . ($area->name ?? ''),
                    'estimated_unit_price' => $unitPrice,
                    'estimated_total' => $total,
                    'status' => 'Pendiente',
                ]);
            }
        }

        // Crear solicitudes de compra relacionadas con las solicitudes generales
        $this->command->info('Creando solicitudes de compra...');
        
        $purchaseRequest1 = PurchaseRequest::create([
            'request_number' => PurchaseRequest::generateNextNumber(),
            'request_date' => Carbon::now()->subDays(25),
            'status' => 'Aprobada',
            'priority' => 'Alta',
            'justification' => 'Justificación para la solicitud de compra 1',
            'responsibility_area_id' => $area->id,
            'requesting_user_id' => $user->id,
            'approved_by' => $user->id,
            'approved_date' => Carbon::now()->subDays(24),
            'total_amount' => 75000.00,
            'converted_from_general_request_id' => $generalRequest1->id,
        ]);

        // Replicar productos de la solicitud general a la solicitud de compra 1
        $this->replicateGeneralRequestDetailsToPurchaseRequest($generalRequest1, $purchaseRequest1);

        $purchaseRequest2 = PurchaseRequest::create([
            'request_number' => PurchaseRequest::generateNextNumber(),
            'request_date' => Carbon::now()->subDays(20),
            'status' => 'Aprobada',
            'priority' => 'Media',
            'justification' => 'Justificación para la solicitud de compra 2',
            'responsibility_area_id' => $area->id,
            'requesting_user_id' => $user->id,
            'approved_by' => $user->id,
            'approved_date' => Carbon::now()->subDays(19),
            'total_amount' => 50000.00,
            'converted_from_general_request_id' => $generalRequest2->id,
        ]);

        // Replicar productos de la solicitud general a la solicitud de compra 2
        $this->replicateGeneralRequestDetailsToPurchaseRequest($generalRequest2, $purchaseRequest2);

        // Crear solicitudes de compra incompletas (sin órdenes de compra)
        // Necesitamos crear solicitudes generales adicionales para estas solicitudes de compra
        $this->command->info('Creando solicitudes de compra incompletas...');
        
        // Crear una solicitud general adicional para la solicitud de compra 3
        $generalRequest5 = GeneralRequest::create([
            'number' => GeneralRequest::generateNextNumber(),
            'created_by' => $user->id,
            'area_id' => $area->id,
            'title' => 'Solicitud General para Compra Pendiente',
            'description' => 'Solicitud general convertida a compra pero sin orden de compra aún',
            'priority' => 'Alta',
            'status' => 'convertida_a_compra',
        ]);

        // Crear otra solicitud general adicional para la solicitud de compra 4
        $generalRequest6 = GeneralRequest::create([
            'number' => GeneralRequest::generateNextNumber(),
            'created_by' => $user->id,
            'area_id' => $area->id,
            'title' => 'Solicitud General en Proceso',
            'description' => 'Solicitud general convertida a compra pero aún en proceso de cotización',
            'priority' => 'Media',
            'status' => 'convertida_a_compra',
        ]);
        
        // Agregar productos a las solicitudes generales adicionales (5 y 6) ANTES de crear las solicitudes de compra
        $additionalGeneralRequests = [$generalRequest5, $generalRequest6];
        foreach ($additionalGeneralRequests as $gr) {
            $productsForRequest = $products->random(min(2, $products->count()));
            
            foreach ($productsForRequest as $product) {
                $quantity = rand(5, 25);
                $unitPrice = rand(100, 1000);
                $total = $quantity * $unitPrice;
                
                GeneralRequestDetail::create([
                    'general_request_id' => $gr->id,
                    'product_id' => $product->id,
                    'requested_quantity' => $quantity,
                    'specifications' => 'Especificaciones según solicitud general ' . $gr->number,
                    'justification' => 'Necesario para el área ' . ($area->name ?? ''),
                    'estimated_unit_price' => $unitPrice,
                    'estimated_total' => $total,
                    'status' => 'Pendiente',
                ]);
            }
        }
        
        $purchaseRequest3 = PurchaseRequest::create([
            'request_number' => PurchaseRequest::generateNextNumber(),
            'request_date' => Carbon::now()->subDays(15),
            'status' => 'Aprobada',
            'priority' => 'Alta',
            'justification' => 'Solicitud de compra aprobada pero sin orden de compra generada aún',
            'responsibility_area_id' => $area->id,
            'requesting_user_id' => $user->id,
            'approved_by' => $user->id,
            'approved_date' => Carbon::now()->subDays(14),
            'total_amount' => 60000.00,
            'converted_from_general_request_id' => $generalRequest5->id,
        ]);

        // Replicar productos de la solicitud general a la solicitud de compra 3
        $this->replicateGeneralRequestDetailsToPurchaseRequest($generalRequest5, $purchaseRequest3);
        
        $purchaseRequest4 = PurchaseRequest::create([
            'request_number' => PurchaseRequest::generateNextNumber(),
            'request_date' => Carbon::now()->subDays(10),
            'status' => 'En Proceso',
            'priority' => 'Media',
            'justification' => 'Solicitud de compra en proceso de cotización',
            'responsibility_area_id' => $area->id,
            'requesting_user_id' => $user->id,
            'approved_by' => null,
            'approved_date' => null,
            'total_amount' => 40000.00,
            'converted_from_general_request_id' => $generalRequest6->id,
        ]);

        // Replicar productos de la solicitud general a la solicitud de compra 4
        $this->replicateGeneralRequestDetailsToPurchaseRequest($generalRequest6, $purchaseRequest4);

        // Crear cotizaciones (Market Rates) para las solicitudes de compra
        $this->command->info('Creando cotizaciones...');
        
        try {
            // Verificar si la tabla tiene la columna purchase_request_id
            $columns = DB::select('SHOW COLUMNS FROM market_rates');
            $hasPurchaseRequestId = collect($columns)->contains(function($column) {
                return $column->Field === 'purchase_request_id';
            });

            if ($hasPurchaseRequestId) {
                $marketRate1 = MarketRate::create([
                    'supplier_id' => $supplier->id,
                    'purchase_request_id' => $purchaseRequest1->id,
                    'date' => Carbon::now()->subDays(23),
                    'total_amount' => 75000.00,
                    'is_selected' => true,
                ]);

                $marketRate2 = MarketRate::create([
                    'supplier_id' => $supplier->id,
                    'purchase_request_id' => $purchaseRequest2->id,
                    'date' => Carbon::now()->subDays(18),
                    'total_amount' => 50000.00,
                    'is_selected' => true,
                ]);

                // Actualizar las solicitudes de compra con la cotización seleccionada
                $purchaseRequest1->update(['selected_market_rate_id' => $marketRate1->id]);
                $purchaseRequest2->update(['selected_market_rate_id' => $marketRate2->id]);
            } else {
                $this->command->warn('La tabla market_rates no tiene la columna purchase_request_id. Omitiendo creación de cotizaciones.');
            }
        } catch (\Exception $e) {
            $this->command->warn('Error al crear cotizaciones: ' . $e->getMessage());
        }

        // Crear órdenes de compra relacionadas con las solicitudes de compra
        $this->command->info('Creando órdenes de compra...');
        
        // Generar números de orden de compra usando el mismo formato que el sistema
        $year = Carbon::now()->year;
        $lastOrder = PurchaseOrder::where('number', 'like', 'OC-' . $year . '-%')
            ->orderByDesc('number')
            ->first();
        
        $nextSequence = 1;
        if ($lastOrder && $lastOrder->number) {
            $parts = explode('-', $lastOrder->number);
            $suffix = end($parts);
            $seq = (int) ltrim($suffix, '0');
            $nextSequence = $seq + 1;
        } else {
            // Contar órdenes existentes del año actual
            $nextSequence = PurchaseOrder::where('number', 'like', 'OC-' . $year . '-%')->count() + 1;
        }
        
        $purchaseOrder1 = PurchaseOrder::create([
            'number' => 'OC-' . $year . '-' . str_pad((string)$nextSequence, 3, '0', STR_PAD_LEFT),
            'date' => Carbon::now()->subDays(20),
            'status' => 'Aprobada',
            'supplier_id' => $supplier->id,
            'authorizing_user_id' => $user->id,
            'purchase_request_id' => $purchaseRequest1->id,
        ]);

        $nextSequence++;
        $purchaseOrder2 = PurchaseOrder::create([
            'number' => 'OC-' . $year . '-' . str_pad((string)$nextSequence, 3, '0', STR_PAD_LEFT),
            'date' => Carbon::now()->subDays(15),
            'status' => 'Aprobada',
            'supplier_id' => $supplier->id,
            'authorizing_user_id' => $user->id,
            'purchase_request_id' => $purchaseRequest2->id,
        ]);

        // Crear órdenes de compra incompletas (sin órdenes de pago o sin recepciones)
        $this->command->info('Creando órdenes de compra incompletas...');
        
        // Orden de compra sin orden de pago
        $nextSequence++;
        $purchaseOrder3 = PurchaseOrder::create([
            'number' => 'OC-' . $year . '-' . str_pad((string)$nextSequence, 3, '0', STR_PAD_LEFT),
            'date' => Carbon::now()->subDays(8),
            'status' => 'Aprobada',
            'supplier_id' => $supplier->id,
            'authorizing_user_id' => $user->id,
            'purchase_request_id' => $purchaseRequest3->id,
        ]);

        // Orden de compra sin recepción (pero con orden de pago)
        $nextSequence++;
        $purchaseOrder4 = PurchaseOrder::create([
            'number' => 'OC-' . $year . '-' . str_pad((string)$nextSequence, 3, '0', STR_PAD_LEFT),
            'date' => Carbon::now()->subDays(5),
            'status' => 'Aprobada',
            'supplier_id' => $supplier->id,
            'authorizing_user_id' => $user->id,
            'purchase_request_id' => $purchaseRequest3->id,
        ]);

        // Replicar productos de las solicitudes de compra a las órdenes de compra
        $this->command->info('Replicando productos de solicitudes de compra a órdenes de compra...');
        
        // Replicar productos de PurchaseRequest1 a PurchaseOrder1
        $this->replicatePurchaseRequestDetailsToPurchaseOrder($purchaseRequest1, $purchaseOrder1);
        
        // Replicar productos de PurchaseRequest2 a PurchaseOrder2
        $this->replicatePurchaseRequestDetailsToPurchaseOrder($purchaseRequest2, $purchaseOrder2);
        
        // Recargar las órdenes de compra para obtener el total calculado
        $purchaseOrder1->refresh();
        $purchaseOrder2->refresh();
        
        // Calcular los totales de las órdenes de compra
        $purchaseOrder1Total = $purchaseOrder1->total;
        $purchaseOrder2Total = $purchaseOrder2->total;
        
        $this->command->info('Total OC1: $' . number_format($purchaseOrder1Total, 2));
        $this->command->info('Total OC2: $' . number_format($purchaseOrder2Total, 2));

        // Crear órdenes de pago relacionadas con las órdenes de compra
        $this->command->info('Creando órdenes de pago relacionadas...');

        // Generar números de orden de pago usando el mismo formato que el sistema
        $lastPaymentOrder = PaymentOrder::where('payment_number', 'like', 'OP-' . $year . '-%')
            ->orderByDesc('payment_number')
            ->first();
        
        $nextPaymentSequence = 1;
        if ($lastPaymentOrder && $lastPaymentOrder->payment_number) {
            $parts = explode('-', $lastPaymentOrder->payment_number);
            $suffix = end($parts);
            $seq = (int) ltrim($suffix, '0');
            $nextPaymentSequence = $seq + 1;
        } else {
            // Contar órdenes de pago existentes del año actual
            $nextPaymentSequence = PaymentOrder::where('payment_number', 'like', 'OP-' . $year . '-%')->count() + 1;
        }

        // Calcular los totales reales de las órdenes de compra después de replicar productos
        $purchaseOrder1->refresh();
        $purchaseOrder2->refresh();
        $purchaseOrder1Total = $purchaseOrder1->total;
        $purchaseOrder2Total = $purchaseOrder2->total;
        
        // Orden de pago 1 - relacionada con la primera orden de compra
        // Primera orden de pago: anticipo del 66.67% (aproximadamente)
        $payment1Amount = round($purchaseOrder1Total * 0.6667, 2);
        $paymentOrder1 = PaymentOrder::create([
            'payment_number' => 'OP-' . $year . '-' . str_pad((string)$nextPaymentSequence, 3, '0', STR_PAD_LEFT),
            'date' => Carbon::now()->subDays(18),
            'total_amount' => $payment1Amount, // Anticipo del 66.67% (no supera el total)
            'status' => 'Aprobada',
            'purchase_order_id' => $purchaseOrder1->id,
            'authorizing_user_id' => $user->id,
        ]);

        // Orden de pago 2 - segunda orden de pago para la misma OC1 (saldo restante)
        // Total pagado hasta ahora: payment1Amount, Restante: purchaseOrder1Total - payment1Amount
        $remaining1 = $purchaseOrder1Total - $payment1Amount;
        $nextPaymentSequence++;
        $paymentOrder2 = PaymentOrder::create([
            'payment_number' => 'OP-' . $year . '-' . str_pad((string)$nextPaymentSequence, 3, '0', STR_PAD_LEFT),
            'date' => Carbon::now()->subDays(12),
            'total_amount' => $remaining1, // Saldo restante (total pagado = purchaseOrder1Total)
            'status' => 'Ejecutada',
            'purchase_order_id' => $purchaseOrder1->id,
            'authorizing_user_id' => $user->id,
        ]);

        // Orden de pago 3 - relacionada con la segunda orden de compra (pago completo)
        $nextPaymentSequence++;
        $paymentOrder3 = PaymentOrder::create([
            'payment_number' => 'OP-' . $year . '-' . str_pad((string)$nextPaymentSequence, 3, '0', STR_PAD_LEFT),
            'date' => Carbon::now()->subDays(13),
            'total_amount' => $purchaseOrder2Total, // Pago completo de OC2 (igual al total)
            'status' => 'Pendiente',
            'purchase_order_id' => $purchaseOrder2->id,
            'authorizing_user_id' => $user->id,
        ]);

        // Replicar productos de PurchaseRequest3 a PurchaseOrder4 (OC4 viene de la misma solicitud de compra 3)
        $this->replicatePurchaseRequestDetailsToPurchaseOrder($purchaseRequest3, $purchaseOrder4);
        
        $purchaseOrder4->refresh();
        $purchaseOrder4Total = $purchaseOrder4->total;
        
        $nextPaymentSequence++;
        $paymentOrder4 = PaymentOrder::create([
            'payment_number' => 'OP-' . $year . '-' . str_pad((string)$nextPaymentSequence, 3, '0', STR_PAD_LEFT),
            'date' => Carbon::now()->subDays(3),
            'total_amount' => $purchaseOrder4Total, // Pago completo de OC4 (igual al total)
            'status' => 'Aprobada',
            'purchase_order_id' => $purchaseOrder4->id,
            'authorizing_user_id' => $user->id,
        ]);
        
        // Replicar productos de PurchaseRequest3 a PurchaseOrder3
        $this->replicatePurchaseRequestDetailsToPurchaseOrder($purchaseRequest3, $purchaseOrder3);
        
        $purchaseOrder3->refresh();
        $purchaseOrder3Total = $purchaseOrder3->total;


        // Crear recepciones relacionadas con las órdenes de compra
        $this->command->info('Creando recepciones relacionadas...');

        // Recepción 1 - relacionada con OC1 (conforme, SIN devolución)
        $reception1 = Reception::create([
            'purchase_order_id' => $purchaseOrder1->id,
            'date' => Carbon::now()->subDays(10),
            'according' => 'Si',
            'area_manager_id' => $user->id,
        ]);

        // Recepción 2 - relacionada con OC2 (conforme, SIN devolución)
        $reception2 = Reception::create([
            'purchase_order_id' => $purchaseOrder2->id,
            'date' => Carbon::now()->subDays(5),
            'according' => 'Si',
            'area_manager_id' => $user->id,
        ]);

        // Recepción 3 - relacionada con OC2 (NO conforme, CON devolución)
        $reception3 = Reception::create([
            'purchase_order_id' => $purchaseOrder2->id,
            'date' => Carbon::now()->subDays(3),
            'according' => 'No',
            'area_manager_id' => $user->id,
        ]);

        // Recepción 4 - relacionada con OC4 (conforme, SIN devolución)
        $reception4 = Reception::create([
            'purchase_order_id' => $purchaseOrder4->id,
            'date' => Carbon::now()->subDays(2),
            'according' => 'Si',
            'area_manager_id' => $user->id,
        ]);
        
        // Nota: OC3 no tiene recepción (proceso incompleto)

        // Crear devoluciones relacionadas solo con recepciones NO conformes
        $this->command->info('Creando devoluciones relacionadas...');

        // Devolución 1 - relacionada con Recepción 3 (que tiene according = 'No')
        $devolution1 = Devolution::create([
            'reception_id' => $reception3->id,
            'reason' => 'Productos defectuosos recibidos. Se requiere devolución completa.',
            'date' => Carbon::now()->subDays(1),
            'user_id' => $user->id,
        ]);

        // Nota: Las recepciones 1, 2 y 4 están conformes y NO tienen devoluciones

        // Crear detalles de órdenes de pago para hacer más realista
        // Los montos de los detalles deben coincidir con el total_amount de cada orden de pago
        $this->command->info('Creando detalles de órdenes de pago...');

        // Recargar las órdenes de pago para obtener sus total_amount reales
        $paymentOrder1->refresh();
        $paymentOrder2->refresh();
        $paymentOrder3->refresh();
        $paymentOrder4->refresh();

        // Detalles para la primera orden de pago (OP1 - anticipo de OC1)
        DB::table('op_details')->insert([
            [
                'payment_order_id' => $paymentOrder1->id,
                'concept' => 'advance',
                'amount' => $paymentOrder1->total_amount, // Monto igual al total de la orden de pago
                'method_payment' => 'Transferencia bancaria',
                'expiration_date' => Carbon::now()->addDays(30),
                'actual_payment_date' => Carbon::now()->subDays(15),
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        // Detalles para la segunda orden de pago (OP2 - saldo restante de OC1)
        DB::table('op_details')->insert([
            [
                'payment_order_id' => $paymentOrder2->id,
                'concept' => 'residue',
                'amount' => $paymentOrder2->total_amount, // Monto igual al total de la orden de pago
                'method_payment' => 'Cheque',
                'expiration_date' => Carbon::now()->addDays(45),
                'actual_payment_date' => Carbon::now()->subDays(10),
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        // Detalles para la tercera orden de pago (OP3 - pago completo de OC2)
        DB::table('op_details')->insert([
            [
                'payment_order_id' => $paymentOrder3->id,
                'concept' => 'partiality',
                'amount' => $paymentOrder3->total_amount, // Monto igual al total de la orden de pago
                'method_payment' => 'Transferencia bancaria',
                'expiration_date' => Carbon::now()->addDays(20),
                'actual_payment_date' => null,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        // Detalles para la cuarta orden de pago (OP4 - pago completo de OC4, sin recepción)
        DB::table('op_details')->insert([
            [
                'payment_order_id' => $paymentOrder4->id,
                'concept' => 'partiality',
                'amount' => $paymentOrder4->total_amount, // Monto igual al total de la orden de pago
                'method_payment' => 'Transferencia bancaria',
                'expiration_date' => Carbon::now()->addDays(15),
                'actual_payment_date' => null,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);



        $this->command->info('¡Ejemplos de trazabilidad completa creados exitosamente!');
        $this->command->info('');
        $this->command->info('Resumen de datos creados:');
        $this->command->info('');
        $this->command->info('PROCESOS COMPLETOS:');
        $this->command->info('- 2 Solicitudes Generales completas:');
        $this->command->info('  • ' . $generalRequest1->number . ' → Solicitud de Compra → ' . $purchaseOrder1->number . ' → 2 Órdenes de Pago → Recepción → Devolución');
        $this->command->info('  • ' . $generalRequest2->number . ' → Solicitud de Compra → ' . $purchaseOrder2->number . ' → Orden de Pago → Recepción → Devolución');
        $this->command->info('');
        $this->command->info('PROCESOS INCOMPLETOS:');
        $this->command->info('- 2 Solicitudes Generales sin solicitudes de compra:');
        $this->command->info('  • ' . $generalRequest3->number . ' (Estado: revisada_area)');
        $this->command->info('  • ' . $generalRequest4->number . ' (Estado: creada)');
        $this->command->info('- 2 Solicitudes Generales con solicitudes de compra pero sin órdenes de compra:');
        $this->command->info('  • ' . $generalRequest5->number . ' → ' . $purchaseRequest3->request_number . ' (Aprobada, sin OC)');
        $this->command->info('  • ' . $generalRequest6->number . ' → ' . $purchaseRequest4->request_number . ' (En Proceso)');
        $this->command->info('- 1 Orden de Compra sin orden de pago:');
        $this->command->info('  • ' . $purchaseOrder3->number . ' (Total: $' . number_format($purchaseOrder3Total, 2) . ')');
        $this->command->info('- 1 Orden de Compra sin recepción (pero con orden de pago):');
        $this->command->info('  • ' . $purchaseOrder3->number . ' (Total: $' . number_format($purchaseOrder3Total, 2) . ')');
        $this->command->info('');
        $this->command->info('RECEPCIONES:');
        $this->command->info('- 3 Recepciones CONFORMES sin devoluciones:');
        $this->command->info('  • Recepción para ' . $purchaseOrder1->number . ' (conforme, sin devolución)');
        $this->command->info('  • Recepción para ' . $purchaseOrder2->number . ' (conforme, sin devolución)');
        $this->command->info('  • Recepción para ' . $purchaseOrder4->number . ' (conforme, sin devolución)');
        $this->command->info('- 1 Recepción NO CONFORME con devolución:');
        $this->command->info('  • Recepción para ' . $purchaseOrder2->number . ' (no conforme, con devolución)');
        $this->command->info('');
        $this->command->info('TOTALES:');
        $this->command->info('- 6 Solicitudes Generales (2 completas, 4 incompletas)');
        $this->command->info('- 4 Solicitudes de Compra (2 con OC, 2 sin OC)');
        $this->command->info('- 4 Órdenes de Compra (2 completas, 2 incompletas)');
        $this->command->info('- 4 Órdenes de Pago');
        $this->command->info('- 4 Recepciones (3 conformes sin devolución, 1 no conforme con devolución)');
        $this->command->info('- 1 Devolución');
        $this->command->info('');
        $this->command->info('Puedes ver todos los procesos (completos e incompletos) en el dashboard.');
    }

    /**
     * Replicar detalles de solicitud general a solicitud de compra
     */
    private function replicateGeneralRequestDetailsToPurchaseRequest($generalRequest, $purchaseRequest)
    {
        try {
            // Cargar los detalles de la solicitud general con sus productos
            $generalRequest->load('details.product');

            $totalAmount = 0;
            $replicatedCount = 0;

            foreach ($generalRequest->details as $generalDetail) {
                if (!$generalDetail->product) {
                    continue;
                }

                // Crear el detalle en la solicitud de compra
                $purchaseRequestDetail = PurchaseRequestDetail::create([
                    'purchase_request_id' => $purchaseRequest->id,
                    'product_id' => $generalDetail->product_id,
                    'requested_quantity' => $generalDetail->requested_quantity,
                    'specifications' => $generalDetail->specifications,
                    'justification' => $generalDetail->justification,
                    'estimated_unit_price' => $generalDetail->estimated_unit_price ?? 0,
                    'estimated_total' => $generalDetail->estimated_total ?? ($generalDetail->estimated_unit_price * $generalDetail->requested_quantity ?? 0),
                    'status' => 'Pendiente'
                ]);

                $totalAmount += $purchaseRequestDetail->estimated_total;
                $replicatedCount++;
            }

            // Actualizar el monto total de la solicitud de compra
            if ($totalAmount > 0) {
                $purchaseRequest->update(['total_amount' => $totalAmount]);
            }

            $this->command->info("  ✓ Replicados {$replicatedCount} productos desde {$generalRequest->number} a {$purchaseRequest->request_number}");

        } catch (\Exception $e) {
            $this->command->warn("Error al replicar productos: " . $e->getMessage());
        }
    }

    /**
     * Replicar detalles de solicitud de compra a orden de compra
     */
    private function replicatePurchaseRequestDetailsToPurchaseOrder($purchaseRequest, $purchaseOrder)
    {
        try {
            // Cargar los detalles de la solicitud de compra con sus productos
            $purchaseRequest->load('details.product');

            $replicatedCount = 0;

            foreach ($purchaseRequest->details as $requestDetail) {
                if (!$requestDetail->product) {
                    continue;
                }

                // Buscar o crear el Input correspondiente al Product
                $input = $this->findOrCreateInputFromProduct($requestDetail->product);
                
                if (!$input) {
                    $this->command->warn("No se pudo crear input para producto: " . ($requestDetail->product->name ?? 'N/A'));
                    continue;
                }

                // Crear el detalle en la orden de compra
                PurchaseOrderDetail::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'input_id' => $input->id,
                    'quantity' => $requestDetail->requested_quantity,
                    'unit_price' => $requestDetail->estimated_unit_price ?? 0,
                ]);

                $replicatedCount++;
            }

            $this->command->info("  ✓ Replicados {$replicatedCount} productos desde {$purchaseRequest->request_number} a {$purchaseOrder->number}");

        } catch (\Exception $e) {
            $this->command->warn("Error al replicar productos de solicitud de compra a orden de compra: " . $e->getMessage());
        }
    }

    /**
     * Find or create input from product
     */
    private function findOrCreateInputFromProduct($product)
    {
        // Intentar encontrar un input con el mismo nombre
        $input = DB::table('inputs')->where('name', $product->name)->first();
        
        if ($input) {
            return (object)['id' => $input->id];
        }

        // Si no existe, crear uno nuevo
        try {
            $inputId = DB::table('inputs')->insertGetId([
                'name' => $product->name,
                'description' => $product->description,
                'unit' => $product->unit_measurement ?? 'unidad',
                'price' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return (object)['id' => $inputId];
        } catch (\Exception $e) {
            $this->command->warn("Error al crear input desde producto: " . $e->getMessage());
            return null;
        }
    }
}

