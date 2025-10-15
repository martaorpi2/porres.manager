# Sistema de Solicitudes por Área

## 🎯 Funcionalidad Implementada

Se ha implementado un sistema completo de solicitudes de productos y solicitudes de compra organizadas por áreas de responsabilidad, permitiendo un control eficiente de las necesidades de cada departamento.

## 🏢 Áreas de Responsabilidad

### Áreas Configuradas:
1. **Informática** - Equipos de cómputo, software y sistemas informáticos
2. **Insumos de Salud** - Material médico, reactivos y equipos de laboratorio
3. **Mantenimiento** - Herramientas, repuestos y materiales de mantenimiento
4. **Insumos Generales** - Material de oficina, limpieza y suministros generales

## 📋 Estructura del Sistema

### 1. Áreas de Responsabilidad (`responsibility_areas`)
- **ID único** de cada área
- **Nombre** descriptivo del área
- **Descripción** detallada de responsabilidades
- **Usuario responsable** asignado
- **Estado activo/inactivo**

### 2. Solicitudes de Compra (`purchase_requests`)
- **Número de solicitud** (SR-2024-0001, SR-2024-0002, etc.)
- **Fecha de solicitud**
- **Estado**: Pendiente, Aprobada, Rechazada, En Proceso, Completada
- **Prioridad**: Baja, Media, Alta, Urgente
- **Justificación** de la solicitud
- **Observaciones** adicionales
- **Área de responsabilidad** asociada
- **Usuario solicitante**
- **Usuario aprobador** (opcional)
- **Fecha de aprobación** (opcional)
- **Monto total** estimado

### 3. Detalles de Solicitudes (`purchase_request_details`)
- **Producto solicitado**
- **Cantidad requerida**
- **Especificaciones técnicas**
- **Justificación individual**
- **Precio unitario estimado**
- **Total estimado**
- **Estado individual**: Pendiente, Aprobada, Rechazada, En Cotización, Comprada

## 🔧 Archivos Creados

### Migraciones:
- `2025_10_15_102908_create_responsibility_areas_table.php`
- `2025_10_15_102916_create_purchase_requests_table.php`
- `2025_10_15_102935_create_purchase_request_details_table.php`

### Modelos:
- `ResponsibilityArea.php` - Gestión de áreas
- `PurchaseRequest.php` - Solicitudes de compra
- `PurchaseRequestDetail.php` - Detalles de productos

### Controladores:
- `ResponsibilityAreaCrudController.php` - CRUD de áreas
- `PurchaseRequestCrudController.php` - CRUD de solicitudes

## 🚀 Funcionalidades

### Gestión de Áreas de Responsabilidad:
- ✅ **Crear áreas** con usuario responsable
- ✅ **Listar áreas** con información completa
- ✅ **Editar áreas** y cambiar responsables
- ✅ **Activar/desactivar** áreas
- ✅ **Vista previa** detallada

### Gestión de Solicitudes de Compra:
- ✅ **Crear solicitudes** con numeración automática
- ✅ **Asignar a áreas** específicas
- ✅ **Definir prioridades** y justificaciones
- ✅ **Seguimiento de estados** completo
- ✅ **Aprobación** por usuarios autorizados
- ✅ **Vista previa** con detalles de productos

### Características Avanzadas:
- ✅ **Numeración automática** (SR-2024-0001, SR-2024-0002...)
- ✅ **Cálculo automático** de totales
- ✅ **Estados múltiples** para seguimiento
- ✅ **Relaciones completas** entre entidades
- ✅ **Interfaz en español** completa

## 📊 Datos de Ejemplo Generados

### Áreas de Responsabilidad:
1. **Informática** - Responsable: Usuario aleatorio
2. **Insumos de Salud** - Responsable: Usuario aleatorio
3. **Mantenimiento** - Responsable: Usuario aleatorio
4. **Insumos Generales** - Responsable: Usuario aleatorio

### Solicitudes de Compra:
1. **SR-2024-0001** - Informática (Aprobada) - $15,000.00
2. **SR-2024-0002** - Insumos de Salud (Pendiente) - $8,500.00
3. **SR-2024-0003** - Mantenimiento (En Proceso) - $3,200.00

### Detalles de Productos:
- **Microscopios** para laboratorio (2 unidades)
- **Reactivos de glucosa** para análisis (10 kits)
- **Jeringas descartables** para prácticas (5 cajas)
- **Guantes de protección** para mantenimiento (8 cajas)

## 🎨 Interfaz de Usuario

### Listado de Áreas:
- Nombre del área
- Descripción
- Usuario responsable
- Estado activo/inactivo
- Fecha de creación

### Listado de Solicitudes:
- Número de solicitud
- Fecha
- Área responsable
- Usuario solicitante
- Estado y prioridad
- Monto total
- Contador de productos

### Vista Previa de Solicitudes:
- Información completa de la solicitud
- Tabla detallada de productos solicitados
- Especificaciones y justificaciones
- Precios estimados y totales
- Estados individuales de productos

## 🔄 Flujo de Trabajo

### 1. Configuración Inicial:
1. Crear áreas de responsabilidad
2. Asignar usuarios responsables
3. Activar áreas necesarias

### 2. Proceso de Solicitud:
1. **Solicitante** crea solicitud en su área
2. **Responsable de área** revisa y aprueba
3. **Administrador** procesa la solicitud
4. **Compras** ejecuta la adquisición
5. **Recepción** confirma entrega

### 3. Estados del Proceso:
- **Pendiente** → Solicitud creada
- **Aprobada** → Autorizada por responsable
- **En Proceso** → En gestión de compras
- **Completada** → Productos recibidos

## 🎯 Beneficios del Sistema

1. **Organización por Áreas**: Control específico por departamento
2. **Trazabilidad Completa**: Seguimiento de cada solicitud
3. **Aprobaciones Escaladas**: Flujo de autorización claro
4. **Priorización**: Gestión por urgencia e importancia
5. **Documentación**: Justificaciones y observaciones
6. **Integración**: Conectado con productos y usuarios existentes

## 🔧 Mantenimiento

### Para Agregar Nuevas Áreas:
1. Acceder a **Áreas de Responsabilidad**
2. Crear nueva área con responsable
3. Activar el área

### Para Modificar Estados:
1. Editar solicitud en **Solicitudes de Compra**
2. Cambiar estado según proceso
3. Asignar aprobador si corresponde

### Para Agregar Productos:
1. Usar el sistema existente de **Productos**
2. Los productos estarán disponibles en solicitudes
3. Se mantiene la integración completa

¡El sistema está completamente funcional y listo para usar! 🎉
