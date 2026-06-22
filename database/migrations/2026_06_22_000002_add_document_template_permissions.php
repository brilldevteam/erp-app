<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = [
        'manage-document-templates' => 'Manage Document Templates',
        'create-document-templates' => 'Create Document Templates',
        'edit-document-templates' => 'Edit Document Templates',
        'delete-document-templates' => 'Delete Document Templates',
        'view-document-templates' => 'View Document Templates',
        'set-default-document-templates' => 'Set Default Document Templates',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions as $name => $label) {
            $permission = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['module' => 'document-templates', 'label' => $label, 'add_on' => 'general']
            );

            Role::where('name', 'company')
                ->where('guard_name', 'web')
                ->get()
                ->each(fn (Role $role) => $role->givePermissionTo($permission));

            if ($name === 'view-document-templates') {
                Role::where('name', 'staff')
                    ->where('guard_name', 'web')
                    ->get()
                    ->each(fn (Role $role) => $role->givePermissionTo($permission));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', array_keys($this->permissions))
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
