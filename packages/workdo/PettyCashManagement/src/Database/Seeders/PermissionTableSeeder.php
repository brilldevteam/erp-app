<?php

namespace Workdo\PettyCashManagement\Database\Seeders;

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
            ['name' => 'manage-petty-cash-management', 'module' => 'petty-cash-management', 'label' => 'Manage Petty Cash Management'],

            // PettyCashCategory management
            ['name' => 'manage-petty-cash-categories', 'module' => 'petty-cash-categories', 'label' => 'Manage Petty Cash Categories'],
            ['name' => 'manage-any-petty-cash-categories', 'module' => 'petty-cash-categories', 'label' => 'Manage All Petty Cash Categories'],
            ['name' => 'manage-own-petty-cash-categories', 'module' => 'petty-cash-categories', 'label' => 'Manage Own Petty Cash Categories'],
            ['name' => 'create-petty-cash-categories', 'module' => 'petty-cash-categories', 'label' => 'Create Petty Cash Categories'],
            ['name' => 'edit-petty-cash-categories', 'module' => 'petty-cash-categories', 'label' => 'Edit Petty Cash Categories'],
            ['name' => 'delete-petty-cash-categories', 'module' => 'petty-cash-categories', 'label' => 'Delete Petty Cash Categories'],

            // PettyCash management
            ['name' => 'manage-petty-cashes', 'module' => 'petty-cashes', 'label' => 'Manage Petty Cashes'],
            ['name' => 'manage-any-petty-cashes', 'module' => 'petty-cashes', 'label' => 'Manage All Petty Cashes'],
            ['name' => 'manage-own-petty-cashes', 'module' => 'petty-cashes', 'label' => 'Manage Own Petty Cashes'],
            ['name' => 'view-petty-cashes', 'module' => 'petty-cashes', 'label' => 'View Petty Cashes'],
            ['name' => 'create-petty-cashes', 'module' => 'petty-cashes', 'label' => 'Create Petty Cashes'],
            ['name' => 'edit-petty-cashes', 'module' => 'petty-cashes', 'label' => 'Edit Petty Cashes'],
            ['name' => 'delete-petty-cashes', 'module' => 'petty-cashes', 'label' => 'Delete Petty Cashes'],
            ['name' => 'approve-petty-cashes', 'module' => 'petty-cashes', 'label' => 'Approve Petty Cashes'],

            // PettyCashRequest management
            ['name' => 'manage-petty-cash-requests', 'module' => 'petty-cash-requests', 'label' => 'Manage Petty Cash Requests'],
            ['name' => 'manage-any-petty-cash-requests', 'module' => 'petty-cash-requests', 'label' => 'Manage All Petty Cash Requests'],
            ['name' => 'manage-own-petty-cash-requests', 'module' => 'petty-cash-requests', 'label' => 'Manage Own Petty Cash Requests'],
            ['name' => 'view-petty-cash-requests', 'module' => 'petty-cash-requests', 'label' => 'View Petty Cash Requests'],
            ['name' => 'create-petty-cash-requests', 'module' => 'petty-cash-requests', 'label' => 'Create Petty Cash Requests'],
            ['name' => 'edit-petty-cash-requests', 'module' => 'petty-cash-requests', 'label' => 'Edit Petty Cash Requests'],
            ['name' => 'delete-petty-cash-requests', 'module' => 'petty-cash-requests', 'label' => 'Delete Petty Cash Requests'],
            ['name' => 'approve-petty-cash-requests', 'module' => 'petty-cash-requests', 'label' => 'Approve Petty Cash Requests'],

            // PettyCashExpense management
            ['name' => 'manage-petty-cash-expenses', 'module' => 'petty-cash-expenses', 'label' => 'Manage Petty Cash Expenses'],
            ['name' => 'manage-any-petty-cash-expenses', 'module' => 'petty-cash-expenses', 'label' => 'Manage All Petty Cash Expenses'],
            ['name' => 'manage-own-petty-cash-expenses', 'module' => 'petty-cash-expenses', 'label' => 'Manage Own Petty Cash Expenses'],
            ['name' => 'view-petty-cash-expenses', 'module' => 'petty-cash-expenses', 'label' => 'View Petty Cash Expenses'],

            // Reimbursement management
            ['name' => 'manage-reimbursements', 'module' => 'reimbursements', 'label' => 'Manage Reimbursements'],
            ['name' => 'manage-any-reimbursements', 'module' => 'reimbursements', 'label' => 'Manage All Reimbursements'],
            ['name' => 'manage-own-reimbursements', 'module' => 'reimbursements', 'label' => 'Manage Own Reimbursements'],
            ['name' => 'view-reimbursements', 'module' => 'reimbursements', 'label' => 'View Reimbursements'],
            ['name' => 'create-reimbursements', 'module' => 'reimbursements', 'label' => 'Create Reimbursements'],
            ['name' => 'edit-reimbursements', 'module' => 'reimbursements', 'label' => 'Edit Reimbursements'],
            ['name' => 'delete-reimbursements', 'module' => 'reimbursements', 'label' => 'Delete Reimbursements'],
            ['name' => 'approve-reimbursements', 'module' => 'reimbursements', 'label' => 'Approve Reimbursements'],
        ];

        $company_role = Role::where('name', 'company')->first();

        foreach ($permission as $perm) {
            $permission_obj = Permission::firstOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                [
                    'module' => $perm['module'],
                    'label' => $perm['label'],
                    'add_on' => 'PettyCashManagement',
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
