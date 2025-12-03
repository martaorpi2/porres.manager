# Casos de Prueba - Roles y Permisos del Sistema

Este documento describe las capacidades, restricciones y límites de cada rol en el sistema de gestión de solicitudes y compras.

---

## 1. Personal (role_personal)

### Capacidades
- ✅ Crear solicitudes generales
- ✅ Ver sus propias solicitudes generales
- ✅ Ver sus entregas (donde es el receptor)
- ✅ Editar sus propias solicitudes generales (solo si estado = "creada")
- ✅ Eliminar sus propias solicitudes generales (solo si estado = "creada" y no está convertida)

### Restricciones
- ❌ No puede ver solicitudes de compra
- ❌ No puede ver solicitudes generales de otros usuarios
- ❌ No puede editar solicitudes generales que no sean suyas
- ❌ No puede editar solicitudes generales si el estado no es "creada"
- ❌ No puede eliminar solicitudes generales convertidas a compra

### Menú Disponible
- Inicio
- Solicitudes Generales
- Entregas

---

## 2. Responsable de Área (role_responsable_area)

### Capacidades
- ✅ Crear solicitudes generales
- ✅ Ver solicitudes generales de su área o que él creó
- ✅ Crear solicitudes de compra
- ✅ Ver sus propias solicitudes de compra
- ✅ Editar sus propias solicitudes de compra (solo si estado = "Pendiente")
  - Solo puede modificar: **Prioridad**, **Justificación** y **Productos**
  - No puede modificar: Número de solicitud, Fecha, Área, Usuario solicitante, Aprobado por, Fecha de aprobación
- ✅ Ver productos (solo de categorías relacionadas con su área)
- ✅ Ver proveedores (solo relacionados a su área/rubro)
- ✅ Ver stock de su área
- ✅ Ver entregas relacionadas con solicitudes de su área
- ✅ Ver recepciones de su área
- ✅ Ver devoluciones
- ✅ Sugerir proveedores para solicitudes de compra

### Restricciones
- ❌ No puede subir cotizaciones (solo el sector de compras)
- ❌ No puede ver cotizaciones en el show de solicitudes de compra
- ❌ No puede editar solicitudes de compra que no sean suyas
- ❌ No puede editar solicitudes de compra si el estado no es "Pendiente"
- ❌ No puede eliminar solicitudes de compra
- ❌ No puede ver proveedores de otras áreas/rubros
- ❌ No puede ver productos de otras categorías

### Límites
- Sin límites de monto para crear solicitudes

### Menú Disponible
- Inicio
- Solicitudes (Generales, de Compra)
- Productos
- Proveedores
- Stock
- Entregas
- Recepciones
- Devoluciones

---

## 3. Responsable de Compras (role_responsable_compras)

### Capacidades
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

### Restricciones
- ❌ No puede ver el menú de "Áreas de Responsabilidad" (solo administradores)
- ❌ No puede aprobar solicitudes que superen su límite de monto

### Límites
- **Límite de aprobación: $360,000.00**
  - Puede aprobar solicitudes hasta $360,000.00
  - Solicitudes que superen este monto requieren aprobación del administrador del instituto, apoderado o representante legal

### Menú Disponible
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

## 4. Administrador del Instituto (role_admin_institucion)

### Capacidades
- ✅ Ver todas las solicitudes de compra
- ✅ Ver todas las solicitudes generales
- ✅ Editar **solo sus propias** solicitudes de compra (solo si estado = "Pendiente")
- ✅ Editar **solo sus propias** solicitudes generales (solo si estado = "creada")
- ✅ Aprobar solicitudes de compra (hasta su límite de monto)
- ✅ Modificar proveedores
- ✅ Calificar proveedores
- ✅ Editar/eliminar **solo sus propias** calificaciones de proveedores
- ✅ Ver productos, stock, movimientos de inventario
- ✅ Ver todas las órdenes de compra y pago
- ✅ Ver cotizaciones

### Restricciones
- ❌ **NO puede crear** solicitudes de compra, órdenes de compra ni órdenes de pago
- ❌ **NO puede eliminar** solicitudes de compra
- ❌ **NO puede editar/eliminar** en ningún CRUD excepto:
  - Aprobar solicitudes de compra
  - Editar sus propias solicitudes de compra/generales
  - Modificar y calificar proveedores
  - Editar/eliminar sus propias calificaciones
- ❌ **NO puede ver** categorías ni ubicaciones (menú bloqueado)
- ❌ **NO puede aprobar** solicitudes que superen su límite de monto
- ❌ No puede editar solicitudes de compra que no sean suyas
- ❌ No puede editar solicitudes generales que no sean suyas

### Límites
- **Límite de aprobación: $500,000.00**
  - Puede aprobar solicitudes hasta $500,000.00
  - Solicitudes que superen este monto requieren aprobación del apoderado o representante legal

### Menú Disponible
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

## 5. Apoderado (role_apoderado)

### Capacidades
- ✅ Ver todas las solicitudes de compra
- ✅ Ver todas las cotizaciones
- ✅ Ver todas las órdenes de compra
- ✅ Ver todas las órdenes de pago
- ✅ Aprobar solicitudes de compra (hasta su límite de monto)
- ✅ Ver el dashboard completo (mismo que administrador)
- ✅ Ver solicitudes pendientes de aprobación en el dashboard

### Restricciones
- ❌ **NO puede crear** solicitudes de compra, órdenes de compra ni órdenes de pago
- ❌ **NO puede editar** nada
- ❌ **NO puede eliminar** nada
- ❌ **NO puede ver** inventario
- ❌ **NO puede aprobar** solicitudes que superen su límite de monto

### Límites
- **Límite de aprobación: $600,000.00**
  - Puede aprobar solicitudes hasta $600,000.00
  - Solicitudes que superen este monto requieren aprobación del representante legal

### Menú Disponible
- Inicio
- Solicitudes de Compra
- Cotizaciones
- Órdenes de Compra
- Órdenes de Pago

---

## 6. Representante Legal (role_representante_legal)

### Capacidades
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
- ✅ Ver el dashboard completo (mismo que administrador)
- ✅ Ver solicitudes pendientes de aprobación en el dashboard

### Restricciones
- ❌ **NO puede crear** solicitudes de compra, órdenes de compra ni órdenes de pago
- ❌ **NO puede editar** nada
- ❌ **NO puede eliminar** nada
- ❌ **NO puede crear/editar/eliminar** en inventario (solo lectura)
- ❌ **NO puede aprobar** solicitudes que superen su límite de monto

### Límites
- **Límite de aprobación: $11,700,000.00**
  - Puede aprobar solicitudes hasta $11,700,000.00
  - Es el nivel más alto de aprobación en el sistema

### Menú Disponible
- Inicio
- Solicitudes de Compra
- Cotizaciones
- Órdenes de Compra
- Órdenes de Pago
- Inventario (Productos, Categorías, Ubicaciones, Stock, Movimientos) - **Solo lectura**

---

## Resumen de Límites de Aprobación

| Rol | Límite de Aprobación | Observaciones |
|-----|---------------------|---------------|
| Responsable de Compras | $360,000.00 | Solicitudes superiores requieren aprobación superior |
| Administrador del Instituto | $500,000.00 | Solo puede aprobar sus propias solicitudes si están pendientes |
| Apoderado | $600,000.00 | Solo puede aprobar, no puede crear/editar/eliminar |
| Representante Legal | $11,700,000.00 | Nivel más alto de aprobación, solo lectura en inventario |

---

## Flujo de Aprobación de Solicitudes de Compra

1. **Solicitud creada** → Estado: "Pendiente"
2. **Si monto ≤ $360,000.00**:
   - Puede ser aprobada por: **Responsable de Compras**
3. **Si monto > $360,000.00 y ≤ $500,000.00**:
   - Requiere aprobación de: **Administrador del Instituto**
4. **Si monto > $500,000.00 y ≤ $600,000.00**:
   - Requiere aprobación de: **Apoderado**
5. **Si monto > $600,000.00 y ≤ $11,700,000.00**:
   - Requiere aprobación de: **Representante Legal**
6. **Si monto > $11,700,000.00**:
   - Requiere aprobación de nivel superior (no implementado en el sistema actual)

---

## Restricciones Generales por Rol

### Restricciones de Eliminación
- **Solo Administrador del Sistema** puede eliminar:
  - Productos
  - Stock
- **Ningún usuario** puede eliminar:
  - Solicitudes de compra (excepto administrador del sistema y responsable de compras)
  - Órdenes de compra
  - Órdenes de pago
  - Movimientos de inventario

### Restricciones de Edición
- **Administrador del Instituto, Apoderado y Representante Legal**:
  - No pueden editar solicitudes de compra (excepto admin instituto sus propias solicitudes pendientes)
  - No pueden editar órdenes de compra
  - No pueden editar órdenes de pago
  - No pueden editar cotizaciones
  - No pueden editar inventario (excepto representante legal puede ver pero no editar)

### Restricciones de Creación
- **Administrador del Instituto, Apoderado y Representante Legal**:
  - No pueden crear solicitudes de compra
  - No pueden crear órdenes de compra
  - No pueden crear órdenes de pago

---

## Casos de Prueba Específicos

### Caso 1: Personal crea solicitud general
1. Usuario con rol `role_personal` inicia sesión
2. Navega a "Solicitudes Generales"
3. Crea una nueva solicitud general
4. ✅ **Resultado esperado**: Solicitud creada exitosamente
5. Intenta editar la solicitud
6. ✅ **Resultado esperado**: Puede editar solo si estado = "creada"
7. Intenta editar solicitud de otro usuario
8. ❌ **Resultado esperado**: Error 403 - No tiene permiso

### Caso 2: Responsable de Área edita solicitud de compra
1. Usuario con rol `role_responsable_area` inicia sesión
2. Crea una solicitud de compra
3. Intenta editar la solicitud
4. ✅ **Resultado esperado**: Solo puede modificar Prioridad, Justificación y Productos
5. Campos como Número, Fecha, Área aparecen como readonly
6. Intenta editar solicitud de otro usuario
7. ❌ **Resultado esperado**: Error 403 - No tiene permiso

### Caso 3: Responsable de Compras aprueba solicitud
1. Usuario con rol `role_responsable_compras` inicia sesión
2. Ve una solicitud de compra con monto $300,000.00
3. ✅ **Resultado esperado**: Puede aprobar (está dentro de su límite)
4. Ve una solicitud de compra con monto $400,000.00
5. ❌ **Resultado esperado**: No puede aprobar (supera su límite de $360,000.00)
6. Ve mensaje: "Requiere aprobación del administrador del instituto"

### Caso 4: Administrador del Instituto aprueba solicitud
1. Usuario con rol `role_admin_institucion` inicia sesión
2. Ve solicitud pendiente de aprobación con monto $450,000.00
3. ✅ **Resultado esperado**: Puede aprobar (está dentro de su límite de $500,000.00)
4. Ve solicitud pendiente con monto $550,000.00
5. ❌ **Resultado esperado**: No puede aprobar (supera su límite)
6. Ve mensaje: "Límite excedido: Esta solicitud supera tu límite de autorización"

### Caso 5: Apoderado intenta crear solicitud
1. Usuario con rol `role_apoderado` inicia sesión
2. Navega a "Solicitudes de Compra"
3. ❌ **Resultado esperado**: No ve botón "Añadir" o "Crear"
4. Intenta acceder directamente a la ruta de creación
5. ❌ **Resultado esperado**: Error 403 o redirección

### Caso 6: Representante Legal ve inventario
1. Usuario con rol `role_representante_legal` inicia sesión
2. Navega a "Inventario" → "Productos"
3. ✅ **Resultado esperado**: Ve lista de productos
4. ❌ **Resultado esperado**: No ve botones de crear, editar ni eliminar
5. Intenta acceder a crear producto
6. ❌ **Resultado esperado**: Error 403 - No tiene permiso

### Caso 7: Administrador del Instituto intenta ver categorías
1. Usuario con rol `role_admin_institucion` inicia sesión
2. Intenta acceder a "Categorías"
3. ❌ **Resultado esperado**: Error 403 - No tiene permiso para acceder a categorías
4. Verifica menú de inventario
5. ✅ **Resultado esperado**: No ve opción "Categorías" ni "Ubicaciones"

### Caso 8: Solicitud aprobada - productos no editables
1. Usuario crea solicitud de compra
2. Usuario con permiso aprueba la solicitud
3. Usuario intenta editar la solicitud aprobada
4. ✅ **Resultado esperado**: Los productos aparecen como solo lectura
5. Ve mensaje: "Los productos no pueden ser modificados porque la solicitud está aprobada"

---

## Notas Importantes

1. **Solicitudes de Compra Aprobadas**: Una vez aprobada, los productos no pueden ser modificados por ningún usuario.

2. **Dashboard de Aprobaciones**: Los usuarios con capacidad de aprobar (Administrador del Instituto, Apoderado, Representante Legal) ven una sección destacada en el dashboard con las solicitudes pendientes de aprobación que pueden aprobar según su límite.

3. **Filtrado de Proveedores**: Los responsables de área solo ven proveedores relacionados con los rubros de su área.

4. **Filtrado de Productos**: Los responsables de área solo ven productos de categorías relacionadas con su área.

5. **Stock por Ubicación**: El stock se calcula y muestra por ubicación, no como total general.

6. **Sugerencias de Proveedores**: Los responsables de área pueden sugerir proveedores, pero no pueden subir cotizaciones (solo el sector de compras).

---

## Matriz de Permisos Resumida

| Acción | Personal | Responsable Área | Responsable Compras | Admin Instituto | Apoderado | Representante Legal |
|--------|----------|------------------|---------------------|-----------------|-----------|---------------------|
| Crear Solicitud General | ✅ (propias) | ✅ | ✅ | ✅ (propias) | ❌ | ❌ |
| Editar Solicitud General | ✅ (propias, estado="creada") | ✅ (su área) | ✅ | ✅ (propias, estado="creada") | ❌ | ❌ |
| Eliminar Solicitud General | ✅ (propias, estado="creada") | ✅ | ✅ | ❌ | ❌ | ❌ |
| Crear Solicitud Compra | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Editar Solicitud Compra | ❌ | ✅ (propias, estado="Pendiente", campos limitados) | ✅ | ✅ (propias, estado="Pendiente") | ❌ | ❌ |
| Eliminar Solicitud Compra | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| Aprobar Solicitud Compra | ❌ | ❌ | ✅ (≤$360k) | ✅ (≤$500k) | ✅ (≤$600k) | ✅ (≤$11.7M) |
| Crear Cotización | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| Crear Orden Compra | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| Crear Orden Pago | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| Ver Inventario | ❌ | ✅ (limitado) | ✅ | ✅ (sin categorías/ubicaciones) | ❌ | ✅ (solo lectura) |
| Editar Inventario | ❌ | ✅ (limitado) | ✅ | ❌ | ❌ | ❌ |
| Eliminar Productos/Stock | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Modificar Proveedores | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ |
| Calificar Proveedores | ❌ | ❌ | ✅ | ✅ (solo propias) | ❌ | ❌ |

---

*Documento generado el: {{ date('Y-m-d') }}*
*Versión del sistema: 1.0*

