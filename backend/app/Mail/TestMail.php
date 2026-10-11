<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * `php artisan mail:test` — a plain check that sending works end to end
 * (spec §5.10). Not a catalog email: the log files it under its class name.
 */
class TestMail extends Mailable
{
    public function __construct(public readonly string $mailerName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Axel Nova dashboard — test email');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin.test', with: [
            'mailerName' => $this->mailerName,
            'sentAt' => now()->format('j M Y, g:i a'),
        ]);
    }
}
