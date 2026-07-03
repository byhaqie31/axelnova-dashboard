<?php

namespace Tests\Feature\Auth;

use App\Models\Referrer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_partner_can_login_with_passcode(): void
    {
        $referrer = Referrer::factory()->create();

        $response = $this->postJson('/api/v1/partner/login', [
            'email' => $referrer->email,
            'passcode' => '12345678',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'partner']);

        $token = $referrer->tokens()->latest('id')->first();
        $this->assertSame('partner-portal', $token->name);
        $this->assertSame(['partner'], $token->abilities);
    }

    public function test_pending_partner_cannot_login(): void
    {
        $referrer = Referrer::factory()->pending()->create();

        $this->postJson('/api/v1/partner/login', [
            'email' => $referrer->email,
            'passcode' => '12345678',
        ])->assertUnprocessable();
    }

    public function test_wrong_passcode_is_rejected(): void
    {
        $referrer = Referrer::factory()->create();

        $this->postJson('/api/v1/partner/login', [
            'email' => $referrer->email,
            'passcode' => '00000000',
        ])->assertUnprocessable();
    }

    public function test_partner_token_reaches_partner_me(): void
    {
        $referrer = Referrer::factory()->create();
        $token = $referrer->createToken('partner-portal', ['partner'])->plainTextToken;

        $this->getJson('/api/v1/partner/me', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('email', $referrer->email);
    }

    public function test_partner_token_is_rejected_on_admin_and_team_surfaces(): void
    {
        $referrer = Referrer::factory()->create();
        $token = $referrer->createToken('partner-portal', ['partner'])->plainTextToken;

        $this->getJson('/api/v1/admin/me', ['Authorization' => "Bearer {$token}"])
            ->assertUnauthorized();
        $this->getJson('/api/v1/team/me', ['Authorization' => "Bearer {$token}"])
            ->assertUnauthorized();
    }

    public function test_user_token_is_rejected_on_the_partner_surface(): void
    {
        $founder = User::factory()->founder()->create();
        $token = $founder->createToken('admin-spa', ['cockpit'])->plainTextToken;

        $this->getJson('/api/v1/partner/me', ['Authorization' => "Bearer {$token}"])
            ->assertUnauthorized();
    }
}
