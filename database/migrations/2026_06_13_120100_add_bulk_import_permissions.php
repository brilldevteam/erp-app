<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $permissions = [
        ['name' => 'import-customers', 'module' => 'customers', 'label' => 'Import Customers', 'add_on' => 'Account'],
        ['name' => 'import-product-service-items', 'module' => 'product-service-item', 'label' => 'Import Product Service', 'add_on' => 'ProductService'],
    ];

    public function up(): void
    {
        foreach ($this->permissions as $permission) {
            $id = DB::table('permissions')
                ->where('name', $permission['name'])
                ->where('guard_name', 'web')
                ->value('id');

            if (!$id) {
                $id = DB::table('permissions')->insertGetId([
                    ...$permission,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach (DB::table('roles')->where('name', 'company')->pluck('id') as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $id,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')
            ->whereIn('name', array_column($this->permissions, 'name'))
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
