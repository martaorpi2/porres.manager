# Casos de Prueba - Roles y Permisos del Sistema

Este documento describe las capacidades, restricciones y límites de cada rol en el sistema de gestión de solicitudes y compras.

## 🔐 Credenciales de Acceso

**IMPORTANTE:** La contraseña de todos los usuarios del sistema es: **`password`**

Esto aplica tanto para usuarios de prueba como para usuarios creados mediante seeders.

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
- ✅ **Marcar solicitudes como compra directa** (único proveedor por especialidad)
- ✅ **Justificar compras directas** con proveedor y razón
- ✅ **Generar órdenes de compra para compras directas autorizadas** (sin requerir cotizaciones)
- ✅ **Descargar planilla comparativa** cuando hay más de una cotización

### Restricciones
- ❌ No puede ver el menú de "Áreas de Responsabilidad" (solo administradores)
- ❌ No puede aprobar solicitudes que superen su límite de monto
- ❌ No puede aprobar compras directas (solo puede marcarlas y justificarlas)

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
- ✅ **Aprobar compras directas** (hasta su límite de monto)
- ✅ **Rechazar compras directas** con justificación
- ✅ Modificar proveedores
- ✅ Calificar proveedores
- ✅ Editar/eliminar **solo sus propias** calificaciones de proveedores
- ✅ Ver productos, stock, movimientos de inventario
- ✅ Ver todas las órdenes de compra y pago
- ✅ Ver cotizaciones
- ✅ Ver solicitudes pendientes de aprobación en el dashboard (incluye compras directas)

### Restricciones
- ❌ **NO puede crear** solicitudes de compra, órdenes de compra ni órdenes de pago
- ❌ **NO puede eliminar** solicitudes de compra
- ❌ **NO puede editar/eliminar** en ningún CRUD excepto:
  - Aprobar solicitudes de compra
  - Aprobar/rechazar compras directas
  - Editar sus propias solicitudes de compra/generales
  - Modificar y calificar proveedores
  - Editar/eliminar sus propias calificaciones
- ❌ **NO puede ver** categorías ni ubicaciones (menú bloqueado)
- ❌ **NO puede aprobar** solicitudes que superen su límite de monto (incluye compras directas)
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
- ✅ **Aprobar compras directas** (hasta su límite de monto)
- ✅ **Rechazar compras directas** con justificación
- ✅ Ver el dashboard completo (mismo que administrador)
- ✅ Ver solicitudes pendientes de aprobación en el dashboard (incluye compras directas)

### Restricciones
- ❌ **NO puede crear** solicitudes de compra, órdenes de compra ni órdenes de pago
- ❌ **NO puede editar** nada
- ❌ **NO puede eliminar** nada
- ❌ **NO puede ver** inventario
- ❌ **NO puede aprobar** solicitudes que superen su límite de monto (incluye compras directas)

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
- ✅ **Aprobar compras directas** (hasta su límite de monto)
- ✅ **Rechazar compras directas** con justificación
- ✅ Ver el dashboard completo (mismo que administrador)
- ✅ Ver solicitudes pendientes de aprobación en el dashboard (incluye compras directas)

### Restricciones
- ❌ **NO puede crear** solicitudes de compra, órdenes de compra ni órdenes de pago
- ❌ **NO puede editar** nada
- ❌ **NO puede eliminar** nada
- ❌ **NO puede crear/editar/eliminar** en inventario (solo lectura)
- ❌ **NO puede aprobar** solicitudes que superen su límite de monto (incluye compras directas)

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

1. **Solicitud creada** → Estado: "Pendiente", `purchase_type`: "normal"
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

## Tipos de Compra (purchase_type)

El sistema maneja tres tipos de compra que se establecen automáticamente:

### 1. Compra Normal (`normal`)
- **Valor por defecto** al crear una solicitud
- Requiere cotizaciones según el monto:
  - **≤ $60,000**: Requiere 1 cotización
  - **> $60,000**: Requiere exactamente 3 cotizaciones
- Se convierte automáticamente a "Compra Rápida" si al generar la orden el monto es ≤ $60,000

### 2. Compra Rápida (`rapida`)
- Se establece automáticamente cuando se genera una orden de compra con monto **≤ $60,000**
- Requiere **1 sola cotización** y un proveedor seleccionado
- Mensaje: "Esta solicitud tiene un monto de $X, por lo que no se requiere cotización. Puede generar la orden de compra directamente seleccionando un proveedor y subiendo su cotización."

### 3. Compra Directa (`directa`)
- Se establece cuando el **Responsable de Compras** marca una solicitud como compra directa
- **Flujo completo:**
  1. Responsable de Compras marca la solicitud como compra directa
  2. Debe seleccionar un proveedor único y justificar la elección
  3. Se solicita automáticamente autorización a nivel superior (Administrador, Apoderado o Representante Legal)
  4. El nivel superior puede aprobar o rechazar la compra directa
  5. Si se aprueba, el Responsable de Compras puede generar la orden **sin requerir cotizaciones**
  6. El campo `purchase_type` se actualiza a "directa"
- **Respeto de límites:** Las compras directas también respetan los límites de autorización por monto
- **Dashboard:** Las compras directas pendientes aparecen en el dashboard de los usuarios con capacidad de aprobación

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

### Caso 9: Compra Rápida (monto ≤ $60,000)
1. Usuario crea una solicitud de compra con monto $50,000.00
2. Responsable de Compras sube 1 cotización
3. Responsable de Compras selecciona la cotización
4. Responsable de Compras genera la orden de compra
5. ✅ **Resultado esperado**: 
   - El campo `purchase_type` se actualiza automáticamente a "rapida"
   - La orden se genera exitosamente
   - Mensaje: "Esta solicitud tiene un monto de $50,000.00, por lo que no se requiere cotización. Puede generar la orden de compra directamente seleccionando un proveedor y subiendo su cotización."

### Caso 10: Compra Directa - Flujo completo
1. Responsable de Compras crea una solicitud de compra con monto $400,000.00
2. Responsable de Compras marca la solicitud como compra directa
3. ✅ **Resultado esperado**: Aparece modal para seleccionar proveedor y justificar
4. Responsable de Compras selecciona un proveedor y completa la justificación
5. ✅ **Resultado esperado**: 
   - La solicitud se marca como compra directa
   - Se solicita automáticamente autorización
   - El campo `purchase_type` permanece como "normal" hasta que se apruebe
6. Administrador del Instituto ve la solicitud en su dashboard
7. Administrador del Instituto aprueba la compra directa
8. ✅ **Resultado esperado**: 
   - El campo `purchase_type` se actualiza a "directa"
   - El Responsable de Compras puede generar la orden sin cotizaciones
   - El botón "Generar OC" se habilita

### Caso 11: Compra Directa - Rechazo
1. Responsable de Compras marca una solicitud como compra directa
2. Administrador del Instituto rechaza la compra directa con justificación
3. ✅ **Resultado esperado**: 
   - La solicitud vuelve a estado normal
   - Se registra la razón del rechazo
   - El Responsable de Compras debe seguir el proceso normal con cotizaciones

### Caso 12: Compra Directa - Límite de autorización
1. Responsable de Compras marca una solicitud de $900,000.00 como compra directa
2. Administrador del Instituto intenta aprobar
3. ❌ **Resultado esperado**: No puede aprobar (supera su límite de $500,000.00)
4. Apoderado intenta aprobar
5. ❌ **Resultado esperado**: No puede aprobar (supera su límite de $600,000.00)
6. Representante Legal aprueba
7. ✅ **Resultado esperado**: Aprobación exitosa (dentro de su límite de $11,700,000.00)

### Caso 13: Planilla Comparativa
1. Responsable de Compras sube 3 cotizaciones a una solicitud
2. Responsable de Compras ve el show de la solicitud
3. ✅ **Resultado esperado**: Aparece botón "Descargar Planilla Comparativa"
4. Si solo hay 1 cotización
5. ❌ **Resultado esperado**: No aparece el botón de planilla comparativa

### Caso 14: Actualización de monto total al seleccionar cotización
1. Responsable de Compras crea una solicitud con monto estimado $900,000.00
2. Responsable de Compras sube cotizaciones
3. Responsable de Compras selecciona una cotización con monto total $370,000.00
4. ✅ **Resultado esperado**: 
   - El `total_amount` de la solicitud se actualiza a $370,000.00
   - Se recalcula `requires_admin_approval` según el nuevo monto
   - Si el nuevo monto es ≤ $360,000, el Responsable de Compras puede aprobar directamente

---

## Notas Importantes

1. **Solicitudes de Compra Aprobadas**: Una vez aprobada, los productos no pueden ser modificados por ningún usuario.

2. **Dashboard de Aprobaciones**: Los usuarios con capacidad de aprobar (Administrador del Instituto, Apoderado, Representante Legal) ven una sección destacada en el dashboard con las solicitudes pendientes de aprobación que pueden aprobar según su límite. Esto incluye compras directas pendientes de autorización.

3. **Filtrado de Proveedores**: Los responsables de área solo ven proveedores relacionados con los rubros de su área.

4. **Filtrado de Productos**: Los responsables de área solo ven productos de categorías relacionadas con su área.

5. **Stock por Ubicación**: El stock se calcula y muestra por ubicación, no como total general.

6. **Sugerencias de Proveedores**: Los responsables de área pueden sugerir proveedores, pero no pueden subir cotizaciones (solo el sector de compras).

7. **Compras Directas**: 
   - Solo el Responsable de Compras puede marcar una solicitud como compra directa
   - Las compras directas también respetan los límites de autorización por monto
   - Una vez aprobada una compra directa, se puede generar la orden sin requerir cotizaciones
   - El campo `purchase_type` se actualiza a "directa" cuando se aprueba

8. **Compras Rápidas**: 
   - Se establecen automáticamente cuando se genera una orden con monto ≤ $60,000
   - Requieren solo 1 cotización
   - El campo `purchase_type` se actualiza a "rapida" al generar la orden

9. **Actualización de Monto Total**: 
   - Cuando se selecciona una cotización, el `total_amount` de la solicitud se actualiza con el monto total de la cotización seleccionada
   - Esto puede cambiar el nivel de aprobación requerido

10. **Planilla Comparativa**: 
    - Solo aparece cuando hay más de una cotización cargada
    - Permite comparar todas las cotizaciones en formato Excel

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
| Marcar Compra Directa | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| Aprobar Compra Directa | ❌ | ❌ | ❌ | ✅ (≤$500k) | ✅ (≤$600k) | ✅ (≤$11.7M) |
| Rechazar Compra Directa | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ |
| Crear Cotización | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| Crear Orden Compra | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| Crear Orden Pago | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| Descargar Planilla Comparativa | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| Ver Inventario | ❌ | ✅ (limitado) | ✅ | ✅ (sin categorías/ubicaciones) | ❌ | ✅ (solo lectura) |
| Editar Inventario | ❌ | ✅ (limitado) | ✅ | ❌ | ❌ | ❌ |
| Eliminar Productos/Stock | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Modificar Proveedores | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ |
| Calificar Proveedores | ❌ | ❌ | ✅ | ✅ (solo propias) | ❌ | ❌ |

---

---

## 🔐 Información de Acceso

**Contraseña de todos los usuarios:** `password`

Esta contraseña aplica para:
- Usuarios creados mediante seeders
- Usuarios de prueba
- Todos los roles del sistema

---

*Documento actualizado: 2025-01-XX*
*Versión del sistema: 2.0*
*Última actualización: Incluye funcionalidades de Compras Directas, Compras Rápidas y Planilla Comparativa*

