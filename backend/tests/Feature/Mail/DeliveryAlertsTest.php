<?php

namespace Tests\Feature\Mail;

use App\Mail\EmailDeliveryAlertMail;
use App\Mail\InvoiceMail;
use App\Models\EmailLog;
use App\Models\Order;
use App\Services\Mail\DeliveryAlerts;
use App\Services\Mail\EmailLogger;
use App\Services\Quoting\DocumentIssuer;
use App\Support\Mail\EmailCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * When an email to a client, partner or team member bounces, is marked as
 * spam or finally fails, the owner gets one alert (spec §5.6) — never for
 * their own inbox, never twice for the same email.
 */
class DeliveryAlertsTest extends TestCase
{
    use RefreshDatabase;

    private function log(array $attrs = []): EmailLog
    {
        return EmailLog::create($attrs + [
            'type' => 'invoice.sent', 'audience' => 'client', 'to_email' => 'client@example.com',
            'subject' => 'Invoice', 'route' => 'resend', 'status' => 'sent',
            'sent_at' => now(), 'status_changed_at' => now(),
        ]);
    }

    public function test_a_client_bounce_alerts_the_owner_once(): void
    {
        Mail::fake();
        $log = $this->log();
        $alerts = app(DeliveryAlerts::class);

        $log->advanceTo('bounced', 'mailbox does not exist');
        $alerts->maybeAlert($log);
        $log->advanceTo('complained');
        $alerts->maybeAlert($log);

        Mail::assertQueued(EmailDeliveryAlertMail::class, 1);
        Mail::assertQueued(EmailDeliveryAlertMail::class, fn ($m) => $m->hasTo(config('services.admin.email')) && $m->log->is($log));
        $this->assertNotNull($log->fresh()->alerted_at);
    }

    public function test_no_alert_for_a_healthy_email(): void
    {
        Mail::fake();
        $log = $this->log(['status' => 'delivered']);

        app(DeliveryAlerts::class)->maybeAlert($log);

        Mail::assertNothingQueued();
    }

    public function test_no_alert_for_an_owner_inbox_email(): void
    {
        Mail::fake();
        $log = $this->log(['type' => 'inquiry.new_alert', 'audience' => 'admin', 'status' => 'bounced']);

        app(DeliveryAlerts::class)->maybeAlert($log);

        Mail::assertNothingQueued();
        $this->assertNull($log->fresh()->alerted_at);
    }

    public function test_a_final_send_failure_alerts(): void
    {
        Mail::fake();
        $invoice = DocumentIssuer::issueInvoice(Order::factory()->create(), ['amount' => 1300]);

        app(EmailLogger::class)->recordFailure(InvoiceMail::class, $invoice, 'client@example.com', null, null, new RuntimeException('SMTP timeout'));

        Mail::assertQueued(EmailDeliveryAlertMail::class, 1);
    }

    public function test_the_alert_names_the_record_and_links_to_it(): void
    {
        $invoice = DocumentIssuer::issueInvoice(Order::factory()->create(), ['amount' => 1300]);
        $log = $this->log(['related_type' => 'invoice', 'related_id' => $invoice->id, 'status' => 'bounced', 'status_detail' => 'mailbox does not exist (550 5.1.1)']);

        $data = (new EmailDeliveryAlertMail($log))->viewData();

        $this->assertSame('Invoice', $data['emailLabel']);
        $this->assertSame('bounced', $data['problem']);
        $this->assertSame("Invoice {$invoice->invoice_number}", $data['recordLabel']);
        $this->assertStringEndsWith("/admin/invoices/{$invoice->id}", $data['recordUrl']);
        $this->assertSame('client', $data['audience']);
        $this->assertSame('mailbox does not exist (550 5.1.1)', $data['reason']);
    }

    public function test_the_alert_itself_is_owner_audience_so_it_cannot_alert_again(): void
    {
        $this->assertSame('system.delivery_alert', EmailDeliveryAlertMail::emailType());
        $this->assertSame('admin', EmailCatalog::audience(EmailDeliveryAlertMail::emailType()));
    }
}
