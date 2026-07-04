<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The founder's user-provisioning screen (/admin/users, Task 8). Every action
 * gates on `manage-users` (founder-only). Deactivation is a persistent lockout
 * (`deactivated_at`, added in Task 8) checked at login on both /v1/admin and
 * /v1/team — see AdminAuthTest/TeamAuthTest for the login-rejection coverage.
 */
class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(?User $founder = null): array
    {
        $founder ??= User::factory()->founder()->create();
        $token = $founder->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_founder_can_list_the_team_roster(): void
    {
        $founder = User::factory()->founder()->create();
        User::factory()->marketer()->create(['name' => 'Aisyah', 'monthly_allowance_myr' => 2500]);

        $response = $this->getJson('/api/v1/admin/users', $this->adminHeaders($founder));

        $response->assertOk();
        $names = collect($response->json())->pluck('name');
        $this->assertTrue($names->contains('Aisyah'));
        $this->assertTrue($names->contains($founder->name));

        $aisyah = collect($response->json())->firstWhere('name', 'Aisyah');
        $this->assertSame(2500, $aisyah['monthly_allowance_myr']);
        $this->assertArrayHasKey('availability', $aisyah);
        $this->assertArrayHasKey('deactivated_at', $aisyah);
        $this->assertNull($aisyah['deactivated_at']);
    }

    public function test_a_workspace_role_cannot_reach_the_users_endpoints(): void
    {
        $marketer = User::factory()->marketer()->create();
        $token = $marketer->createToken('team-spa', ['workspace'])->plainTextToken;

        // The role:cockpit middleware rejects a workspace token outright,
        // before the controller's manage-users Gate even runs.
        $this->getJson('/api/v1/admin/users', ['Authorization' => "Bearer {$token}"])
            ->assertForbidden();
    }

    public function test_founder_can_create_a_marketer_or_engineer(): void
    {
        foreach (['marketer', 'engineer'] as $role) {
            $response = $this->postJson('/api/v1/admin/users', [
                'name' => 'New Teammate',
                'email' => "new-{$role}@example.com",
                'password' => 'a-secure-password-123',
                'role' => $role,
                'monthly_allowance_myr' => 1800,
            ], $this->adminHeaders());

            $response->assertCreated()
                ->assertJsonPath('role', $role)
                ->assertJsonPath('monthly_allowance_myr', 1800);

            $this->assertDatabaseHas('users', ['email' => "new-{$role}@example.com", 'role' => $role]);
        }
    }

    public function test_the_backend_still_allows_a_founder_to_provision_another_founder(): void
    {
        // The role whitelist itself (User::WORKSPACE_ROLES) is unchanged by
        // Task 8 — the marketer|engineer-only restriction on /admin/users'
        // create form is a frontend decision, not a backend one.
        $response = $this->postJson('/api/v1/admin/users', [
            'name' => 'Second Founder',
            'email' => 'second-founder@example.com',
            'password' => 'a-secure-password-123',
            'role' => 'founder',
        ], $this->adminHeaders());

        $response->assertCreated()->assertJsonPath('role', 'founder');
    }

    public function test_create_rejects_a_role_outside_the_workspace_whitelist(): void
    {
        $this->postJson('/api/v1/admin/users', [
            'name' => 'Bad Role',
            'email' => 'bad-role@example.com',
            'password' => 'a-secure-password-123',
            'role' => 'partner',
        ], $this->adminHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_create_rejects_an_out_of_range_allowance(): void
    {
        $this->postJson('/api/v1/admin/users', [
            'name' => 'Negative Allowance',
            'email' => 'negative@example.com',
            'password' => 'a-secure-password-123',
            'role' => 'marketer',
            'monthly_allowance_myr' => -5,
        ], $this->adminHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['monthly_allowance_myr']);

        $this->postJson('/api/v1/admin/users', [
            'name' => 'Huge Allowance',
            'email' => 'huge@example.com',
            'password' => 'a-secure-password-123',
            'role' => 'marketer',
            'monthly_allowance_myr' => 5_000_000,
        ], $this->adminHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['monthly_allowance_myr']);
    }

    public function test_founder_can_update_name_role_and_allowance(): void
    {
        $member = User::factory()->engineer()->create(['name' => 'Old Name', 'monthly_allowance_myr' => 1000]);

        $response = $this->patchJson("/api/v1/admin/users/{$member->id}", [
            'name' => 'New Name',
            'role' => 'marketer',
            'monthly_allowance_myr' => 2200,
        ], $this->adminHeaders());

        $response->assertOk()
            ->assertJsonPath('name', 'New Name')
            ->assertJsonPath('role', 'marketer')
            ->assertJsonPath('monthly_allowance_myr', 2200);
    }

    public function test_update_can_clear_the_allowance_back_to_null(): void
    {
        $member = User::factory()->engineer()->create(['monthly_allowance_myr' => 1500]);

        $this->patchJson("/api/v1/admin/users/{$member->id}", [
            'monthly_allowance_myr' => null,
        ], $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('monthly_allowance_myr', null);

        $this->assertNull($member->fresh()->monthly_allowance_myr);
    }

    public function test_updating_the_allowance_alone_does_not_revoke_the_teammates_session(): void
    {
        $member = User::factory()->engineer()->create();
        $memberToken = $member->createToken('team-spa', ['workspace'])->plainTextToken;

        $this->patchJson("/api/v1/admin/users/{$member->id}", [
            'monthly_allowance_myr' => 3000,
        ], $this->adminHeaders())->assertOk();

        // The test harness caches the resolved guard user within a test (real
        // cross-request traffic never does) — see TeamSessionTest.
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/team/me', ['Authorization' => "Bearer {$memberToken}"])
            ->assertOk();
    }

    public function test_changing_a_teammates_role_revokes_their_existing_session(): void
    {
        $member = User::factory()->engineer()->create();
        $memberToken = $member->createToken('team-spa', ['workspace'])->plainTextToken;

        $this->patchJson("/api/v1/admin/users/{$member->id}", [
            'role' => 'marketer',
        ], $this->adminHeaders())->assertOk();

        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/team/me', ['Authorization' => "Bearer {$memberToken}"])
            ->assertUnauthorized();
    }

    public function test_demoting_the_last_founder_is_rejected(): void
    {
        $founder = User::factory()->founder()->create();

        $this->patchJson("/api/v1/admin/users/{$founder->id}", [
            'role' => 'marketer',
        ], $this->adminHeaders($founder))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot demote the last founder.');

        $this->assertSame('founder', $founder->fresh()->role);
    }

    public function test_founder_can_deactivate_a_teammate_and_it_revokes_their_tokens(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->marketer()->create();
        $memberToken = $member->createToken('team-spa', ['workspace'])->plainTextToken;

        $response = $this->postJson("/api/v1/admin/users/{$member->id}/deactivate", [], $this->adminHeaders($founder));

        $response->assertOk();
        $this->assertNotNull($response->json('deactivated_at'));
        $this->assertNotNull($member->fresh()->deactivated_at);
        $this->assertSame(0, $member->tokens()->count());

        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/team/me', ['Authorization' => "Bearer {$memberToken}"])
            ->assertUnauthorized();
    }

    public function test_a_founder_cannot_deactivate_their_own_account(): void
    {
        $founder = User::factory()->founder()->create();

        $this->postJson("/api/v1/admin/users/{$founder->id}/deactivate", [], $this->adminHeaders($founder))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'You cannot deactivate your own account.');

        $this->assertNull($founder->fresh()->deactivated_at);
    }

    public function test_deactivating_an_already_deactivated_teammate_is_rejected(): void
    {
        $member = User::factory()->marketer()->create(['deactivated_at' => now()]);

        $this->postJson("/api/v1/admin/users/{$member->id}/deactivate", [], $this->adminHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This teammate is already deactivated.');
    }

    public function test_deactivating_the_only_other_founder_still_leaves_the_platform_manageable(): void
    {
        // The platform can never end up with zero active founders through this
        // endpoint: self-deactivation is unconditionally blocked (above), and
        // manage-users requires the actor to already BE an active founder — so
        // deactivating a *different* founder always leaves the actor active.
        $founder = User::factory()->founder()->create();
        $otherFounder = User::factory()->founder()->create();

        $this->postJson("/api/v1/admin/users/{$otherFounder->id}/deactivate", [], $this->adminHeaders($founder))
            ->assertOk();

        $this->assertNotNull($otherFounder->fresh()->deactivated_at);
        $this->assertNull($founder->fresh()->deactivated_at);

        // The sole remaining active founder still cannot deactivate themselves.
        $this->postJson("/api/v1/admin/users/{$founder->id}/deactivate", [], $this->adminHeaders($founder))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'You cannot deactivate your own account.');
    }

    public function test_founder_can_reactivate_a_deactivated_teammate(): void
    {
        $member = User::factory()->marketer()->create(['deactivated_at' => now()]);

        $response = $this->postJson("/api/v1/admin/users/{$member->id}/reactivate", [], $this->adminHeaders());

        $response->assertOk()->assertJsonPath('deactivated_at', null);
        $this->assertNull($member->fresh()->deactivated_at);
    }

    public function test_reactivating_an_already_active_teammate_is_rejected(): void
    {
        $member = User::factory()->marketer()->create();

        $this->postJson("/api/v1/admin/users/{$member->id}/reactivate", [], $this->adminHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This teammate is already active.');
    }
}
