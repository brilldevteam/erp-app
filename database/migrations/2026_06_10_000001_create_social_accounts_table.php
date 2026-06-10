<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('provider_user_id');
            $table->string('provider_email')->nullable();
            $table->text('provider_avatar')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);
            $table->unique(['user_id', 'provider']);
        });

        if (Schema::hasTable('permissions') && Schema::hasTable('roles') && Schema::hasTable('role_has_permissions')) {
            $permissionId = DB::table('permissions')
                ->where('name', 'edit-social-login-settings')
                ->where('guard_name', 'web')
                ->value('id');

            if (!$permissionId) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'add_on' => 'general',
                    'module' => 'settings',
                    'label' => 'Edit Social Login Settings',
                    'name' => 'edit-social-login-settings',
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $superAdminRoleIds = DB::table('roles')
                ->where('name', 'superadmin')
                ->where('guard_name', 'web')
                ->pluck('id');

            foreach ($superAdminRoleIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }

            app('cache')
                ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
                ->forget(config('permission.cache.key'));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->where('name', 'edit-social-login-settings')
                ->where('guard_name', 'web')
                ->delete();
        }

        Schema::dropIfExists('social_accounts');
    }
};
