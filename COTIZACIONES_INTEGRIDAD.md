# Migraciones para Integridad de Cotizaciones

Este conjunto de migraciones asegura que las cotizaciones (market-rates) solo contengan productos que estén detallados en las solicitudes de compra (purchase-requests).

## Archivos Creados

### 1. Migraciones
- `2025_10_20_193927_ensure_quote_details_only_contain_purchase_request_products.php`
- `2025_10_20_193944_add_constraint_quote_details_purchase_request_products.php`

### 2. Seeder
- `PurchaseRequestQuoteSeeder.php`

### 3. Comando Artisan
- `EnsureQuoteDataIntegrity.php`

## Instrucciones de Ejecución

### Paso 1: Verificar Estado Actual
```bash
# Ver qué datos inconsistentes existen (sin hacer cambios)
php artisan quotes:ensure-integrity --dry-run
```

### Paso 2: Crear Backup (Recomendado)
```bash
# Crear backup antes de hacer cambios
php artisan quotes:ensure-integrity --backup --dry-run
```

### Paso 3: Ejecutar Migraciones
```bash
# Ejecutar las migraciones
php artisan migrate
```

### Paso 4: Limpiar Datos Inconsistentes
```bash
# Limpiar datos inconsistentes existentes
php artisan quotes:ensure-integrity --backup
```

### Paso 5: Generar Datos de Ejemplo (Opcional)
```bash
# Generar datos de ejemplo que cumplan con la restricción
php artisan db:seed --class=PurchaseRequestQuoteSeeder
```

## Qué Hacen las Migraciones

### Migración 1: Limpieza de Datos
- Elimina `quote_details` que contengan productos no presentes en `purchase_request_details`
- Elimina `market_rates` que queden sin `quote_details` después de la limpieza
- Agrega índices para mejorar performance

### Migración 2: Restricciones de Base de Datos
- Crea triggers que previenen la inserción/actualización de `quote_details` con productos no válidos
- Crea una vista para validar la integridad de los datos
- Establece restricciones a nivel de base de datos

## Comando de Verificación

El comando `quotes:ensure-integrity` permite:

```bash
# Solo verificar (sin cambios)
php artisan quotes:ensure-integrity --dry-run

# Crear backup y limpiar
php artisan quotes:ensure-integrity --backup

# Solo limpiar (sin backup)
php artisan quotes:ensure-integrity
```

## Estructura de Datos Esperada

Después de ejecutar las migraciones:

1. **Purchase Request Details**: Contiene los productos solicitados
2. **Quote Details**: Solo puede contener productos que estén en Purchase Request Details
3. **Market Rates**: Solo puede existir si tiene Quote Details válidos

## Flujo de Trabajo Recomendado

1. Crear Solicitud de Compra con productos
2. Crear Cotización solo para productos de la solicitud
3. Seleccionar cotización en la solicitud
4. Generar Orden de Compra

## Validaciones Implementadas

- ✅ Productos en cotizaciones deben existir en solicitudes de compra
- ✅ Cotizaciones sin productos válidos se eliminan automáticamente
- ✅ Triggers previenen inserción de datos inconsistentes
- ✅ Totales se recalculan automáticamente
- ✅ Índices mejoran performance de consultas

## Notas Importantes

- ⚠️ **Backup**: Siempre crear backup antes de ejecutar en producción
- ⚠️ **Irreversible**: La eliminación de datos no se puede revertir
- ⚠️ **Triggers**: Los triggers solo funcionan en MySQL/MariaDB
- ✅ **Seguro**: El comando `--dry-run` permite verificar antes de ejecutar
