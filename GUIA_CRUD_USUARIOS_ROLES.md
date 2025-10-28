# Guía de Gestión CRUD - Usuarios, Roles y Permisos

## ✅ Sistema Completo Implementado

Se han creado **3 CRUDs completos** para gestionar usuarios, roles y permisos en Backpack.

## 📋 CRUDs Disponibles

### 1. Gestión de Usuarios (`/admin/user`)
- Crear nuevos usuarios
- Editar usuarios existentes
- Asignar múltiples roles a cada usuario
- Ver lista de usuarios con sus roles
- Eliminar usuarios

**Campos:**
- Nombre
- Email
- Contraseña
- Roles (multiselect)

**URL:** `http://localhost/admin/user`

---

### 2. Gestión de Roles (`/admin/role`)
- Crear nuevos roles
- Editar roles existentes
- Asignar múltiples permisos a cada rol
- Ver lista de roles con sus permisos
- Eliminar roles

**Campos:**
- Nombre del Rol
- Guard (por defecto: web)
- Permisos (multiselect)

**URL:** `http://localhost/admin/role`

---

### 3. Gestión de Permisos (`/admin/permission`)
- Crear nuevos permisos
- Editar permisos existentes
- Asignar permisos a múltiples roles
- Ver lista de permisos con roles asignados
- Eliminar permisos

**Campos:**
- Nombre del Permiso
- Guard (por defecto: web)
- Roles (multiselect)

**URL:** `http://localhost/admin/permission`

---

## 🎯 Cómo Usar

### Paso 1: Acceder al Panel de Backpack
```
http://localhost/admin
```

### Paso 2: Acceder a la Gestión
En el menú lateral de Backpack, busque:
- **Usuarios** - para gestionar usuarios
- **Roles** - para gestionar roles
- **Permisos** - para gestionar permisos

## 📝 Ejemplos de Uso

### Crear un Usuario con Roles

1. Ir a `/admin/user`
2. Click en "Nuevo"
3. Completar campos:
   - Nombre: "Juan Pérez"
   - Email: "juan@example.com"
   - Contraseña: "password123"
4. Seleccionar roles (marcar checkboxes):
   - ☑ Personal solicitante
   - ☐ Responsable de depósito
   - ☐ etc.
5. Click en "Guardar"

### Crear un Nuevo Rol

1. Ir a `/admin/role`
2. Click en "Nuevo"
3. Completar campos:
   - Nombre del Rol: "role_supervisor"
   - Guard: "web"
4. Seleccionar permisos:
   - ☑ solicitud.ver
   - ☑ solicitud.aprobar
   - ☑ compra.ver
5. Click en "Guardar"

### Crear un Nuevo Permiso

1. Ir a `/admin/permission`
2. Click en "Nuevo"
3. Completar campos:
   - Nombre del Permiso: "reporte.mensual"
   - Guard: "web"
4. Seleccionar roles que tendrán este permiso:
   - ☑ Administrador del sistema
   - ☑ Tesorería
5. Click en "Guardar"

### Editar un Usuario para Asignar Roles

1. Ir a `/admin/user`
2. Buscar el usuario en la lista
3. Click en "Editar"
4. Marcar/desmarcar roles según necesidad
5. Click en "Guardar"

### Editar un Rol para Asignar Permisos

1. Ir a `/admin/role`
2. Buscar el rol en la lista
3. Click en "Editar"
4. Marcar/desmarcar permisos según necesidad
5. Click en "Guardar"

---

## 🔄 Sincronización Automática

Los CRUDs manejan automáticamente las relaciones:

- **Usuarios ↔ Roles**: Al guardar un usuario, sus roles se sincronizan
- **Roles ↔ Permisos**: Al guardar un rol, sus permisos se sincronizan
- **Permisos ↔ Roles**: Al guardar un permiso, los roles se sincronizan

---

## 🎨 Características de los CRUDs

### En Listas
- Muestra columnas relevantes
- Muestra relaciones (roles, permisos)
- Ordenamiento por columnas
- Búsqueda rápida
- Paginación

### En Formularios
- Validación de datos
- Campos con hints/ayudas
- Multiselect para relaciones
- Botones de acción (ver, editar, eliminar)

---

## 🔒 Seguridad

- Todos los CRUDs validan permisos con `backpack_auth()`
- Las contraseñas se hashean automáticamente
- Los nombres de roles y permisos deben ser únicos
- La cascada elimina relaciones al eliminar

---

## 💡 Consejos

1. **Nomenclatura de Roles**: Use el prefijo `role_` (ej: `role_admin`)
2. **Nomenclatura de Permisos**: Use formato `modulo.accion` (ej: `solicitud.crear`)
3. **No elimine roles críticos**: Los roles predefinidos son del sistema
4. **Backup antes de cambios**: Haga backup de la BD antes de cambios masivos
5. **Revisar relaciones**: Verifique que las relaciones están correctas tras cada cambio

---

## 🐛 Solución de Problemas

### "No puedo ver los CRUDs"
- Verifique que está logueado como admin
- Revisar que las rutas estén registradas en `routes/backpack/custom.php`

### "Los roles no se actualizan"
- Limpiar caché: `php artisan config:clear`
- Limpiar caché de permisos: `php artisan permission:cache-reset`

### "Error de validación"
- Verifique que los nombres sean únicos
- Revise que el guard sea "web"
- Vea los mensajes de error específicos

---

## 📚 Archivos Creados

```
app/Http/Controllers/Admin/
├── UserCrudController.php        ← CRUD de Usuarios
├── RoleCrudController.php         ← CRUD de Roles
└── PermissionCrudController.php   ← CRUD de Permisos

app/Http/Requests/
├── UserRequest.php               ← Validación de Usuarios
├── RoleRequest.php               ← Validación de Roles
└── PermissionRequest.php         ← Validación de Permisos

routes/backpack/custom.php        ← Rutas registradas
```

---

## ✅ Listo para Usar

El sistema está **completamente funcional** y listo para usar en producción.

Acceda a cada módulo desde el menú de Backpack o directamente por URL:
- `/admin/user`
- `/admin/role`
- `/admin/permission`

