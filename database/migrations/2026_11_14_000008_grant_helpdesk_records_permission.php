<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'view-helpdesk-records', 'guard_name' => 'web'],
            ['module' => 'helpdesk-tickets', 'label' => 'View Helpdesk Records', 'add_on' => 'general']
        );

        Role::whereIn('name', ['company', 'client'])
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permission = Permission::where('name', 'view-helpdesk-records')->where('guard_name', 'web')->first();
        if ($permission) {
            Role::whereIn('name', ['company', 'client'])
                ->where('guard_name', 'web')
                ->get()
                ->each(fn (Role $role) => $role->revokePermissionTo($permission));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
