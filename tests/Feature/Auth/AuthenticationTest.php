<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_enabled_production_client_assigned_to_a_production_project_can_authenticate(): void
    {
        [$client, $projectId] = $this->createProductionClient();

        DB::table('project_clients')->insert([
            'project_id' => $projectId,
            'client_id' => $client->id,
        ]);

        $response = $this->post('/login', [
            'email' => $client->email,
            'password' => 'Password1!',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($client);
    }

    public function test_unassigned_production_client_cannot_authenticate(): void
    {
        [$client] = $this->createProductionClient();

        $response = $this->post('/login', [
            'email' => $client->email,
            'password' => 'Password1!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_disabled_production_client_cannot_authenticate(): void
    {
        [$client, $projectId] = $this->createProductionClient([
            'is_enable_login' => false,
            'is_disable' => true,
        ]);

        DB::table('project_clients')->insert([
            'project_id' => $projectId,
            'client_id' => $client->id,
        ]);

        $response = $this->post('/login', [
            'email' => $client->email,
            'password' => 'Password1!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    private function createProductionClient(array $overrides = []): array
    {
        $company = User::factory()->create(['type' => 'company']);
        $client = User::factory()->create(array_merge([
            'type' => 'client',
            'password' => 'Password1!',
            'is_enable_login' => true,
            'is_disable' => false,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ], $overrides));

        $role = new Role();
        $role->name = 'production-client';
        $role->guard_name = 'web';
        $role->editable = false;
        $role->created_by = $company->id;
        $role->save();
        $client->assignRole($role);

        $projectId = DB::table('projects')->insertGetId([
            'name' => 'Production Portal Test',
            'category' => 'production',
            'status' => 'Ongoing',
            'creator_id' => $company->id,
            'created_by' => $company->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$client, $projectId];
    }
}
