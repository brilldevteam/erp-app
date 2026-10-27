<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = [
        ['name' => 'view-zoho-migrations', 'label' => 'View Zoho Migrations'],
        ['name' => 'create-zoho-migrations', 'label' => 'Create Zoho Migrations'],
        ['name' => 'upload-zoho-migration-files', 'label' => 'Upload Zoho Migration Files'],
        ['name' => 'approve-zoho-migrations', 'label' => 'Approve Zoho Migrations'],
        ['name' => 'execute-zoho-migrations', 'label' => 'Execute Zoho Migrations'],
        ['name' => 'undo-zoho-migrations', 'label' => 'Undo Zoho Migrations'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $companyRole = Role::where('name', 'company')->first();

        foreach ($this->permissions as $data) {
            $permission = Permission::firstOrCreate(
                ['name' => $data['name'], 'guard_name' => 'web'],
                ['module' => 'zoho-migrations', 'label' => $data['label'], 'add_on' => 'Account']
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
