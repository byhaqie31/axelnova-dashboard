{{-- Email 10 — project completed; carries the feedback button (new; mailable + trigger in Phase 3). --}}
@php
    $heading = "That's a wrap, {$name}";
@endphp
<x-email::layouts.client title="Project completed" preheader="Your project is complete. Thank you.">
<x-email::blocks.heading-intro eyebrow="Project completed" :heading="$heading">
Your project is complete. Thank you for trusting me with it — it's been a pleasure to build.
</x-email::blocks.heading-intro>
<x-email::blocks.spacer :height="32" />
<x-email::blocks.order-tracker :current="5" />
<x-email::blocks.spacer :height="32" />
<x-email::blocks.summary-table :rows="[
    ['Order', $orderNumber],
    ['Package', $packageName],
]" />
@if (filled($feedbackUrl))
<x-email::blocks.paragraph>If you have two minutes, I'd love to hear how the experience was for you. It's a short form, and nothing is ever published without your permission.</x-email::blocks.paragraph>
<x-email::blocks.spacer :height="32" />
<x-email::blocks.main-button :url="$feedbackUrl" label="Share your feedback" />
@endif
<x-email::blocks.signature signoff="Thanks for building with me," />
</x-email::layouts.client>
