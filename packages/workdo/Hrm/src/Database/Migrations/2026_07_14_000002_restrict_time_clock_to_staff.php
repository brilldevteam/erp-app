<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'use-staff-time-clock', 'guard_name' => 'web'],
            ['module' => 'attendances', 'label' => 'Use Staff Time Clock', 'add_on' => 'Hrm'],
        );

        Role::where('name', 'staff')->get()->each(fn (Role $role) => $role->givePermissionTo($permission));
        Role::where('name', '!=', 'staff')->get()->each(function (Role $role) use ($permission) {
            if ($role->hasPermissionTo($permission)) {
                $role->revokePermissionTo($permission);
            }
        });
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'use-staff-time-clock')->where('guard_name', 'web')->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
