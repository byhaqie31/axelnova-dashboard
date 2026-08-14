<?php

namespace App\Mail;

use App\Models\Quotation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientQuoteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Quotation $quote) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your quote {$this->quote->reference_code} from Axel Nova Ventures",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.client-quote',
            with: [
                'quote' => $this->quote,
                // Same computed validity as the PDF header — the stored expiry
                // (stamped by send() just before this job is queued) wins.
                'validUntil' => $this->quote->validUntil()->format('d F Y'),
                'whatsappUrl' => config('services.admin.whatsapp_url')
                    .'?text='.rawurlencode("Hi Qie, I'd like to chat about quote {$this->quote->reference_code}."),
                'pdfUrl' => $this->quote->public_token
                    ? rtrim((string) config('services.frontend.public_url'), '/')."/documents/{$this->quote->public_token}/pdf"
                    : null,
            ],
        );
    }
}
