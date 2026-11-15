<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = collect([
            ['name' => 'manage-platform-ai-settings', 'label' => 'Manage Platform AI Settings'],
            ['name' => 'edit-platform-ai-settings', 'label' => 'Edit Platform AI Settings'],
        ])->map(fn (array $permission) => Permission::firstOrCreate(
            ['name' => $permission['name'], 'guard_name' => 'web'],
            ['module' => 'settings', 'label' => $permission['label'], 'add_on' => 'general']
        ));

        Role::where('name', 'superadmin')->where('guard_name', 'web')->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissions = Permission::whereIn('name', [
            'manage-platform-ai-settings',
            'edit-platform-ai-settings',
        ])->where('guard_name', 'web')->get();

        Role::where('name', 'superadmin')->where('guard_name', 'web')->get()
            ->each(fn (Role $role) => $role->revokePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
