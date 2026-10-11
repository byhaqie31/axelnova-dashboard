{{-- Email 1a — quote-builder estimate (kit: 01a-estimate.html). --}}
@php
    $article = preg_match('/^[aeiou]/i', (string) $packageName) ? 'an' : 'a';
    $preheader = filled($packageName)
        ? "Your estimated range for {$article} {$packageName}, based on what you selected."
        : 'Your estimated range, based on what you selected.';
    $heading = "Here's your estimate, {$name}";
@endphp
<x-email::layouts.client title="Your estimate" :preheader="$preheader">
<x-email::blocks.heading-intro eyebrow="Estimate" :heading="$heading">
Thanks for using the quote builder. This is an estimated range based on what you selected. I'll confirm the final figure once we've talked through the details.
</x-email::blocks.heading-intro>
<x-email::blocks.spacer :height="32" />
<x-email::blocks.highlight-panel label="Estimated range" :amount="$range" note="Estimate only. A formal quotation follows once the scope is confirmed." />
<x-email::blocks.spacer :height="32" />
<x-email::blocks.summary-table :rows="[
    ['Reference', $reference],
    ['Package', $packageName],
    ['Timeline', $timeline],
    ['Valid until', $validUntil],
]" />
@if ($included !== [])
<x-email::blocks.section-label text="What's included" />
<x-email::blocks.line-items :items="array_map(fn (string $feature) => ['label' => $feature], $included)" />
@endif
@if ($addons !== [])
<x-email::blocks.section-label text="Add-ons" />
<x-email::blocks.line-items :items="$addons" />
@endif
@if (filled($pdfUrl))
<x-email::blocks.spacer :height="32" />
<x-email::blocks.main-button :url="$pdfUrl" label="Download PDF" />
@endif
@if (filled($whatsappUrl))
<x-email::blocks.spacer :height="32" />
<x-email::blocks.whatsapp-button :url="$whatsappUrl" prompt="Want to talk it through?" />
@endif
<x-email::blocks.signature />
</x-email::layouts.client>
