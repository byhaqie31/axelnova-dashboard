<?php

namespace App\Mail;

use App\Mail\Concerns\DescribesQuotation;
use App\Models\Quotation;
use App\Support\Mail\EmailFormat;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

/** Email 24 — owner alert: a new lead came in through the quote builder. */
class AdminNotificationMail extends KitMailable
{
    use DescribesQuotation, Queueable, SerializesModels;

    public function __construct(public readonly Quotation $quote) {}

    public static function emailType(): string
    {
        return 'quotation.new_lead_alert';
    }

    public static function relatedProperty(): ?string
    {
        return 'quote';
    }

    public function viewData(): array
    {
        $quote = $this->quote;
        $packages = $this->quotePackages($quote);

        return [
            'reference' => $quote->reference_code,
            'name' => $quote->name,
            'email' => $quote->email,
            'phone' => $quote->phone,
            'company' => $quote->company,
            'packageName' => $packages === [] ? null : implode(' + ', array_column($packages, 'name')),
            'estimate' => EmailFormat::range($quote->estimate_min_myr, $quote->estimate_max_myr),
            'timeline' => $quote->eta_label,
            'submitted' => EmailFormat::dateTime($quote->submitted_at),
            'adminUrl' => rtrim((string) config('app.url'), '/').'/admin/quotations/'.$quote->id,
            // The subject line keeps its long-standing "RM 1,500.00–RM 2,500.00" form.
            'subjectRange' => 'RM '.number_format((float) $quote->estimate_min_myr, 2).'–RM '.number_format((float) $quote->estimate_max_myr, 2),
        ];
    }
}
