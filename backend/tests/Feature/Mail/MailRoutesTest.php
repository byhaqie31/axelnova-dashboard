<?php

namespace Tests\Feature\Mail;

use App\Models\EmailLog;
use App\Support\Mail\MailRouteRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use Tests\TestCase;

/**
 * Production sends through Resend with Hostinger as the automatic backup
 * (spec §5.1). Each route is wrapped so the email log can record which one
 * actually carried a message.
 */
class MailRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::extend('always-fails', fn () => new class implements TransportInterface
        {
            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                throw new TransportException('Resend unreachable');
            }

            public function __toString(): string
            {
                return 'always-fails://';
            }
        });

        config([
            'mail.mailers.test_primary_ok' => ['transport' => 'tagged', 'route' => 'resend', 'inner' => ['transport' => 'array']],
            'mail.mailers.test_primary_down' => ['transport' => 'tagged', 'route' => 'resend', 'inner' => ['transport' => 'always-fails']],
            'mail.mailers.test_backup' => ['transport' => 'tagged', 'route' => 'hostinger', 'inner' => ['transport' => 'array']],
            'mail.mailers.test_failover_ok' => ['transport' => 'failover', 'mailers' => ['test_primary_ok', 'test_backup']],
            'mail.mailers.test_failover_down' => ['transport' => 'failover', 'mailers' => ['test_primary_down', 'test_backup']],
        ]);
    }

    public function test_a_healthy_primary_records_the_resend_route(): void
    {
        Mail::mailer('test_failover_ok')->raw('hello', fn ($m) => $m->to('a@example.com')->subject('Route check'));

        $this->assertSame('resend', EmailLog::sole()->route);
    }

    public function test_a_failing_primary_falls_back_to_hostinger_and_records_it(): void
    {
        Mail::mailer('test_failover_down')->raw('hello', fn ($m) => $m->to('a@example.com')->subject('Route check'));

        $this->assertSame('hostinger', EmailLog::sole()->route);
    }

    public function test_pull_clears_the_recorded_route(): void
    {
        $recorder = new MailRouteRecorder;
        $recorder->record('resend');

        $this->assertSame('resend', $recorder->pull());
        $this->assertNull($recorder->pull());
    }

    public function test_production_mailers_are_defined(): void
    {
        $this->assertSame(['resend', 'hostinger'], config('mail.mailers.failover.mailers'));
        $this->assertSame('smtp.resend.com', config('mail.mailers.resend.inner.host'));
        $this->assertSame('resend', config('mail.mailers.resend.inner.username'));
        $this->assertSame('tagged', config('mail.mailers.hostinger.transport'));
    }
}
