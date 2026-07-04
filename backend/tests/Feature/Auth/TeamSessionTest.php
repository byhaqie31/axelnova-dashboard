<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin → Team direct sign-in. A cockpit user exchanges their admin token for a
 * fresh workspace token instead of re-typing credentials on /team/login. The
 * exchange is the ONLY sanctioned bridge between the two surfaces — tokens
 * themselves stay non-replayable (see TokenAbilityTest).
 */
class TeamSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_cockpit_user_can_exchange_for_a_team_token(): void
    {
        $founder = User::factory()->founder()->create();
        $adminToken = $founder->createToken('admin-spa', ['cockpit'])->plainTextToken;

        $response = $this->postJson('/api/v1/admin/team-session', [], [
            'Authorization' => "Bearer {$adminToken}",
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role', 'tier']]);

        $minted = $founder->tokens()->latest('id')->first();
        $this->assertSame('team-spa', $minted->name);
        $this->assertSame(['workspace'], $minted->abilities);
    }

    public function test_the_exchanged_token_works_on_the_team_surface(): void
    {
        $founder = User::factory()->founder()->create();
        $adminToken = $founder->createToken('admin-spa', ['cockpit'])->plainTextToken;

        $teamToken = $this->postJson('/api/v1/admin/team-session', [], [
            'Authorization' => "Bearer {$adminToken}",
        ])->assertOk()->json('token');

        // Simulate a fresh HTTP request — the test harness caches the resolved
        // guard user within a test, which real cross-request traffic never does.
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/team/me', ['Authorization' => "Bearer {$teamToken}"])
            ->assertOk()
            ->assertJsonPath('email', $founder->email);
    }

    public function test_a_deactivated_founder_cannot_exchange_for_a_team_token(): void
    {
        // Defense-in-depth (Task 8): deactivation revokes every token, but if
        // one ever survived, the exchange must not mint around the lockout.
        $founder = User::factory()->founder()->create();
        $adminToken = $founder->createToken('admin-spa', ['cockpit'])->plainTextToken;
        $founder->forceFill(['deactivated_at' => now()])->save();

        $this->postJson('/api/v1/admin/team-session', [], [
            'Authorization' => "Bearer {$adminToken}",
        ])->assertForbidden();

        $this->assertSame(1, $founder->tokens()->count()); // no new token minted
    }

    public function test_a_team_token_cannot_mint_further_sessions(): void
    {
        $founder = User::factory()->founder()->create();
        $teamToken = $founder->createToken('team-spa', ['workspace'])->plainTextToken;

        $this->postJson('/api/v1/admin/team-session', [], [
            'Authorization' => "Bearer {$teamToken}",
        ])->assertForbidden();
    }

    public function test_the_exchange_lands_in_the_audit_trail(): void
    {
        $founder = User::factory()->founder()->create();
        $adminToken = $founder->createToken('admin-spa', ['cockpit'])->plainTextToken;

        $this->postJson('/api/v1/admin/team-session', [], [
            'Authorization' => "Bearer {$adminToken}",
        ])->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'actor_id' => $founder->id,
            'action' => 'team-session',
        ]);
    }
}
