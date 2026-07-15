<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Role::where('name', 'staff')->get()->each(function (Role $role) {
            foreach (['create-attendances', 'edit-attendances', 'delete-attendances'] as $permission) {
                if ($role->hasPermissionTo($permission)) {
                    $role->revokePermissionTo($permission);
                }
            }
        });
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'staff')->get()->each(fn (Role $role) => $role->givePermissionTo([
            'create-attendances', 'edit-attendances',
        ]));
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
