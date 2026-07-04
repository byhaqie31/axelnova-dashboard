<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_founder_can_login_and_receives_cockpit_token(): void
    {
        $user = User::factory()->founder()->create();

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role', 'tier']])
            ->assertJsonPath('user.tier', 'cockpit');

        $token = $user->tokens()->latest('id')->first();
        $this->assertSame('admin-spa', $token->name);
        $this->assertSame(['cockpit'], $token->abilities);
    }

    public function test_partner_is_no_longer_a_valid_role(): void
    {
        // The `partner` RBAC role was dropped (users.role enum narrowed to
        // founder/marketer/engineer) — the DB now rejects it outright.
        $this->expectException(QueryException::class);

        User::factory()->create(['role' => 'partner']);
    }

    public function test_workspace_roles_cannot_login_to_cockpit(): void
    {
        foreach (['marketer', 'engineer'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->postJson('/api/v1/admin/login', [
                'email' => $user->email,
                'password' => 'password',
            ])->assertUnprocessable();
        }
    }

    public function test_a_deactivated_founder_cannot_login(): void
    {
        // Task 8 — /admin/users deactivation is a persistent lockout
        // (`deactivated_at`), not just a signed-out session.
        $user = User::factory()->founder()->create(['deactivated_at' => now()]);

        $this->postJson('/api/v1/admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertUnprocessable();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->founder()->create();

        $this->postJson('/api/v1/admin/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    public function test_admin_token_reaches_admin_me(): void
    {
        $user = User::factory()->founder()->create();
        $token = $user->createToken('admin-spa', ['cockpit'])->plainTextToken;

        $this->getJson('/api/v1/admin/me', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/admin/me')->assertUnauthorized();
    }

    public function test_workspace_role_token_cannot_reach_admin_routes(): void
    {
        $marketer = User::factory()->marketer()->create();
        $token = $marketer->createToken('team-spa', ['workspace'])->plainTextToken;

        $this->getJson('/api/v1/admin/me', ['Authorization' => "Bearer {$token}"])
            ->assertForbidden();
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->founder()->create();
        $token = $user->createToken('admin-spa', ['cockpit'])->plainTextToken;

        $this->postJson('/api/v1/admin/logout', [], ['Authorization' => "Bearer {$token}"])
            ->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }
}
