<?php

namespace App\Mail\Concerns;

use App\Models\Quotation;
use App\Models\ServicePackage;
use App\Services\Quoting\PricingEngine;
use Illuminate\Support\Str;

/**
 * Shared quotation facts for the estimate and quotation emails: the packages
 * (every one — a quote can carry several), the PDF link and the WhatsApp
 * hand-off, exactly as the old single quote email built them.
 */
trait DescribesQuotation
{
    /** @return list<array{name: string, features: list<string>}> */
    protected function quotePackages(Quotation $quote): array
    {
        $entries = $quote->normalizedForm()['packages'] ?? [];
        $ids = array_values(array_filter(array_column($entries, 'service_package_id')));
        $rows = $ids === [] ? collect() : ServicePackage::whereKey($ids)->get()->keyBy('id');

        $packages = [];
        foreach ($entries as $entry) {
            $row = isset($entry['service_package_id']) ? $rows->get($entry['service_package_id']) : null;
            $key = $entry['package_key'] ?? null;

            $name = $row?->name ?? ($key
                // Legacy JSON-only package: the catalog name, never the raw slug.
                ? rescue(fn () => PricingEngine::active()->packageName($key), Str::headline($key), false)
                : null);

            if ($name === null) {
                continue;
            }

            $features = array_values(array_filter((array) ($row?->features ?? []), fn ($f) => is_string($f) && $f !== ''));
            $packages[] = ['name' => $name, 'features' => $features];
        }

        return $packages;
    }

    protected function quotePdfUrl(Quotation $quote): ?string
    {
        return $quote->public_token
            ? rtrim((string) config('services.frontend.public_url'), '/')."/documents/{$quote->public_token}/pdf"
            : null;
    }

    protected function quoteWhatsappUrl(Quotation $quote): ?string
    {
        $base = (string) config('services.admin.whatsapp_url');

        return $base === ''
            ? null
            : $base.'?text='.rawurlencode("Hi Qie, I'd like to chat about quote {$quote->displayReference()}.");
    }
}
