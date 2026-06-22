<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = [
        'manage-document-templates' => 'Manage Document Templates',
        'send-documents' => 'Send Documents',
        'manage-document-links' => 'Manage Document Links',
        'send-invoice-reminders' => 'Send Invoice Reminders',
        'view-document-activity' => 'View Document Activity',
        'initiate-invoice-payments' => 'Initiate Invoice Payments',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $companyRole = Role::where('name', 'company')->where('guard_name', 'web')->first();
        foreach ($this->permissions as $name => $label) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], [
                'module' => 'documents', 'label' => $label, 'add_on' => 'general',
            ]);
            $companyRole?->givePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', array_keys($this->permissions))->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
