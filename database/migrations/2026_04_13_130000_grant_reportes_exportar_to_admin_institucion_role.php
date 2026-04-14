<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['backpack', 'web'] as $guard) {
            $role = Role::query()
                ->where('name', 'role_admin_institucion')
                ->where('guard_name', $guard)
                ->first();

            $permission = Permission::query()
                ->where('name', 'reportes.exportar')
                ->where('guard_name', $guard)
                ->first();

            if ($role && $permission && !$role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['backpack', 'web'] as $guard) {
            $role = Role::query()
                ->where('name', 'role_admin_institucion')
                ->where('guard_name', $guard)
                ->first();

            $permission = Permission::query()
                ->where('name', 'reportes.exportar')
                ->where('guard_name', $guard)
                ->first();

            if ($role && $permission && $role->hasPermissionTo($permission)) {
                $role->revokePermissionTo($permission);
            }
        }
    }
};
