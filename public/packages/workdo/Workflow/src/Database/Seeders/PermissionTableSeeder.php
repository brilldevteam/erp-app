<?php

namespace Workdo\Workflow\Database\Seeders;

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
            ['name' => 'manage-workflow', 'module' => 'workflow', 'label' => 'Manage Workflow'],
            ['name' => 'manage-any-workflow', 'module' => 'workflow', 'label' => 'Manage All Workflow'],
            ['name' => 'manage-own-workflow', 'module' => 'workflow', 'label' => 'Manage Own Workflow'],
            ['name' => 'create-workflow', 'module' => 'workflow', 'label' => 'Create Workflow'],
            ['name' => 'edit-workflow', 'module' => 'workflow', 'label' => 'Edit Workflow'],
            ['name' => 'delete-workflow', 'module' => 'workflow', 'label' => 'Delete Workflow'],
        ];

        $company_role = Role::where('name', 'company')->first();

        foreach ($permission as $perm) {
            $permission_obj = Permission::firstOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                [
                    'module' => $perm['module'],
                    'label' => $perm['label'],
                    'add_on' => 'Workflow',
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );

            if ($company_role && !$company_role->hasPermissionTo($permission_obj)) {
                $company_role->givePermissionTo($permission_obj);
            }
        }
    }
}
