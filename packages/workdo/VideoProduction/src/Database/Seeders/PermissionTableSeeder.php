<?php

namespace Workdo\VideoProduction\Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionTableSeeder extends Seeder
{
    public function run()
    {
        Model::unguard();
        Artisan::call('cache:clear');

        $permission = [
            ['name' => 'manage-video-production', 'module' => 'video-production', 'label' => 'Manage Video Production'],
            ['name' => 'view-video-production', 'module' => 'video-production', 'label' => 'View Video Production'],
            ['name' => 'view-video-production-dashboard', 'module' => 'video-production', 'label' => 'View Video Production Dashboard'],
            ['name' => 'manage-video-production-client-access', 'module' => 'video-production', 'label' => 'Manage Production Client Access'],
            ['name' => 'manage-video-production-settings', 'module' => 'video-production-settings', 'label' => 'Manage Video Production Settings'],
        ];

        $company_role = Role::where('name', 'company')->first();

        foreach ($permission as $perm) {
            $permission_obj = Permission::firstOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                [
                    'module' => $perm['module'],
                    'label' => $perm['label'],
                    'add_on' => 'VideoProduction',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            if (
                $perm['name'] !== 'manage-video-production-client-access'
                && $company_role
                && ! $company_role->hasPermissionTo($permission_obj)
            ) {
                $company_role->givePermissionTo($permission_obj);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
