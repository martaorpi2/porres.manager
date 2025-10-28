<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Crear permisos
        $permissions = [
            // Solicitudes
            'solicitud.crear',
            'solicitud.ver',
            'solicitud.aprobar',
            'solicitud.entregar',
            
            // Compras
            'compra.crear',
            'compra.aprobar',
            'compra.ejecutar',
            
            // Finanzas
            'finanzas.ver',
            'reportes.exportar',
            
            // Administración
            'admin.usuarios',
            'admin.config',
            'admin.audit',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'web']);
        }

        // Crear roles
        $rolePersonal = Role::create(['name' => 'role_personal', 'guard_name' => 'web']);
        $roleResponsableArea = Role::create(['name' => 'role_responsable_area', 'guard_name' => 'web']);
        $roleResponsableCompras = Role::create(['name' => 'role_responsable_compras', 'guard_name' => 'web']);
        $roleAdminInstitucion = Role::create(['name' => 'role_admin_institucion', 'guard_name' => 'web']);
        $roleApoderado = Role::create(['name' => 'role_apoderado', 'guard_name' => 'web']);
        $roleRepresentanteLegal = Role::create(['name' => 'role_representante_legal', 'guard_name' => 'web']);
        $roleConsejo = Role::create(['name' => 'role_consejo', 'guard_name' => 'web']);
        $roleTesoreria = Role::create(['name' => 'role_tesoreria', 'guard_name' => 'web']);
        $roleContabilidad = Role::create(['name' => 'role_contabilidad', 'guard_name' => 'web']);
        $roleAdminSistema = Role::create(['name' => 'role_admin_sistema', 'guard_name' => 'web']);

        // Asignar permisos a roles

        // Personal solicitante - puede crear y ver solicitudes
        $rolePersonal->givePermissionTo([
            'solicitud.crear',
            'solicitud.ver',
        ]);

        // Responsable de depósito - puede ver, aprobar y entregar solicitudes
        $roleResponsableArea->givePermissionTo([
            'solicitud.ver',
            'solicitud.aprobar',
            'solicitud.entregar',
        ]);

        // Responsable de compras - puede crear y aprobar compras según monto
        $roleResponsableCompras->givePermissionTo([
            'compra.crear',
            'compra.aprobar',
            'compra.ejecutar',
            'solicitud.ver',
        ]);

        // Administrador - permisos de administración institucional y aprobación de compras
        $roleAdminInstitucion->givePermissionTo([
            'compra.aprobar',
            'compra.ejecutar',
            'solicitud.ver',
            'solicitud.aprobar',
        ]);

        // Apoderado legal - puede aprobar compras de mayor monto
        $roleApoderado->givePermissionTo([
            'compra.aprobar',
            'compra.ejecutar',
            'solicitud.ver',
            'solicitud.aprobar',
        ]);

        // Representante legal - puede aprobar compras de mayor monto
        $roleRepresentanteLegal->givePermissionTo([
            'compra.aprobar',
            'compra.ejecutar',
            'solicitud.ver',
            'solicitud.aprobar',
        ]);

        // Consejo de dirección - puede aprobar compras de mayor monto
        $roleConsejo->givePermissionTo([
            'compra.aprobar',
            'compra.ejecutar',
            'solicitud.ver',
            'solicitud.aprobar',
        ]);

        // Tesorería - puede ver finanzas y exportar reportes
        $roleTesoreria->givePermissionTo([
            'finanzas.ver',
            'reportes.exportar',
        ]);

        // Contabilidad - puede ver finanzas y exportar reportes
        $roleContabilidad->givePermissionTo([
            'finanzas.ver',
            'reportes.exportar',
        ]);

        // Administrador del sistema - todos los permisos
        $roleAdminSistema->givePermissionTo(Permission::all());

        $this->command->info('✓ Roles y permisos creados exitosamente');
    }
}
