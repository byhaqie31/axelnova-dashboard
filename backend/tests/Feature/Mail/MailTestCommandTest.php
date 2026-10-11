<?php

namespace Tests\Feature\Mail;

use App\Models\EmailLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `php artisan mail:test {to}` — the go-live check (spec §5.10, §7): sends one
 * small email through the configured mailer and prints its log row, so the
 * owner can see which route carried it and which ids Resend will match on.
 */
class MailTestCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_a_test_email_and_prints_its_log_row(): void
    {
        config(['mail.default' => 'array']);

        $this->artisan('mail:test', ['to' => 'delivered@resend.dev'])
            ->expectsOutputToContain('route')
            ->expectsOutputToContain('message_id')
            ->assertSuccessful();

        $log = EmailLog::sole();
        $this->assertSame('delivered@resend.dev', $log->to_email);
        $this->assertSame('TestMail', $log->type);
        $this->assertSame('array', $log->route);
        $this->assertSame(1, count(app('mailer')->getSymfonyTransport()->messages()));
    }

    public function test_it_rejects_a_bad_address(): void
    {
        $this->artisan('mail:test', ['to' => 'not-an-email'])->assertFailed();

        $this->assertSame(0, EmailLog::count());
    }
}
