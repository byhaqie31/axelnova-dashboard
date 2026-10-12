<?php

namespace Tests\Feature\Auth;

use App\Mail\AdminAccountRecoveryMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * POST /api/v1/admin/forgot-password — founder account recovery (Phase 2).
 * Only a founder account ever gets a link; every request gets the same answer.
 */
class AdminRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC = "If that email belongs to the admin account, we've sent it a link to reset the password.";

    public function test_the_founder_gets_an_admin_reset_link(): void
    {
        Mail::fake();
        $founder = User::factory()->founder()->create();

        $this->postJson('/api/v1/admin/forgot-password', ['email' => $founder->email])
            ->assertOk()->assertJsonPath('message', self::GENERIC);

        Mail::assertQueued(AdminAccountRecoveryMail::class, fn (AdminAccountRecoveryMail $mail) => $mail->hasTo($founder->email)
            && str_contains($mail->resetUrl, '/admin/reset-password?token='));
    }

    public function test_anyone_else_gets_the_same_answer_and_no_link(): void
    {
        Mail::fake();
        $engineer = User::factory()->engineer()->create();
        $deactivatedFounder = User::factory()->founder()->create(['deactivated_at' => now()]);

        foreach ([$engineer->email, $deactivatedFounder->email, 'nobody@example.com'] as $email) {
            $this->postJson('/api/v1/admin/forgot-password', ['email' => $email])
                ->assertOk()->assertJsonPath('message', self::GENERIC);
        }

        Mail::assertNothingOutgoing();
    }

    public function test_the_link_resets_the_founder_password_and_returns_to_admin_sign_in(): void
    {
        Mail::fake();
        $founder = User::factory()->founder()->create();
        $founder->createToken('admin-spa', ['cockpit']);
        $this->postJson('/api/v1/admin/forgot-password', ['email' => $founder->email]);

        $url = null;
        Mail::assertQueued(AdminAccountRecoveryMail::class, function (AdminAccountRecoveryMail $mail) use (&$url) {
            $url = $mail->resetUrl;

            return true;
        });
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        $this->postJson("/api/v1/auth/access-links/{$q['token']}", ['secret' => 'new-founder-pass-1', 'secret_confirmation' => 'new-founder-pass-1'])
            ->assertOk()
            ->assertJsonPath('login_url', rtrim((string) config('services.frontend.url'), '/').'/admin/login');

        $this->assertTrue(Hash::check('new-founder-pass-1', $founder->fresh()->password));
        $this->assertSame(0, $founder->tokens()->count());
    }

    public function test_recovery_sends_at_most_three_links_an_hour(): void
    {
        Mail::fake();
        $founder = User::factory()->founder()->create();

        foreach (range(1, 4) as $_) {
            $this->postJson('/api/v1/admin/forgot-password', ['email' => $founder->email])
                ->assertOk()->assertJsonPath('message', self::GENERIC);
        }

        Mail::assertQueued(AdminAccountRecoveryMail::class, 3);
    }
}
