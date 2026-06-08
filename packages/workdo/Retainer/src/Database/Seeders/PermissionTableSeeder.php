<?php

namespace Workdo\Retainer\Database\Seeders;

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
            ['name' => 'manage-retainer', 'module' => 'retainer', 'label' => 'Manage Retainer'],
            ['name' => 'manage-any-retainer', 'module' => 'retainer', 'label' => 'Manage All Retainer'],
            ['name' => 'manage-own-retainer', 'module' => 'retainer', 'label' => 'Manage Own Retainer'],
            ['name' => 'view-retainer', 'module' => 'retainer', 'label' => 'View Retainer'],
            ['name' => 'create-retainer', 'module' => 'retainer', 'label' => 'Create Retainer'],
            ['name' => 'edit-retainer', 'module' => 'retainer', 'label' => 'Edit Retainer'],
            ['name' => 'delete-retainer', 'module' => 'retainer', 'label' => 'Delete Retainer'],
            ['name' => 'print-retainer', 'module' => 'retainer', 'label' => 'Print Retainer'],
            ['name' => 'sent-retainer', 'module' => 'retainer', 'label' => 'Sent Retainer'],
            ['name' => 'accept-retainer', 'module' => 'retainer', 'label' => 'Accept Retainer'],
            ['name' => 'reject-retainer', 'module' => 'retainer', 'label' => 'Reject Retainer'],
            ['name' => 'duplicate-retainer', 'module' => 'retainer', 'label' => 'Duplicate Retainer'],
            ['name' => 'convert-to-invoice-retainer', 'module' => 'retainer', 'label' => 'Convert Retainer to Invoice'],


            // RetainerPayment management
            ['name' => 'manage-retainer-payments', 'module' => 'retainer-payments', 'label' => 'Manage Retainer Payments'],
            ['name' => 'manage-any-retainer-payments', 'module' => 'retainer-payments', 'label' => 'Manage All Retainer Payments'],
            ['name' => 'manage-own-retainer-payments', 'module' => 'retainer-payments', 'label' => 'Manage Own Retainer Payments'],
            ['name' => 'view-retainer-payments', 'module' => 'retainer-payments', 'label' => 'View Retainer Payments'],
            ['name' => 'create-retainer-payments', 'module' => 'retainer-payments', 'label' => 'Create Retainer Payments'],
            ['name' => 'delete-retainer-payments', 'module' => 'retainer-payments', 'label' => 'Delete Retainer Payments'],
            ['name' => 'cleared-retainer-payments', 'module' => 'retainer-payments', 'label' => 'Clear Retainer Payments'],
            ['name' => 'cancelled-retainer-payments', 'module' => 'retainer-payments', 'label' => 'Clear Retainer Payments'],
        ];

        $company_role = Role::where('name', 'company')->first();

        foreach ($permission as $perm) {
            $permission_obj = Permission::firstOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                [
                    'module'     => $perm['module'],
                    'label'      => $perm['label'],
                    'add_on'     => 'Retainer',
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