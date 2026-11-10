<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'manage-helpdesk-tickets',
        'manage-own-helpdesk-tickets',
        'view-helpdesk-tickets',
        'create-helpdesk-tickets',
        'manage-helpdesk-replies',
        'create-helpdesk-replies',
    ];

    public function up(): void
    {
        $roleIds = DB::table('roles')->where('name', 'client')->where('guard_name', 'web')->pluck('id');
        $permissionIds = DB::table('permissions')->where('guard_name', 'web')->whereIn('name', self::PERMISSIONS)->pluck('id');

        $rows = [];
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                $rows[] = ['role_id' => $roleId, 'permission_id' => $permissionId];
            }
        }

        if ($rows) {
            DB::table('role_has_permissions')->insertOrIgnore($rows);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Keep access that may have been granted independently before this migration.
    }
};
