<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthSessionVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanctum_token_created_before_revocation_is_rejected(): void
    {
        $user = User::factory()->create(['security_version' => 1]);
        $token = $user->createToken('api-token');
        $token->accessToken->forceFill(['security_version' => 1])->save();

        $user->forceFill([
            'security_version' => 2,
            'security_revoked_reason' => 'LOGOUT_OTHER_DEVICES',
        ])->save();

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/user')
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'code' => 'SESSION_REVOKED',
                'reason' => 'LOGOUT_OTHER_DEVICES',
            ]);
    }
}
