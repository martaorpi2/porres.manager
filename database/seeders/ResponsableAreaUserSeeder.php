<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;

class ResponsableAreaUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Verificar si el usuario ya existe con el email antiguo
        $oldUser = User::where('email', 'responsable.area@admin')->first();
        if ($oldUser) {
            // El usuario ya existe, solo asegurar el rol
            $user = $oldUser;
        } else {
            // Crear usuario con rol de Responsable de Área
            $user = User::create([
                'name' => 'Responsable de Área',
                'email' => 'responsable.area@admin',
                'password' => bcrypt('123456789'),
            ]);
            
            $this->command->info('✓ Usuario responsable.area@admin creado exitosamente');
        }
        
        // Asignar rol (usando guard backpack)
        $role = \Spatie\Permission\Models\Role::findByName('role_responsable_area', 'backpack');
        if (!$user->hasRole($role)) {
            $user->assignRole($role);
            $this->command->info('✓ Rol role_responsable_area asignado al usuario responsable.area@admin');
        } else {
            $this->command->info('✓ Usuario responsable.area@admin ya tiene el rol role_responsable_area');
        }
        
        // Asignar área Informática al usuario (si existe)
        $areaInformatica = \App\Models\ResponsibilityArea::where('name', 'Informática')->first();
        if ($areaInformatica) {
            $areaInformatica->responsible_user_id = $user->id;
            $areaInformatica->save();
            $this->command->info('✓ Área Informática asignada al usuario responsable.area@admin');
        } else {
            $this->command->warn('⚠ Área Informática no encontrada. Debes asignarla manualmente.');
        }
    }
}

