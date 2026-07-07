<?php

namespace Tests\Feature\Auth;

use App\Mail\PartnerPasscodeMail;
use App\Mail\PartnerResetRequestedMail;
use App\Models\ExternalAccount;
use App\Models\Investor;
use App\Models\Referrer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Task 9 — partner-portal auth on the unified `external` guard. Both partner
 * kinds (referrer + investor) authenticate against external_accounts; the old
 * `referral` guard on referral_partners is gone.
 */
class PartnerAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_referrer_can_login_via_external_account_and_last_login_is_stamped(): void
    {
        $referrer = Referrer::factory()->credentialed()->create();
        $account = $referrer->account;

        $this->assertNull($account->last_login_at);

        $response = $this->postJson('/api/v1/partner/login', [
            'email' => $account->email,
            'passcode' => '12345678',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'partner' => ['id', 'type', 'email', 'profile']])
            ->assertJsonPath('partner.type', 'referrer')
            ->assertJsonPath('partner.profile.code', $referrer->code);

        $this->assertNotNull($account->fresh()->last_login_at);

        $token = $account->tokens()->latest('id')->first();
        $this->assertSame('partner-portal', $token->name);
        $this->assertSame(['partner'], $token->abilities);
    }

    public function test_investor_can_login_via_external_account(): void
    {
        $investor = Investor::factory()->create();
        $account = $investor->account;

        $this->postJson('/api/v1/partner/login', [
            'email' => $account->email,
            'passcode' => '12345678',
        ])
            ->assertOk()
            ->assertJsonPath('partner.type', 'investor')
            ->assertJsonPath('partner.profile.name', $investor->name)
            ->assertJsonPath('partner.profile.company', $investor->company);
    }

    public function test_suspended_account_cannot_login(): void
    {
        $account = ExternalAccount::factory()->suspended()->create();

        $this->postJson('/api/v1/partner/login', [
            'email' => $account->email,
            'passcode' => '12345678',
        ])->assertUnprocessable();
    }

    public function test_referrer_without_an_account_cannot_login(): void
    {
        // Never credentialed — no external_accounts row exists at all.
        $referrer = Referrer::factory()->create();

        $this->postJson('/api/v1/partner/login', [
            'email' => $referrer->email,
            'passcode' => '12345678',
        ])->assertUnprocessable();
    }

    public function test_account_without_a_passcode_cannot_login(): void
    {
        $account = ExternalAccount::factory()->uncredentialed()->create();

        $this->postJson('/api/v1/partner/login', [
            'email' => $account->email,
            'passcode' => '12345678',
        ])->assertUnprocessable();
    }

    public function test_wrong_passcode_is_rejected(): void
    {
        $referrer = Referrer::factory()->credentialed()->create();

        $this->postJson('/api/v1/partner/login', [
            'email' => $referrer->account->email,
            'passcode' => '00000000',
        ])->assertUnprocessable();
    }

    public function test_referrer_token_reaches_me_and_dashboard(): void
    {
        $referrer = Referrer::factory()->credentialed()->create();
        $token = $referrer->account->createToken('partner-portal', ['partner'])->plainTextToken;

        $this->getJson('/api/v1/partner/me', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('type', 'referrer')
            ->assertJsonPath('email', $referrer->account->email)
            ->assertJsonPath('profile.relationship_tier', 'warm');

        $this->getJson('/api/v1/partner/dashboard', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('partner.code', $referrer->code);
    }

    public function test_dashboard_ref_link_uses_the_public_site_url_not_the_admin_origin(): void
    {
        // Admin cockpit on its own subdomain; the shareable ?ref link must point
        // at the PUBLIC site where visitors land, never the admin origin.
        config([
            'services.frontend.url' => 'https://admin.example.com',
            'services.frontend.public_url' => 'https://example.com',
        ]);

        $referrer = Referrer::factory()->credentialed()->create();
        $token = $referrer->account->createToken('partner-portal', ['partner'])->plainTextToken;

        $this->getJson('/api/v1/partner/dashboard', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('ref_link', "https://example.com/?ref={$referrer->code}");
    }

    public function test_investor_token_is_bounced_from_referrer_endpoints_but_reaches_me(): void
    {
        $investor = Investor::factory()->create();
        $token = $investor->account->createToken('partner-portal', ['partner'])->plainTextToken;

        // /me is shared — both types.
        $this->getJson('/api/v1/partner/me', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('type', 'investor');

        // Referrer-only endpoints 403 an investor token.
        $this->getJson('/api/v1/partner/dashboard', ['Authorization' => "Bearer {$token}"])
            ->assertForbidden();
        $this->postJson('/api/v1/partner/referrals', [
            'business_name' => 'Acme Sdn Bhd',
            'relationship_tier' => 'cold',
        ], ['Authorization' => "Bearer {$token}"])
            ->assertForbidden();
    }

    public function test_partner_token_is_rejected_on_admin_and_team_surfaces(): void
    {
        $referrer = Referrer::factory()->credentialed()->create();
        $token = $referrer->account->createToken('partner-portal', ['partner'])->plainTextToken;

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

    public function test_approve_creates_and_links_an_external_account(): void
    {
        Mail::fake();

        $founder = User::factory()->founder()->create();
        $adminToken = $founder->createToken('admin-spa', ['cockpit'])->plainTextToken;

        $referrer = Referrer::factory()->pending()->create();
        $this->assertNull($referrer->external_account_id);

        $this->postJson("/api/v1/admin/referral-partners/{$referrer->id}/approve", [], [
            'Authorization' => "Bearer {$adminToken}",
        ])->assertOk();

        $referrer->refresh();
        $this->assertSame('active', $referrer->status);
        $this->assertNotNull($referrer->external_account_id);

        $account = $referrer->account;
        $this->assertSame('referrer', $account->type);
        $this->assertSame($referrer->email, $account->email);
        $this->assertSame('active', $account->status);
        $this->assertNotNull($account->password);

        Mail::assertSent(PartnerPasscodeMail::class, fn ($mail) => $mail->account->is($account));
    }

    public function test_reset_passcode_updates_the_same_account_not_a_new_one(): void
    {
        Mail::fake();

        $founder = User::factory()->founder()->create();
        $adminToken = $founder->createToken('admin-spa', ['cockpit'])->plainTextToken;

        $referrer = Referrer::factory()->credentialed()->create();
        $accountId = $referrer->external_account_id;
        $oldHash = $referrer->account->password;

        $this->postJson("/api/v1/admin/referral-partners/{$referrer->id}/reset-passcode", [], [
            'Authorization' => "Bearer {$adminToken}",
        ])->assertOk();

        $referrer->refresh();
        $this->assertSame($accountId, $referrer->external_account_id);
        $this->assertSame(1, ExternalAccount::count());
        $this->assertNotSame($oldHash, $referrer->account->password);

        Mail::assertSent(PartnerPasscodeMail::class);
    }

    public function test_reset_passcode_creates_a_first_account_for_a_migrated_active_referrer(): void
    {
        Mail::fake();

        $founder = User::factory()->founder()->create();
        $adminToken = $founder->createToken('admin-spa', ['cockpit'])->plainTextToken;

        // Active but never credentialed (e.g. backfilled) — no account yet.
        $referrer = Referrer::factory()->create();
        $this->assertNull($referrer->external_account_id);

        $this->postJson("/api/v1/admin/referral-partners/{$referrer->id}/reset-passcode", [], [
            'Authorization' => "Bearer {$adminToken}",
        ])->assertOk();

        $referrer->refresh();
        $this->assertNotNull($referrer->external_account_id);
        $this->assertSame('referrer', $referrer->account->type);
    }

    public function test_forgot_passcode_reissues_for_an_active_account_and_notifies_the_founder(): void
    {
        Mail::fake();

        $referrer = Referrer::factory()->credentialed()->create();
        $oldHash = $referrer->account->password;

        $this->postJson('/api/v1/partner/forgot-passcode', [
            'email' => $referrer->account->email,
        ])->assertOk();

        $this->assertNotSame($oldHash, $referrer->account->fresh()->password);
        Mail::assertSent(PartnerPasscodeMail::class);
        Mail::assertSent(PartnerResetRequestedMail::class);
    }

    public function test_forgot_passcode_is_silent_for_unknown_or_suspended_emails(): void
    {
        Mail::fake();

        $suspended = ExternalAccount::factory()->suspended()->create();

        $this->postJson('/api/v1/partner/forgot-passcode', ['email' => 'nobody@example.com'])
            ->assertOk();
        $this->postJson('/api/v1/partner/forgot-passcode', ['email' => $suspended->email])
            ->assertOk();

        Mail::assertNothingSent();
    }

    public function test_old_referral_guard_is_gone(): void
    {
        $this->assertNull(config('auth.guards.referral'));
        $this->assertNull(config('auth.providers.referrers'));
        $this->assertSame('sanctum', config('auth.guards.external.driver'));
        $this->assertSame('external_accounts', config('auth.guards.external.provider'));
    }
}
