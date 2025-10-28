# Sistema de Roles y Permisos con Spatie

Este documento describe el sistema de roles y permisos implementado en la aplicación usando Spatie Laravel Permission y Backpack.

## Instalación y Setup

### 1. Ejecutar Migraciones

Si las tablas de permisos no existen, ejecute:

```bash
php artisan migrate
```

### 2. Crear Roles y Permisos

Ejecute el seeder para crear todos los roles y permisos:

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

O ejecute el seeder completo:

```bash
php artisan db:seed
```

### 3. Crear Usuarios de Ejemplo (Opcional)

Para crear usuarios de ejemplo con diferentes roles:

```bash
php artisan db:seed --class=ExampleUsersSeeder
```

**IMPORTANTE**: Este seeder es solo para desarrollo. NO ejecutarlo en producción.

## Roles Definidos

| Rol | Código | Descripción | Permisos |
|-----|--------|-------------|----------|
| Personal solicitante | `role_personal` | Personal que crea solicitudes | solicitud.crear, solicitud.ver |
| Responsable de depósito | `role_responsable_area` | Gestión de depósito/stock | solicitud.ver, solicitud.aprobar, solicitud.entregar |
| Responsable de compras | `role_responsable_compras` | Gestión de compras | compra.crear, compra.aprobar, compra.ejecutar, solicitud.ver |
| Administrador | `role_admin_institucion` | Administración institucional | compra.aprobar, compra.ejecutar, solicitud.ver, solicitud.aprobar |
| Apoderado legal | `role_apoderado` | Apoderado legal | compra.aprobar, compra.ejecutar, solicitud.ver, solicitud.aprobar |
| Representante legal | `role_representante_legal` | Representante legal | compra.aprobar, compra.ejecutar, solicitud.ver, solicitud.aprobar |
| Consejo de dirección | `role_consejo` | Consejo directivo | compra.aprobar, compra.ejecutar, solicitud.ver, solicitud.aprobar |
| Tesorería | `role_tesoreria` | Departamento de tesorería | finanzas.ver, reportes.exportar |
| Contabilidad | `role_contabilidad` | Departamento de contabilidad | finanzas.ver, reportes.exportar |
| Administrador del sistema | `role_admin_sistema` | Control total del sistema | Todos los permisos |

## Permisos Definidos

### Solicitudes
- `solicitud.crear` - Crear solicitudes
- `solicitud.ver` - Ver solicitudes
- `solicitud.aprobar` - Aprobar solicitudes (solo responsables de área y niveles superiores)
- `solicitud.entregar` - Entregar productos desde depósito

### Compras
- `compra.crear` - Crear órdenes de compra (solo Responsable de Compras)
- `compra.aprobar` - Aprobar compras según monto (flujo: Responsable Compras → Admin → Apoderado → Representante → Consejo)
- `compra.ejecutar` - Ejecutar la OC y enviar a proveedor

### Finanzas
- `finanzas.ver` - Ver información financiera (Tesorería y Contabilidad)
- `reportes.exportar` - Exportar reportes (opcional)

### Administración
- `admin.usuarios` - Gestionar usuarios
- `admin.config` - Configurar sistema
- `admin.audit` - Auditar acciones del sistema

## Uso en el Código

### Asignar Roles a Usuarios

```php
use App\Models\User;

$user = User::find(1);
$user->assignRole('role_personal');

// Múltiples roles
$user->assignRole(['role_responsable_compras', 'role_admin_institucion']);

// Sincronizar roles (reemplaza todos los roles existentes)
$user->syncRoles(['role_admin_sistema']);
```

### Verificar Roles

```php
// Verificar si tiene un rol específico
if ($user->hasRole('role_admin_sistema')) {
    // El usuario es administrador del sistema
}

// Verificar si tiene alguno de varios roles
if ($user->hasAnyRole(['role_admin_sistema', 'role_responsable_compras'])) {
    // El usuario es admin o responsable de compras
}
```

### Asignar Permisos a Usuarios

```php
// Asignar un permiso específico
$user->givePermissionTo('compra.aprobar');

// Múltiples permisos
$user->givePermissionTo(['compra.aprobar', 'compra.ejecutar']);

// Verificar permiso
if ($user->hasPermissionTo('compra.aprobar')) {
    // El usuario puede aprobar compras
}

// Verificar múltiples permisos
if ($user->hasAllPermissions(['compra.aprobar', 'compra.ejecutar'])) {
    // El usuario tiene todos los permisos
}
```

### Proteger Rutas y Controladores

```php
// En rutas (web.php o custom.php)
Route::get('/admin/compras', function () {
    // Solo usuarios con permiso pueden acceder
})->middleware('permission:compra.ver');

// Múltiples permisos (requiere cualquiera de ellos)
Route::get('/admin/finanzas', function () {
    // Solo usuarios con permiso pueden acceder
})->middleware('permission:finanzas.ver|reportes.exportar');

// Requerir rol
Route::get('/admin/system', function () {
    // Solo administradores del sistema
})->middleware('role:role_admin_sistema');
```

### En Vistas Blade

```blade
{{-- Mostrar contenido solo si tiene permiso --}}
@can('solicitud.crear')
    <button>Crear Solicitud</button>
@endcan

{{-- Mostrar contenido según rol --}}
@role('role_admin_sistema')
    <div>Panel de Administración</div>
@endrole

{{-- Verificar si tiene alguno de varios roles --}}
@hasanyrole('role_responsable_compras|role_admin_sistema')
    <div>Panel de Compras</div>
@endhasanyrole
```

### En Controladores

```php
public function index()
{
    // Verificar permisos en el controlador
    if (!auth()->user()->hasPermissionTo('solicitud.ver')) {
        abort(403, 'No tienes permiso para ver solicitudes');
    }
    
    // O más simple con middleware
    $this->authorize('viewAny', Solicitud::class);
}

public function aprobar(Request $request, $id)
{
    // Solo usuarios con permiso de aprobación
    if (!auth()->user()->hasPermissionTo('solicitud.aprobar')) {
        abort(403, 'No tienes permiso para aprobar solicitudes');
    }
    
    // Lógica de aprobación
}
```

## Gestión de Usuarios en Backpack

### Acceder al Panel de Usuarios

1. Inicie sesión en Backpack: `/admin`
2. En el menú lateral, busque "Usuarios"
3. Aquí puede:
   - Crear nuevos usuarios
   - Editar usuarios existentes
   - Asignar múltiples roles a cada usuario
   - Eliminar usuarios

### Crear un Nuevo Usuario con Roles

1. Haga clic en "Nuevo" en el panel de usuarios
2. Complete:
   - Nombre
   - Email
   - Contraseña
3. Marque los roles que desea asignar al usuario
4. Haga clic en "Guardar"

## Flujo de Aprobación de Compras

El sistema implementa un flujo escalonado de aprobación según el monto:

1. **Hasta $XXX** - Responsable de Compras
2. **Hasta $XXX** - Administrador Institucional
3. **Hasta $XXX** - Apoderado Legal
4. **Hasta $XXX** - Representante Legal
5. **Más de $XXX** - Consejo de Dirección

Para implementar esto en su código:

```php
public function aprobarCompra($purchaseOrder)
{
    $monto = $purchaseOrder->total;
    
    // Montos de ejemplo - ajustar según necesidades
    if ($monto <= 10000) {
        // Solo responsable de compras
        if (!auth()->user()->hasRole('role_responsable_compras')) {
            abort(403, 'Solo responsable de compras puede aprobar');
        }
    } elseif ($monto <= 50000) {
        // Responsable de compras o admin
        if (!auth()->user()->hasAnyRole(['role_responsable_compras', 'role_admin_institucion'])) {
            abort(403, 'No tiene permisos para aprobar este monto');
        }
    } elseif ($monto <= 100000) {
        // Nivel de apoderado
        if (!auth()->user()->hasAnyRole(['role_responsable_compras', 'role_admin_institucion', 'role_apoderado'])) {
            abort(403, 'Requiere aprobación de apoderado');
        }
    } else {
        // Consejo de dirección
        if (!auth()->user()->hasAnyRole(['role_responsable_compras', 'role_admin_institucion', 'role_apoderado', 'role_representante_legal', 'role_consejo'])) {
            abort(403, 'Requiere aprobación del consejo');
        }
    }
    
    // Aprobar la compra
    $purchaseOrder->approve();
}
```

## Comandos Útiles

```bash
# Ver todos los roles
php artisan tinker
>>> Spatie\Permission\Models\Role::all();

# Ver todos los permisos
>>> Spatie\Permission\Models\Permission::all();

# Ver roles de un usuario
>>> App\Models\User::find(1)->roles;

# Ver permisos de un usuario
>>> App\Models\User::find(1)->permissions;

# Ver usuarios con un rol
>>> Spatie\Permission\Models\Role::findByName('role_admin_sistema')->users;
```

## Seguridad y Buenas Prácticas

1. **Nunca** asigne múltiples roles administrativos innecesariamente
2. **Siempre** valide permisos en el servidor, no solo en el frontend
3. **Use** middleware para proteger rutas importantes
4. **Audite** regularmente los roles y permisos de los usuarios
5. **Cambie** las contraseñas de los usuarios de ejemplo antes de producción

## Solución de Problemas

### "Permission does not exist"
Ejecute: `php artisan db:seed --class=RolesAndPermissionsSeeder`

### "Model User is not using HasRoles trait"
Verifique que el modelo User use `HasRoles`:
```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;
}
```

### Limpiar caché de permisos
```bash
php artisan permission:cache-reset
php artisan config:clear
```

