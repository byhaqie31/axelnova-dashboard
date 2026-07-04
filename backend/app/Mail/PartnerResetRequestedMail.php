<?php

namespace App\Mail;

use App\Models\ExternalAccount;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Heads-up to the founder that a partner (referrer OR investor) used self-service
 * "forgot passcode" and a fresh passcode was auto-issued to them. Carries NO
 * passcode — it's just a notice. Sent synchronously so it doesn't depend on the
 * queue worker.
 */
class PartnerResetRequestedMail extends Mailable
{
    use SerializesModels;

    public function __construct(public readonly ExternalAccount $account) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Partner passcode reset — '.$this->account->displayName(),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.partner-reset-requested',
            with: [
                'account' => $this->account,
                'name' => $this->account->displayName(),
            ],
        );
    }
}
