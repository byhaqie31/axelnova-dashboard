{{-- Email 1b — formal quotation sent from admin (kit: 01b-quotation.html). --}}
@php
    $preheader = "Quotation {$reference} for {$total}, valid until {$validUntil}.";
    $heading = "Your quotation is ready, {$name}";
@endphp
<x-email::layouts.client title="Your quotation" :preheader="$preheader">
<x-email::blocks.heading-intro eyebrow="Quotation" :heading="$heading">
Here's the formal quotation for your project.@if (filled($pdfUrl)) The PDF has the full scope and terms.@endif
</x-email::blocks.heading-intro>
<x-email::blocks.spacer :height="32" />
<x-email::blocks.highlight-panel label="Total" :amount="$total" :note="'Valid until '.$validUntil" />
<x-email::blocks.spacer :height="32" />
<x-email::blocks.summary-table :rows="[
    ['Reference', $reference],
    ['Timeline', $timeline],
    ['Valid until', $validUntil],
]" />
@if ($items !== [])
<x-email::blocks.section-label text="What's included" />
<x-email::blocks.line-items :items="$items" :total="['label' => 'Total', 'amount' => $total]" />
@endif
@if (filled($pdfUrl))
<x-email::blocks.spacer :height="32" />
<x-email::blocks.main-button :url="$pdfUrl" label="Download PDF" />
@endif
@if (filled($whatsappUrl))
<x-email::blocks.spacer :height="32" />
<x-email::blocks.whatsapp-button :url="$whatsappUrl" prompt="Questions before you decide?" />
@endif
<x-email::blocks.signature />
</x-email::layouts.client>
