<?php

namespace App\Mail;

use App\Mail\Concerns\DescribesQuotation;
use App\Models\Invoice;
use App\Services\Quoting\DocumentMapper;
use App\Support\Mail\EmailFormat;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

/**
 * Email 3 — the client-facing invoice (kit: 03-invoice.html): amount due, how
 * to pay (the studio's one bank account, DocumentMapper::BANK), the PDF linked
 * and attached when the fetch succeeded (null bytes → link-only).
 */
class InvoiceMail extends KitMailable
{
    use DescribesQuotation, Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        private readonly ?string $pdfBytes = null,
    ) {}

    public static function emailType(): string
    {
        return 'invoice.sent';
    }

    public static function relatedProperty(): ?string
    {
        return 'invoice';
    }

    public function viewData(): array
    {
        $invoice = $this->invoice;
        $quotation = $invoice->order?->quotation;
        $packages = $quotation ? $this->quotePackages($quotation) : [];

        return [
            'name' => $quotation?->name ?: $quotation?->company ?: 'there',
            'invoiceNumber' => $invoice->invoice_number,
            'projectName' => $packages === [] ? null : implode(' + ', array_column($packages, 'name')),
            'amountDue' => EmailFormat::money(max((float) $invoice->amount_total - (float) ($invoice->amount_paid ?? 0), 0)),
            'dueDate' => EmailFormat::date($invoice->due_at),
            'quoteRef' => $quotation?->reference_code,
            'issuedDate' => EmailFormat::date($invoice->issued_at),
            'bank' => [
                'name' => DocumentMapper::BANK['name'],
                'holder' => DocumentMapper::BANK['holder'],
                'account' => DocumentMapper::BANK['acct'],
            ],
            // No card/FPX gateway is connected yet — the row stays hidden (spec §5.13).
            'onlinePaymentLink' => null,
            'pdfUrl' => rtrim((string) config('services.frontend.public_url'), '/').$invoice->pdf_path,
            'attached' => $this->pdfBytes !== null,
        ];
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
