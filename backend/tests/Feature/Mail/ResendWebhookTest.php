<?php

namespace Tests\Feature\Mail;

use App\Models\EmailLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Resend's delivery reports advance the email log (spec §5.5): signed with
 * Svix, matched by our Message-ID → Resend's email id → recipient + subject
 * + time, applied forward-only. Unknown emails and event types are accepted
 * and ignored, so Resend never retries them forever.
 */
class ResendWebhookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A throwaway Svix-format secret, assembled at runtime so no literal
     * `whsec_…` value sits in the repo (GitHub secret scanning reads that
     * prefix as a Stripe webhook secret).
     */
    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->secret = 'whsec_'.base64_encode('test-signing-secret-123456789');
        config(['services.resend.webhook_secret' => $this->secret]);
        Carbon::setTestNow('2026-10-12 09:00:00');
    }

    private function log(array $attrs = []): EmailLog
    {
        return EmailLog::create($attrs + [
            'type' => 'invoice.sent', 'audience' => 'client', 'to_email' => 'client@example.com',
            'subject' => 'Invoice AXNI-2026-0031 from Axel Nova Ventures', 'route' => 'resend',
            'status' => 'sent', 'message_id' => 'abc-123@axelnova.tech',
            'sent_at' => now()->subMinute(), 'status_changed_at' => now()->subMinute(),
        ]);
    }

    /** @return array<string, string> */
    private function headers(string $body, ?int $timestamp = null, ?string $secret = null): array
    {
        $id = 'msg_'.md5($body);
        $timestamp ??= now()->getTimestamp();
        $key = base64_decode(substr($secret ?? $this->secret, 6));
        $signature = base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", $key, true));

        return [
            'svix-id' => $id,
            'svix-timestamp' => (string) $timestamp,
            'svix-signature' => "v1,bm90LXRoaXMtb25l v1,{$signature}",
            'Content-Type' => 'application/json',
        ];
    }

    private function deliver(string $type, array $data, array $headerOverrides = []): TestResponse
    {
        // Resend sends UTC (Zulu) timestamps; the app and the DB run on Malaysia time.
        $body = json_encode(['type' => $type, 'created_at' => now()->utc()->toIso8601ZuluString(), 'data' => $data + [
            'email_id' => 're_4ef9a417',
            'to' => ['client@example.com'],
            'subject' => 'Invoice AXNI-2026-0031 from Axel Nova Ventures',
            'created_at' => now()->subMinute()->utc()->toIso8601ZuluString(),
        ]]);

        return $this->call('POST', '/api/v1/webhooks/resend', [], [], [], $this->transformHeadersToServerVars(
            $headerOverrides + $this->headers($body)
        ), $body);
    }

    public function test_a_signed_delivery_report_advances_the_row_and_stores_resends_id(): void
    {
        $log = $this->log();

        $this->deliver('email.delivered', ['message_id' => '<abc-123@axelnova.tech>'])->assertOk();

        $log->refresh();
        $this->assertSame('delivered', $log->status);
        $this->assertSame('re_4ef9a417', $log->provider_id);
    }

    public function test_a_bad_signature_is_rejected(): void
    {
        $log = $this->log();
        $body = json_encode(['type' => 'email.delivered', 'data' => ['message_id' => 'abc-123@axelnova.tech']]);
        $headers = $this->headers($body, secret: 'whsec_'.base64_encode('someone-else'));

        $this->call('POST', '/api/v1/webhooks/resend', [], [], [], $this->transformHeadersToServerVars($headers), $body)
            ->assertUnauthorized();

        $this->assertSame('sent', $log->fresh()->status);
    }

    public function test_a_stale_timestamp_is_rejected(): void
    {
        $this->log();
        $body = json_encode(['type' => 'email.delivered', 'data' => ['message_id' => 'abc-123@axelnova.tech']]);
        $headers = $this->headers($body, now()->subMinutes(6)->getTimestamp());

        $this->call('POST', '/api/v1/webhooks/resend', [], [], [], $this->transformHeadersToServerVars($headers), $body)
            ->assertUnauthorized();
    }

    public function test_an_unconfigured_secret_rejects_everything(): void
    {
        config(['services.resend.webhook_secret' => null]);
        $this->log();

        $this->deliver('email.delivered', ['message_id' => 'abc-123@axelnova.tech'])->assertUnauthorized();
    }

    public function test_it_falls_back_to_resends_email_id(): void
    {
        $log = $this->log(['message_id' => 'rewritten@amazonses.com', 'provider_id' => 're_4ef9a417']);

        $this->deliver('email.delivered', ['message_id' => 'nope@elsewhere'])->assertOk();

        $this->assertSame('delivered', $log->fresh()->status);
    }

    public function test_it_falls_back_to_recipient_subject_and_time(): void
    {
        $log = $this->log(['message_id' => null]);
        $this->log(['message_id' => null, 'to_email' => 'other@example.com']);

        $this->deliver('email.delivered', ['message_id' => 'unknown@amazonses.com', 'email_id' => 're_other'])->assertOk();

        $this->assertSame('delivered', $log->fresh()->status);
        $this->assertSame('sent', EmailLog::where('to_email', 'other@example.com')->sole()->status);
    }

    public function test_the_time_fallback_ignores_sends_outside_ten_minutes(): void
    {
        $log = $this->log(['message_id' => null, 'sent_at' => now()->subMinutes(30)]);

        $this->deliver('email.delivered', ['message_id' => 'unknown@amazonses.com', 'email_id' => 're_other'])->assertOk();

        $this->assertSame('sent', $log->fresh()->status);
    }

    public function test_a_bounce_records_the_reason(): void
    {
        $log = $this->log();

        $this->deliver('email.bounced', [
            'message_id' => 'abc-123@axelnova.tech',
            'bounce' => ['message' => 'mailbox does not exist (550 5.1.1)', 'subType' => 'General', 'type' => 'Permanent'],
        ])->assertOk();

        $log->refresh();
        $this->assertSame('bounced', $log->status);
        $this->assertSame('mailbox does not exist (550 5.1.1)', $log->status_detail);
    }

    public function test_each_event_maps_to_its_status(): void
    {
        $map = [
            'email.delivery_delayed' => 'delayed',
            'email.suppressed' => 'bounced',
            'email.failed' => 'failed',
            'email.complained' => 'complained',
        ];

        foreach ($map as $event => $status) {
            $log = $this->log(['message_id' => "{$status}@axelnova.tech"]);
            $this->deliver($event, ['message_id' => "{$status}@axelnova.tech"])->assertOk();
            $this->assertSame($status, $log->fresh()->status, $event);
        }
    }

    public function test_a_late_delivered_report_never_undoes_a_bounce(): void
    {
        $log = $this->log(['status' => 'bounced']);

        $this->deliver('email.delivered', ['message_id' => 'abc-123@axelnova.tech'])->assertOk();

        $this->assertSame('bounced', $log->fresh()->status);
    }

    public function test_a_repeated_report_is_harmless(): void
    {
        $log = $this->log();

        $this->deliver('email.delivered', ['message_id' => 'abc-123@axelnova.tech'])->assertOk();
        $changedAt = $log->fresh()->status_changed_at;
        Carbon::setTestNow(now()->addMinute());
        $this->deliver('email.delivered', ['message_id' => 'abc-123@axelnova.tech'])->assertOk();

        $this->assertEquals($changedAt, $log->fresh()->status_changed_at);
    }

    public function test_reports_for_unknown_emails_and_unknown_events_are_accepted_and_ignored(): void
    {
        $log = $this->log();

        $this->deliver('email.delivered', ['message_id' => 'never-sent@x', 'email_id' => 're_x', 'to' => ['nobody@example.com']])->assertOk();
        $this->deliver('email.opened', ['message_id' => 'abc-123@axelnova.tech'])->assertOk();
        $this->deliver('contact.created', ['id' => 'c_1'])->assertOk();

        $this->assertSame('sent', $log->fresh()->status);
    }
}
