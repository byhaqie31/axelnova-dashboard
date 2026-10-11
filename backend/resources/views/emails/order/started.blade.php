{{-- Email 8 — project started (new; mailable + trigger in Phase 3). --}}
@php
    $heading = "Work has started, {$name}";
@endphp
<x-email::layouts.client title="Project started" preheader="Your project is underway.">
<x-email::blocks.heading-intro eyebrow="Project started" :heading="$heading">
Your project is underway. I'll keep you posted as it comes together.
</x-email::blocks.heading-intro>
<x-email::blocks.spacer :height="32" />
<x-email::blocks.order-tracker :current="2" />
<x-email::blocks.spacer :height="32" />
<x-email::blocks.summary-table :rows="[
    ['Order', $orderNumber],
    ['Package', $packageName],
    ['Expected delivery', $expectedDelivery],
]" />
@if (filled($whatsappUrl))
<x-email::blocks.spacer :height="32" />
<x-email::blocks.whatsapp-button :url="$whatsappUrl" prompt="Questions along the way?" />
@endif
<x-email::blocks.signature />
</x-email::layouts.client>
