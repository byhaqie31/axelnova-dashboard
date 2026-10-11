<?php

namespace Tests\Feature\Auth;

use App\Models\AccessToken;
use App\Models\ExternalAccount;
use App\Models\User;
use App\Services\Auth\AccessLinks;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * GET/POST /api/v1/auth/access-links/{token} — the one endpoint pair behind
 * the team, partner and admin "set password" pages (Phase 2 spec §3.2).
 */
class AccessLinkEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function token(Model $account, string $purpose, ?string $surface = null): string
    {
        parse_str((string) parse_url(app(AccessLinks::class)->issue($account, $purpose, $surface), PHP_URL_QUERY), $q);

        return (string) $q['token'];
    }

    public function test_it_describes_a_live_link(): void
    {
        $user = User::factory()->engineer()->create(['name' => 'Danial', 'email' => 'danial@example.com']);
        $token = $this->token($user, AccessToken::SETUP);

        $this->getJson("/api/v1/auth/access-links/{$token}")
            ->assertOk()
            ->assertExactJson([
                'purpose' => 'setup',
                'surface' => 'team',
                'name' => 'Danial',
                'email' => 'danial@example.com',
                'min_length' => 12,
            ]);
    }

    public function test_a_partner_link_asks_for_a_ten_character_passcode(): void
    {
        $token = $this->token(ExternalAccount::factory()->create(), AccessToken::RESET);

        $this->getJson("/api/v1/auth/access-links/{$token}")
            ->assertOk()
            ->assertJsonPath('surface', 'partner')
            ->assertJsonPath('min_length', 10);
    }

    public function test_unknown_expired_and_used_links_get_one_generic_gone(): void
    {
        $user = User::factory()->engineer()->create();
        $expired = $this->token($user, AccessToken::RESET);
        $this->travel(61)->minutes();

        foreach (['not-a-token', $expired] as $token) {
            $this->getJson("/api/v1/auth/access-links/{$token}")
                ->assertStatus(410)
                ->assertJsonPath('message', 'This link has expired or has already been used.');
        }
    }

    public function test_setting_the_password_works_once_and_points_to_sign_in(): void
    {
        $user = User::factory()->engineer()->create();
        $token = $this->token($user, AccessToken::SETUP);

        $this->postJson("/api/v1/auth/access-links/{$token}", ['secret' => 'a-long-new-passphrase', 'secret_confirmation' => 'a-long-new-passphrase'])
            ->assertOk()
            ->assertJsonPath('login_url', rtrim((string) config('services.frontend.public_url'), '/').'/team/login');

        $this->assertTrue(Hash::check('a-long-new-passphrase', $user->fresh()->password));

        $this->postJson("/api/v1/auth/access-links/{$token}", ['secret' => 'another-long-passphrase', 'secret_confirmation' => 'another-long-passphrase'])
            ->assertStatus(410);
    }

    public function test_the_secret_must_meet_the_minimum_and_be_confirmed(): void
    {
        $token = $this->token(User::factory()->engineer()->create(), AccessToken::RESET);

        $this->postJson("/api/v1/auth/access-links/{$token}", ['secret' => 'short', 'secret_confirmation' => 'short'])
            ->assertUnprocessable()->assertJsonValidationErrors('secret');
        $this->postJson("/api/v1/auth/access-links/{$token}", ['secret' => 'a-long-new-passphrase', 'secret_confirmation' => 'different-passphrase'])
            ->assertUnprocessable()->assertJsonValidationErrors('secret');

        // A failed attempt doesn't burn the link.
        $this->getJson("/api/v1/auth/access-links/{$token}")->assertOk();
    }

    public function test_a_founder_recovery_link_returns_to_the_admin_sign_in(): void
    {
        $token = $this->token(User::factory()->founder()->create(), AccessToken::RESET, 'admin');

        $this->postJson("/api/v1/auth/access-links/{$token}", ['secret' => 'founder-passphrase-1', 'secret_confirmation' => 'founder-passphrase-1'])
            ->assertOk()
            ->assertJsonPath('login_url', rtrim((string) config('services.frontend.url'), '/').'/admin/login');
    }
}
