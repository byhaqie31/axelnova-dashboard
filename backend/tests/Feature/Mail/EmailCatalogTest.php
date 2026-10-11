<?php

namespace Tests\Feature\Mail;

use App\Mail\PreviewMail;
use App\Support\Mail\EmailCatalog;
use App\Support\Mail\EmailSamples;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use Tests\TestCase;

/**
 * The catalog is the single list of every email the dashboard can send
 * (spec §5.13: 33 templates — email 1 split in two). Templates, preview
 * sends, the email log's labels and audiences all read from it.
 */
class EmailCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_lists_every_email_type_once(): void
    {
        $all = EmailCatalog::all();

        $this->assertCount(33, $all);
        $this->assertCount(33, array_unique(array_column($all, 'view')));
        foreach ($all as $type => $def) {
            $this->assertContains($def['audience'], ['client', 'partner', 'team', 'admin'], $type);
            $this->assertNotSame('', $def['label'], $type);
            $this->assertStringStartsWith('emails.', $def['view'], $type);
        }
    }

    public function test_admin_emails_use_the_internal_layout_and_the_rest_the_client_layout(): void
    {
        foreach (EmailCatalog::all() as $type => $def) {
            $this->assertSame($def['audience'] === 'admin' ? 'internal' : 'client', EmailCatalog::layout($type), $type);
        }
    }

    public function test_every_built_template_renders_with_its_sample_data(): void
    {
        $built = EmailSamples::types();
        $this->assertNotEmpty($built);

        foreach ($built as $type) {
            $html = view(EmailCatalog::view($type), EmailSamples::for($type))->render();

            $this->assertStringContainsString('<!DOCTYPE html>', $html, $type);
            $marker = EmailCatalog::layout($type) === 'internal' ? '>Internal</td>' : 'All rights reserved.';
            $this->assertStringContainsString($marker, $html, $type);
        }
    }

    public function test_every_catalog_email_has_a_built_template(): void
    {
        $missing = array_diff(array_keys(EmailCatalog::all()), EmailSamples::types());

        $this->assertSame([], array_values($missing), 'Templates not built yet: '.implode(', ', $missing));
    }

    public function test_client_layout_emails_carry_the_founder_signature(): void
    {
        foreach (EmailSamples::types() as $type) {
            if (EmailCatalog::layout($type) !== 'client') {
                continue;
            }
            $html = view(EmailCatalog::view($type), EmailSamples::for($type))->render();
            $this->assertStringContainsString('mailto:'.config('email_design.signature.email'), $html, $type);
        }
    }

    public function test_no_template_uses_the_reserved_message_key(): void
    {
        // Laravel injects its own $message (Illuminate\Mail\Message) into
        // every mail view, overwriting any template data of that name.
        foreach (EmailSamples::types() as $type) {
            $this->assertArrayNotHasKey('message', EmailSamples::for($type), $type);
        }
    }

    public function test_subject_is_built_from_the_sample_data(): void
    {
        foreach (EmailSamples::types() as $type) {
            $subject = EmailCatalog::subject($type, EmailSamples::for($type));
            $this->assertNotSame('', trim($subject), $type);
            $this->assertStringNotContainsString('{', $subject, $type);
        }
    }

    public function test_preview_rejects_an_unknown_type(): void
    {
        Mail::fake();

        $this->artisan('mail:preview', ['type' => 'nope.nothing', '--pause' => 0])->assertFailed();

        Mail::assertNothingSent();
    }

    public function test_preview_sends_one_built_type_with_a_preview_subject(): void
    {
        Mail::fake();
        $type = EmailSamples::types()[0];

        $this->artisan('mail:preview', ['type' => $type, '--to' => 'qa@example.com', '--pause' => 0])->assertSuccessful();

        Mail::assertSent(PreviewMail::class, fn (PreviewMail $mail) => $mail->hasTo('qa@example.com')
            && str_starts_with($mail->envelope()->subject, '[Preview] '));
    }

    public function test_preview_waits_and_retries_when_the_inbox_rate_limits(): void
    {
        $attempts = 0;
        Mail::extend('rate-limited-once', function () use (&$attempts) {
            return new class($attempts) implements TransportInterface
            {
                public function __construct(private int &$attempts) {}

                public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
                {
                    if (++$this->attempts === 1) {
                        throw new TransportException('550 5.7.0 Too many emails per second. Please upgrade your plan');
                    }

                    return new SentMessage($message, $envelope ?? Envelope::create($message));
                }

                public function __toString(): string
                {
                    return 'rate-limited-once://';
                }
            };
        });
        config(['mail.mailers.rate_limited_once' => ['transport' => 'rate-limited-once'], 'mail.default' => 'rate_limited_once']);

        $this->artisan('mail:preview', ['type' => EmailSamples::types()[0], '--pause' => 0, '--retry-wait' => 0])
            ->assertSuccessful();

        $this->assertSame(2, $attempts);
    }

    public function test_preview_reports_sends_that_still_fail(): void
    {
        Mail::extend('always-down', fn () => new class implements TransportInterface
        {
            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                throw new TransportException('connection refused');
            }

            public function __toString(): string
            {
                return 'always-down://';
            }
        });
        config(['mail.mailers.always_down' => ['transport' => 'always-down'], 'mail.default' => 'always_down']);

        $this->artisan('mail:preview', ['--all' => true, '--pause' => 0, '--retry-wait' => 0])
            ->expectsOutputToContain('failed')
            ->assertFailed();
    }

    public function test_preview_all_sends_every_built_type(): void
    {
        Mail::fake();

        $this->artisan('mail:preview', ['--all' => true, '--pause' => 0])->assertSuccessful();

        Mail::assertSent(PreviewMail::class, count(EmailSamples::types()));
    }
}
