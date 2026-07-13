<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = [
        ['name' => 'import-product-service-categories', 'module' => 'product-service-category', 'label' => 'Import Product Service Categories', 'add_on' => 'ProductService'],
        ['name' => 'import-product-service-units', 'module' => 'product-service-unit', 'label' => 'Import Product Service Units', 'add_on' => 'ProductService'],
        ['name' => 'import-product-service-taxes', 'module' => 'product-service-tax', 'label' => 'Import Product Service Taxes', 'add_on' => 'ProductService'],
        ['name' => 'import-account-types', 'module' => 'account-types', 'label' => 'Import Account Types', 'add_on' => 'Account'],
        ['name' => 'import-chart-of-accounts', 'module' => 'chart-of-accounts', 'label' => 'Import Chart Of Accounts', 'add_on' => 'Account'],
        ['name' => 'import-revenue-categories', 'module' => 'revenue-categories', 'label' => 'Import Revenue Categories', 'add_on' => 'Account'],
        ['name' => 'import-expense-categories', 'module' => 'expense-categories', 'label' => 'Import Expense Categories', 'add_on' => 'Account'],
        ['name' => 'import-warehouses', 'module' => 'warehouses', 'label' => 'Import Warehouses', 'add_on' => 'general'],
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
