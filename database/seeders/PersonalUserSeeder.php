<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;

class PersonalUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Verificar si el usuario ya existe con el email antiguo
        $oldUser = User::where('email', 'personal@ismp.edu.ar')->first();
        if ($oldUser) {
            // Actualizar el email del usuario existente
            $oldUser->update(['email' => 'personal@admin']);
            $user = $oldUser;
        } else {
            // Verificar si el usuario ya existe con el nuevo email
            $user = User::where('email', 'personal@admin')->first();
        }
        
        if (!$user) {
            // Crear usuario con rol de Personal
            $user = User::create([
                'name' => 'Personal ISMP',
                'email' => 'personal@admin',
                'password' => bcrypt('123456789'),
            ]);
            
            // Asignar rol (usando guard backpack)
            $user->assignRole(\Spatie\Permission\Models\Role::findByName('role_personal', 'backpack'));
            
            $this->command->info('✓ Usuario personal@admin creado exitosamente con rol role_personal');
        } else {
            // Si ya existe, asegurar que tenga el rol correcto
            $role = \Spatie\Permission\Models\Role::findByName('role_personal', 'backpack');
            if (!$user->hasRole($role)) {
                $user->assignRole($role);
                $this->command->info('✓ Rol role_personal asignado al usuario personal@admin');
            } else {
                $this->command->info('✓ Usuario personal@admin ya existe con el rol role_personal');
            }
        }
    }
}

