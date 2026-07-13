<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = [
        ['name' => 'import-vendors', 'module' => 'vendors', 'label' => 'Import Vendors', 'add_on' => 'Account'],
        ['name' => 'import-bank-accounts', 'module' => 'bank-accounts', 'label' => 'Import Bank Accounts', 'add_on' => 'Account'],
        ['name' => 'import-sales-invoices', 'module' => 'sales-invoices', 'label' => 'Import Sales Invoices', 'add_on' => 'general'],
        ['name' => 'import-purchase-invoices', 'module' => 'purchase-invoices', 'label' => 'Import Purchase Invoices', 'add_on' => 'general'],
        ['name' => 'import-customer-payments', 'module' => 'customer-payments', 'label' => 'Import Customer Payments', 'add_on' => 'Account'],
        ['name' => 'import-vendor-payments', 'module' => 'vendor-payments', 'label' => 'Import Vendor Payments', 'add_on' => 'Account'],
        ['name' => 'import-revenues', 'module' => 'revenues', 'label' => 'Import Revenues', 'add_on' => 'Account'],
        ['name' => 'import-expenses', 'module' => 'expenses', 'label' => 'Import Expenses', 'add_on' => 'Account'],
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
