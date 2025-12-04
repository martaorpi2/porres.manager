# Resumen de Usuarios y Roles del Sistema

## 📋 Roles del Sistema (10 roles)

### 1. **Personal Solicitante** (`role_personal`)
**Descripción:** Personal que crea solicitudes generales.

**Capacidades:**
- ✅ Crear solicitudes generales
- ✅ Ver sus propias solicitudes generales
- ✅ Ver sus entregas (donde es el receptor)
- ✅ Editar sus propias solicitudes generales (solo si estado = "creada")
- ✅ Eliminar sus propias solicitudes generales (solo si estado = "creada" y no está convertida)

**Restricciones:**
- ❌ No puede ver solicitudes de compra
- ❌ No puede ver solicitudes generales de otros usuarios
- ❌ No puede editar solicitudes generales que no sean suyas

**Menú Disponible:**
- Inicio
- Solicitudes Generales
- Entregas

---

### 2. **Responsable de Área** (`role_responsable_area`)
**Descripción:** Gestión de depósito/stock y solicitudes de su área.

**Capacidades:**
- ✅ Crear solicitudes generales
- ✅ Ver solicitudes generales de su área o que él creó
- ✅ Crear solicitudes de compra
- ✅ Ver sus propias solicitudes de compra
- ✅ Editar sus propias solicitudes de compra (solo si estado = "Pendiente")
  - Solo puede modificar: **Prioridad**, **Justificación** y **Productos**
- ✅ Ver productos (solo de categorías relacionadas con su área)
- ✅ Ver proveedores (solo relacionados a su área/rubro)
- ✅ Ver stock de su área
- ✅ Ver entregas relacionadas con solicitudes de su área
- ✅ Ver recepciones de su área
- ✅ Ver devoluciones
- ✅ Sugerir proveedores para solicitudes de compra

**Restricciones:**
- ❌ No puede subir cotizaciones (solo el sector de compras)
- ❌ No puede ver cotizaciones en el show de solicitudes de compra
- ❌ No puede editar solicitudes de compra que no sean suyas
- ❌ No puede eliminar solicitudes de compra
- ❌ No puede ver proveedores de otras áreas/rubros

**Límites:**
- Sin límites de monto para crear solicitudes

**Menú Disponible:**
- Inicio
- Solicitudes (Generales, de Compra)
- Productos
- Proveedores
- Stock
- Entregas
- Recepciones
- Devoluciones

---

### 3. **Responsable de Compras** (`role_responsable_compras`)
**Descripción:** Gestión completa de compras y cotizaciones.

**Capacidades:**
- ✅ Ver todas las solicitudes de compra
- ✅ Crear solicitudes de compra
- ✅ Editar cualquier solicitud de compra
- ✅ Eliminar solicitudes de compra
- ✅ Crear cotizaciones
- ✅ Editar cotizaciones
- ✅ Eliminar cotizaciones
- ✅ Seleccionar cotizaciones para solicitudes de compra
- ✅ Aprobar solicitudes de compra (hasta su límite de monto)
- ✅ Generar órdenes de compra
- ✅ Ver todas las órdenes de compra
- ✅ Ver todas las órdenes de pago
- ✅ Acceso completo a proveedores, inventario, stock, etc.
- ✅ Marcar solicitudes como compra directa
- ✅ Justificar compras directas

**Restricciones:**
- ❌ No puede ver el menú de "Áreas de Responsabilidad" (solo administradores)
- ❌ No puede aprobar solicitudes que superen su límite de monto

**Límites de Aprobación:**
- **$360,000.00**
  - Puede aprobar solicitudes hasta $360,000.00
  - Solicitudes que superen este monto requieren aprobación superior

**Menú Disponible:**
- Inicio
- Proveedores (Listado, Calificaciones, Rubros, Sectores)
- Inventario (Productos, Categorías, Ubicaciones, Stock, Movimientos)
- Solicitudes (Generales, de Compra)
- Cotizaciones
- Órdenes de Compra
- Órdenes de Pago
- Recepciones
- Devoluciones
- Entregas

---

### 4. **Administrador del Instituto** (`role_admin_institucion`)
**Descripción:** Administración institucional con capacidad de aprobación.

**Capacidades:**
- ✅ Ver todas las solicitudes de compra
- ✅ Ver todas las solicitudes generales
- ✅ Editar **solo sus propias** solicitudes de compra (solo si estado = "Pendiente")
- ✅ Editar **solo sus propias** solicitudes generales (solo si estado = "creada")
- ✅ Aprobar solicitudes de compra (hasta su límite de monto)
- ✅ Aprobar compras directas (hasta su límite de monto)
- ✅ Modificar proveedores
- ✅ Calificar proveedores
- ✅ Editar/eliminar **solo sus propias** calificaciones de proveedores
- ✅ Ver productos, stock, movimientos de inventario
- ✅ Ver todas las órdenes de compra y pago
- ✅ Ver cotizaciones
- ✅ Ver solicitudes pendientes de aprobación en el dashboard

**Restricciones:**
- ❌ **NO puede crear** solicitudes de compra, órdenes de compra ni órdenes de pago
- ❌ **NO puede eliminar** solicitudes de compra
- ❌ **NO puede ver** categorías ni ubicaciones (menú bloqueado)
- ❌ **NO puede aprobar** solicitudes que superen su límite de monto

**Límites de Aprobación:**
- **$500,000.00**
  - Puede aprobar solicitudes hasta $500,000.00
  - Solicitudes que superen este monto requieren aprobación del apoderado o representante legal

**Menú Disponible:**
- Inicio
- Proveedores (Listado, Calificaciones, Rubros, Sectores)
- Inventario (Productos, Stock, Movimientos) - **SIN Categorías ni Ubicaciones**
- Solicitudes (Generales, de Compra)
- Cotizaciones
- Órdenes de Compra
- Órdenes de Pago
- Recepciones
- Devoluciones
- Entregas

---

### 5. **Apoderado Legal** (`role_apoderado`)
**Descripción:** Apoderado legal con capacidad de aprobación de alto nivel.

**Capacidades:**
- ✅ Ver todas las solicitudes de compra
- ✅ Ver todas las cotizaciones
- ✅ Ver todas las órdenes de compra
- ✅ Ver todas las órdenes de pago
- ✅ Aprobar solicitudes de compra (hasta su límite de monto)
- ✅ Aprobar compras directas (hasta su límite de monto)
- ✅ Ver el dashboard completo
- ✅ Ver solicitudes pendientes de aprobación en el dashboard

**Restricciones:**
- ❌ **NO puede crear** solicitudes de compra, órdenes de compra ni órdenes de pago
- ❌ **NO puede editar** nada
- ❌ **NO puede eliminar** nada
- ❌ **NO puede ver** inventario
- ❌ **NO puede aprobar** solicitudes que superen su límite de monto

**Límites de Aprobación:**
- **$600,000.00**
  - Puede aprobar solicitudes hasta $600,000.00
  - Solicitudes que superen este monto requieren aprobación del representante legal

**Menú Disponible:**
- Inicio
- Solicitudes de Compra
- Cotizaciones
- Órdenes de Compra
- Órdenes de Pago

---

### 6. **Representante Legal** (`role_representante_legal`)
**Descripción:** Representante legal con el nivel más alto de aprobación.

**Capacidades:**
- ✅ Ver todas las solicitudes de compra
- ✅ Ver todas las cotizaciones
- ✅ Ver todas las órdenes de compra
- ✅ Ver todas las órdenes de pago
- ✅ Ver inventario completo (solo lectura):
  - Productos
  - Categorías
  - Ubicaciones
  - Stock
  - Movimientos de inventario
- ✅ Aprobar solicitudes de compra (hasta su límite de monto)
- ✅ Aprobar compras directas (hasta su límite de monto)
- ✅ Ver el dashboard completo
- ✅ Ver solicitudes pendientes de aprobación en el dashboard

**Restricciones:**
- ❌ **NO puede crear** solicitudes de compra, órdenes de compra ni órdenes de pago
- ❌ **NO puede editar** nada
- ❌ **NO puede eliminar** nada
- ❌ **NO puede crear/editar/eliminar** en inventario (solo lectura)
- ❌ **NO puede aprobar** solicitudes que superen su límite de monto

**Límites de Aprobación:**
- **$11,700,000.00**
  - Puede aprobar solicitudes hasta $11,700,000.00
  - Es el nivel más alto de aprobación en el sistema

**Menú Disponible:**
- Inicio
- Solicitudes de Compra
- Cotizaciones
- Órdenes de Compra
- Órdenes de Pago
- Inventario (Productos, Categorías, Ubicaciones, Stock, Movimientos) - **Solo lectura**

---

### 7. **Consejo de Dirección** (`role_consejo`)
**Descripción:** Consejo directivo.

**Capacidades:**
- ✅ Ver solicitudes de compra
- ✅ Ver cotizaciones
- ✅ Ver órdenes de compra
- ✅ Ver órdenes de pago
- ✅ Aprobar compras (según permisos configurados)

**Restricciones:**
- ❌ No puede crear solicitudes de compra, órdenes de compra ni órdenes de pago
- ❌ No puede editar ni eliminar

---

### 8. **Tesorería** (`role_tesoreria`)
**Descripción:** Departamento de tesorería.

**Capacidades:**
- ✅ Ver información financiera
- ✅ Exportar reportes

**Restricciones:**
- ❌ Acceso limitado a funciones financieras

---

### 9. **Contabilidad** (`role_contabilidad`)
**Descripción:** Departamento de contabilidad.

**Capacidades:**
- ✅ Ver información financiera
- ✅ Exportar reportes

**Restricciones:**
- ❌ Acceso limitado a funciones financieras

---

### 10. **Administrador del Sistema** (`role_admin_sistema`)
**Descripción:** Control total del sistema.

**Capacidades:**
- ✅ **Todos los permisos del sistema**
- ✅ Gestionar usuarios y roles
- ✅ Configurar el sistema
- ✅ Acceso completo a todas las funcionalidades

**Restricciones:**
- Ninguna (acceso total)

---

## 📊 Resumen de Límites de Aprobación

| Rol | Código | Límite de Aprobación | Observaciones |
|-----|--------|---------------------|---------------|
| Responsable de Compras | `role_responsable_compras` | **$360,000.00** | Solicitudes superiores requieren aprobación superior |
| Administrador del Instituto | `role_admin_institucion` | **$500,000.00** | Solo puede aprobar sus propias solicitudes si están pendientes |
| Apoderado Legal | `role_apoderado` | **$600,000.00** | Solo puede aprobar, no puede crear/editar/eliminar |
| Representante Legal | `role_representante_legal` | **$11,700,000.00** | Nivel más alto de aprobación, solo lectura en inventario |

---

## 🔄 Flujo de Aprobación de Solicitudes de Compra

1. **Solicitud creada** → Estado: "Pendiente"

2. **Si monto ≤ $360,000.00**:
   - Puede ser aprobada por: **Responsable de Compras**

3. **Si monto > $360,000.00 y ≤ $500,000.00**:
   - Requiere aprobación de: **Administrador del Instituto**

4. **Si monto > $500,000.00 y ≤ $600,000.00**:
   - Requiere aprobación de: **Apoderado Legal**

5. **Si monto > $600,000.00 y ≤ $11,700,000.00**:
   - Requiere aprobación de: **Representante Legal**

6. **Si monto > $11,700,000.00**:
   - Requiere aprobación de nivel superior (no implementado en el sistema actual)

---

## 🔐 Permisos del Sistema

### Solicitudes
- `solicitud.crear` - Crear solicitudes
- `solicitud.ver` - Ver solicitudes
- `solicitud.aprobar` - Aprobar solicitudes
- `solicitud.entregar` - Entregar productos desde depósito

### Compras
- `compra.crear` - Crear órdenes de compra
- `compra.aprobar` - Aprobar compras según monto
- `compra.ejecutar` - Ejecutar la OC y enviar a proveedor

### Finanzas
- `finanzas.ver` - Ver información financiera
- `reportes.exportar` - Exportar reportes

### Administración
- `admin.usuarios` - Gestionar usuarios
- `admin.config` - Configurar sistema
- `admin.audit` - Auditar acciones del sistema

---

## 📝 Notas Importantes

1. **Compras Directas**: 
   - Solo el **Responsable de Compras** puede marcar una solicitud como compra directa
   - Las compras directas también respetan los límites de autorización
   - Los roles de aprobación (Administrador, Apoderado, Representante Legal) pueden aprobar compras directas según su límite

2. **Dashboard de Aprobaciones**: 
   - Los usuarios con capacidad de aprobar (Administrador del Instituto, Apoderado, Representante Legal) ven una sección destacada en el dashboard con las solicitudes pendientes de aprobación que pueden aprobar según su límite

3. **Filtrado por Área**: 
   - Los responsables de área solo ven proveedores y productos relacionados con su área/rubro

4. **Edición de Solicitudes**: 
   - Una vez aprobada, los productos no pueden ser modificados por ningún usuario
   - Solo se pueden editar solicitudes en estado "Pendiente" o "creada" según el caso

---

*Documento generado: {{ date('Y-m-d') }}*
*Versión del sistema: 1.0*

