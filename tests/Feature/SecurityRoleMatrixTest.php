<?php

namespace Tests\Feature;

use App\Models\User;
use App\Rules\TenantAccountingCustomer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SecurityRoleMatrixTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_all_supported_roles_remain_tenant_scoped_and_only_accounting_customers_are_selectable(): void
    {
        $tenant = User::factory()->create(['type' => 'company', 'created_by' => null]);
        $otherTenant = User::factory()->create(['type' => 'company', 'created_by' => null]);

        $users = [
            'superadmin' => User::factory()->create(['type' => 'superadmin', 'created_by' => null]),
            'company admin' => $tenant,
            'staff' => User::factory()->create(['type' => 'staff', 'created_by' => $tenant->id]),
            'customer' => User::factory()->create(['type' => 'client', 'created_by' => $tenant->id]),
            'vendor' => User::factory()->create(['type' => 'vendor', 'created_by' => $tenant->id]),
            'production client' => User::factory()->create(['type' => 'client', 'created_by' => $tenant->id]),
        ];

        $productionRole = Role::query()->firstOrCreate(
            ['name' => 'production-client', 'guard_name' => 'web'],
            ['label' => 'Production Client', 'editable' => false]
        );
        $users['production client']->assignRole($productionRole);

        $foreignCustomer = User::factory()->create(['type' => 'client', 'created_by' => $otherTenant->id]);
        $selectableIds = User::query()->accountingCustomers($tenant->id)->pluck('id');

        $this->assertTrue($selectableIds->contains($users['customer']->id));
        $this->assertFalse($selectableIds->contains($users['production client']->id));
        $this->assertFalse($selectableIds->contains($users['vendor']->id));
        $this->assertFalse($selectableIds->contains($foreignCustomer->id));

        foreach ($users as $role => $user) {
            $this->assertSame(
                $role === 'customer',
                $this->customerRulePasses($tenant->id, $user->id),
                "Unexpected accounting-customer access for {$role}."
            );
        }

        $this->assertFalse($this->customerRulePasses($tenant->id, $foreignCustomer->id));
    }

    private function customerRulePasses(int $tenantId, int $userId): bool
    {
        return Validator::make(
            ['customer_id' => $userId],
            ['customer_id' => [new TenantAccountingCustomer($tenantId)]]
        )->passes();
    }
}
