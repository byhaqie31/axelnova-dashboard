<?php

namespace Tests\Feature\Mail;

use App\Jobs\SendInvoiceEmail;
use App\Mail\InquiryReceivedMail;
use App\Mail\InvoiceMail;
use App\Models\EmailLog;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\User;
use App\Services\Mail\EmailLogger;
use App\Services\Quoting\DocumentIssuer;
use App\Support\Mail\MailRouteRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Tests\TestCase;
use Throwable;

/**
 * Every email that leaves becomes one `sent` row, linked to its record; a
 * send that finally fails becomes one `failed` row (spec §5.4). Logging must
 * never stop an email going out.
 */
class EmailCaptureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'array']);
    }

    private function invoice()
    {
        return DocumentIssuer::issueInvoice(Order::factory()->create(), ['amount' => 1300])->load('order.quotation');
    }

    private function lastSent(): Email
    {
        return app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
    }

    public function test_a_sent_email_is_logged_and_linked_to_its_record(): void
    {
        $invoice = $this->invoice();

        Mail::to('client@example.com', 'Client Co')->send(new InvoiceMail($invoice));

        $log = EmailLog::sole();
        $this->assertSame('invoice.sent', $log->type);
        $this->assertSame('client', $log->audience);
        $this->assertSame('invoice', $log->related_type);
        $this->assertSame($invoice->id, $log->related_id);
        $this->assertSame('client@example.com', $log->to_email);
        $this->assertSame('Client Co', $log->to_name);
        $this->assertSame("Invoice {$invoice->invoice_number} from Axel Nova Ventures", $log->subject);
        $this->assertSame('array', $log->route);
        $this->assertSame('sent', $log->status);
        $this->assertNotNull($log->sent_at);
        $this->assertNull($log->triggered_by);
        $this->assertSame($invoice->id, $log->relatedRecord()?->id);
    }

    public function test_the_logged_message_id_is_the_one_on_the_wire(): void
    {
        Mail::to('client@example.com')->send(new InvoiceMail($this->invoice()));

        $header = $this->lastSent()->getHeaders()->get('Message-ID')->getId();
        $this->assertSame($header, EmailLog::sole()->message_id);
        $this->assertStringEndsWith('@axelnova.tech', $header);
    }

    /** A transport whose SMTP-style reply id is fixed — like Mailtrap's "250 2.0.0 Ok: queued". */
    private function replyingTransport(string $replyId): void
    {
        Mail::extend('replying', fn () => new class($replyId) implements TransportInterface
        {
            public function __construct(private string $replyId) {}

            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                $sent = new SentMessage($message, $envelope ?? Envelope::create($message));
                $sent->setMessageId($this->replyId);

                return $sent;
            }

            public function __toString(): string
            {
                return 'replying://';
            }
        });
    }

    public function test_a_non_resend_smtp_reply_is_not_stored_as_a_resend_id(): void
    {
        $this->replyingTransport('queued');
        config(['mail.mailers.hostinger_like' => ['transport' => 'tagged', 'route' => 'hostinger', 'inner' => ['transport' => 'replying']], 'mail.default' => 'hostinger_like']);

        Mail::to('client@example.com')->send(new InvoiceMail($this->invoice()));

        $this->assertNull(EmailLog::sole()->provider_id);
    }

    public function test_resends_own_id_from_the_smtp_reply_is_kept(): void
    {
        $this->replyingTransport('4ef9a417-02e9-4d39-ad75-9611e0fcc33c');
        config(['mail.mailers.resend_like' => ['transport' => 'tagged', 'route' => 'resend', 'inner' => ['transport' => 'replying']], 'mail.default' => 'resend_like']);

        Mail::to('client@example.com')->send(new InvoiceMail($this->invoice()));

        $this->assertSame('4ef9a417-02e9-4d39-ad75-9611e0fcc33c', EmailLog::sole()->provider_id);
    }

    public function test_the_acting_user_is_recorded(): void
    {
        $user = User::factory()->founder()->create();
        Context::addHidden('mail_actor_id', $user->id);

        Mail::to('client@example.com')->send(new InvoiceMail($this->invoice()));

        $this->assertSame($user->id, EmailLog::sole()->triggered_by);
    }

    public function test_an_admin_request_carries_the_actor_into_the_queued_send(): void
    {
        Http::fake(['*' => Http::response('%PDF-1.7', 200, ['Content-Type' => 'application/pdf'])]);
        $founder = User::factory()->founder()->create();
        $token = $founder->createToken('admin-spa', ['cockpit'])->plainTextToken;
        $invoice = $this->invoice();

        $this->postJson("/api/v1/admin/invoices/{$invoice->id}/send", ['email' => 'client@example.com'], ['Authorization' => "Bearer {$token}"])
            ->assertAccepted();

        $this->assertSame($founder->id, EmailLog::where('type', 'invoice.sent')->sole()->triggered_by);
    }

    public function test_a_public_form_send_is_automatic(): void
    {
        $this->postJson('/api/v1/inquiries', [
            'origin' => 'contact', 'subject' => 'General question', 'name' => 'Aina',
            'email' => 'aina@example.com', 'message' => 'Do you build booking systems?',
        ])->assertCreated();

        $log = EmailLog::where('type', 'inquiry.received')->sole();
        $this->assertNull($log->triggered_by);
        $this->assertSame('aina@example.com', $log->to_email);
        $this->assertSame('admin', EmailLog::where('type', 'inquiry.new_alert')->sole()->audience);
    }

    public function test_a_queued_email_that_finally_fails_is_logged_once(): void
    {
        Mail::extend('always-fails', fn () => new class implements TransportInterface
        {
            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                throw new TransportException('550 relay refused');
            }

            public function __toString(): string
            {
                return 'always-fails://';
            }
        });
        config(['mail.mailers.always_fails' => ['transport' => 'always-fails'], 'mail.default' => 'always_fails']);
        $inquiry = Inquiry::create(['origin' => 'contact', 'subject' => 'General question', 'name' => 'Aina', 'email' => 'aina@example.com', 'message' => 'Hi', 'status' => 'new']);

        try {
            Mail::to('aina@example.com', 'Aina')->send(new InquiryReceivedMail($inquiry));
        } catch (Throwable) {
            // the sync queue rethrows after marking the job failed
        }

        $log = EmailLog::where('type', 'inquiry.received')->sole();
        $this->assertSame('failed', $log->status);
        $this->assertSame('inquiry', $log->related_type);
        $this->assertSame($inquiry->id, $log->related_id);
        $this->assertSame('aina@example.com', $log->to_email);
        $this->assertNull($log->sent_at);
        $this->assertStringContainsString('550 relay refused', (string) $log->status_detail);
    }

    public function test_a_job_carried_email_that_finally_fails_is_logged(): void
    {
        $invoice = $this->invoice();

        (new SendInvoiceEmail($invoice->id, 'client@example.com', 'Client'))->failed(new RuntimeException('SMTP timeout'));

        $log = EmailLog::where('type', 'invoice.sent')->sole();
        $this->assertSame('failed', $log->status);
        $this->assertSame($invoice->id, $log->related_id);
        $this->assertSame('client@example.com', $log->to_email);
        $this->assertSame('SMTP timeout', $log->status_detail);
        // …and the owner was alerted about it (a sent, owner-audience row).
        $this->assertSame('admin', EmailLog::where('type', 'system.delivery_alert')->sole()->audience);
    }

    public function test_a_logging_error_never_stops_the_email(): void
    {
        $this->app->instance(EmailLogger::class, new class(app(MailRouteRecorder::class)) extends EmailLogger
        {
            public function recordSent(MessageSent $event): ?EmailLog
            {
                throw new RuntimeException('log table is down');
            }
        });

        Mail::to('client@example.com')->send(new InvoiceMail($this->invoice()));

        $this->assertCount(1, app('mailer')->getSymfonyTransport()->messages());
        $this->assertSame(0, EmailLog::count());
    }
}
