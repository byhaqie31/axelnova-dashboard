<?php

namespace App\Mail;

use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReferralReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Referral $referral) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Thanks for the referral — Axel Nova Ventures',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.referral-received',
            with: [
                'referral' => $this->referral,
            ],
        );
    }
}
