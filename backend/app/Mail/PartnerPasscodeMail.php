<?php

namespace App\Mail;

use App\Models\ExternalAccount;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The ONLY place a partner passcode is ever surfaced. Sent when staff approve a
 * referrer (first passcode), reset one, or a partner self-serves "forgot
 * passcode" — for BOTH partner kinds (referrer + investor) since the Task 9
 * unification onto external_accounts. The plaintext passcode lives only in this
 * in-flight message — it is never rendered on a staff screen, returned by an
 * API, or written to a log. Sent synchronously (no ShouldQueue) so the acting
 * staffer gets immediate success/failure and there's no queue-worker dependency
 * for a credential delivery.
 */
class PartnerPasscodeMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly ExternalAccount $account,
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
                'account' => $this->account,
                'name' => $this->account->displayName(),
                'passcode' => $this->passcode,
                'loginUrl' => rtrim((string) config('services.frontend.public_url'), '/').'/partners/login',
            ],
        );
    }
}
