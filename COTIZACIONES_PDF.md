# Botón PDF para Cotizaciones

## 🎯 Funcionalidad Implementada

Se ha agregado un botón **PDF** para cada cotización que genera un documento profesional con el mismo estilo que las órdenes de compra.

## 🔧 Archivos Creados/Modificados

### 1. Vista PDF (`resources/views/market-rate-pdf.blade.php`)
- ✅ **Estilo consistente** con las órdenes de compra
- ✅ **Header azul** para diferenciar cotizaciones
- ✅ **Información completa** de la cotización
- ✅ **Tabla detallada** con todos los productos
- ✅ **Cálculos automáticos** de subtotales y total
- ✅ **Condiciones generales** incluidas

### 2. Controlador (`app/Http/Controllers/Admin/MarketRateCrudController.php`)
- ✅ **Método `generatePdf()`** para generar el PDF
- ✅ **Botón PDF** agregado en cada fila del listado
- ✅ **Carga de relaciones** optimizada para el PDF

### 3. Ruta (`routes/web.php`)
- ✅ **Ruta registrada**: `admin/market-rate/{id}/pdf`
- ✅ **Nombre de ruta**: `market-rate.pdf`

### 4. Botón Personalizado (`resources/views/vendor/backpack/crud/buttons/pdf.blade.php`)
- ✅ **Icono PDF** con enlace directo
- ✅ **Apertura en nueva pestaña**
- ✅ **Estilo consistente** con otros botones

## 📊 Contenido del PDF

### Información Mostrada:
1. **Header de Cotización**:
   - Título "COTIZACIÓN" en azul
   - Número de cotización (COT-0001, COT-0002, etc.)

2. **Datos Básicos**:
   - Fecha de cotización
   - Monto total
   - Proveedor (nombre, CUIT, dirección, contacto)
   - Aplicación asociada
   - Validez de la cotización

3. **Tabla de Productos**:
   - Ítem numerado
   - Nombre y descripción del producto
   - Cantidad
   - Precio unitario
   - Subtotal calculado

4. **Total General**:
   - Suma de todos los subtotales
   - Formato monetario con separadores

5. **Condiciones Generales**:
   - Validez de precios (30 días)
   - Inclusión de IVA
   - Condiciones de entrega
   - Forma de pago
   - Fecha de generación del documento

## 🎨 Estilo Visual

### Características del Diseño:
- 🔵 **Header azul** (#e8f4fd) para diferenciar de órdenes de compra
- 📄 **Fuente DejaVu Sans** compatible con PDF
- 📊 **Tabla profesional** con bordes y encabezados
- 💰 **Formato monetario** con separadores de miles
- 📅 **Fechas en formato** dd/mm/yyyy
- 🏷️ **Numeración automática** de cotizaciones

### Colores Utilizados:
- **Azul principal**: #1976D2 (título)
- **Fondo header**: #e8f4fd
- **Bordes**: #2196F3
- **Texto secundario**: #666
- **Bordes tabla**: #ddd

## 🚀 Cómo Usar

### Acceso al PDF:
1. Ir a **Cotizaciones → Listado**
2. Hacer clic en el botón **📄 PDF** de cualquier cotización
3. Se abrirá el PDF en una nueva pestaña
4. El archivo se descarga automáticamente con el nombre: `cotizacion-0001.pdf`

### Ejemplo de Uso:
```
┌─────────────────────────────────────────────────────────────┐
│                    COTIZACIÓN                               │
│                    N.º COT-0001                            │
├─────────────────────────────────────────────────────────────┤
│ Fecha: 15/09/2024                                          │
│ Proveedor: MedEquip S.A.                                   │
│ Monto Total: $15,000.00                                    │
├─────────────────────────────────────────────────────────────┤
│ Producto          │ Cant. │ Precio Unit. │ Subtotal        │
├─────────────────────────────────────────────────────────────┤
│ Microscopio       │   1   │   $7,500.00  │   $7,500.00     │
│ Reactivo Glucosa  │   2   │     $850.00  │   $1,700.00     │
├─────────────────────────────────────────────────────────────┤
│ TOTAL:                                    │   $9,200.00     │
└─────────────────────────────────────────────────────────────┘
```

## 🔧 Configuración Técnica

### Dependencias:
- **DomPDF**: Para generación de PDFs
- **Carbon**: Para manejo de fechas
- **Backpack CRUD**: Para la interfaz administrativa

### Métodos Implementados:
```php
// Generar PDF
public function generatePdf($id)
{
    $marketRate = MarketRate::with(['supplier', 'quoteDetails.product'])->findOrFail($id);
    $pdf = Pdf::loadView('market-rate-pdf', compact('marketRate'));
    return $pdf->stream('cotizacion-' . str_pad($marketRate->id, 4, '0', STR_PAD_LEFT) . '.pdf');
}
```

### Ruta Registrada:
```php
Route::get('admin/market-rate/{id}/pdf', [MarketRateCrudController::class, 'generatePdf'])
    ->name('market-rate.pdf');
```

## ✅ Beneficios

1. **Documentación Profesional**: PDFs con formato empresarial
2. **Consistencia Visual**: Mismo estilo que órdenes de compra
3. **Información Completa**: Todos los detalles de la cotización
4. **Fácil Acceso**: Botón directo desde el listado
5. **Descarga Automática**: Archivo listo para compartir
6. **Numeración Automática**: Identificación clara de cotizaciones

## 🔄 Mantenimiento

### Para Modificar el Estilo:
1. Editar `resources/views/market-rate-pdf.blade.php`
2. Ajustar los estilos CSS en la sección `<style>`
3. Modificar la estructura HTML según necesidades

### Para Agregar Más Información:
1. Actualizar el método `generatePdf()` en el controlador
2. Cargar las relaciones necesarias
3. Modificar la vista para mostrar los nuevos datos

¡La funcionalidad está lista para usar! 🎉
