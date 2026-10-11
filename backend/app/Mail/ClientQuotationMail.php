<?php

namespace App\Mail;

use App\Mail\Concerns\DescribesQuotation;
use App\Models\Quotation;
use App\Services\Quoting\DocumentMapper;
use App\Support\Mail\EmailFormat;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

/**
 * Email 1b — the formal quotation the founder sends from admin. Line items are
 * the PDF's own (DocumentMapper), and discount, tax and total come from
 * Quotation::documentTotals() — the PDF's computeTotals maths — so the email
 * and the PDF can never disagree.
 */
class ClientQuotationMail extends KitMailable
{
    use DescribesQuotation, Queueable, SerializesModels;

    public function __construct(public readonly Quotation $quote) {}

    public static function emailType(): string
    {
        return 'quotation.sent';
    }

    public static function relatedProperty(): ?string
    {
        return 'quote';
    }

    public function viewData(): array
    {
        $quote = $this->quote;
        $doc = DocumentMapper::toDocumentData($quote);
        $docItems = is_array($doc['items'] ?? null) ? $doc['items'] : [];

        $items = [];
        foreach ($docItems as $item) {
            $qty = (float) ($item['qty'] ?? 1);
            $rate = (float) ($item['rate'] ?? 0);

            $detail = array_values(array_filter([
                filled($item['desc'] ?? null) ? (string) $item['desc'] : null,
                $qty != 1.0 ? rtrim(rtrim(number_format($qty, 2), '0'), '.').' × '.EmailFormat::money($rate) : null,
            ]));

            $items[] = [
                'label' => (string) ($item['title'] ?? 'Item'),
                'detail' => $detail === [] ? null : implode(' · ', $detail),
                'amount' => EmailFormat::money($qty * $rate),
            ];
        }

        // The same maths as the PDF, over the PDF's own items (which fall back to
        // the estimate breakdown when the quotation has no line items).
        $totals = Quotation::documentTotals([
            'items' => $docItems,
            'discount' => $doc['discount'] ?? 0,
            'tax_rate' => $doc['taxRate'] ?? 0,
        ]);

        if ($totals !== null && $totals['discount'] > 0) {
            $items[] = ['label' => 'Discount', 'detail' => null, 'amount' => '− '.EmailFormat::money($totals['discount'])];
        }
        if ($totals !== null && (float) ($doc['taxRate'] ?? 0) > 0) {
            $items[] = ['label' => (string) ($doc['taxLabel'] ?? 'Tax'), 'detail' => null, 'amount' => EmailFormat::money($totals['tax'])];
        }

        return [
            'name' => $quote->name,
            'reference' => $quote->displayReference(),
            // Detailed (admin-composed) quotes carry no flat items — their own total.
            'total' => EmailFormat::money($totals['total'] ?? $quote->finalAmount()),
            'timeline' => $quote->eta_label,
            'validUntil' => EmailFormat::date($quote->validUntil()),
            'items' => $items,
            'pdfUrl' => $this->quotePdfUrl($quote),
            'whatsappUrl' => $this->quoteWhatsappUrl($quote),
        ];
    }
}
