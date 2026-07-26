<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = [
        'manage-project-contractors' => 'Manage Project Contractors',
        'create-project-contractors' => 'Create Project Contractors',
        'edit-project-contractors' => 'Edit Project Contractors',
        'delete-project-contractors' => 'Delete Project Contractors',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions as $name => $label) {
            $permission = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['module' => 'project-contractor', 'label' => $label, 'add_on' => 'Taskly']
            );

            Role::where('name', 'company')->where('guard_name', 'web')->get()
                ->each(fn (Role $role) => $role->givePermissionTo($permission));

            if ($name === 'manage-project-contractors') {
                Role::where('name', 'staff')->where('guard_name', 'web')->get()
                    ->each(fn (Role $role) => $role->givePermissionTo($permission));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', array_keys($this->permissions))->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
