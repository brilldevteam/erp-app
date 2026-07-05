<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'manage-journal-entries' => 'Manage Journal Entries',
            'view-journal-entries' => 'View Journal Entries',
            'create-journal-entries' => 'Create Journal Entries',
            'delete-journal-entries' => 'Delete Journal Entries',
        ];

        $companyRole = Role::where('name', 'company')->first();

        foreach ($permissions as $name => $label) {
            $permission = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['module' => 'journal-entries', 'label' => $label, 'add_on' => 'Account']
            );

            if ($companyRole && !$companyRole->hasPermissionTo($permission)) {
                $companyRole->givePermissionTo($permission);
            }
        }
    }

    public function down(): void
    {
        $permissions = [
            'manage-journal-entries',
            'view-journal-entries',
            'create-journal-entries',
            'delete-journal-entries',
        ];

        Permission::whereIn('name', $permissions)->delete();
    }
};
