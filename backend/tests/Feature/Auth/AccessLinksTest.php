<?php

namespace Tests\Feature\Auth;

use App\Console\Commands\MintConnectorToken;
use App\Models\AccessToken;
use App\Models\ExternalAccount;
use App\Models\User;
use App\Services\Auth\AccessLinks;
use App\Services\Auth\InvalidAccessLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * One-time access links (Phase 2 spec §3.1): single use, expiring, hashed,
 * one live link per account and purpose, and redeeming signs the account
 * out everywhere.
 */
class AccessLinksTest extends TestCase
{
    use RefreshDatabase;

    private function links(): AccessLinks
    {
        return app(AccessLinks::class);
    }

    private function tokenFrom(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return (string) $query['token'];
    }

    public function test_a_team_setup_link_points_at_the_team_page_and_inspects_back(): void
    {
        $user = User::factory()->engineer()->create();

        $url = $this->links()->issue($user, AccessToken::SETUP);
        $token = $this->tokenFrom($url);

        $this->assertStringContainsString('/team/set-password?token=', $url);
        $this->assertSame(64, strlen($token));
        $link = $this->links()->inspect($token);
        $this->assertSame(AccessToken::SETUP, $link->purpose);
        $this->assertTrue($link->account->is($user));
        $this->assertSame('team', $link->surface);
    }

    public function test_the_token_is_stored_only_as_a_hash(): void
    {
        $token = $this->tokenFrom($this->links()->issue(User::factory()->engineer()->create(), AccessToken::RESET));

        $this->assertSame(0, AccessToken::where('token_hash', $token)->count());
        $this->assertSame(1, AccessToken::where('token_hash', hash('sha256', $token))->count());
    }

    public function test_partner_and_founder_links_point_at_their_own_pages(): void
    {
        $partnerUrl = $this->links()->issue(ExternalAccount::factory()->create(), AccessToken::SETUP);
        $founderUrl = $this->links()->issue(User::factory()->founder()->create(), AccessToken::RESET, 'admin');

        $this->assertStringContainsString('/partners/set-passcode?token=', $partnerUrl);
        $this->assertStringContainsString('/admin/reset-password?token=', $founderUrl);
    }

    public function test_setup_links_last_72_hours_and_reset_links_60_minutes(): void
    {
        $user = User::factory()->engineer()->create();
        $setup = $this->tokenFrom($this->links()->issue($user, AccessToken::SETUP));
        $reset = $this->tokenFrom($this->links()->issue($user, AccessToken::RESET));

        $this->travel(59)->minutes();
        $this->assertNotNull($this->links()->inspect($reset));
        $this->travel(2)->minutes();
        $this->assertNull($this->links()->inspect($reset));

        $this->travel(70)->hours(); // 71h01m after issue
        $this->assertNotNull($this->links()->inspect($setup));
        $this->travel(2)->hours();
        $this->assertNull($this->links()->inspect($setup));
    }

    public function test_a_new_link_replaces_the_earlier_unused_one_of_the_same_purpose(): void
    {
        $user = User::factory()->engineer()->create();
        $first = $this->tokenFrom($this->links()->issue($user, AccessToken::RESET));
        $second = $this->tokenFrom($this->links()->issue($user, AccessToken::RESET));

        $this->assertNull($this->links()->inspect($first));
        $this->assertNotNull($this->links()->inspect($second));
    }

    public function test_redeeming_sets_the_secret_signs_out_everywhere_and_works_once(): void
    {
        $user = User::factory()->engineer()->create();
        $user->createToken('team-spa', ['workspace']);
        $token = $this->tokenFrom($this->links()->issue($user, AccessToken::SETUP));

        $account = $this->links()->redeem($token, 'a-brand-new-passphrase');

        $user->refresh();
        $this->assertTrue($account->is($user));
        $this->assertTrue(Hash::check('a-brand-new-passphrase', $user->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertNotNull($user->password_set_at);
        $this->assertNull($this->links()->inspect($token));

        $this->expectException(InvalidAccessLink::class);
        $this->links()->redeem($token, 'another-passphrase-123');
    }

    public function test_a_partner_redeems_into_their_passcode(): void
    {
        $account = ExternalAccount::factory()->create();
        $account->createToken('partner-portal', ['partner']);
        $token = $this->tokenFrom($this->links()->issue($account, AccessToken::RESET));

        $this->links()->redeem($token, 'my-passcode-10');

        $this->assertTrue(Hash::check('my-passcode-10', $account->fresh()->password));
        $this->assertSame(0, $account->tokens()->count());
    }

    public function test_deactivated_teammates_and_suspended_partners_cannot_use_links(): void
    {
        $user = User::factory()->engineer()->create();
        $userToken = $this->tokenFrom($this->links()->issue($user, AccessToken::RESET));
        $user->forceFill(['deactivated_at' => now()])->save();

        $partner = ExternalAccount::factory()->create();
        $partnerToken = $this->tokenFrom($this->links()->issue($partner, AccessToken::RESET));
        $partner->forceFill(['status' => 'suspended'])->save();

        $this->assertNull($this->links()->inspect($userToken));
        $this->assertNull($this->links()->inspect($partnerToken));

        $this->expectException(InvalidAccessLink::class);
        $this->links()->redeem($userToken, 'whatever-passphrase');
    }

    public function test_a_secret_shorter_than_the_surface_minimum_is_refused(): void
    {
        $token = $this->tokenFrom($this->links()->issue(User::factory()->engineer()->create(), AccessToken::RESET));

        $this->expectException(\InvalidArgumentException::class);
        $this->links()->redeem($token, 'short');
    }

    public function test_unknown_tokens_inspect_to_nothing(): void
    {
        $this->assertNull($this->links()->inspect('not-a-real-token'));
        $this->assertNull($this->links()->inspect(''));
    }

    public function test_using_one_link_kills_the_accounts_other_live_links(): void
    {
        // Invited (set-up link T1), then used "forgot password" (reset link T2)
        // instead: once T2 sets the password, the old welcome link must be dead.
        $user = User::factory()->engineer()->create(['password_set_at' => null]);
        $setup = $this->tokenFrom($this->links()->issue($user, AccessToken::SETUP));
        $reset = $this->tokenFrom($this->links()->issue($user, AccessToken::RESET));

        $this->links()->redeem($reset, 'a-brand-new-password');

        $this->assertNull($this->links()->inspect($setup));
        $this->expectException(InvalidAccessLink::class);
        $this->links()->redeem($setup, 'someone-elses-password');
    }

    public function test_redeeming_keeps_the_founders_mcp_connector_token(): void
    {
        // The connector token is a machine credential minted from the VPS shell
        // (mcp:mint-token), not a session — a password reset must not cut Claude off.
        $founder = User::factory()->founder()->create();
        $founder->createToken('admin-spa', ['cockpit']);
        $founder->createToken(MintConnectorToken::TOKEN_NAME, ['connector:read', 'connector:draft']);
        $token = $this->tokenFrom($this->links()->issue($founder, AccessToken::RESET, 'admin'));

        $this->links()->redeem($token, 'a-brand-new-password');

        $this->assertSame([MintConnectorToken::TOKEN_NAME], $founder->tokens()->pluck('name')->all());
    }
}
