<?php

namespace Workdo\ContractTemplate\Database\Seeders;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;

class PermissionTableSeeder extends Seeder
{
    public function run()
    {
        Model::unguard();
        Artisan::call('cache:clear');

        $permission = [
            // Contract Template management
            ['name' => 'manage-contract-templates', 'module' => 'contract-templates', 'label' => 'Manage Contract Templates'],
            ['name' => 'manage-any-contract-templates', 'module' => 'contract-templates', 'label' => 'Manage All Contract Templates'],
            ['name' => 'manage-own-contract-templates', 'module' => 'contract-templates', 'label' => 'Manage Own Contract Templates'],
            ['name' => 'view-contract-templates', 'module' => 'contract-templates', 'label' => 'View Contract Templates'],
            ['name' => 'create-contract-templates', 'module' => 'contract-templates', 'label' => 'Create Contract Templates'],
            ['name' => 'edit-contract-templates', 'module' => 'contract-templates', 'label' => 'Edit Contract Templates'],
            ['name' => 'delete-contract-templates', 'module' => 'contract-templates', 'label' => 'Delete Contract Templates'],
            ['name' => 'duplicate-contract-templates', 'module' => 'contract-templates', 'label' => 'Duplicate Contract Templates'],
            ['name' => 'change-status-contract-templates', 'module' => 'contract-templates', 'label' => 'Change Contract Template Status'],
            ['name' => 'preview-contract-templates', 'module' => 'contract-templates', 'label' => 'Preview Contract Templates'],

            // Template conversion
            ['name' => 'convert-template-to-contract', 'module' => 'contract-templates', 'label' => 'Convert Template to Contract'],
            ['name' => 'convert-contract-to-template', 'module' => 'contract-templates', 'label' => 'Convert Contract to Template'],

            // Contract Template attachments
            ['name' => 'manage-any-contract-template-attachments', 'module' => 'contract-template-attachments', 'label' => 'Manage All Contract Template Attachments'],
            ['name' => 'manage-own-contract-template-attachments', 'module' => 'contract-template-attachments', 'label' => 'Manage Own Contract Template Attachments'],
            ['name' => 'create-contract-template-attachments', 'module' => 'contract-template-attachments', 'label' => 'Upload Contract Template Attachments'],
            ['name' => 'delete-contract-template-attachments', 'module' => 'contract-template-attachments', 'label' => 'Delete Contract Template Attachments'],

            // Contract Template comments
            ['name' => 'manage-any-contract-template-comments', 'module' => 'contract-template-comments', 'label' => 'Manage All Contract Template Comments'],
            ['name' => 'manage-own-contract-template-comments', 'module' => 'contract-template-comments', 'label' => 'Manage Own Contract Template Comments'],
            ['name' => 'create-contract-template-comments', 'module' => 'contract-template-comments', 'label' => 'Create Contract Template Comments'],
            ['name' => 'edit-contract-template-comments', 'module' => 'contract-template-comments', 'label' => 'Edit Contract Template Comments'],
            ['name' => 'delete-contract-template-comments', 'module' => 'contract-template-comments', 'label' => 'Delete Contract Template Comments'],

            // Contract Template notes
            ['name' => 'manage-any-contract-template-notes', 'module' => 'contract-template-notes', 'label' => 'Manage All Contract Template Notes'],
            ['name' => 'manage-own-contract-template-notes', 'module' => 'contract-template-notes', 'label' => 'Manage Own Contract Template Notes'],
            ['name' => 'create-contract-template-notes', 'module' => 'contract-template-notes', 'label' => 'Create Contract Template Notes'],
            ['name' => 'edit-contract-template-notes', 'module' => 'contract-template-notes', 'label' => 'Edit Contract Template Notes'],
            ['name' => 'delete-contract-template-notes', 'module' => 'contract-template-notes', 'label' => 'Delete Contract Template Notes'],
        ];

        $staff_permissions = [
            'manage-contract-templates',
            'manage-own-contract-templates',
            'view-contract-templates',
            'preview-contract-templates',
            'manage-own-contract-types',
            'manage-any-contract-template-attachments',
            'create-contract-template-attachments',
            'delete-contract-template-attachments',
            'manage-any-contract-template-comments',
            'create-contract-template-comments',
            'edit-contract-template-comments',
            'delete-contract-template-comments',
            'manage-any-contract-template-notes',
            'create-contract-template-notes',
            'edit-contract-template-notes',
            'delete-contract-template-notes',
        ];

        $client_permissions = [
            'manage-contract-templates',
            'manage-own-contract-templates',
            'view-contract-templates',
            'create-contract-templates',
            'duplicate-contract-templates',
            'preview-contract-templates',
            'manage-own-contract-types',
            'manage-any-contract-template-attachments',
            'create-contract-template-attachments',
            'delete-contract-template-attachments',
            'manage-any-contract-template-comments',
            'create-contract-template-comments',
            'edit-contract-template-comments',
            'delete-contract-template-comments',
            'manage-any-contract-template-notes',
            'create-contract-template-notes',
            'edit-contract-template-notes',
            'delete-contract-template-notes',
        ];

        $company_role = Role::where('name', 'company')->first();
        $staff_role = Role::where('name', 'staff')->first();
        $client_role = Role::where('name', 'client')->first();

        foreach ($permission as $perm) {
            $permission_obj = Permission::firstOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                [
                    'module' => $perm['module'],
                    'label' => $perm['label'],
                    'add_on' => 'ContractTemplate',
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );

            if ($company_role && !$company_role->hasPermissionTo($permission_obj)) {
                $company_role->givePermissionTo($permission_obj);
            }

            if ($staff_role && in_array($perm['name'], $staff_permissions) && !$staff_role->hasPermissionTo($permission_obj)) {
                $staff_role->givePermissionTo($permission_obj);
            }

            if ($client_role && in_array($perm['name'], $client_permissions) && !$client_role->hasPermissionTo($permission_obj)) {
                $client_role->givePermissionTo($permission_obj);
            }
        }
    }
}
