<?php

namespace Tests\Feature\Auth;

use App\Mail\TeamPasswordResetRequestedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TeamAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_internal_role_can_login_to_the_workspace(): void
    {
        foreach (['founder', 'partner', 'marketer', 'engineer'] as $role) {
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
            ->assertJsonPath('email', $user->email);
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
