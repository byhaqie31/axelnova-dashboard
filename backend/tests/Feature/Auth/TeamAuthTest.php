<?php

namespace Tests\Feature\Auth;

use App\Mail\TeamPasswordResetRequestedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TeamAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_internal_role_can_login_to_the_workspace(): void
    {
        foreach (['founder', 'marketer', 'engineer'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $response = $this->postJson('/api/v1/team/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

            $response->assertOk()->assertJsonStructure(['token', 'user']);

            $token = $user->tokens()->latest('id')->first();
            $this->assertSame('team-spa', $token->name);
            $this->assertSame(['workspace'], $token->abilities);
        }
    }

    public function test_a_deactivated_teammate_cannot_login(): void
    {
        // Task 8 — /admin/users deactivation is a persistent lockout
        // (`deactivated_at`), not just a signed-out session.
        $user = User::factory()->marketer()->create(['deactivated_at' => now()]);

        $this->postJson('/api/v1/team/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertUnprocessable();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_unknown_email_is_rejected(): void
    {
        $this->postJson('/api/v1/team/login', [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ])->assertUnprocessable();
    }

    public function test_team_token_reaches_team_me(): void
    {
        $user = User::factory()->marketer()->create();
        $token = $user->createToken('team-spa', ['workspace'])->plainTextToken;

        $this->getJson('/api/v1/team/me', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('email', $user->email)
            ->assertJsonPath('availability', 'available');
    }

    public function test_a_team_member_can_update_their_own_profile(): void
    {
        $user = User::factory()->engineer()->create(['name' => 'Original Name']);
        $token = $user->createToken('team-spa', ['workspace'])->plainTextToken;

        $this->patchJson('/api/v1/team/me', [
            'name' => 'Updated Name',
            'availability' => 'busy',
        ], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('name', 'Updated Name')
            ->assertJsonPath('availability', 'busy');

        $this->assertSame('Updated Name', $user->fresh()->name);
        $this->assertSame('busy', $user->fresh()->availability);
    }

    public function test_updating_the_profile_rejects_an_invalid_availability_value(): void
    {
        $user = User::factory()->marketer()->create();
        $token = $user->createToken('team-spa', ['workspace'])->plainTextToken;

        $this->patchJson('/api/v1/team/me', [
            'availability' => 'on-holiday',
        ], ['Authorization' => "Bearer {$token}"])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['availability']);

        $this->assertSame('available', $user->fresh()->availability);
    }

    public function test_updating_the_profile_requires_a_workspace_token(): void
    {
        $this->patchJson('/api/v1/team/me', ['availability' => 'busy'])
            ->assertUnauthorized();
    }

    public function test_the_removed_operational_team_routes_are_gone(): void
    {
        $user = User::factory()->marketer()->create();
        $token = $user->createToken('team-spa', ['workspace'])->plainTextToken;
        $headers = ['Authorization' => "Bearer {$token}"];

        // Task 4 dropped inquiry triage, the referral programme, and marketing
        // spend entry from the /team surface — the team no longer touches
        // admin-owned operational data. Routes are gone outright (404), not
        // merely forbidden.
        $this->getJson('/api/v1/team/inquiries', $headers)->assertNotFound();
        $this->getJson('/api/v1/team/referrals', $headers)->assertNotFound();
        $this->getJson('/api/v1/team/marketing-expenses', $headers)->assertNotFound();

        $this->assertFalse(Route::has('team.inquiries.index'));
        $this->assertFalse(Route::has('team.referrals.index'));
        $this->assertFalse(Route::has('team.marketing-expenses.index'));
    }

    public function test_forgot_password_notifies_the_admin_for_a_known_account(): void
    {
        Mail::fake();
        $user = User::factory()->marketer()->create();

        $this->postJson('/api/v1/team/forgot-password', ['email' => $user->email])
            ->assertOk();

        Mail::assertSent(TeamPasswordResetRequestedMail::class);
        $this->assertDatabaseHas('activity_log', [
            'action' => 'team.password_reset_requested',
            'subject_id' => $user->id,
        ]);
    }

    public function test_forgot_password_reveals_nothing_for_an_unknown_email(): void
    {
        Mail::fake();

        $known = $this->postJson('/api/v1/team/forgot-password', ['email' => 'ghost@example.com']);

        $known->assertOk();
        Mail::assertNothingSent();
    }
}
