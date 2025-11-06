# Seeder de Ejemplos de Trazabilidad

Este seeder crea ejemplos de datos relacionados para probar la funcionalidad de trazabilidad en el dashboard.

## ¿Qué crea este seeder?

El seeder `TraceabilityExampleSeeder` genera:

### Órdenes de Compra (3)
- **OC-TRAZ-001**: Orden de compra con 2 órdenes de pago y 1 recepción (con devolución)
- **OC-TRAZ-002**: Orden de compra con 1 orden de pago y 1 recepción
- **OC-TRAZ-003**: Orden de compra con 1 orden de pago y 1 recepción (con devolución)

### Órdenes de Pago (4)
- **OP-TRAZ-001**: Relacionada con OC-TRAZ-001 (Estado: Aprobada)
- **OP-TRAZ-002**: Relacionada con OC-TRAZ-001 (Estado: Ejecutada) - Segunda orden de pago para la misma OC
- **OP-TRAZ-003**: Relacionada con OC-TRAZ-002 (Estado: Pendiente)
- **OP-TRAZ-004**: Relacionada con OC-TRAZ-003 (Estado: Aprobada)

### Recepciones (3)
- **Recepción 1**: Relacionada con OC-TRAZ-001 (Conforme: Si)
- **Recepción 2**: Relacionada con OC-TRAZ-002 (Conforme: Si)
- **Recepción 3**: Relacionada con OC-TRAZ-003 (Conforme: No)

### Devoluciones (2)
- **Devolución 1**: Relacionada con Recepción 3 (motivo: Productos defectuosos)
- **Devolución 2**: Relacionada con Recepción 1 (motivo: Devolución parcial)

## Cómo ejecutar el seeder

### Opción 1: Ejecutar solo este seeder
```bash
php artisan db:seed --class=TraceabilityExampleSeeder
```

### Opción 2: Ejecutar desde tinker
```bash
php artisan tinker
```
Luego ejecutar:
```php
\DB::seed(\Database\Seeders\TraceabilityExampleSeeder::class);
```

### Opción 3: Agregar al DatabaseSeeder
Si deseas que se ejecute automáticamente con otros seeders, agrega esta línea al método `run()` en `database/seeders/DatabaseSeeder.php`:

```php
$this->call(TraceabilityExampleSeeder::class);
```

## Verificar la trazabilidad

Después de ejecutar el seeder, puedes verificar la trazabilidad completa en:

1. **Dashboard**: Ve a `/admin` y desplázate hasta la sección "Trazabilidad Completa de Procesos"
2. **Órdenes de Compra**: Verás las órdenes OC-TRAZ-001, OC-TRAZ-002 y OC-TRAZ-003
3. **Órdenes de Pago**: Verás las órdenes OP-TRAZ-001 a OP-TRAZ-004 relacionadas con las órdenes de compra
4. **Recepciones**: Verás las recepciones relacionadas con las órdenes de compra
5. **Devoluciones**: Verás las devoluciones relacionadas con las recepciones

## Estructura de relaciones creada

```
OC-TRAZ-001
├── OP-TRAZ-001 (Orden de Pago 1)
├── OP-TRAZ-002 (Orden de Pago 2)
└── Recepción 1
    └── Devolución 2

OC-TRAZ-002
├── OP-TRAZ-003 (Orden de Pago)
└── Recepción 2

OC-TRAZ-003
├── OP-TRAZ-004 (Orden de Pago)
└── Recepción 3
    └── Devolución 1
```

## Notas importantes

- El seeder crea automáticamente un usuario y proveedor si no existen
- Si no hay insumos en la base de datos, crea uno automáticamente
- Los datos se crean con fechas relativas (hace X días) para simular un flujo temporal real
- Puedes ejecutar este seeder múltiples veces, pero puede generar duplicados si los números ya existen

## Limpiar datos de ejemplo

Si deseas eliminar los datos creados por este seeder, puedes ejecutar:

```sql
DELETE FROM devolutions WHERE reception_id IN (
    SELECT id FROM receptions WHERE purchase_order_id IN (
        SELECT id FROM purchase_orders WHERE number LIKE 'OC-TRAZ-%'
    )
);

DELETE FROM receptions WHERE purchase_order_id IN (
    SELECT id FROM purchase_orders WHERE number LIKE 'OC-TRAZ-%'
);

DELETE FROM op_details WHERE payment_order_id IN (
    SELECT id FROM payment_orders WHERE payment_number LIKE 'OP-TRAZ-%'
);

DELETE FROM payment_orders WHERE payment_number LIKE 'OP-TRAZ-%';

DELETE FROM oc_details WHERE purchase_order_id IN (
    SELECT id FROM purchase_orders WHERE number LIKE 'OC-TRAZ-%'
);

DELETE FROM purchase_orders WHERE number LIKE 'OC-TRAZ-%';
```

O usar el seeder `CleanDatabaseSeeder` si está configurado para limpiar estos datos.

