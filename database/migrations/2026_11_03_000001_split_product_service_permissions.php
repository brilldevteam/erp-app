<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $actions = [
        'manage' => 'Manage',
        'manage-any' => 'Manage All',
        'manage-own' => 'Manage Own',
        'view' => 'View',
        'create' => 'Create',
        'edit' => 'Edit',
        'delete' => 'Delete',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['products' => 'Products', 'services' => 'Services'] as $module => $label) {
            foreach ($this->actions as $action => $actionLabel) {
                $permission = Permission::firstOrCreate(
                    ['name' => "{$action}-{$module}", 'guard_name' => 'web'],
                    ['module' => $module, 'label' => "{$actionLabel} {$label}", 'add_on' => 'ProductService']
                );

                $legacy = Permission::where('name', "{$action}-product-service-item")->where('guard_name', 'web')->first();
                if ($legacy) {
                    Role::permission($legacy)->each(fn (Role $role) => $role->givePermissionTo($permission));
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::whereIn('name', collect(['products', 'services'])->flatMap(
            fn (string $module) => array_map(fn (string $action) => "{$action}-{$module}", array_keys($this->actions))
        ))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
