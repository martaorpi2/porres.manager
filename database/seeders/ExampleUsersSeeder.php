<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Este seeder es SOLO un ejemplo de cómo crear usuarios con roles
 * No debe ejecutarse en producción
 */
class ExampleUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seeder Ejemplo - NO EJECUTAR EN PRODUCCIÓN
        // Este es solo un ejemplo de cómo asignar roles a usuarios
        
        // Ejemplo 1: Crear usuario con rol de Personal Solicitante
        $personalSolicitante = User::create([
            'name' => 'Juan Pérez',
            'email' => 'juan.perez@example.com',
            'password' => bcrypt('password'),
        ]);
        $personalSolicitante->assignRole('role_personal');

        // Ejemplo 2: Crear usuario con rol de Responsable de Depósito
        $responsableDeposito = User::create([
            'name' => 'María González',
            'email' => 'maria.gonzalez@example.com',
            'password' => bcrypt('password'),
        ]);
        $responsableDeposito->assignRole('role_responsable_area');

        // Ejemplo 3: Crear usuario con múltiples roles (Responsable de Compras + Admin)
        $responsableCompras = User::create([
            'name' => 'Carlos Rodríguez',
            'email' => 'carlos.rodriguez@example.com',
            'password' => bcrypt('password'),
        ]);
        $responsableCompras->assignRole(['role_responsable_compras', 'role_admin_institucion']);

        // Ejemplo 4: Crear usuario administrador del sistema (todos los permisos)
        $adminSistema = User::create([
            'name' => 'Admin Sistema',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $adminSistema->assignRole('role_admin_sistema');

        // Ejemplo 5: Crear usuario con roles financieros (Tesorería)
        $tesoreria = User::create([
            'name' => 'Ana Martínez',
            'email' => 'ana.martinez@example.com',
            'password' => bcrypt('password'),
        ]);
        $tesoreria->assignRole('role_tesoreria');

        // Ejemplo 6: Crear usuario con rol de Contabilidad
        $contabilidad = User::create([
            'name' => 'Luis Fernández',
            'email' => 'luis.fernandez@example.com',
            'password' => bcrypt('password'),
        ]);
        $contabilidad->assignRole('role_contabilidad');

        // Ejemplo 7: Crear usuario con rol Apoderado Legal
        $apoderado = User::create([
            'name' => 'Dr. Roberto Silva',
            'email' => 'roberto.silva@example.com',
            'password' => bcrypt('password'),
        ]);
        $apoderado->assignRole('role_apoderado');

        // Ejemplo 8: Crear usuario con rol Representante Legal
        $representante = User::create([
            'name' => 'Dra. Patricia Vázquez',
            'email' => 'patricia.vazquez@example.com',
            'password' => bcrypt('password'),
        ]);
        $representante->assignRole('role_representante_legal');

        // Ejemplo 9: Crear usuario con rol Consejo de Dirección
        $consejo = User::create([
            'name' => 'Ing. Miguel Torres',
            'email' => 'miguel.torres@example.com',
            'password' => bcrypt('password'),
        ]);
        $consejo->assignRole('role_consejo');

        $this->command->info('✓ Usuarios de ejemplo creados exitosamente');
        $this->command->warn('NOTA: Cambie las contraseñas en producción!');
    }
}

