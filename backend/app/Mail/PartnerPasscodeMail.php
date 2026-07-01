<?php

namespace App\Mail;

use App\Models\Referrer;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The ONLY place a partner passcode is ever surfaced. Sent when a marketer approves
 * a referrer (first passcode) or resets it (regenerated). The plaintext passcode
 * lives only in this in-flight message — it is never rendered on a staff screen,
 * returned by an API, or written to a log. Sent synchronously (no ShouldQueue) so
 * the acting staffer gets immediate success/failure and there's no queue-worker
 * dependency for a credential delivery.
 */
class PartnerPasscodeMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly Referrer $referrer,
        public readonly string $passcode,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Partner Portal access — Axel Nova Ventures',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.partner-passcode',
            with: [
                'referrer' => $this->referrer,
                'passcode' => $this->passcode,
                'loginUrl' => rtrim((string) config('services.frontend.url'), '/').'/partners/login',
            ],
        );
    }
}
