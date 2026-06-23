<?php

namespace App\Mail;

use App\Models\Referral;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent from the admin commission section once a referral converts: tells the
 * referrer their estimated commission and asks them to reply with the bank
 * details we'll pay it into. Sent synchronously so the admin gets immediate
 * success/failure feedback — no queue worker dependency.
 */
class ReferralCommissionMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly Referral $referral,
        public readonly float $commission,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your referral commission — Axel Nova Ventures',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.referral-commission',
            with: [
                'referral' => $this->referral,
                'commission' => $this->commission,
            ],
        );
    }
}
