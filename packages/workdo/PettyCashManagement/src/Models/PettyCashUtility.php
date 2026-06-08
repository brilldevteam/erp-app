<?php

namespace Workdo\PettyCashManagement\Models;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PettyCashUtility
{
    public static function GivePermissionToRoles($role_id = null, $rolename = null)
    {
        $staff_permissions = [
            'manage-petty-cash-management',
            'manage-petty-cash-requests',
            'manage-own-petty-cash-requests',
            'view-petty-cash-requests',
            'create-petty-cash-requests',
            'edit-petty-cash-requests',
            'delete-petty-cash-requests',
            'manage-reimbursements',
            'manage-own-reimbursements',
            'view-reimbursements',
            'create-reimbursements',
            'edit-reimbursements',
            'delete-reimbursements',
        ];

        if ($rolename == 'staff') {
            $roles_v = Role::where('name', 'staff')->where('id', $role_id)->first();
            foreach ($staff_permissions as $permission_v) {
                $permission = Permission::where('name', $permission_v)->first();
                if (!empty($permission)) {
                    if (!$roles_v->hasPermissionTo($permission_v)) {
                        $roles_v->givePermissionTo($permission);
                    }
                }
            }
        }
    }
}