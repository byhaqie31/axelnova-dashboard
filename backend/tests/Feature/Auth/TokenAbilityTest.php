<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cross-surface token isolation. Each surface mints tokens with its own ability
 * (cockpit / workspace / partner) and every surface's route group must demand
 * that ability — otherwise a cockpit user's *team* token replays fine against
 * /v1/admin/* because the role middleware only inspects the user's role.
 */
class TokenAbilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_team_token_cannot_reach_admin_routes_even_for_a_cockpit_user(): void
    {
        $founder = User::factory()->founder()->create();

        $teamToken = $this->postJson('/api/v1/team/login', [
            'email' => $founder->email,
            'password' => 'password',
        ])->assertOk()->json('token');

        $this->getJson('/api/v1/admin/me', ['Authorization' => "Bearer {$teamToken}"])
            ->assertForbidden();
    }

    public function test_an_admin_token_cannot_reach_team_routes(): void
    {
        $founder = User::factory()->founder()->create();

        $adminToken = $this->postJson('/api/v1/admin/login', [
            'email' => $founder->email,
            'password' => 'password',
        ])->assertOk()->json('token');

        $this->getJson('/api/v1/team/me', ['Authorization' => "Bearer {$adminToken}"])
            ->assertForbidden();
    }

    // NB: one authenticated request per test — Laravel's test harness reuses the
    // sanctum guard instance across in-test requests, so a second bearer token
    // would be ignored in favour of the first request's cached user.

    public function test_a_minted_admin_token_works_on_the_admin_surface(): void
    {
        $founder = User::factory()->founder()->create();

        $adminToken = $this->postJson('/api/v1/admin/login', [
            'email' => $founder->email, 'password' => 'password',
        ])->assertOk()->json('token');

        $this->getJson('/api/v1/admin/me', ['Authorization' => "Bearer {$adminToken}"])->assertOk();
    }

    public function test_a_minted_team_token_works_on_the_team_surface(): void
    {
        $founder = User::factory()->founder()->create();

        $teamToken = $this->postJson('/api/v1/team/login', [
            'email' => $founder->email, 'password' => 'password',
        ])->assertOk()->json('token');

        $this->getJson('/api/v1/team/me', ['Authorization' => "Bearer {$teamToken}"])->assertOk();
    }
}
