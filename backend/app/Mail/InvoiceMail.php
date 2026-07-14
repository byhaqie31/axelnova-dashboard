<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The client-facing invoice email: summary + "View invoice" PDF link, with the
 * rendered PDF attached when the fetch succeeded (null bytes → link-only).
 */
class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        private readonly ?string $pdfBytes = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invoice {$this->invoice->invoice_number} from Axel Nova Ventures",
        );
    }

    public function content(): Content
    {
        $quotation = $this->invoice->order?->quotation;

        return new Content(
            markdown: 'mail.client-invoice',
            with: [
                'invoice' => $this->invoice,
                'clientName' => $quotation?->name ?: $quotation?->company ?: 'there',
                'referenceCode' => $quotation?->reference_code,
                'pdfUrl' => rtrim((string) config('services.frontend.public_url'), '/')
                    .$this->invoice->pdf_path,
                'amountDue' => max(
                    (float) $this->invoice->amount_total - (float) ($this->invoice->amount_paid ?? 0),
                    0,
                ),
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        if ($this->pdfBytes === null) {
            return [];
        }

        return [
            Attachment::fromData(fn () => $this->pdfBytes, "{$this->invoice->invoice_number}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
