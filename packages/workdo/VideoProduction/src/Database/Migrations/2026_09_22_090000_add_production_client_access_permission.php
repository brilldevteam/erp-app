<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => 'manage-video-production-client-access', 'guard_name' => 'web'],
            [
                'module' => 'video-production',
                'label' => 'Manage Production Client Access',
                'add_on' => 'VideoProduction',
            ]
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'manage-video-production-client-access')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
