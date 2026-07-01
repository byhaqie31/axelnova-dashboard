<?php

namespace App\Mail;

use App\Models\Referrer;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Heads-up to the founder that a partner used self-service "forgot passcode" and a
 * fresh passcode was auto-issued to them. Carries NO passcode — it's just a notice.
 * Sent synchronously so it doesn't depend on the queue worker.
 */
class PartnerResetRequestedMail extends Mailable
{
    use SerializesModels;

    public function __construct(public readonly Referrer $referrer) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Partner passcode reset — '.$this->referrer->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.partner-reset-requested',
            with: ['referrer' => $this->referrer],
        );
    }
}
