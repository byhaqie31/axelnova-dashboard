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
        $validForDays = $this->quote->pricingConfig?->config['valid_for_days'] ?? 30;

        return new Content(
            markdown: 'mail.client-quote',
            with: [
                'quote' => $this->quote,
                'validUntil' => now()->addDays($validForDays)->format('d F Y'),
                'whatsappUrl' => config('services.admin.whatsapp_url')
                    .'?text='.rawurlencode("Hi Qie, I'd like to chat about quote {$this->quote->reference_code}."),
                'pdfUrl' => $this->quote->public_token
                    ? rtrim((string) config('services.frontend.url'), '/')."/api/documents/{$this->quote->public_token}/pdf"
                    : null,
            ],
        );
    }
}
