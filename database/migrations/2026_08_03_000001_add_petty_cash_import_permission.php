<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'import-petty-cashes', 'guard_name' => 'web'],
            [
                'module' => 'petty-cashes',
                'label' => 'Import Petty Cashes',
                'add_on' => 'PettyCashManagement',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $permission->forceFill([
            'module' => 'petty-cashes',
            'label' => 'Import Petty Cashes',
            'add_on' => 'PettyCashManagement',
            'updated_at' => now(),
        ])->save();

        $companyRole = Role::where('name', 'company')->where('guard_name', 'web')->first();
        if ($companyRole && !$companyRole->hasPermissionTo($permission)) {
            $companyRole->givePermissionTo($permission);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permission = Permission::where('name', 'import-petty-cashes')->where('guard_name', 'web')->first();

        if ($permission) {
            DB::table('role_has_permissions')->where('permission_id', $permission->id)->delete();
            DB::table('model_has_permissions')->where('permission_id', $permission->id)->delete();
            $permission->delete();
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
