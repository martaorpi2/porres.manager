<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $backpack = Role::firstOrCreate(
            ['name' => 'role_autoridad_instituto', 'guard_name' => 'backpack']
        );
        $sourceBackpack = Role::where('name', 'role_responsable_area')->where('guard_name', 'backpack')->first();
        if ($sourceBackpack) {
            $backpack->syncPermissions($sourceBackpack->permissions);
        }

        $web = Role::firstOrCreate(
            ['name' => 'role_autoridad_instituto', 'guard_name' => 'web']
        );
        $sourceWeb = Role::where('name', 'role_responsable_area')->where('guard_name', 'web')->first();
        if ($sourceWeb) {
            $web->syncPermissions($sourceWeb->permissions);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'role_autoridad_instituto')->delete();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
