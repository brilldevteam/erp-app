<?php

namespace Workdo\EInvoice\Database\Seeders;

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
            ['name' => 'manage-einvoice', 'module' => 'e-invoice', 'label' => 'Manage E-Invoice'],
            ['name' => 'manage-einvoice-settings', 'module' => 'e-invoice', 'label' => 'Manage E-Invoice Settings'],
            ['name' => 'edit-einvoice-settings', 'module' => 'e-invoice', 'label' => 'Edit E-Invoice Settings'],
        ];

        $company_role = Role::where('name', 'company')->first();

        foreach ($permission as $perm) {
            $permission_obj = Permission::firstOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                [
                    'module' => $perm['module'],
                    'label' => $perm['label'],
                    'add_on' => 'EInvoice',
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