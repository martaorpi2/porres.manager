# Guía Rápida - Sistema de Roles y Permisos

## ✅ Implementación Completada

Se ha configurado un sistema completo de roles y permisos usando **Spatie Laravel Permission** y **Backpack**.

## 🎯 Roles Implementados (10 roles)

1. **role_personal** - Personal solicitante
2. **role_responsable_area** - Responsable de depósito  
3. **role_responsable_compras** - Responsable de compras
4. **role_admin_institucion** - Administrador
5. **role_apoderado** - Apoderado legal
6. **role_representante_legal** - Representante legal
7. **role_consejo** - Consejo de dirección
8. **role_tesoreria** - Tesorería
9. **role_contabilidad** - Contabilidad
10. **role_admin_sistema** - Administrador del sistema (todos los permisos)

## 🔑 Permisos Implementados (12 permisos)

### Solicitudes
- `solicitud.crear`
- `solicitud.ver`
- `solicitud.aprobar`
- `solicitud.entregar`

### Compras
- `compra.crear`
- `compra.aprobar`
- `compra.ejecutar`

### Finanzas
- `finanzas.ver`
- `reportes.exportar`

### Administración
- `admin.usuarios`
- `admin.config`
- `admin.audit`

## 🚀 Cómo Usar

### 1. Crear Roles y Permisos

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

### 2. Crear Usuarios de Ejemplo (solo desarrollo)

```bash
php artisan db:seed --class=ExampleUsersSeeder
```

**Credenciales de ejemplo:**
- Todos los usuarios usan contraseña: `password`

### 3. Acceder a Gestión de Usuarios

1. Iniciar sesión en `/admin`
2. Click en "Usuarios" en el menú
3. Crear/editar usuarios y asignar roles

## 📝 Ejemplos de Uso en el Código

### En controladores
```php
// Verificar permiso
if (auth()->user()->hasPermissionTo('solicitud.aprobar')) {
    // Aprobar solicitud
}

// Verificar rol
if (auth()->user()->hasRole('role_admin_sistema')) {
    // Administrador del sistema
}
```

### En rutas
```php
Route::get('/admin/compras', function () {
    //
})->middleware('permission:compra.ver');
```

### En vistas Blade
```blade
@can('solicitud.crear')
    <button>Crear Solicitud</button>
@endcan

@role('role_admin_sistema')
    <div>Panel de Administración</div>
@endrole
```

## 📚 Documentación Completa

Ver archivo `ROLES_PERMISOS.md` para documentación detallada.

## ⚠️ Importante

- Los usuarios de ejemplo son SOLO para desarrollo
- Cambiar contraseñas antes de producción
- El administrador del sistema tiene TODOS los permisos
- Los permisos están asignados automáticamente a cada rol según su descripción

## 🔧 Comandos Útiles

```bash
# Ver configuración de permisos
php artisan config:show permission

# Limpiar caché de permisos
php artisan permission:cache-reset

# Ver usuarios con sus roles
php artisan tinker
>>> App\Models\User::with('roles')->get();
```

