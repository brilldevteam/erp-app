<?php

namespace Tests\Feature\Auth;

use App\Data\SocialProviderUser;
use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\LoginHistoryService;
use App\Services\SocialAuthSettingsService;
use App\Services\SocialOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Mockery;
use Tests\TestCase;

class SocialAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_credentials_are_encrypted_and_not_exposed_in_public_status(): void
    {
        $admin = User::factory()->create(['type' => 'superadmin']);
        $service = app(SocialAuthSettingsService::class);

        $service->update([
            'social_login_enabled' => true,
            'google_login_enabled' => true,
            'google_client_id' => 'google-client-id',
            'google_client_secret' => 'google-client-secret',
            'microsoft_login_enabled' => false,
            'microsoft_client_id' => '',
            'microsoft_tenant_id' => 'common',
        ]);

        $stored = Setting::where('created_by', $admin->id)
            ->where('key', 'google_client_secret')
            ->firstOrFail();

        $this->assertNotSame('google-client-secret', $stored->value);
        $this->assertSame('google-client-secret', Crypt::decryptString($stored->value));
        $this->assertFalse((bool) $stored->is_public);
        $this->assertSame([
            'enabled' => true,
            'google' => ['enabled' => true],
            'microsoft' => ['enabled' => false],
        ], $service->publicStatus());
        $this->assertArrayNotHasKey('google_client_secret', $service->adminPayload());
    }

    public function test_blank_secret_submission_preserves_the_existing_secret(): void
    {
        User::factory()->create(['type' => 'superadmin']);
        $service = app(SocialAuthSettingsService::class);

        $service->update([
            'social_login_enabled' => true,
            'google_login_enabled' => true,
            'google_client_id' => 'google-client-id',
            'google_client_secret' => 'original-secret',
        ]);

        $service->update([
            'social_login_enabled' => true,
            'google_login_enabled' => true,
            'google_client_id' => 'google-client-id',
            'google_client_secret' => '',
        ]);

        $stored = Setting::where('key', 'google_client_secret')->value('value');

        $this->assertSame('original-secret', Crypt::decryptString($stored));
        $this->assertTrue($service->adminPayload()['google_client_secret_configured']);
    }

    public function test_social_login_does_not_create_an_unknown_user(): void
    {
        $oauth = Mockery::mock(SocialOAuthService::class);
        $oauth->shouldReceive('user')->once()->andReturn($this->providerUser('new@example.com'));
        $this->app->instance(SocialOAuthService::class, $oauth);

        $response = $this->withSession([
            'social_auth.provider' => 'google',
            'social_auth.intent' => 'login',
        ])->get('/auth/google/callback');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
        $this->assertGuest();
    }

    public function test_trusted_matching_email_is_linked_and_authenticated(): void
    {
        $user = User::factory()->create(['email' => 'member@example.com']);
        $oauth = Mockery::mock(SocialOAuthService::class);
        $oauth->shouldReceive('user')->once()->andReturn($this->providerUser('member@example.com'));
        $this->app->instance(SocialOAuthService::class, $oauth);

        $history = Mockery::mock(LoginHistoryService::class);
        $history->shouldReceive('record')->once()->with(Mockery::type('Illuminate\Http\Request'), $user);
        $this->app->instance(LoginHistoryService::class, $history);

        $response = $this->withSession([
            'social_auth.provider' => 'google',
            'social_auth.intent' => 'login',
        ])->get('/auth/google/callback');

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'provider-user-id',
        ]);
    }

    public function test_disabled_user_is_not_linked_or_authenticated(): void
    {
        $user = User::factory()->create([
            'email' => 'disabled@example.com',
            'is_disable' => 1,
        ]);
        $oauth = Mockery::mock(SocialOAuthService::class);
        $oauth->shouldReceive('user')->once()->andReturn($this->providerUser('disabled@example.com'));
        $this->app->instance(SocialOAuthService::class, $oauth);

        $response = $this->withSession([
            'social_auth.provider' => 'google',
            'social_auth.intent' => 'login',
        ])->get('/auth/google/callback');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
        $this->assertFalse(SocialAccount::where('user_id', $user->id)->exists());
        $this->assertGuest();
    }

    public function test_new_social_signup_creates_an_enabled_company_and_redirects_to_plans(): void
    {
        User::factory()->create(['type' => 'superadmin']);
        Setting::create([
            'key' => 'enableRegistration',
            'value' => 'on',
            'is_public' => true,
            'created_by' => User::where('type', 'superadmin')->value('id'),
        ]);

        $oauth = Mockery::mock(SocialOAuthService::class);
        $oauth->shouldReceive('user')->once()->andReturn($this->providerUser('new-company@example.com'));
        $this->app->instance(SocialOAuthService::class, $oauth);

        $history = Mockery::mock(LoginHistoryService::class);
        $history->shouldReceive('record')->once();
        $this->app->instance(LoginHistoryService::class, $history);

        $response = $this->withSession([
            'social_auth.provider' => 'google',
            'social_auth.intent' => 'signup',
            'social_auth.terms_accepted' => true,
        ])->get('/auth/google/callback');

        $user = User::where('email', 'new-company@example.com')->firstOrFail();

        $response->assertRedirect(route('plans.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('company', $user->type);
        $this->assertSame(0, (int) $user->is_disable);
        $this->assertSame(1, (int) $user->is_enable_login);
        $this->assertNull($user->password);
    }

    private function providerUser(string $email): SocialProviderUser
    {
        return new SocialProviderUser(
            'provider-user-id',
            'Provider User',
            $email,
            null,
            true
        );
    }
}
