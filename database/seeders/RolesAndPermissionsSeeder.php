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
            'solicitud.analizar',
            
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

        $permissionsCreated = 0;
        // Crear permisos para ambos guards (web y backpack)
        foreach ($permissions as $permission) {
            foreach (['web', 'backpack'] as $guard) {
                $created = Permission::firstOrCreate(['name' => $permission, 'guard_name' => $guard]);
                if ($created->wasRecentlyCreated) {
                    $permissionsCreated++;
                }
            }
        }
        
        $this->command->info("✓ Creados {$permissionsCreated} permisos nuevos de " . count($permissions) . " totales");

        // Crear roles para ambos guards
        $rolePersonal = Role::firstOrCreate(['name' => 'role_personal', 'guard_name' => 'backpack']);
        $rolePersonalWeb = Role::firstOrCreate(['name' => 'role_personal', 'guard_name' => 'web']);
        $roleResponsableArea = Role::firstOrCreate(['name' => 'role_responsable_area', 'guard_name' => 'backpack']);
        $roleAutoridadInstituto = Role::firstOrCreate(['name' => 'role_autoridad_instituto', 'guard_name' => 'backpack']);
        $roleResponsableCompras = Role::firstOrCreate(['name' => 'role_responsable_compras', 'guard_name' => 'backpack']);
        $roleAdminInstitucion = Role::firstOrCreate(['name' => 'role_admin_institucion', 'guard_name' => 'backpack']);
        $roleApoderado = Role::firstOrCreate(['name' => 'role_apoderado', 'guard_name' => 'backpack']);
        $roleRepresentanteLegal = Role::firstOrCreate(['name' => 'role_representante_legal', 'guard_name' => 'backpack']);
        $roleConsejo = Role::firstOrCreate(['name' => 'role_consejo', 'guard_name' => 'backpack']);
        $roleTesoreria = Role::firstOrCreate(['name' => 'role_tesoreria', 'guard_name' => 'backpack']);
        $roleContabilidad = Role::firstOrCreate(['name' => 'role_contabilidad', 'guard_name' => 'backpack']);
        $roleAnalistaArea = Role::firstOrCreate(['name' => 'role_analista_area', 'guard_name' => 'backpack']);
        $roleAdminSistema = Role::firstOrCreate(['name' => 'role_admin_sistema', 'guard_name' => 'backpack']);

        // Asignar permisos a roles (usando guard backpack)

        // Personal solicitante - puede crear y ver solicitudes
        $rolePersonal->givePermissionTo([
            Permission::findByName('solicitud.crear', 'backpack'),
            Permission::findByName('solicitud.ver', 'backpack'),
        ]);
        
        // También asignar al rol web para compatibilidad
        $rolePersonalWeb->givePermissionTo([
            Permission::findByName('solicitud.crear', 'web'),
            Permission::findByName('solicitud.ver', 'web'),
        ]);

        // Responsable de depósito - puede crear, ver, aprobar y entregar solicitudes
        $roleResponsableArea->givePermissionTo([
            Permission::findByName('solicitud.crear', 'backpack'),
            Permission::findByName('solicitud.ver', 'backpack'),
            Permission::findByName('solicitud.aprobar', 'backpack'),
            Permission::findByName('solicitud.entregar', 'backpack'),
        ]);
        
        // También asignar al rol web para compatibilidad
        $roleResponsableAreaWeb = Role::firstOrCreate(['name' => 'role_responsable_area', 'guard_name' => 'web']);
        $roleResponsableAreaWeb->givePermissionTo([
            Permission::findByName('solicitud.crear', 'web'),
            Permission::findByName('solicitud.ver', 'web'),
            Permission::findByName('solicitud.aprobar', 'web'),
            Permission::findByName('solicitud.entregar', 'web'),
        ]);

        // Autoridad del instituto: mismos permisos que responsable de área (incl. cotizaciones vinculadas a solicitudes).
        $roleAutoridadInstituto->givePermissionTo([
            Permission::findByName('solicitud.crear', 'backpack'),
            Permission::findByName('solicitud.ver', 'backpack'),
            Permission::findByName('solicitud.aprobar', 'backpack'),
            Permission::findByName('solicitud.entregar', 'backpack'),
        ]);
        $roleAutoridadInstitutoWeb = Role::firstOrCreate(['name' => 'role_autoridad_instituto', 'guard_name' => 'web']);
        $roleAutoridadInstitutoWeb->givePermissionTo([
            Permission::findByName('solicitud.crear', 'web'),
            Permission::findByName('solicitud.ver', 'web'),
            Permission::findByName('solicitud.aprobar', 'web'),
            Permission::findByName('solicitud.entregar', 'web'),
        ]);

        // Responsable de compras - compras y visibilidad de solicitudes; entregas vinculadas al flujo; crear solicitudes generales
        $roleResponsableCompras->givePermissionTo([
            Permission::findByName('compra.crear', 'backpack'),
            Permission::findByName('compra.aprobar', 'backpack'),
            Permission::findByName('compra.ejecutar', 'backpack'),
            Permission::findByName('solicitud.crear', 'backpack'),
            Permission::findByName('solicitud.ver', 'backpack'),
            Permission::findByName('solicitud.entregar', 'backpack'),
        ]);

        // Administrador - permisos de administración institucional y aprobación de compras
        $roleAdminInstitucion->givePermissionTo([
            Permission::findByName('compra.aprobar', 'backpack'),
            Permission::findByName('compra.ejecutar', 'backpack'),
            Permission::findByName('solicitud.ver', 'backpack'),
            Permission::findByName('solicitud.aprobar', 'backpack'),
            Permission::findByName('reportes.exportar', 'backpack'),
        ]);

        // Apoderado legal - puede aprobar compras de mayor monto
        $roleApoderado->givePermissionTo([
            Permission::findByName('compra.aprobar', 'backpack'),
            Permission::findByName('compra.ejecutar', 'backpack'),
            Permission::findByName('solicitud.ver', 'backpack'),
            Permission::findByName('solicitud.aprobar', 'backpack'),
        ]);

        // Representante legal - puede aprobar compras de mayor monto
        $roleRepresentanteLegal->givePermissionTo([
            Permission::findByName('compra.aprobar', 'backpack'),
            Permission::findByName('compra.ejecutar', 'backpack'),
            Permission::findByName('solicitud.ver', 'backpack'),
            Permission::findByName('solicitud.aprobar', 'backpack'),
        ]);

        // Consejo de dirección - puede aprobar compras de mayor monto
        $roleConsejo->givePermissionTo([
            Permission::findByName('compra.aprobar', 'backpack'),
            Permission::findByName('compra.ejecutar', 'backpack'),
            Permission::findByName('solicitud.ver', 'backpack'),
            Permission::findByName('solicitud.aprobar', 'backpack'),
        ]);

        // Tesorería - puede ver finanzas y exportar reportes
        $roleTesoreria->givePermissionTo([
            Permission::findByName('finanzas.ver', 'backpack'),
            Permission::findByName('reportes.exportar', 'backpack'),
        ]);

        // Contabilidad - puede ver finanzas y exportar reportes
        $roleContabilidad->givePermissionTo([
            Permission::findByName('finanzas.ver', 'backpack'),
            Permission::findByName('reportes.exportar', 'backpack'),
        ]);

        // Analista de área - puede ver y analizar solicitudes de Informática e Insumos de Salud
        $roleAnalistaArea->givePermissionTo([
            Permission::findByName('solicitud.ver', 'backpack'),
            Permission::findByName('solicitud.analizar', 'backpack'),
        ]);
        
        // También asignar al rol web para compatibilidad
        $roleAnalistaAreaWeb = Role::firstOrCreate(['name' => 'role_analista_area', 'guard_name' => 'web']);
        $roleAnalistaAreaWeb->givePermissionTo([
            Permission::findByName('solicitud.ver', 'web'),
            Permission::findByName('solicitud.analizar', 'web'),
        ]);

        // Administrador del sistema - todos los permisos del guard backpack
        $allPermissions = Permission::where('guard_name', 'backpack')->get();
        $roleAdminSistema->syncPermissions($allPermissions);
        
        $this->command->info("✓ Asignados " . $allPermissions->count() . " permisos al rol Administrador del Sistema");

        $this->command->info('');
        $this->command->info('✓ Roles y permisos creados exitosamente');
        $this->command->info("  - Total de permisos: " . Permission::count());
        $this->command->info("  - Total de roles: " . Role::count());
    }
}
