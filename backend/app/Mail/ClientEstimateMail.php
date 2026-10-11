<?php

namespace App\Mail;

use App\Mail\Concerns\DescribesQuotation;
use App\Models\Quotation;
use App\Support\Mail\EmailFormat;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

/**
 * Email 1a — the instant estimate a visitor gets from the quote builder: an
 * estimated range, what the packages include, the add-ons they picked.
 */
class ClientEstimateMail extends KitMailable
{
    use DescribesQuotation, Queueable, SerializesModels;

    public function __construct(public readonly Quotation $quote) {}

    public static function emailType(): string
    {
        return 'quotation.estimate';
    }

    public static function relatedProperty(): ?string
    {
        return 'quote';
    }

    public function viewData(): array
    {
        $quote = $this->quote;
        $packages = $this->quotePackages($quote);
        $prefix = count($packages) > 1;

        $included = [];
        foreach ($packages as $package) {
            foreach ($package['features'] as $feature) {
                $included[] = $prefix ? "{$package['name']}: {$feature}" : $feature;
            }
        }

        return [
            'name' => $quote->name,
            'reference' => $quote->displayReference(),
            'packageName' => $packages === [] ? null : implode(' + ', array_column($packages, 'name')),
            'timeline' => $quote->eta_label,
            'validUntil' => EmailFormat::date($quote->validUntil()),
            'range' => EmailFormat::range($quote->estimate_min_myr, $quote->estimate_max_myr),
            'included' => $included,
            'addons' => $quote->addons->map(fn ($addon) => [
                'label' => $addon->addon_label,
                'amount' => EmailFormat::money($addon->amount_myr),
            ])->values()->all(),
            'pdfUrl' => $this->quotePdfUrl($quote),
            'whatsappUrl' => $this->quoteWhatsappUrl($quote),
        ];
    }
}
