<?php

namespace App\Mail;

use App\Models\QuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly QuoteRequest $quote) {}

    public function envelope(): Envelope
    {
        $minFmt = 'RM '.number_format((float) $this->quote->estimate_min_myr / 1000, 0).'k';
        $maxFmt = 'RM '.number_format((float) $this->quote->estimate_max_myr / 1000, 0).'k';

        return new Envelope(
            subject: "New lead: {$this->quote->reference_code} — {$minFmt}–{$maxFmt}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.admin-notification',
            with: [
                'quote' => $this->quote,
                'adminUrl' => rtrim(config('app.url'), '/').'/admin/quotations/'.$this->quote->id,
            ],
        );
    }
}
