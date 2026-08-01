<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = [
        ['name' => 'import-quotations', 'module' => 'quotation', 'label' => 'Import Quotation', 'add_on' => 'Quotation'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $companyRole = Role::where('name', 'company')->first();

        foreach ($this->permissions as $data) {
            $permission = Permission::firstOrCreate(
                ['name' => $data['name'], 'guard_name' => 'web'],
                [
                    'module' => $data['module'],
                    'label' => $data['label'],
                    'add_on' => $data['add_on'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            if ($companyRole && !$companyRole->hasPermissionTo($permission)) {
                $companyRole->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::whereIn('name', array_column($this->permissions, 'name'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
