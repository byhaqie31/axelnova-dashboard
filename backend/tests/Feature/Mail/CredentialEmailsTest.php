<?php

namespace Tests\Feature\Mail;

use App\Mail\AdminAccountRecoveryMail;
use App\Mail\PartnerPasscodeChangedMail;
use App\Mail\PartnerPasscodeMail;
use App\Mail\PartnerResetLinkMail;
use App\Mail\TeamPasswordChangedMail;
use App\Mail\TeamPasswordResetMail;
use App\Mail\TeamWelcomeMail;
use App\Models\ExternalAccount;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Tests\TestCase;

/**
 * Phase 2: no email carries a password or passcode — set-up and reset emails
 * carry a one-time link and say how long it lasts; the owner's heads-ups
 * fire after a change and name who changed it.
 */
class CredentialEmailsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://axelnovaventures.com/team/set-password?token=abc123';

    private function text(Mailable $mail): string
    {
        return (string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($mail->render()), ENT_QUOTES | ENT_HTML5));
    }

    private function assertCarriesTheLinkAndNoSecret(Mailable $mail, string $button, string $lifetime): void
    {
        $html = $mail->render();
        $text = $this->text($mail);

        $this->assertStringContainsString(htmlspecialchars(self::URL), $html);
        $this->assertStringContainsString($button, $text);
        $this->assertStringContainsString($lifetime, $text);
        foreach (['Temporary password', 'Passcode:', '>Passcode<'] as $secretLabel) {
            $this->assertStringNotContainsString($secretLabel, $html);
        }
        $this->assertTrue($mail instanceof ShouldQueue, $mail::class.' must be queued');
    }

    public function test_team_welcome_carries_a_set_up_link(): void
    {
        $user = User::factory()->engineer()->create(['name' => 'Danial']);
        $mail = new TeamWelcomeMail($user, self::URL);

        $this->assertCarriesTheLinkAndNoSecret($mail, 'Set your password', '72 hours');
        $this->assertStringContainsString('Welcome to the team, Danial!', $this->text($mail));
        $this->assertStringContainsString("I'm genuinely glad to have you with us at Axel Nova Ventures.", $this->text($mail));
    }

    public function test_team_reset_carries_a_reset_link(): void
    {
        $mail = new TeamPasswordResetMail(User::factory()->engineer()->create(), self::URL);

        $this->assertCarriesTheLinkAndNoSecret($mail, 'Choose a new password', '60 minutes');
        $this->assertStringContainsString('Your current password keeps working', $this->text($mail));
        $this->assertSame('Reset your Axel Nova Ventures team password', $mail->envelope()->subject);
    }

    public function test_partner_access_carries_a_set_up_link_and_is_now_queued(): void
    {
        $mail = new PartnerPasscodeMail(ExternalAccount::factory()->create(), self::URL);

        $this->assertCarriesTheLinkAndNoSecret($mail, 'Set up your access', '72 hours');
    }

    public function test_partner_reset_carries_a_reset_link(): void
    {
        $mail = new PartnerResetLinkMail(ExternalAccount::factory()->create(), self::URL);

        $this->assertCarriesTheLinkAndNoSecret($mail, 'Choose a new passcode', '60 minutes');
        $this->assertStringContainsString('Your current passcode keeps working', $this->text($mail));
        $this->assertSame('partner.reset_confirm', PartnerResetLinkMail::emailType());
    }

    public function test_founder_recovery_carries_a_reset_link(): void
    {
        $mail = new AdminAccountRecoveryMail(User::factory()->founder()->create(), self::URL);

        $this->assertCarriesTheLinkAndNoSecret($mail, 'Reset password', '60 minutes');
        $this->assertSame('admin.account_recovery', AdminAccountRecoveryMail::emailType());
    }

    public function test_owner_heads_ups_report_a_completed_change(): void
    {
        $user = User::factory()->engineer()->create(['name' => 'Danial']);
        $team = new TeamPasswordChangedMail($user);
        $this->assertStringContainsString('Danial set a new password through a reset link.', $this->text($team));
        $this->assertSame('Team password changed — Danial', $team->envelope()->subject);

        $partner = new PartnerPasscodeChangedMail(ExternalAccount::factory()->create(['email' => 'p@example.com']));
        $this->assertStringContainsString('set a new passcode through a reset link.', $this->text($partner));
        $this->assertStringStartsWith('Partner passcode changed — ', $partner->envelope()->subject);
    }
}
