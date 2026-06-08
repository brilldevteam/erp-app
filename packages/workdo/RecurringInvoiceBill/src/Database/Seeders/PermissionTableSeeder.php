<?php

namespace Workdo\RecurringInvoiceBill\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionTableSeeder extends Seeder
{
    public function run()
    {
        Artisan::call('cache:clear');

        $permission = [
            ['name' => 'manage-recurring-invoice-bill', 'module' => 'recurring invoice bill', 'label' => 'Manage Recurring Invoice Bill'],
            ['name' => 'edit-recurring-invoice-bill', 'module' => 'recurring invoice bill', 'label' => 'Edit Recurring Invoice Bill'],

        ];

        $superadminRole = Role::where('name', 'superadmin')->first();

        $companyRole = Role::where('name', 'company')->first();

        foreach ($permission as $perm) {
            $permission_obj = Permission::firstOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                [
                    'module' => $perm['module'],
                    'label' => $perm['label'],
                    'add_on' => 'RecurringInvoiceBill',
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );

            if ($superadminRole && !$superadminRole->hasPermissionTo($permission_obj)) {
                $superadminRole->givePermissionTo($permission_obj);
            }

            if ($companyRole && !$companyRole->hasPermissionTo($permission_obj)) {
                $companyRole->givePermissionTo($permission_obj);
            }
        }
    }
}
