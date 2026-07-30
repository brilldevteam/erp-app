<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\AuthSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ForceLogoutOtherDevicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'auth.session.current'])
            ->get('/_test/protected-session', fn () => response()->json(['ok' => true]));
    }

    public function test_password_change_with_checkbox_revokes_other_database_sessions(): void
    {
        config(['session.driver' => 'database']);

        $user = $this->userWithPasswordPermission();

        DB::table('sessions')->insert([
            'id' => 'other-session-id',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.2',
            'user_agent' => 'Test Browser',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($user)
            ->withSession([AuthSessionService::SESSION_KEY => 1])
            ->from('/security')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'New-password1!',
                'password_confirmation' => 'New-password1!',
                'logout_other_devices' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/security');

        $this->assertSame(2, (int) $user->refresh()->security_version);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session-id']);
    }

    public function test_password_change_without_checkbox_does_not_revoke_other_sessions(): void
    {
        config(['session.driver' => 'database']);

        $user = $this->userWithPasswordPermission();

        DB::table('sessions')->insert([
            'id' => 'other-session-id',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.2',
            'user_agent' => 'Test Browser',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($user)
            ->withSession([AuthSessionService::SESSION_KEY => 1])
            ->from('/security')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'New-password1!',
                'password_confirmation' => 'New-password1!',
                'logout_other_devices' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/security');

        $this->assertSame(1, (int) $user->refresh()->security_version);
        $this->assertDatabaseHas('sessions', ['id' => 'other-session-id']);
    }

    public function test_revoked_web_session_gets_session_revoked_response(): void
    {
        $user = User::factory()->create(['security_version' => 2]);

        $this->actingAs($user)
            ->withSession([AuthSessionService::SESSION_KEY => 1])
            ->getJson('/_test/protected-session')
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'code' => 'SESSION_REVOKED',
            ]);
    }

    public function test_incorrect_current_password_does_not_revoke_sessions(): void
    {
        $user = $this->userWithPasswordPermission();

        $this->actingAs($user)
            ->withSession([AuthSessionService::SESSION_KEY => 1])
            ->from('/security')
            ->post('/security/logout-other-sessions', [
                'current_password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/security');

        $this->assertSame(1, (int) $user->refresh()->security_version);
    }

    private function userWithPasswordPermission(): User
    {
        Permission::findOrCreate('change-password-profile');

        $user = User::factory()->create(['security_version' => 1]);
        $user->givePermissionTo('change-password-profile');

        return $user;
    }
}
