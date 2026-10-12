<?php

namespace Tests\Feature\Mail;

use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/v1/admin/emails — the per-record "Emails" panel's feed (spec §5.9):
 * filter by a record (related_type + related_id) or by an address, newest
 * first, 25 a page, founder-only.
 */
class AdminEmailLogsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function founder(): array
    {
        $token = User::factory()->founder()->create()->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    private function log(array $attrs = []): EmailLog
    {
        return EmailLog::create($attrs + [
            'type' => 'invoice.sent', 'audience' => 'client', 'related_type' => 'invoice', 'related_id' => 7,
            'to_email' => 'client@example.com', 'subject' => 'Invoice', 'route' => 'resend', 'status' => 'sent',
            'sent_at' => now(), 'status_changed_at' => now(),
        ]);
    }

    public function test_it_lists_a_records_emails_newest_first(): void
    {
        $older = $this->log(['sent_at' => now()->subDay()]);
        $newer = $this->log(['status' => 'bounced', 'status_detail' => 'mailbox does not exist', 'provider_id' => 're_123']);
        $this->log(['related_id' => 8]);

        $res = $this->getJson('/api/v1/admin/emails?related_type=invoice&related_id=7', $this->founder())->assertOk();

        $this->assertSame([$newer->id, $older->id], array_column($res->json('data'), 'id'));
        $res->assertJsonPath('data.0.type_label', 'Invoice')
            ->assertJsonPath('data.0.status', 'bounced')
            ->assertJsonPath('data.0.status_label', 'Bounced')
            ->assertJsonPath('data.0.status_detail', 'mailbox does not exist')
            ->assertJsonPath('data.0.route', 'resend')
            ->assertJsonPath('data.0.resend_url', 'https://resend.com/emails/re_123')
            ->assertJsonPath('data.0.triggered_by', null)
            ->assertJsonPath('data.1.resend_url', null);
    }

    public function test_it_counts_problems_across_every_page(): void
    {
        $this->log(['status' => 'bounced']);
        $this->log(['status' => 'complained']);
        $this->log(['status' => 'failed', 'sent_at' => now()->subYear(), 'created_at' => now()->subYear()]);
        $this->log(['status' => 'bounced', 'related_id' => 8]);
        foreach (range(1, 25) as $i) {
            $this->log(['status' => 'delivered']);
        }

        // The oldest failure is off the first page, but the sidebar card still warns about it.
        $this->getJson('/api/v1/admin/emails?related_type=invoice&related_id=7', $this->founder())
            ->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 28)
            ->assertJsonPath('meta.problems', 3);
    }

    public function test_only_resend_routed_rows_link_to_resend(): void
    {
        $this->log(['route' => 'hostinger', 'provider_id' => 'queued']);

        $this->getJson('/api/v1/admin/emails?related_type=invoice&related_id=7', $this->founder())
            ->assertJsonPath('data.0.resend_url', null);
    }

    public function test_it_lists_everything_sent_to_an_address(): void
    {
        $this->log(['to_email' => 'aisyah@example.com', 'related_type' => 'quotation', 'related_id' => 1, 'type' => 'quotation.estimate']);
        $this->log(['to_email' => 'aisyah@example.com']);
        $this->log(['to_email' => 'someone@example.com']);

        $this->getJson('/api/v1/admin/emails?email=aisyah@example.com', $this->founder())
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_it_names_who_triggered_the_send(): void
    {
        $user = User::factory()->founder()->create(['name' => 'Qie']);
        $this->log(['triggered_by' => $user->id]);

        $this->getJson('/api/v1/admin/emails?related_type=invoice&related_id=7', $this->founder())
            ->assertJsonPath('data.0.triggered_by.name', 'Qie');
    }

    public function test_it_pages_by_25(): void
    {
        foreach (range(1, 27) as $i) {
            $this->log();
        }

        $this->getJson('/api/v1/admin/emails?related_type=invoice&related_id=7', $this->founder())
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 27);
    }

    public function test_it_needs_a_filter(): void
    {
        $this->getJson('/api/v1/admin/emails', $this->founder())->assertUnprocessable();
        $this->getJson('/api/v1/admin/emails?related_type=spaceship&related_id=1', $this->founder())->assertUnprocessable();
    }

    public function test_it_is_founder_only(): void
    {
        $this->getJson('/api/v1/admin/emails?email=a@example.com')->assertUnauthorized();

        $team = User::factory()->engineer()->create()->createToken('team-spa', ['workspace'])->plainTextToken;
        $this->getJson('/api/v1/admin/emails?email=a@example.com', ['Authorization' => "Bearer {$team}"])->assertForbidden();
    }
}
