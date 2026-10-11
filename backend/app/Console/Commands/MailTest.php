<?php

namespace App\Console\Commands;

use App\Mail\TestMail;
use App\Models\EmailLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Send one test email through the configured mailer and print its email-log
 * row — the go-live check after switching to Resend (docs/global/DEPLOY.md):
 * send to delivered@resend.dev and bounced@resend.dev, then watch the rows
 * reach "delivered" / "bounced" as Resend's reports arrive.
 */
class MailTest extends Command
{
    protected $signature = 'mail:test {to : Recipient address, e.g. delivered@resend.dev}';

    protected $description = 'Send a test email and show its email-log row';

    public function handle(): int
    {
        $to = (string) $this->argument('to');

        if (Validator::make(['to' => $to], ['to' => 'required|email'])->fails()) {
            $this->error("Not an email address: {$to}");

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');

        try {
            Mail::to($to)->send(new TestMail($mailer));
        } catch (Throwable $e) {
            $this->error('Send failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $log = EmailLog::where('to_email', $to)->latest('id')->first();

        if ($log === null) {
            $this->warn('Sent, but no email-log row was written — check the Laravel log.');

            return self::SUCCESS;
        }

        $this->table(['field', 'value'], [
            ['id', $log->id],
            ['status', $log->status],
            ['route', $log->route ?? '—'],
            ['message_id', $log->message_id ?? '—'],
            ['provider_id', $log->provider_id ?? '— (filled from the first Resend report)'],
        ]);

        return self::SUCCESS;
    }
}
