<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Seeder para crear usuarios para todos los roles del sistema
 * Ejecutar con: php artisan db:seed --class=UsersForAllRolesSeeder
 */
class UsersForAllRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creando usuarios para todos los roles...');
        
        // Mapeo de roles con información de usuarios
        $rolesData = [
            'role_personal' => [
                'name' => 'Personal Solicitante',
                'email' => 'personal@porres.manager',
                'user_name' => 'Usuario Personal',
            ],
            'role_responsable_area' => [
                'name' => 'Responsable de Depósito',
                'email' => 'deposito@porres.manager',
                'user_name' => 'Responsable Depósito',
            ],
            'role_responsable_compras' => [
                'name' => 'Responsable de Compras',
                'email' => 'compras@porres.manager',
                'user_name' => 'Responsable Compras',
            ],
            'role_admin_institucion' => [
                'name' => 'Administrador Institucional',
                'email' => 'admin@porres.manager',
                'user_name' => 'Admin Institucional',
            ],
            'role_apoderado' => [
                'name' => 'Apoderado Legal',
                'email' => 'apoderado@porres.manager',
                'user_name' => 'Apoderado Legal',
            ],
            'role_representante_legal' => [
                'name' => 'Representante Legal',
                'email' => 'representante@porres.manager',
                'user_name' => 'Representante Legal',
            ],
            'role_consejo' => [
                'name' => 'Consejo de Dirección',
                'email' => 'consejo@porres.manager',
                'user_name' => 'Consejo Dirección',
            ],
            'role_tesoreria' => [
                'name' => 'Tesorería',
                'email' => 'tesoreria@porres.manager',
                'user_name' => 'Usuario Tesorería',
            ],
            'role_contabilidad' => [
                'name' => 'Contabilidad',
                'email' => 'contabilidad@porres.manager',
                'user_name' => 'Usuario Contabilidad',
            ],
            'role_admin_sistema' => [
                'name' => 'Administrador del Sistema',
                'email' => 'admin.sistema@porres.manager',
                'user_name' => 'Admin Sistema',
            ],
        ];

        $createdCount = 0;
        $skippedCount = 0;

        foreach ($rolesData as $roleName => $userData) {
            // Verificar si el rol existe
            $role = Role::where('name', $roleName)->first();
            
            if (!$role) {
                $this->command->warn("  ⚠ Rol '{$roleName}' no existe. Omitiendo...");
                $skippedCount++;
                continue;
            }

            // Verificar si ya existe un usuario con este email
            $existingUser = User::where('email', $userData['email'])->first();
            
            if ($existingUser) {
                // Si existe, verificar si ya tiene el rol asignado
                if (!$existingUser->hasRole($roleName)) {
                    $existingUser->assignRole($roleName);
                    $this->command->info("  ✓ Rol '{$userData['name']}' asignado a usuario existente: {$userData['email']}");
                    $createdCount++;
                } else {
                    $this->command->info("  ⊙ Usuario ya existe con rol '{$userData['name']}': {$userData['email']}");
                    $skippedCount++;
                }
            } else {
                // Crear nuevo usuario
                $user = User::create([
                    'name' => $userData['user_name'],
                    'email' => $userData['email'],
                    'password' => bcrypt('password'), // Contraseña por defecto: password
                    'email_verified_at' => now(),
                ]);

                // Asignar rol
                $user->assignRole($roleName);

                $this->command->info("  ✓ Usuario creado: {$userData['user_name']} ({$userData['email']}) - Rol: {$userData['name']}");
                $createdCount++;
            }
        }

        $this->command->info('');
        $this->command->info("✓ Proceso completado:");
        $this->command->info("  - Usuarios creados/actualizados: {$createdCount}");
        $this->command->info("  - Usuarios omitidos: {$skippedCount}");
        $this->command->warn('');
        $this->command->warn('⚠ IMPORTANTE: Todos los usuarios tienen la contraseña por defecto: "password"');
        $this->command->warn('⚠ Cambie las contraseñas en producción!');
    }
}

