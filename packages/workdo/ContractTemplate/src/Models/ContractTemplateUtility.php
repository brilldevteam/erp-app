<?php

namespace Workdo\ContractTemplate\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ContractTemplateUtility extends Model
{
    public static function givePermissionToRoles($role_id = null, $rolename = null)
    {
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

        if ($rolename == 'staff') {
            $role = Role::where('name', 'staff')->where('id', $role_id)->first();
            if ($role) {
                foreach ($staff_permissions as $permission_name) {
                    $permission = Permission::where('name', $permission_name)->first();
                    if ($permission && !$role->hasPermissionTo($permission_name)) {
                        $role->givePermissionTo($permission);
                    }
                }
            }
        }

        if ($rolename == 'client') {
            $role = Role::where('name', 'client')->where('id', $role_id)->first();
            if ($role) {
                foreach ($client_permissions as $permission_name) {
                    $permission = Permission::where('name', $permission_name)->first();
                    if ($permission && !$role->hasPermissionTo($permission_name)) {
                        $role->givePermissionTo($permission);
                    }
                }
            }
        }
    }
}
