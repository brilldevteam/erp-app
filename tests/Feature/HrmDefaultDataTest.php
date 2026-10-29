<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Hrm\Models\HrmUtility;

class HrmDefaultDataTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hr_permissions_can_be_initialized_repeatedly_without_duplicate_links(): void
    {
        $company = User::factory()->create(['type' => 'company']);

        HrmUtility::defaultdata($company->id);
        $role = Role::where('name', 'hr')->where('created_by', $company->id)->firstOrFail();
        $permissionCount = $role->permissions()->count();

        HrmUtility::defaultdata($company->id);

        $this->assertGreaterThan(0, $permissionCount);
        $this->assertSame($permissionCount, $role->permissions()->count());
        $this->assertSame(
            $permissionCount,
            $role->permissions()->distinct()->count('permissions.id'),
        );
    }
}
