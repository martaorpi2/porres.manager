# Detalles de Cotización - Vista Previa

## 🎯 Funcionalidad Implementada

Se ha implementado la funcionalidad para mostrar el detalle completo de cada cotización en el botón de **Vista Previa** del módulo de Cotizaciones.

## 🔧 Cambios Realizados

### 1. Modelos Actualizados

#### MarketRate.php
- ✅ Agregada relación `supplier()` - pertenece a un proveedor
- ✅ Agregada relación `application()` - pertenece a una aplicación
- ✅ Agregada relación `quoteDetails()` - tiene muchos detalles de cotización

#### QuoteDetail.php
- ✅ Agregada relación `marketRate()` - pertenece a una cotización
- ✅ Agregada relación `product()` - pertenece a un producto

### 2. Controlador Configurado

#### MarketRateCrudController.php
- ✅ **Listado mejorado**: Muestra información básica + contador de productos
- ✅ **Vista previa personalizada**: Tabla completa con detalles de cotización
- ✅ **Carga de relaciones**: Optimizada para evitar consultas N+1
- ✅ **Interfaz en español**: "cotización" en lugar de "market rate"

## 📊 Vista Previa de Cotización

### Información Mostrada:
1. **Datos básicos de la cotización**:
   - ID de la cotización
   - Proveedor
   - Aplicación asociada
   - Fecha
   - Monto total

2. **Tabla de detalles de productos**:
   - **Producto**: Nombre y descripción
   - **Cantidad**: Con badge visual
   - **Precio Unitario**: Formato monetario
   - **Subtotal**: Calculado automáticamente
   - **Total General**: Suma de todos los subtotales

### Características Visuales:
- 🎨 **Tabla responsiva** con Bootstrap
- 🏷️ **Badges coloridos** para cantidades y subtotales
- 💰 **Formato monetario** con separadores de miles
- 📱 **Diseño adaptativo** para móviles
- ⚠️ **Mensaje informativo** cuando no hay detalles

## 🚀 Cómo Usar

### Acceso a la Vista Previa:
1. Ir a **Cotizaciones → Listado**
2. Hacer clic en el botón **👁️ Vista Previa** de cualquier cotización
3. Se mostrará la página completa con todos los detalles

### Ejemplo de Vista:
```
┌─────────────────────────────────────────────────────────────┐
│                    COTIZACIÓN #1                            │
├─────────────────────────────────────────────────────────────┤
│ Proveedor: MedEquip S.A.                                    │
│ Fecha: 15/09/2024                                           │
│ Monto Total: $15,000.00                                     │
├─────────────────────────────────────────────────────────────┤
│                    DETALLES DE COTIZACIÓN                   │
├─────────────────────────────────────────────────────────────┤
│ Producto          │ Cant. │ Precio Unit. │ Subtotal        │
├─────────────────────────────────────────────────────────────┤
│ Microscopio       │   1   │   $7,500.00  │   $7,500.00     │
│ Reactivo Glucosa  │   2   │     $850.00  │   $1,700.00     │
├─────────────────────────────────────────────────────────────┤
│ TOTAL:                                    │   $9,200.00     │
└─────────────────────────────────────────────────────────────┘
```

## 🔍 Estructura de Datos

### Relaciones Implementadas:
```
MarketRate (Cotización)
├── supplier (Proveedor)
├── application (Aplicación)
└── quoteDetails[] (Detalles de Cotización)
    ├── product (Producto)
    └── marketRate (Cotización padre)
```

### Campos Mostrados:
- **MarketRate**: ID, proveedor, aplicación, fecha, monto total
- **QuoteDetail**: producto, cantidad, precio unitario, subtotal calculado

## 🎨 Personalización Visual

### Colores Utilizados:
- 🔵 **Badge Primario**: Cantidades
- 🟢 **Badge Verde**: Subtotales y total
- 🔵 **Badge Info**: Contador en listado
- ⚪ **Tabla Striped**: Alternancia de filas

### Clases CSS:
- `table-responsive`: Tabla adaptativa
- `table-striped`: Filas alternadas
- `table-bordered`: Bordes completos
- `text-end`: Alineación derecha
- `badge bg-*`: Badges coloridos

## ✅ Beneficios

1. **Información Completa**: Vista detallada de todos los productos cotizados
2. **Cálculos Automáticos**: Subtotales y totales calculados dinámicamente
3. **Interfaz Intuitiva**: Fácil de leer y entender
4. **Responsive**: Funciona en todos los dispositivos
5. **Optimizada**: Carga eficiente de datos con relaciones

## 🔧 Mantenimiento

### Para agregar más campos:
1. Modificar el método `setupShowOperation()` en `MarketRateCrudController.php`
2. Actualizar la tabla HTML en el campo `quote_details_table`
3. Agregar las relaciones necesarias en los modelos

### Para cambiar el diseño:
1. Modificar las clases CSS en el HTML generado
2. Ajustar la estructura de la tabla
3. Personalizar los badges y colores

¡La funcionalidad está lista para usar! 🎉
